<?php
//handler_get_method.php:	GETリクエストハンドラ: ファイルの閲覧（view）やダウンロード（download）処理を実行
if (!defined('ONESTORAGE_RUNNING')) {
    die('Access Denied: Invalid execution context.');
}
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/helpers.php';

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $file_path_raw = $_GET['path'] ?? '';
    $file_path = realpath(DATA_ROOT . '/' . $file_path_raw);

    if (defined('INBOX_DIR_NAME') && str_starts_with($file_path_raw, 'inbox/')) {
        $internal_path = INBOX_DIR_NAME . '/' . substr($file_path_raw, 6);
        $file_path = realpath(DATA_ROOT . '/' . $internal_path);
    } else {
        $file_path = realpath(DATA_ROOT . '/' . $file_path_raw);
    }

    if ($file_path && strpos($file_path, DATA_ROOT) === 0 && !is_dir($file_path)) {
        $file_name = basename($file_path);
        if ($action === 'view') {
            $mime_types = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'txt' => 'text/plain; charset=utf-8', 'html' => 'text/html; charset=utf-8'];
            $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $content_type = $mime_types[$extension] ?? 'application/octet-stream';
            header('Content-Type: ' . $content_type);
            header('Content-Disposition: inline; filename="' . rawurlencode($file_name) . '"');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
        } elseif ($action === 'download') {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . rawurlencode($file_name) . '"');
            readfile($file_path);
            exit;
        }
    } else {
        $_SESSION['message'] = ['type' => 'danger', 'text' => '無効なファイルです。'];
        redirect('index.php');
    }
}
