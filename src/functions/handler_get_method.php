<?php
//handler_get_method.php:	GETリクエストハンドラ: ファイルの閲覧（view）やダウンロード（download）処理を実行
if (!defined('ONESTORAGE_RUNNING')) {
    die('Access Denied: Invalid execution context.');
}
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/helpers.php';

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    if ($action === 'search') {
        require_once __DIR__ . '/search.php';
        exit;
    }
    if ($action === 'pdf_preview') {
        require_once __DIR__ . '/pdf_preview.php';
        exit;
    }
    if ($action === 'serve_preview') {
        require_once __DIR__ . '/serve_preview.php';
        exit;
    }
    if ($action === 'delete_preview_cache') {
        require_once __DIR__ . '/api_delete_preview_cache.php';
        exit;
    }
    
    global $encryption_enabled;
    $file_path_raw = $_GET['path'] ?? '';

    if (defined('SHARE_ROOT') && str_starts_with($file_path_raw, 'sharebox/')) {
        $internal_path = substr($file_path_raw, 9);
        $file_path = realpath(SHARE_ROOT . '/' . $internal_path);
        $is_valid_file = ($file_path && str_starts_with($file_path, SHARE_ROOT) && !is_dir($file_path));
    } elseif (defined('INBOX_DIR_NAME') && str_starts_with($file_path_raw, 'inbox/')) {
        $internal_path = INBOX_DIR_NAME . '/' . substr($file_path_raw, 6);
        $file_path = realpath(DATA_ROOT . '/' . $internal_path);
        $is_valid_file = ($file_path && strpos($file_path, DATA_ROOT) === 0 && !is_dir($file_path));
    } else {
        $file_path = realpath(DATA_ROOT . '/' . $file_path_raw);
        $is_valid_file = ($file_path && strpos($file_path, DATA_ROOT) === 0 && !is_dir($file_path));
    }

    if ($is_valid_file) {
        $file_name = basename($file_path);

        // --- ヘッダー設定 ---
        if ($action === 'view') {
            $mime_types = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'txt' => 'text/plain; charset=utf-8', 'html' => 'text/html; charset=utf-8'];
            $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $content_type = $mime_types[$extension] ?? 'application/octet-stream';
            header('Content-Type: ' . $content_type);
            header('Content-Disposition: inline; filename="' . rawurlencode($file_name) . '"');
        } else { // downloadボタンのヘッダー
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . rawurlencode($file_name) . '"');
        }

        // --- 出力処理（暗号化ストリーム復号） ---
        $encryption_key = get_encryption_key();
        if ($encryption_key === false) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'ファイルの復号に失敗しました。'];
            redirect('index.php');
        }
        decrypt_file_stream($file_path, $encryption_key);
        exit;

    } else {
        $_SESSION['message'] = ['type' => 'danger', 'text' => '無効なファイルです。'];
        redirect('index.php');
    }
}
