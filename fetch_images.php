<?php
include 'db_conn.php';

if (isset($_POST['album_id'])) {
    $album_id = $_POST['album_id'];

    $stmt = $pdo->prepare("SELECT * FROM capture WHERE album_id = :album_id ORDER BY created_at DESC");
    $stmt->execute(['album_id' => $album_id]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($images);
} else {
    echo json_encode([]);
}
?>