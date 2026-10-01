<?php
// setting.php: 初期セットアップAPIエンドポイント
require_once __DIR__ . '/../path.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/cookie.php';
require_once __DIR__ . '/mfa.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
const DEFAULT_PREDEFINED_EXTENSIONS = [
    'pdf', 'txt', 'csv', 'md', 'doc', 'docx', 'xls', 'xlsx', 'pptx',
    'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'heic', 'ai', 'psd',
    'mp3', 'wav', 'flac', 'aac', 'ogg', 'm4a', 'mp4', 'mov', 'avi', 'mkv', 'webm',
    'zip', '7z', 'rar', 'json', 'yml', 'yaml', 'ini', 'log'
];

$action = $input['action'] ?? '';
$data = $input['data'] ?? [];
$response = ['success' => false, 'message' => 'Unknown action.'];

switch ($action) {
    case 'setup_all':
        $user = trim($data['user'] ?? '');
        $password = $data['password'] ?? '';
        $secret = trim($data['mfa_secret'] ?? '');
        $mfa_enabled = isset($data['mfa_enabled']) ? (bool)$data['mfa_enabled'] : true;

        // 1. バリデーション
        if (empty($user) || !filter_var($user, FILTER_VALIDATE_EMAIL)) {
            $response = ['success' => false, 'message' => '有効なメールアドレスを入力してください。'];
            break;
        }
        if (strlen($password) < 15 || !preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9]).*$/', $password)) {
            $response = ['success' => false, 'message' => 'パスワードは大文字・小文字・数字を含む15文字以上で入力してください。'];
            break;
        }

        // 2. ストレージ設定 & accept.json (未作成の場合に作成)
        if (!file_exists(MAIN_CONFIG_PATH) || !file_exists(COOKIE_KEY_PATH)) {
            $storage_res = create_storage_data_config_api();
            if (!$storage_res['success'] && strpos($storage_res['message'], '既に存在') === false) {
                $response = $storage_res;
                break;
            }
        }
        if (!file_exists(ACCEPT_CONFIG_PATH)) {
            create_storage_accept_config_api(DEFAULT_PREDEFINED_EXTENSIONS, 500);
        }

        // 3. アカウント登録
        $auth_res = register_account_api($user, $password);
        if (!$auth_res['success']) {
            $response = $auth_res;
            break;
        }

        // 4. MFAシークレット保存 (enabledフラグ含む)
        if (!empty($secret)) {
            save_mfa_secret($secret, $mfa_enabled);
        } else {
            $new_secret = generate_mfa_secret(16);
            save_mfa_secret($new_secret, $mfa_enabled);
        }

        // 5. SHARE BOX同期
        $auth_config = require AUTH_CONFIG_PATH;

        if (file_exists(SHARE_CONFIG_PATH)) {
            $share_cfg = require SHARE_CONFIG_PATH;
            $share_cfg['encryption_key_seed'] = $auth_config['user'] ?? '';
            $share_cfg_content = "<?php\n\nreturn [\n"
                . "    'share_root' => '" . addslashes($share_cfg['share_root']) . "',\n"
                . "    'encryption_enabled' => true,\n"
                . "    'encryption_key_seed' => '" . addslashes($share_cfg['encryption_key_seed']) . "',\n"
                . "];\n";
            file_put_contents(SHARE_CONFIG_PATH, $share_cfg_content);

            if (!defined('SHARE_ROOT')) {
                define('SHARE_ROOT', $share_cfg['share_root']);
            }
            require_once __DIR__ . '/db.php';
            get_share_db();
        }

        $response = ['success' => true, 'message' => 'すべての初期設定が完了しました。'];
        break;

    case 'create_storage_data_config':
        $response = create_storage_data_config_api();
        break;

    case 'create_storage_accept_config':
        $extensions = $data['extensions'] ?? [];
        $max_size = $data['max_file_size_mb'] ?? 500;
        $response = create_storage_accept_config_api($extensions, (int)$max_size);
        break;

    case 'register_account':
        $user = $data['user'] ?? '';
        $password = $data['password'] ?? '';
        $response = register_account_api($user, $password);
        break;

    case 'generate_mfa_secret':
        $user = $data['user'] ?? '';
        $response = generate_mfa_secret_api($user);
        break;

    case 'finalize_setup':
        $files_exist = file_exists(AUTH_CONFIG_PATH)
            && file_exists(MAIN_CONFIG_PATH)
            && file_exists(ACCEPT_CONFIG_PATH)
            && file_exists(MFA_SECRET_PATH);

        if ($files_exist) {
            $auth_config = require AUTH_CONFIG_PATH;

            // SHARE BOX設定に暗号化キーシードを反映し、.share.dbを初期化
            if (file_exists(SHARE_CONFIG_PATH)) {
                $share_cfg = require SHARE_CONFIG_PATH;
                $share_cfg['encryption_key_seed'] = $auth_config['user'] ?? '';
                $share_cfg_content = "<?php\n\nreturn [\n"
                    . "    'share_root' => '" . addslashes($share_cfg['share_root']) . "',\n"
                    . "    'encryption_enabled' => " . (($share_cfg['encryption_enabled'] ?? false) ? 'true' : 'false') . ",\n"
                    . "    'encryption_key_seed' => '" . addslashes($share_cfg['encryption_key_seed']) . "',\n"
                    . "];\n";
                file_put_contents(SHARE_CONFIG_PATH, $share_cfg_content);

                if (!defined('SHARE_ROOT')) {
                    define('SHARE_ROOT', $share_cfg['share_root']);
                }
                require_once __DIR__ . '/db.php';
                get_share_db();
            }

            $response = ['success' => true, 'message' => '設定が完了しました。'];
        } else {
            $response = ['success' => false, 'message' => '必須設定ファイルが不足しています。'];
        }
        break;
}

