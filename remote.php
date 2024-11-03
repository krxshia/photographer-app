<?php
session_start();

include 'db_conn.php';
require 'phpqrcode/qrlib.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$device_id = $_GET['device_id'] ?? null;
$token = $_GET['token'] ?? null;

if (!$device_id || !$token) {
    die("Invalid.");
}

try {
    $stmt = $pdo->prepare("
        SELECT album.id AS album_id, album.status, remote.id AS remote_id
        FROM album
        INNER JOIN remote ON album.remote_id = remote.id
        WHERE remote.id = ? AND remote.token = ?
    ");
    $stmt->execute([$device_id, $token]);
    $album = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$album) {
        die("No album found for this device. Please check if the device and token are correct.");
    }

    $isLive = ($album['status'] == 'live');

   $_SESSION['album_id'] = $album['album_id'];
   $_SESSION['remote_id'] = $album['remote_id'];
   $_SESSION['qr_code_path'] = '';

} catch (PDOException $e) {
    die("Database error occurred: " . $e->getMessage());
}

try {
    $stmt = $pdo->prepare("SELECT image_path FROM capture WHERE album_id = ?");
    $stmt->execute([$_SESSION['album_id']]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error occurred while fetching images: " . $e->getMessage());
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/vendor/phpmailer/phpmailer/src/Exception.php';
require 'phpmailer/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require 'phpmailer/vendor/phpmailer/phpmailer/src/SMTP.php';

function send_live_album_email($email, $album_url, $qr_code_file) {
    return send_qr_email($email, $album_url, $qr_code_file, "Live Album Access");
}

function send_longterm_album_email($email, $album_url) {
    $qr_code_file = "";
    return send_qr_email($email, $album_url, $qr_code_file, "Long-term Album Access");
}

function send_qr_email($email, $album_url, $qr_code_file) {
    $mail = new PHPMailer();
    $mail->IsSMTP();

    $mail->SMTPDebug = 0;
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = "tls";
    $mail->Port = 587;
    $mail->Host = "smtp.gmail.com";
    $mail->Username = "atfbuildersconstructionsupply@gmail.com"; // I temporarily used the email of my previous capstone project to send an email
    $mail->Password = "dcmdknofihdfegaw";

    $mail->IsHTML(true);
    $mail->AddAddress($email);
    $mail->setFrom("aerielatijera.19@gmail.com", "Photo Album Access | Photographer App");

    // Subject and Body
    $mail->Subject = "Photographer | Photo Album QR Code";
    $qr_code_image_url = "http://192.168.1.11/photographer-mission/remote/" . htmlspecialchars($qr_code_file ?? '');
    $email_template = "
        <h2>Hello!</h2>
        <p>Here is your link to access the photo album:</p>
        <p><a type='button' class='btn btn-primary' href='$album_url'>Click here to view album</a></p>
        <br><br>
        <p>Regards,</p>
        <h4>Photographer App</h4>
    ";

    $mail->Body = $email_template;
    $mail->AltBody = strip_tags($email_template);

    try {
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

$baseUrl = "http://192.168.1.11/photographer-mission/";
$qr_code_file = $_SESSION['qr_code_path'] ?? '';
if (!$qr_code_file) {
    $album_url = "http://192.168.1.11/photographer-mission/remote/$device_id/$token";
    $qr_code_file = 'qrcodes/' . uniqid() . '.png';
    QRcode::png($album_url, $qr_code_file, QR_ECLEVEL_L, 4);
    $stmt = $pdo->prepare("UPDATE album SET qr_code_path = ? WHERE id = ?");
    $stmt->execute([$qr_code_file, $album['album_id']]);
    $_SESSION['qr_code_path'] = $qr_code_file;
}

if (isset($_POST['send_email'])) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $album_url = "http://192.168.1.11/photographer-mission/remote/$device_id/$token";

    if ($isLive) {
        if ($qr_code_file) {
            if (send_live_album_email($email, $album_url, $qr_code_file)) {
                echo "<script>alert('Live album sent successfully!');</script>";
            } else {
                echo "<script>alert('Failed to send live album email.');</script>";
            }
        } else {
            echo "<script>alert('QR code not available for live album.');</script>";
        }
    } else {
        if (send_longterm_album_email($email, $album_url)) {
            echo "<script>alert('Album email sent successfully!');</script>";
        } else {
            echo "<script>alert('Failed to send long-term album email.');</script>";
        }
    }
}

if (!$qr_code_file) {
    $stmt = $pdo->prepare("SELECT qr_code_path FROM album WHERE id = ?");
    $stmt->execute([$_SESSION['album_id']]);
    $qr_code_file = $stmt->fetchColumn();
    
    if ($qr_code_file) {
        $_SESSION['qr_code_path'] = $qr_code_file;
    } else {
        die("Error: QR code not found.");
    }
}

if (!file_exists($qr_code_file)) {
    die("QR code file does not exist at: $qr_code_file");
}

$showAlert = isset($_GET['status_changed']) && $_GET['status_changed'] == 1;

if (isset($_POST['end_session'])) {
    if (isset($_SESSION['album_id'])) {
        try {
            $stmt = $pdo->prepare("UPDATE album SET status = 'longterm' WHERE id = ?");
            $stmt->execute([$_SESSION['album_id']]);

            session_destroy();

            $redirectUrl = "/photographer-mission/remote/$device_id/$token?status_changed=1&qr_code_path=" . urlencode($qr_code_file);
            header("Location: $redirectUrl");
            exit();
        } catch (PDOException $e) {
            die("Database error occurred while updating album status: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/magnific-popup.js/1.1.0/magnific-popup.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/magnific-popup.js/1.1.0/jquery.magnific-popup.min.js"></script>
    <link rel="stylesheet" type="text/css" href="/photographer-mission/style.css">
    <style>
        #sidebar-wrapper {
            max-width: 300px;
            overflow: hidden;
        }
    </style>
    <title>Photographer App</title>
</head>
<body>
    <!-- Navbar for small screens -->
    <nav class="navbar position-sticky top-0 d-lg-none d-sm-block bg-white" style="z-index: 1030 !important;">
        <div class="container-fluid py-4">
            <div class="d-flex justify-content-between align-items-center w-100">
                <button class="btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasSidebar" aria-controls="offcanvasSidebar">
                    <i class="bi bi-list" style="font-size: 30px !important;"></i>
                </button>
                <div class="mx-auto">
                    <a href="remote.php" class="navbar-brand text-uppercase text-center" style="letter-spacing: .05em;">Photographer</a>
                </div>
            </div>
        </div>
    </nav>
    <!-- / navbar for small screens -->
   
    <!-- Sidebar Start -->
    <div class="d-flex" id="wrapper">
        <div class="row gx-0">
            <div class="col-md-3 col-lg-3 d-none d-lg-block">
                <!-- Sidebar -->
                <div id="sidebar-wrapper" class="vh-100 p-3" style="width: 300px; min-width: 300px; overflow-y: auto;">
                    <!-- Sidebar Heading with fade-in animation -->
                    <div class="navbar-brand mb-5 mt-3 fade-in" style="animation-delay: 0.05s;">
                        <a href="remote.php" class="sidebar-heading text-uppercase mt-3 text-decoration-none" style="color:#1FAD9F !important; font-size:20px; letter-spacing: .05em;">Photographer</a>
                    </div>
                    
                    <ul class="sidebar-nav" style="padding-left: 0 !important;">
                        <?php if ($isLive): ?>
                            <!-- First menu item -->
                            <li class="nav-item fade-in list-unstyled" style="animation-delay: 0.2s;">
                                <button type="button" class="aside-nav-link list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#receivePictures">
                                    <i class="bi bi-envelope-open-fill me-2"></i> Receive all my pictures
                                </button>
                            </li>
                            <!--Modal for 'Receive all my pictures' through email-->
                            <div class="modal fade" id="receivePictures" tabindex="-1" aria-labelledby="receivePicturesLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h4 class="modal-title" id="receivePicturesLabel">Receive Pictures</h4>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <form action="" method="post">
                                                <div class="mb-3">
                                                    <label for="email" class="form-label">Email</label>
                                                    <input type="email" name="email" id="email" class="form-control" placeholder="Enter your email here" required>
                                                </div>
                                                <button name="send_email" type="submit" class="btn btn-primary">Submit</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!--END / Modal for 'Receive all my pictures' through email-->
                            
                            <!-- Second menu item -->
                            <li class="nav-item fade-in list-unstyled" style="animation-delay: 0.4s;">
                                <button type="button" class="aside-nav-link list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#inviteFriendQr">
                                    <img class="me-2" src="/photographer-mission/images/add.png" width="20">Invite a friend
                                </button>
                            </li>
                            <div class="modal fade" id="inviteFriendQr" tabindex="-1" aria-labelledby="inviteFriendQrLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h4 class="modal-title" id="inviteFriendQrLabel">Invite a Friend</h4>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body d-flex flex-column">
                                            <?php if (isset($_SESSION['qr_code_path'])) : ?>
                                                <div class="justify-content-center text-center">
                                                    <p class="mb-0">Scan the QR code to access the album:</p>
                                                    <img src="/photographer-mission/<?= htmlspecialchars($_SESSION['qr_code_path']); ?>" alt="QR Code" class="img-fluid mt-0" width="400">
                                                </div>
                                                <?php else: ?>
                                                <p>QR code not available.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <style>
                                @keyframes fadeInLeft {
                                    from {
                                        opacity: 0;
                                        transform: translateX(-20px);
                                    }
                                    to {
                                        opacity: 1;
                                        transform: translateX(0);
                                    }
                                }

                                .fade-in {
                                    opacity: 0;
                                    animation: fadeInLeft 0.5s ease forwards;
                                }
                            </style>
                            
                        <?php else: ?>
                            <!-- First alternative item -->
                            <li class="nav-item fade-in list-unstyled" style="animation-delay: 0.2s;">
                                <button type="button" class="aside-nav-link list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#receivePictures">
                                    <i class="bi bi-people-fill me-2"></i>Invite a friend (via email)
                                </button>
                            </li>
                            <!--Modal for 'Invite a friend' through email-->
                            <div class="modal fade" id="receivePictures" tabindex="-1" aria-labelledby="receivePicturesLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h4 class="modal-title" id="receivePicturesLabel">Receive Pictures</h4>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <form action="" method="post">
                                                <div class="mb-3">
                                                    <label for="email" class="form-label">Email</label>
                                                    <input type="email" name="email" id="email" class="form-control" placeholder="Enter your email here" required>
                                                </div>
                                                <button name="send_email" type="submit" class="btn btn-primary">Submit</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!--END / Modal for 'Receive all my pictures' through email-->
                            
                            <!-- Second alternative item -->
                            <li class="nav-item fade-in list-unstyled" style="animation-delay: 0.4s;">
                                <a href="http://192.168.1.11/photographer-mission/download_pictures.php?album_id=<?php echo $_SESSION['album_id']; ?>" class="aside-nav-link list-group-item list-group-item-action">
                                    <i class="bi bi-download me-2"></i> Download all my pictures
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <!-- /#sidebar-wrapper -->
            </div>

            <!-- Modals for the offcanvas (for small screens) -->
                <!--Modal for 'Receive all my pictures' through email for navbar-->
                <div class="modal fade" id="receivePicturesModal" tabindex="-1" aria-labelledby="receivePicturesModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-fullscreen-sm-down modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h4 class="modal-title" id="receivePicturesModalLabel">Receive Pictures</h4>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form action="" method="post">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" name="email" id="email" class="form-control" placeholder="Enter your email here" required>
                                    </div>
                                    <button name="send_email" type="submit" class="btn btn-primary">Submit</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <!--END / Modal for 'Receive all my pictures' through email-->

                <!--Modal for 'Invite a Friend - QR' through email for navbar-->
                <div class="modal fade" id="inviteFriendQrModal" tabindex="-1" aria-labelledby="inviteFriendQrModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-fullscreen-sm-down modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h4 class="modal-title" id="inviteFriendQrModalLabel">Invite a Friend</h4>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body d-flex flex-column">
                                <?php if (isset($_SESSION['qr_code_path'])) : ?>
                                    <div class="justify-content-center text-center">
                                        <p class="mb-0">Scan the QR code to access the album:</p>
                                        <img src="/photographer-mission/<?= htmlspecialchars($_SESSION['qr_code_path']); ?>" alt="QR Code" class="img-fluid mt-0" width="400">
                                    </div>
                                    <?php else: ?>
                                    <p>QR code not available.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <!--END / Modal for 'Invite a Friend - QR' through email for navbar-->

                <!--Modal for 'Invite a friend' through email for navbar-->
                <div class="modal fade" id="receivePicturesModal" tabindex="-1" aria-labelledby="receivePicturesModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-fullscreen-sm-down modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h4 class="modal-title" id="receivePicturesModalLabel">Invite a Friend</h4>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form action="" method="post">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" name="email" id="email" class="form-control" placeholder="Enter your email here" required>
                                    </div>
                                    <button name="send_email" type="submit" class="btn btn-primary">Submit</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <!--END / Modal for 'Receive all my pictures' through email-->
            <!-- Modals for the offcanvas (for small screens) -->

            <!--Offcanvas sidebar menu for mobile-->
            <div class="offcanvas offcanvas-start" id="offcanvasSidebar" aria-labelledby="offcanvasSidebarLabel">
                <div class="close-button d-flex justify-content-end m-3">
                    <button type="button" class="bg-transparent border-0 small" data-bs-dismiss="offcanvas" aria-label="Close">
                        <i class="bi bi-arrow-left"></i>
                        <span class="fw-bold text-uppercase">Close</span>
                    </button>
                </div>
                <div class="offcanvas-header navbar-brand mb-5 fade-in" style="animation-delay: 0.05s;">
                    <a href="remote.php" class="offcanvas-heading text-uppercase text-decoration-none" style="color:#1FAD9F !important; font-size:20px; letter-spacing: .05em;">Photographer</a>
                </div>
                <div class="offcanvas-body">
                    <ul class="sidebar-nav" style="padding-left: 0 !important;">
                        <?php if ($isLive): ?>
                            <!-- First menu item -->
                            <li class="nav-item fade-in list-unstyled" style="animation-delay: 0.2s;">
                                <button type="button" class="aside-nav-link list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#receivePicturesModal">
                                    <i class="bi bi-envelope-open-fill me-2"></i> Receive all my pictures
                                </button>
                            </li>
                            
                            <!-- Second menu item -->
                            <li class="nav-item fade-in list-unstyled" style="animation-delay: 0.4s;">
                                <button type="button" class="aside-nav-link list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#inviteFriendQrModal"> <!-- Corrected ID here -->
                                    <img class="me-2" src="/photographer-mission/images/add.png" width="20">Invite a friend
                                </button>
                            </li>
                        <?php else: ?>
                            <!-- First alternative item -->
                            <li class="nav-item fade-in list-unstyled" style="animation-delay: 0.2s;">
                                <button type="button" class="aside-nav-link list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#receivePicturesModal">
                                    <i class="bi bi-people-fill me-2"></i>Invite a friend (via email)
                                </button>
                            </li>
                            
                            <!-- Second alternative item -->
                            <li class="nav-item fade-in list-unstyled" style="animation-delay: 0.4s;">
                                <a href="http://192.168.1.11/photographer-mission/download_pictures.php" class="aside-nav-link list-group-item list-group-item-action">
                                    <i class="bi bi-download me-2"></i> Download all my pictures
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <!--/ Offcanvas sidebar menu for mobile-->

            <div class="page-content-col col-md-9 col-lg-9">
                <!-- Page Content -->
                <div id="page-content-wrapper" class="flex-grow-1" style="padding-left: 0 !important;">
                    <div class="py-4">
                        <div class="row" id="image-gallery">
                            <?php if ($images): ?>
                                <?php $images = array_reverse($images); ?>
                                <?php foreach ($images as $index => $image): ?>
                                    <div class="col-lg-4 col-md-4">
                                        <div class="card mb-4">
                                            <a href="#" type="button" data-bs-toggle="modal" data-bs-target="#capturedImage<?= $index; ?>" class="image-item">
                                                <img src="<?= htmlspecialchars($baseUrl . $image['image_path']); ?>" class="img-fluid" alt="Captured Image">
                                            </a>
                                            <!-- Modal for image viewing -->
                                            <div class="modal fade" id="capturedImage<?= $index; ?>" tabindex="-1" aria-labelledby="capturedImageLabel" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-body text-center">
                                                            <img src="<?= htmlspecialchars($baseUrl . $image['image_path']); ?>" class="img-fluid mb-3" alt="Captured Image">
                                                            <div class="d-none d-md-block">
                                                                <a href="<?= htmlspecialchars($baseUrl . $image['image_path']); ?>" download="CapturedImage<?= $index; ?>" class="btn btn-primary mt-2">Download Image</a>
                                                            </div>
                                                            <div class="d-block d-md-none">
                                                                <p class="small">Long click on the image and click "Save to Camera Roll"</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-center me-5">No images yet. Please capture some!</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <!-- /#page-content-wrapper -->
            </div>
        </div>
    </div>
    <!-- /#wrapper -->

    <!-- Floating button to end visit -->
    <?php if ($isLive): ?>
        <form method="post" style="position: fixed; bottom: 20px; right: 20px;">
            <button type="submit" name="end_session" class="btn btn-danger">
                End Visit
            </button>
        </form>
    <?php endif; ?>

    <!-- JS Alert for album status change -->
    <?php if ($showAlert): ?>
        <script>
            alert("The album has been saved. You can now share the album and download your pictures.");
            window.history.replaceState({}, document.title, window.location.pathname);
        </script>
    <?php endif; ?>

    <script>
        // auto-refresh
        $(document).ready(function() {
            setInterval(function() {
                $('#image-gallery').load(window.location.href + ' #image-gallery > *'); // Load only the image gallery
            }, 20000);
        });
    </script>


<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy" crossorigin="anonymous"></script></body>
</html>