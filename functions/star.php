<?php
// star.php: スター機能APIエンドポイント
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

define('DATA_ROOT', $main_config['data_root']);

if (!validate_auth_cookie()) {
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

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$data = $input['data'] ?? [];
$response = ['success' => false, 'message' => 'Unknown action.'];

switch ($action) {
    case 'toggle_star':
        $web_path = $data['web_path'] ?? '';
        $item_name = $data['item_name'] ?? '';
        $is_dir = filter_var($data['is_dir'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (empty($item_name)) {
            $response['message'] = 'アイテム名が指定されていません。';
        } else {
            $result = toggle_star_item($web_path, $item_name, $is_dir);
            $response = ['success' => $result['success'], 'message' => $result['message'], 'action' => $result['action'] ?? ''];
        }
        break;
}

echo json_encode($response);
exit;