echo json_encode($response);
exit;

// --- 初期設定専用のAPIヘルパー関数 ---

/**
 * データフォルダ、config.php, cookie_key.php を作成するAPI
 */
function create_storage_data_config_api(bool $encryption_enabled = true): array
{
    if (file_exists(MAIN_CONFIG_PATH) || file_exists(COOKIE_KEY_PATH)) {
        return ['success' => false, 'message' => '設定ファイルは既に存在します。'];
    }

    $error_messages = [];

    // 1. データフォルダとconfig.phpを作成
    $htaccess_content = "Order allow,deny\nDeny from all";
    $random_part = generate_random_string(15);
    $data_dir_name = 'data' . $random_part;
    $data_dir_path = dirname(__DIR__) . '/' . $data_dir_name;

    $config_dir = dirname(AUTH_CONFIG_PATH);
    if (!is_dir($config_dir)) {
        mkdir($config_dir, 0755, true);
    }
    ensure_dir_protection($config_dir);
    
    if (mkdir($data_dir_path, 0777, true)) {
        $main_config_content = "<?php\n\n";
        $main_config_content .= "return [\n";
        $main_config_content .= "    'data_root' => '" . addslashes($data_dir_path) . "',\n";
        $main_config_content .= "    'encryption_enabled' => true,\n];\n";
        
        if (file_put_contents(MAIN_CONFIG_PATH, $main_config_content) === false) {
            $error_messages[] = 'データフォルダ設定ファイル(config.php)の作成に失敗しました。';
        }
        ensure_dir_protection($data_dir_path);
    } else {
        $error_messages[] = 'データフォルダの作成に失敗しました。';
    }

    // SHARE BOX 用ディレクトリと初期設定ファイルを作成
    $share_random_part = generate_random_string(15);
    $share_dir_name    = 'share' . $share_random_part;
    $share_dir_path    = dirname(__DIR__) . '/' . $share_dir_name;

    if (mkdir($share_dir_path, 0777, true)) {
        ensure_dir_protection($share_dir_path);
        $share_config_content = "<?php\n\nreturn [\n"
            . "    'share_root' => '" . addslashes($share_dir_path) . "',\n"
            . "    'encryption_enabled' => true,\n"
            . "    'encryption_key_seed' => '',\n"
            . "];\n";
        if (file_put_contents(SHARE_CONFIG_PATH, $share_config_content) === false) {
            $error_messages[] = 'SHARE BOX設定ファイル(share_config.php)の作成に失敗しました。';
        }
    } else {
        $error_messages[] = 'SHARE BOXディレクトリの作成に失敗しました。';
    }

    // 2. クッキーキーを作成
    if (empty(create_and_save_cookie_key())) {
        $error_messages[] = 'クッキー認証キーの作成に失敗しました。';
    }

    // 3. accept.json を自動生成（代表拡張子38種、500MB）
    if (!file_exists(ACCEPT_CONFIG_PATH)) {
        create_storage_accept_config_api(DEFAULT_PREDEFINED_EXTENSIONS, 500);
    }

    if (empty($error_messages)) {
        return ['success' => true, 'message' => '基本設定ファイル(config.php, cookie_key.php)を作成しました。'];
    } else {
        return ['success' => false, 'message' => 'ファイル作成中にエラーが発生しました: ' . implode(' ', $error_messages)];
    }
}


