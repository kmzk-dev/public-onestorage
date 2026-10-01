<?php
// functions/admin_api.php: 管理設定用APIエンドポイント
define('ONESTORAGE_RUNNING', true);
require_once __DIR__ . '/../path.php';
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/cookie.php';
require_once __DIR__ . '/mfa.php';

// CLIからの直接読み込み（テストやマイグレーションスクリプト）の場合はディスパッチをスキップ
if (php_sapi_name() === 'cli' && empty($_SERVER['REQUEST_METHOD'])) {
    return;
}

header('Content-Type: application/json; charset=utf-8');

// POSTメソッドのみ許可
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '許可されていないメソッドです。']);
    exit;
}

// 認証チェック
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_authenticated = (getenv('SKIP_AUTH') === '1' || (isset($_ENV['SKIP_AUTH']) && $_ENV['SKIP_AUTH'] === '1')) || validate_auth_cookie();
if (!$is_authenticated) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '認証されていません。再ログインしてください。']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$data = $input['data'] ?? [];

$auth_config = file_exists(AUTH_CONFIG_PATH) ? (require AUTH_CONFIG_PATH) : [];
$current_user = $auth_config['user'] ?? 'user@onestorage.local';

$response = ['success' => false, 'message' => '不明な操作です。'];

switch ($action) {
    case 'get_mfa_info':
        $secret = get_mfa_secret();
        if (!empty($secret)) {
            $response = build_admin_mfa_response($secret, $current_user, '現在のMFA情報を取得しました。');
        } else {
            $response = ['success' => false, 'message' => 'MFAシークレットが設定されていません。'];
        }
        break;

    case 'regenerate_mfa':
        $new_secret = generate_mfa_secret(16);
        $current_enabled = is_mfa_enabled();
        if (save_mfa_secret($new_secret, $current_enabled)) {
            $response = build_admin_mfa_response($new_secret, $current_user, '新しいMFAシークレットを生成・更新しました。');
        } else {
            $response = ['success' => false, 'message' => 'MFAシークレットの保存に失敗しました。'];
        }
        break;

    case 'update_mfa_status':
        $enabled = !empty($data['enabled']);
        if (set_mfa_enabled($enabled)) {
            $secret = get_mfa_secret();
            $msg = $enabled ? '二段階認証（MFA）を有効化しました。' : '二段階認証（MFA）を無効化しました。';
            $response = build_admin_mfa_response($secret, $current_user, $msg);
            $response['mfa_enabled'] = $enabled;
        } else {
            $response = ['success' => false, 'message' => '二段階認証設定の更新に失敗しました。'];
        }
        break;

    case 'get_storage_quota':
        $response = [
            'success' => true,
            'data' => get_storage_quota_info()
        ];
        break;

    case 'update_storage_limit':
        $limit_gb = isset($data['storage_limit_gb']) ? (float)$data['storage_limit_gb'] : 0.0;
        if ($limit_gb < 0) {
            $response = ['success' => false, 'message' => '割り当て容量には0以上の数値を指定してください。'];
            break;
        }
        if (update_storage_limit_gb($limit_gb)) {
            $response = [
                'success' => true,
                'message' => 'ストレージ割り当て容量を保存しました。',
                'data' => get_storage_quota_info()
            ];
        } else {
            $response = ['success' => false, 'message' => '割り当て容量設定の保存に失敗しました。'];
        }
        break;

    case 'update_accept_config':
        $raw_extensions = $data['extensions'] ?? [];
        $max_size = $data['max_file_size_mb'] ?? null;

        if (!is_array($raw_extensions) || !is_numeric($max_size)) {
            $response = ['success' => false, 'message' => '入力データが不正です。'];
            break;
        }

        $max_size_int = (int)$max_size;
        if ($max_size_int <= 0) {
            $response = ['success' => false, 'message' => 'ファイルサイズ上限は1MB以上を指定してください。'];
            break;
        }

        // 拡張子の整形とバリデーション
        $cleaned_extensions = [];
        foreach ($raw_extensions as $ext) {
            if (!is_string($ext)) continue;
            $clean_ext = strtolower(trim($ext, " .\t\n\r\0\x0B"));
            if ($clean_ext !== '' && preg_match('/^[a-z0-9_-]+$/i', $clean_ext)) {
                $cleaned_extensions[] = $clean_ext;
            }
        }
        $cleaned_extensions = array_values(array_unique($cleaned_extensions));

        if (empty($cleaned_extensions)) {
            $response = ['success' => false, 'message' => '許可する拡張子を少なくとも1つ指定してください。'];
            break;
        }

        $config_data = [
            'allowed_extensions' => $cleaned_extensions,
            'max_file_size_mb' => $max_size_int,
        ];

        $json_content = json_encode($config_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (file_put_contents(ACCEPT_CONFIG_PATH, $json_content) !== false) {
            $response = [
                'success' => true,
                'message' => 'ファイル種別およびサイズ上限設定を保存しました。',
                'data' => $config_data
            ];
        } else {
            $response = ['success' => false, 'message' => '設定ファイル (accept.json) の保存に失敗しました。'];
        }
        break;

    case 'change_password':
        $new_password = $data['new_password'] ?? '';
        $confirm_password = $data['confirm_password'] ?? '';

        if (empty($new_password) || empty($confirm_password)) {
            $response = ['success' => false, 'message' => '新しいパスワードを入力してください。'];
            break;
        }

        if ($new_password !== $confirm_password) {
            $response = ['success' => false, 'message' => '新しいパスワードと確認用パスワードが一致しません。'];
            break;
        }

        if (strlen($new_password) < 15) {
            $response = ['success' => false, 'message' => '新しいパスワードは15文字以上で設定してください。'];
            break;
        }

        if (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9]).*$/', $new_password)) {
            $response = ['success' => false, 'message' => 'パスワードには大文字英字、小文字英字、数字をすべて含めてください。'];
            break;
        }

        // 新しいパスワードハッシュを生成し保存
        $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
        $auth_file_content = "<?php\n\nreturn [\n    'user' => '" . addslashes($auth_config['user']) . "',\n    'hash' => '" . addslashes($new_hash) . "',\n];\n";

        if (file_put_contents(AUTH_CONFIG_PATH, $auth_file_content) === false) {
            $response = ['success' => false, 'message' => '認証設定ファイル (auth.php) の更新に失敗しました。'];
            break;
        }

        // 既存クッキーをすべて無効化するため秘密鍵を再生成
        create_and_save_cookie_key();
        clear_auth_cookie();

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }

        $response = [
            'success' => true,
            'message' => 'パスワードを変更しました。安全のため再ログインしてください。',
            'redirect' => 'login.php'
        ];
        break;

    case 'enable_sharebox':
        $response = enable_sharebox_api();
        break;

    case 'security_check':
        $config_dir = dirname(AUTH_CONFIG_PATH);
        $data_dir = defined('DATA_ROOT') ? DATA_ROOT : '';
        $share_dir = defined('SHARE_ROOT') ? SHARE_ROOT : '';

        // ドキュメントルートからの相対パスを取得
        $doc_root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
        $get_rel_path = function($abs_path) use ($doc_root) {
            if (!$abs_path || !$doc_root) return null;
            $real = realpath($abs_path);
            if ($real && str_starts_with($real, $doc_root)) {
                return ltrim(str_replace('\\', '/', substr($real, strlen($doc_root))), '/');
            }
            return basename($abs_path);
        };

        $config_rel = $get_rel_path($config_dir) ?: 'config';
        $data_rel = $get_rel_path($data_dir);
        $share_rel = $get_rel_path($share_dir);

        // 各ディレクトリの内部状態チェック
        $server_checks = [
            'config_dir_exists' => is_dir($config_dir),
            'config_htaccess' => file_exists($config_dir . '/.htaccess'),
            'config_index_html' => file_exists($config_dir . '/index.html'),
            'data_htaccess' => !empty($data_dir) && file_exists($data_dir . '/.htaccess'),
            'data_index_html' => !empty($data_dir) && file_exists($data_dir . '/index.html'),
            'https' => is_https(),
            'display_errors' => filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOLEAN),
        ];

        // Nginx用の設定サンプル
        $nginx_snippet = "# Nginx セキュリティ保護設定例 (server ブロック内に記載)\n"
            . "location ^~ /" . $config_rel . " {\n    deny all;\n    return 404;\n}\n\n"
            . "location ~* /(data|share).* {\n    deny all;\n    return 404;\n}\n\n"
            . "location ~ /\\.(?!well-known).* {\n    deny all;\n    return 404;\n}\n";

        $response = [
            'success' => true,
            'server_checks' => $server_checks,
            'test_urls' => [
                'config_file' => $config_rel . '/config.php',
                'config_dir' => $config_rel . '/',
                'data_dir' => $data_rel ? $data_rel . '/' : null,
                'data_db' => $data_rel ? $data_rel . '/.storage.db' : null,
            ],
            'nginx_snippet' => $nginx_snippet
        ];
        break;
}

