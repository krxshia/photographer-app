<?php
include 'db_conn.php';
session_start();

if (isset($_POST['album_id']) && isset($_POST['user_id'])) {
    $album_id = $_POST['album_id'];
    $user_id = $_POST['user_id'];
} else {
    die("Missing album_id or user_id.");
}

error_log(print_r($_POST, true));

if (isset($_POST['image_data'])) {
    $image_data = $_POST['image_data'];
    $image_data = str_replace('data:image/png;base64,', '', $image_data);
    $image_data = str_replace(' ', '+', $image_data);
    $image = base64_decode($image_data);

    if ($image === false) {
        die("Failed to decode image data.");
    }

    $image_file = 'images/capture_' . uniqid() . '.png';
    if (file_put_contents($image_file, $image) !== false) {
        $stmt = $pdo->prepare("INSERT INTO capture (album_id, user_id, image_path) VALUES (:album_id, :user_id, :image_path)");
        $stmt->bindParam(':album_id', $album_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':image_path', $image_file);

        if ($stmt->execute()) {
            echo "Image saved successfully!";
        } else {
            echo "Failed to save image path to the database.";
        }
    } else {
        echo "Failed to save image to the server.";
    }
} else {
    echo "No image data received.";
}
?>