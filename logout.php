<?php
session_start();
session_destroy();
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
setcookie('auth_ug', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => $isSecure,
    'httponly' => true,
    'samesite' => 'Lax'
]);
header("Location: index.php");
exit;
?>
