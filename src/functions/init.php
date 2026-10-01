<?php
//init.php:アプリケーションに必要な設定ファイルを初期化
if (!defined('ONESTORAGE_RUNNING')) {
    die('Access Denied: Invalid execution context.');
}
require_once __DIR__ . '/../path.php';
require_once __DIR__ . '/helpers.php';

// DATA_ROOT
if (!file_exists(MAIN_CONFIG_PATH)) { 
    redirect('setting.php');
}
$main_config = require MAIN_CONFIG_PATH;
if (!isset($main_config['data_root'])) {
    redirect('setting.php');
}
define('DATA_ROOT', $main_config['data_root']);
ensure_dir_protection(DATA_ROOT);
ensure_dir_protection(dirname(AUTH_CONFIG_PATH));
get_inbox_path();

// SHARE_ROOT (未設定・既存環境からの移行時は完全自動初期化)
if (!file_exists(SHARE_CONFIG_PATH)) {
    init_sharebox_environment();
}

if (file_exists(SHARE_CONFIG_PATH)) {
    $share_config = require SHARE_CONFIG_PATH;
    if (isset($share_config['share_root'])) {
        if (!is_dir($share_config['share_root'])) {
            @mkdir($share_config['share_root'], 0777, true);
        }
        if (is_dir($share_config['share_root'])) {
            define('SHARE_ROOT', $share_config['share_root']);
            ensure_dir_protection(SHARE_ROOT);
            define('SHARE_ENCRYPTION_ENABLED', true);
            define('SHARE_ENCRYPTION_KEY_SEED', $share_config['encryption_key_seed'] ?? '');
        }
    }
}

// 暗号化設定 (常時必須・完全有効)
global $encryption_enabled;
$encryption_enabled = true;
// ファイル設定の読み込み
global $file_config;
$file_config = [
    'allowed_extensions' => [],
    'max_file_size_mb' => 50
];
if (file_exists(ACCEPT_CONFIG_PATH)) {
    $json_content = file_get_contents(ACCEPT_CONFIG_PATH);
    $loaded_config = json_decode($json_content, true);
    if ($loaded_config) {
        $file_config = array_merge($file_config, $loaded_config);
    }
}
$allowed_ext_list = array_map(fn($ext) => '.' . trim($ext, '.'), $file_config['allowed_extensions']);
$accept_attribute = implode(',', $allowed_ext_list);