<?php
define('ONESTORAGE_RUNNING', true);
require_once __DIR__ . '/functions/helpers.php';
require_once __DIR__ . '/functions/cookie.php';

clear_auth_cookie();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
unset($_SESSION['auth_passed']); 

$_SESSION = [];
session_destroy();

redirect('index.php');