/**
 * accept.json を作成するAPI
 */
function create_storage_accept_config_api(array $extensions = [], int $max_size = 500): array
{
    if (empty($extensions)) {
        $extensions = DEFAULT_PREDEFINED_EXTENSIONS;
    }
    $max_size = ($max_size > 0) ? $max_size : 500;

    $config_dir = dirname(AUTH_CONFIG_PATH);
    if (!is_dir($config_dir)) {
        mkdir($config_dir, 0755, true);
    }

    $accept_config_content = json_encode([
        'allowed_extensions' => $extensions,
        'max_file_size_mb' => $max_size
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    try {
        if (file_put_contents(ACCEPT_CONFIG_PATH, $accept_config_content) !== false) {
            return ['success' => true, 'message' => '拡張子設定ファイル(accept.json)を作成しました。'];
        } else {
            return ['success' => false, 'message' => '拡張子設定ファイル(accept.json)の書き込みに失敗しました。'];
        }
    } catch (\Throwable $th) {
        return ['success' => false, 'message' => '拡張子設定ファイル(accept.json)の作成に失敗しました。' . $th->getMessage()];
    }
}


/**
 * ユーザーアカウント（auth.php）を作成するAPI
 */
function register_account_api(string $user, string $password): array
{
    // バリデーション
    if (empty($user) || !filter_var($user, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'メールアドレスの形式が正しくありません。'];
    }
    if (empty($password)) {
        return ['success' => false, 'message' => 'パスワードを入力してください。'];
    }
    if (strlen($password) < 15) {
        return ['success' => false, 'message' => 'パスワードは15桁以上で設定してください。'];
    }
    if (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9]).*$/', $password)) {
        return ['success' => false, 'message' => 'パスワードには大文字英字、小文字英字、数字をすべて含めてください。'];
    }

    // auth.phpを登録
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $auth_config_content = "<?php\n\n";
    $auth_config_content .= "return [\n";
    $auth_config_content .= "    'user' => '" . addslashes($user) . "',\n";
    $auth_config_content .= "    'hash' => '" . addslashes($hash) . "',\n";
    $auth_config_content .= "];\n";
    if (!file_put_contents(AUTH_CONFIG_PATH, $auth_config_content)) {
        return ['success' => false, 'message' => '認証設定ファイル(auth.php)の作成に失敗しました。'];
    }

    return ['success' => true, 'message' => 'アカウント情報を登録しました。'];
}

/**
 * MFAシークレットキーを生成し、ファイルに保存するAPI
 */
function generate_mfa_secret_api(string $user): array
{
    if (empty($user)) {
        if (file_exists(AUTH_CONFIG_PATH)) {
            $auth_config = require AUTH_CONFIG_PATH;
            $user = $auth_config['user'] ?? 'user@onestorage.local';
        } else {
            $user = 'user@onestorage.local';
        }
    }

    if (file_exists(MFA_SECRET_PATH)) {
        $secret_key = get_mfa_secret();
        return build_mfa_response($secret_key, $user, 'MFAキーは既に存在します。');
    }

    $secret_key = generate_mfa_secret(16);
    if (save_mfa_secret($secret_key)) {
        return build_mfa_response($secret_key, $user, 'MFAシークレットキーを生成しました。');
    } else {
        return ['success' => false, 'message' => 'MFAシークレットキーの作成に失敗しました。'];
    }
}

/**
 * MFA APIレスポンスを構築するヘルパー
 */
function build_mfa_response(string $secret_key, string $user, string $message): array
{
    $issuer = rawurlencode('One Storage');
    $label = rawurlencode($user);
    $secret_url_encoded = rawurlencode($secret_key);

    /**
     * ライブラリ依存を回避するため、QRコードの作成には外部APIを利用しています。
     * https://goqr.me/api/
     */
    $otp_auth_uri = "otpauth://totp/{$issuer}:{$label}?secret={$secret_url_encoded}&issuer={$issuer}";
    $qr_code_url = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode($otp_auth_uri);

    return [
        'success' => true,
        'message' => $message,
        'secret' => $secret_key,
        'qr_code_url' => $qr_code_url,
    ];
}