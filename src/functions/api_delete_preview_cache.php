<?php
// functions/api_delete_preview_cache.php: プレビュー一時キャッシュ即時削除API
if (!defined('ONESTORAGE_RUNNING')) {
    die('Access Denied: Invalid execution context.');
}

$token = preg_replace('/[^a-f0-9]/', '', $_POST['token'] ?? '');
if (!empty($token)) {
    $cache_dir = DATA_ROOT . DIRECTORY_SEPARATOR . PREVIEW_CACHE_DIR_NAME;

    // 暗号化ON時: UUID.pdf の削除
    $target_pdf = realpath($cache_dir . DIRECTORY_SEPARATOR . $token . '.pdf');
    if ($target_pdf && strpos($target_pdf, $cache_dir) === 0 && is_file($target_pdf)) {
        @unlink($target_pdf);
    }

    // 暗号化OFF時: UUID.json の削除
    $target_json = realpath($cache_dir . DIRECTORY_SEPARATOR . $token . '.json');
    if ($target_json && strpos($target_json, $cache_dir) === 0 && is_file($target_json)) {
        @unlink($target_json);
    }
}

http_response_code(204); // No Content
exit;
