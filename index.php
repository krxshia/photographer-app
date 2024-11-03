<?php
include 'db_conn.php';
include 'phpqrcode/qrlib.php';
include 'utils.php';

session_start();

function ensureVenueExists($pdo) {
    $stmt = $pdo->query("SELECT id FROM venue LIMIT 1");
    $venue = $stmt->fetch();

    if (!$venue) {
        $venue_name = "Default Venue";
        $stmt = $pdo->prepare("INSERT INTO venue (name) VALUES (:name)");
        $stmt->execute(['name' => $venue_name]);
        return $pdo->lastInsertId();
    }

    return $venue['id'];
}

$venue_id = ensureVenueExists($pdo);

if (!isset($_SESSION['device_id'], $_SESSION['token'], $_SESSION['user_id'], $_SESSION['album_id'])) {
    $token = bin2hex(random_bytes(8));

    $stmt = $pdo->prepare("INSERT INTO remote (venue_id, token) VALUES (:venue_id, :token)");
    $stmt->execute(['venue_id' => $venue_id, 'token' => $token]);
    $device_id = $pdo->lastInsertId();

    $album_stmt = $pdo->prepare("INSERT INTO album (remote_id, venue_id, status) VALUES (:remote_id, :venue_id, 'live')");
    $album_stmt->execute(['remote_id' => $device_id, 'venue_id' => $venue_id]);
    $album_id = $pdo->lastInsertId();

    $user_stmt = $pdo->prepare("INSERT INTO user (album_id, email, name) VALUES (:album_id, '', 'Anonymous')");
    $user_stmt->execute(['album_id' => $album_id]);
    $user_id = $pdo->lastInsertId();

    $_SESSION['device_id'] = $device_id;
    $_SESSION['token'] = $token;
    $_SESSION['user_id'] = $user_id;
    $_SESSION['album_id'] = $album_id;

    $qr_code_url = "http://192.168.1.11/photographer-mission/remote/$device_id/$token";
    // i put the QR code here so that I can test this app on my mobile phone
    // please change it according to your laptop or PC's IP address
    // all IP addresses must be changed here on all files

} else {
    $device_id = $_SESSION['device_id'];
    $user_id = $_SESSION['user_id'];
    $album_id = $_SESSION['album_id'];
    $token = $_SESSION['token'];
    $qr_code_url = "http://192.168.1.11/photographer-mission/remote/$device_id/$token";
}

$stmt = $pdo->prepare("SELECT qr_code_path FROM album WHERE id = ?");
$stmt->execute([$album_id]);
$qr_code_path = $stmt->fetchColumn();

if (!$qr_code_path) {
    $qrCodeFilename = 'qrcode_' . $device_id . '_' . time() . '.png';
    $qrCodePath = 'qrcodes/' . $qrCodeFilename;

    QRcode::png($qr_code_url, $qrCodePath);

    if (file_exists($qrCodePath)) {
        $_SESSION['qr_code_path'] = $qrCodePath;

        $update_album_stmt = $pdo->prepare("UPDATE album SET qr_code_path = :qr_code_path WHERE id = :album_id");
        $update_album_stmt->execute([
            'qr_code_path' => $qrCodePath,
            'album_id' => $album_id,
        ]);
    } else {
        die("Error generating QR code.");
    }
} else {
    $_SESSION['qr_code_path'] = $qr_code_path;
}

$albumStatus = 'live';
if (isset($_SESSION['album_id'])) {
    $stmt = $pdo->prepare("SELECT status FROM album WHERE id = ?");
    $stmt->execute([$_SESSION['album_id']]);
    $album = $stmt->fetch();
    if ($album) {
        $albumStatus = $album['status'];
    }
}

