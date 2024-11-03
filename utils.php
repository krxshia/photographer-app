<?php
function generateToken($album_id, $user_id) {
    $salt = "SALT123";
    return hash('sha256', $salt . $album_id . $user_id);
}

function validateToken($album_id, $user_id, $provided_token) {
    $expected_token = generateToken($album_id, $user_id);
    return $provided_token === $expected_token;
}
?>