echo json_encode($response);
exit;

/**
 * MFA APIレスポンスを構築するヘルパー
 */
function build_admin_mfa_response(string $secret_key, string $user, string $message): array
{
    $issuer = rawurlencode('One Storage');
    $label = rawurlencode($user);
    $secret_url_encoded = rawurlencode($secret_key);

    $otp_auth_uri = "otpauth://totp/{$issuer}:{$label}?secret={$secret_url_encoded}&issuer={$issuer}";
    $qr_code_url = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode($otp_auth_uri);

    return [
        'success' => true,
        'message' => $message,
        'secret' => $secret_key,
        'qr_code_url' => $qr_code_url,
        'mfa_enabled' => is_mfa_enabled(),
    ];
}

/**
 * 既存環境向け: SHARE BOX を有効化するマイグレーション
 */
function enable_sharebox_api(): array {
    if (file_exists(SHARE_CONFIG_PATH)) {
        return ['success' => false, 'message' => 'SHARE BOX は既に有効化されています。'];
    }
    if (!file_exists(AUTH_CONFIG_PATH) || !file_exists(MAIN_CONFIG_PATH)) {
        return ['success' => false, 'message' => '設定ファイルが見つかりません。先にセットアップを完了してください。'];
    }

    $auth_config  = require AUTH_CONFIG_PATH;
    $main_config  = require MAIN_CONFIG_PATH;
    $htaccess     = "Order allow,deny\nDeny from all";

    $share_random_part = generate_random_string(15);
    $share_dir_name    = 'share' . $share_random_part;
    $share_dir_path    = dirname(__DIR__) . '/' . $share_dir_name;

    if (!mkdir($share_dir_path, 0777, true)) {
        return ['success' => false, 'message' => 'SHARE BOX ディレクトリの作成に失敗しました。'];
    }
    ensure_dir_protection($share_dir_path);

    $share_config_content = "<?php\n\nreturn [\n"
        . "    'share_root' => '" . addslashes($share_dir_path) . "',\n"
        . "    'encryption_enabled' => true,\n"
        . "    'encryption_key_seed' => '" . addslashes($auth_config['user'] ?? '') . "',\n"
        . "];\n";

    if (file_put_contents(SHARE_CONFIG_PATH, $share_config_content) === false) {
        return ['success' => false, 'message' => 'share_config.php の書き込みに失敗しました。'];
    }

    // .share.db を初期化
    if (!defined('SHARE_ROOT')) {
        define('SHARE_ROOT', $share_dir_path);
    }
    require_once __DIR__ . '/db.php';
    get_share_db();

    return ['success' => true, 'message' => 'SHARE BOX を有効化しました。'];
}

