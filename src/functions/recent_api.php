<?php
// recent_api.php: RECENT除外・復元APIエンドポイント
require_once __DIR__ . '/../path.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/cookie.php';
require_once __DIR__ . '/auth.php';

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

if (!is_authenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthenticated.']);
    exit; 
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';
$data = $input['data'] ?? [];
$response = ['success' => false, 'message' => 'Unknown action.'];

switch ($action) {
    case 'exclude':
        $web_path = $data['web_path'] ?? '';
        $item_name = $data['item_name'] ?? '';
        if (empty($item_name)) {
            $response['message'] = 'アイテム名が指定されていません。';
        } else {
            $response = exclude_recent_item($web_path, $item_name);
            $response['exclusion_count'] = get_recent_exclusion_count();
        }
        break;

    case 'batch_exclude':
        $items = $data['items'] ?? [];
        if (empty($items) || !is_array($items)) {
            $response['message'] = 'アイテムが選択されていません。';
        } else {
            $response = batch_exclude_recent_items($items);
            $response['exclusion_count'] = get_recent_exclusion_count();
        }
        break;

    case 'restore':
        $web_path = $data['web_path'] ?? '';
        $item_name = $data['item_name'] ?? '';
        if (empty($item_name)) {
            $response['message'] = 'アイテム名が指定されていません。';
        } else {
            $response = restore_recent_item($web_path, $item_name);
            $response['exclusion_count'] = get_recent_exclusion_count();
        }
        break;

    case 'clear_all':
        $response = clear_all_recent_exclusions();
        $response['exclusion_count'] = 0;
        break;

    case 'list':
        $exclusions = get_recent_exclusions();
        $response = [
            'success' => true,
            'items' => $exclusions,
            'exclusion_count' => count($exclusions)
        ];
        break;
}

echo json_encode($response);
exit;
