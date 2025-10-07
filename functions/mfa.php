<?php
// mfa.php: Google Authenticator (TOTP方式) の認証に必要な処理
require_once __DIR__ . '/../path.php';
require_once __DIR__ . '/helpers.php';

define('BASE32_CHARS', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567');

/**
 * Base32デコードGoogle Authenticatorの秘密鍵:Base32
 */
function base32_decode($base32) {
    $base32 = strtoupper($base32);
    $length = strlen($base32);
    $bytes = '';
    $buffer = 0;
    $bits = 0;
    for ($i = 0; $i < $length; $i++) {
        $char = $base32[$i];
        if ($char == '=') break;
        $char_value = strpos(BASE32_CHARS, $char);
        if ($char_value === false) continue;

        $buffer = ($buffer << 5) | $char_value;
        $bits += 5;

        while ($bits >= 8) {
            $bits -= 8;
            $bytes .= chr(($buffer >> $bits) & 0xFF);
        }
    }
    return $bytes;
}

/**
 * 秘密鍵をファイルからロードする。存在しない場合は空文字列を返す。
 */
function get_mfa_secret(): string {
    if (file_exists(MFA_SECRET_PATH)) {
        $config = require MFA_SECRET_PATH;
        return $config['secret'] ?? '';
    }
    return '';
}

/**
 * 新しいMFAシークレットキーを生成する
 */
function generate_mfa_secret(int $length = 16): string {
    $random_bytes = '';
    if (function_exists('random_bytes')) {
        $random_bytes = random_bytes($length);
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        $random_bytes = openssl_random_pseudo_bytes($length);
    } else {
        for ($i = 0; $i < $length; $i++) {
            $random_bytes .= chr(mt_rand(0, 255));
        }
    }

    $secret = '';
    $buffer = 0;
    $bits = 0;
    foreach (str_split($random_bytes) as $byte) {
        $byte_value = ord($byte);
        $buffer = ($buffer << 8) | $byte_value;
        $bits += 8;

        while ($bits >= 5) {
            $bits -= 5;
            $secret .= BASE32_CHARS[($buffer >> $bits) & 0x1F];
        }
    }
    if ($bits > 0) {
        $secret .= BASE32_CHARS[($buffer << (5 - $bits)) & 0x1F];
    }
    
    return substr($secret, 0, $length); 
}

/**
 * TOTPコードを検証する
 */
function verify_mfa_code(string $secret, string $code, int $time_drift = 1): bool {
    $secret_key = base32_decode($secret);
    if (strlen($secret_key) < 1) {
        return false;
    }
    
    $time_step = floor(time() / 30);
    
    for ($i = -$time_drift; $i <= $time_drift; $i++) {
        $check_time = $time_step + $i;
        
        $time_bytes = pack('N*', 0) . pack('N*', $check_time);
        $hmac = hash_hmac('sha1', $time_bytes, $secret_key, true);
        $offset = ord(substr($hmac, -1)) & 0xF;
        $dbc = (
            (ord(substr($hmac, $offset, 1)) & 0x7F) << 24 |
            (ord(substr($hmac, $offset + 1, 1)) & 0xFF) << 16 |
            (ord(substr($hmac, $offset + 2, 1)) & 0xFF) << 8 |
            (ord(substr($hmac, $offset + 3, 1)) & 0xFF)
        );

        $otp = $dbc % 1000000;
        $generated_code = str_pad($otp, 6, '0', STR_PAD_LEFT);
        if (hash_equals($generated_code, $code)) {
            return true;
        }
    }

    return false;
}

/**
 * 秘密鍵をファイルに保存する
 */
function save_mfa_secret(string $secret): bool {
    $config_dir = dirname(MFA_SECRET_PATH);

    if (!is_dir($config_dir)) {
        if (!mkdir($config_dir, 0755, true)) {
            error_log('Failed to create config directory for MFA secret.');
            return false;
        }
    }

    $content = "<?php\n\nreturn ['secret' => '" . addslashes($secret) . "'];\n";
    if (file_put_contents(MFA_SECRET_PATH, $content) === false) {
        error_log('Failed to save MFA secret key.');
        return false;
    }
    return true;
}