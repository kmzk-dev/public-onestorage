<?php
//auth.php: 認証状態の確認とフロー制御
require_once __DIR__ . '/../path.php';
require_once __DIR__ . '/cookie.php';

if (!defined('AUTH_CONFIG_PATH') || !file_exists(AUTH_CONFIG_PATH)) {
    redirect('setting.php');
}
if (!defined('MFA_SECRET_PATH') || !file_exists(MFA_SECRET_PATH)) {
    redirect('setting.php');
}

function check_authentication() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (validate_auth_cookie()) {
        return true;
    } 
    
    if (isset($_SESSION['auth_passed']) && $_SESSION['auth_passed'] === true) {
        redirect('mfa_login.php');
        exit;
    }

    $_SESSION = [];
    session_destroy();

    redirect('login.php');
    exit;
}