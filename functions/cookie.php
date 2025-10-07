<?php

// cookie_function.php: 認証クッキーの管理を行うヘルパー関数群
require_once __DIR__ . '/../path.php';
require_once __DIR__ . '/helpers.php';

define('AUTH_COOKIE_NAME', 'one_storage_auth');
define('AUTH_COOKIE_EXPIRY_DAYS', 30);

/**
 * 認証用秘密鍵を生成し、config/cookie_key.phpに保存（初回またはリフレッシュ時）
 * @return string 生成された秘密鍵
 */
function create_and_save_cookie_key(): string {
    $key = generate_random_string(64);
    $config_dir = dirname(COOKIE_KEY_PATH);

    if (!is_dir($config_dir)) {
        mkdir($config_dir, 0755, true);
    }
    
    $content = "<?php\n\nreturn ['key' => '" . addslashes($key) . "'];\n";
    if (file_put_contents(COOKIE_KEY_PATH, $content) === false) {
        error_log('Failed to save cookie key.');
        return '';
    }
    return $key;
}

/**
 * 秘密鍵を取得する。存在しない場合は新規作成する。
 * @return string 秘密鍵
 */
function get_cookie_key(): string {
    if (file_exists(COOKIE_KEY_PATH)) {
        $config = require COOKIE_KEY_PATH;
        return $config['key'] ?? create_and_save_cookie_key();
    }
    return create_and_save_cookie_key();
}

/**
 * 認証クッキーを発行する
 * @param string $user 認証されたユーザー名（メールアドレス）
 */
function issue_auth_cookie(string $user): void {
    $secret_key = get_cookie_key();
    if (empty($secret_key)) {
        error_log('Cannot issue auth cookie: secret key is missing.');
        return;
    }

    $token = hash_hmac('sha256', $user, $secret_key);
    $cookie_value = base64_encode(json_encode(['user' => $user, 'token' => $token]));
    $expiry = time() + (86400 * AUTH_COOKIE_EXPIRY_DAYS);

    $is_secure = is_https();
    
    setcookie(AUTH_COOKIE_NAME, $cookie_value, [
        'expires' => $expiry,
        'path' => '/',
        'domain' => '', 
        'secure' => $is_secure, 
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

/**
 * 認証クッキーを検証する
 * @return bool 認証成功ならtrue
 */
function validate_auth_cookie(): bool {
    if (!isset($_COOKIE[AUTH_COOKIE_NAME])) {
        return false;
    }
    
    $cookie_value = $_COOKIE[AUTH_COOKIE_NAME];
    $data = json_decode(base64_decode($cookie_value), true);

    if (!isset($data['user']) || !isset($data['token']) || !file_exists(AUTH_CONFIG_PATH)) {
        return false;
    }
    
    $user = $data['user'];
    $received_token = $data['token'];
    
    $secret_key = get_cookie_key();
    if (empty($secret_key)) {
        return false;
    }
    
    $auth_config = require AUTH_CONFIG_PATH; 
    if ($user !== $auth_config['user']) {
        return false;
    }
    
    $expected_token = hash_hmac('sha256', $user, $secret_key);
    
    return hash_equals($expected_token, $received_token);
}

/**
 * 認証クッキーを削除する
 */
function clear_auth_cookie(): void {
    if (isset($_COOKIE[AUTH_COOKIE_NAME])) {
        // 有効期限を過去に設定して削除
        setcookie(AUTH_COOKIE_NAME, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => '',
            'secure' => false, 
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
}