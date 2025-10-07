<?php
// setting.php: 初期セットアップAPIエンドポイント
require_once __DIR__ . '/../path.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/cookie.php';
require_once __DIR__ . '/mfa.php';

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
            issue_auth_cookie($auth_config['user']);
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
 * config.php, cookie_key.php を確認・作成するAPI
 * 前提条件:必須ディレクトリはsetting.phpのロード時に作成済みであること
 */
function create_storage_data_config_api(): array
{
    $error_messages = [];

    if (!file_exists(MAIN_CONFIG_PATH)) {
        return ['success' => false, 'message' => 'データフォルダ設定ファイル(config.php)がありません。setting.phpを再実行してください。'];
    }

    if (!file_exists(COOKIE_KEY_PATH)) {
        if (empty(create_and_save_cookie_key())) {
            $error_messages[] = 'クッキー認証キーの作成に失敗しました。';
        }
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
function create_storage_accept_config_api(array $extensions, int $max_size): array
{
    if (empty($extensions) || $max_size <= 0) {
        return ['success' => false, 'message' => '許可する拡張子とファイルサイズ上限を指定してください。'];
    }

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
