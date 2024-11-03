<?php
session_start();
include 'db_conn.php';

if (!isset($_SESSION['album_id'])) {
    die("User session not found. Please try again.");
}

$album_id = $_SESSION['album_id'] ?? $_GET['album_id'] ?? null;

$query = $pdo->prepare("SELECT image_path FROM capture WHERE album_id = :album_id");
$query->bindParam(':album_id', $album_id, PDO::PARAM_INT);
$query->execute();
$images = $query->fetchAll(PDO::FETCH_ASSOC);

if (empty($images)) {
    die('No images found for this album.');
}

$images_dir = __DIR__ . '/images/';
$zip_filename = "album_$album_id.zip";
$zip = new ZipArchive();

if ($zip->open($zip_filename, ZipArchive::CREATE) !== TRUE) {
    die("Could not create zip file.");
}

foreach ($images as $image) {
    $image_path = $images_dir . $image['image_path'];
    if (file_exists($image_path)) {
        $zip->addFile($image_path, basename($image_path));
    }
}

$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . basename($zip_filename) . '"');
header('Content-Length: ' . filesize($zip_filename));

readfile($zip_filename);

if (file_exists($zip_filename)) {
    unlink($zip_filename);
}
?>