<?php
// search.php: ファイル名検索APIコンポーネント
require_once __DIR__ . '/../path.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/cookie.php';

header('Content-Type: application/json; charset=utf-8');

if (!file_exists(MAIN_CONFIG_PATH)) { 
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'システム設定ファイル(config.php)がありません。']);
    exit;
}
$main_config = require MAIN_CONFIG_PATH;
if (!isset($main_config['data_root'])) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'config.phpにDATA_ROOTが定義されていません。']);
    exit;
}

if (!defined('DATA_ROOT')) {
    define('DATA_ROOT', $main_config['data_root']);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 認証チェック（クッキーまたはセッション）
$is_authenticated = validate_auth_cookie() || (isset($_SESSION['auth_passed']) && $_SESSION['auth_passed'] === true);
if (!$is_authenticated) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthenticated.']);
    exit; 
}

$query = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $query = $_GET['q'] ?? '';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_input = file_get_contents('php://input');
    $input = json_decode($raw_input, true);
    $query = $input['query'] ?? $_POST['q'] ?? '';
}

$query = trim((string)$query);
if ($query === '') {
    echo json_encode([
        'success' => true,
        'query' => '',
        'count' => 0,
        'results' => []
    ]);
    exit;
}

// キャッシュから検索
$matched_files = search_files_from_cache($query);
$results = [];

foreach ($matched_files as $file) {
    $web_path = $file['path'] ?? '';
    $name = $file['name'] ?? '';
    
    // ダウンロード・プレビュー用パラメータ
    $view_path = ($web_path === '') ? $name : ($web_path . '/' . $name);
    
    $icon_info = get_file_icon_info($name);

    $results[] = [
        'name' => $name,
        'path' => $web_path,
        'view_path' => $view_path,
        'size' => $file['size'] ?? 0,
        'formatted_size' => $file['formatted_size'] ?? format_bytes($file['size'] ?? 0),
        'mtime' => $file['mtime'] ?? 0,
        'formatted_mtime' => !empty($file['mtime']) ? date('Y-m-d H:i', $file['mtime']) : '-',
        'ext' => $file['ext'] ?? strtolower(pathinfo($name, PATHINFO_EXTENSION)),
        'icon' => $icon_info['icon'],
        'icon_color' => $icon_info['color']
    ];
}

echo json_encode([
    'success' => true,
    'query' => $query,
    'count' => count($results),
    'results' => $results
], JSON_UNESCAPED_UNICODE);
