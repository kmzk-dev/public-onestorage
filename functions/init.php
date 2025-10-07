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
get_inbox_path();

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