if ($albumStatus === 'longterm') {
    echo "<script>alert('This album is in longterm mode and will not capture pictures anymore.');</script>";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['image'])) {
    $image = $_FILES['image'];

    if ($image['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'images/';
        $image_path = $upload_dir . basename($image['name']);

        if (move_uploaded_file($image['tmp_name'], $image_path)) {
            $capture_stmt = $pdo->prepare("INSERT INTO capture (album_id, image_path) VALUES (:album_id, :image_path)");
            $capture_stmt->execute(['album_id' => $album_id, 'image_path' => $image_path]);
        } else {
            echo "Failed to move uploaded file.";
        }
    } else {
        echo "Image upload error: " . $image['error'];
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
    <script src="https://kit.fontawesome.com/8908261793.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" type="text/css" href="style.css">
    <title>Photographer</title>
    <style>
        #camera {
            display: none;
        }
        .hidden {
            display: none !important;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row text-center">
            <div class="col-sm-12 col-md-12 col-lg-12">
                <h1 class="text-center">Photographer App</h1>
                <img src="/photographer-mission/<?= htmlspecialchars($_SESSION['qr_code_path']); ?>" alt="QR Code" style="width:200px;height:200px;">
                <p>Album URL: <a href="<?php echo $qr_code_url; ?>" target="_blank"><?php echo $qr_code_url; ?></a></p>

                <?php if ($albumStatus === 'live'): ?>
                    <p class="scan-this">Use your smartphone to scan the QR code above to access live photo album.</p>
                    <button class="btn btn-primary border-0" style="background:#1FAD9F !important;" id="open-camera-btn" onclick="openCamera()">Open Camera</button>
                <?php else: ?>
                    <!-- Hides camera div when in 'longterm' mode -->
                    <style>
                        #camera { display: none; }
                        .scan-this { display: none; }
                    </style>
                <?php endif; ?>

                <!-- Video element for the camera -->
                <div class="d-flex flex-column hidden" id="camera">
                    <div class="justify-content-center">
                        <h2>Camera Preview</h2>
                        <video id="camera-stream" autoplay style="width:100%; max-width:400px;"></video>
                        <canvas id="canvas" style="display:none;"></canvas>
                        <p class="small text-mute">Capture below to capture a moment!</p>
                        <!-- Shutter Button -->
                        <button class="btn btn-primary border-0 w-25" style="background:#1FAD9F !important;" id="shutter-btn" onclick="takePhoto()">
                            <i class="bi bi-camera"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openCamera() {
            const cameraStream = document.getElementById('camera-stream');
            const cameraContainer = document.getElementById('camera');
            const openCameraButton = document.getElementById('open-camera-btn');

            openCameraButton.style.display = 'none';
            cameraContainer.classList.remove('hidden');

            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                navigator.mediaDevices.getUserMedia({ video: true })
                    .then(function(stream) {
                        cameraStream.srcObject = stream;
                    })
                    .catch(function(error) {
                        alert('Error accessing camera: ' + error);
                    });
            } else {
                alert('Camera not supported in this browser.');
            }
        }


        function takePhoto() {
            const cameraStream = document.getElementById('camera-stream');
            const canvas = document.getElementById('canvas');
            const context = canvas.getContext('2d');
            
            canvas.width = cameraStream.videoWidth;
            canvas.height = cameraStream.videoHeight;
            context.drawImage(cameraStream, 0, 0, canvas.width, canvas.height);

            const imageData = canvas.toDataURL('image/png');
            uploadPhoto(imageData);
        }

        function uploadPhoto(imageData) {
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "save_image.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

            const userId = "<?php echo $_SESSION['user_id']; ?>";
            const albumId = "<?php echo $_SESSION['album_id']; ?>";

            xhr.send("image_data=" + encodeURIComponent(imageData) + "&user_id=" + userId + "&album_id=" + albumId);

            xhr.onload = function() {
                if (xhr.status == 200) {
                    alert("Image captured and saved!");
                } else {
                    alert("Error saving image: " + xhr.responseText);
                }
            };
        }
    </script>
</body>
</html>