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
header('X-Robots-Tag: noindex, nofollow, noarchive');

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
            . "# 1. 検索エンジンのインデックス・キャッシュ遮断\n"
            . "add_header X-Robots-Tag \"noindex, nofollow, noarchive\" always;\n\n"
            . "# 2. 設定ディレクトリへの直接アクセス遮断\n"
            . "location ^~ /" . $config_rel . " {\n    deny all;\n    return 404;\n}\n\n"
            . "# 3. データ・共有ディレクトリへの直接アクセス遮断\n"
            . "location ~* /(data|share).* {\n    deny all;\n    return 404;\n}\n\n"
            . "# 4. ドットファイル・隠しファイルへのアクセス拒否\n"
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

    case 'check_releases':
        $repo = 'kmzk-dev/public-onestorage';
        $api_url = "https://api.github.com/repos/{$repo}/releases";

        $response_body = updater_http_get($api_url, [
            'Accept: application/vnd.github+json',
            'User-Agent: OneStorage-Admin/1.0',
        ]);

        if ($response_body === false) {
            $response = ['success' => false, 'message' => 'GitHub API へのアクセスに失敗しました。サーバーの外部通信設定を確認してください。'];
            break;
        }

        $releases = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($releases)) {
            $response = ['success' => false, 'message' => 'GitHub API のレスポンスを解析できませんでした。'];
            break;
        }

        $current_version = defined('APP_VERSION') ? APP_VERSION : '1.0.0';
        $normalized_current = ltrim($current_version, 'v');

        $newer_releases = [];
        foreach ($releases as $rel) {
            if (!empty($rel['draft']) || !empty($rel['prerelease'])) {
                continue;
            }
            $tag = $rel['tag_name'] ?? '';
            $normalized_tag = ltrim($tag, 'v');

            if (version_compare($normalized_tag, $normalized_current, '>')) {
                $zip_url = $rel['zipball_url'] ?? '';
                if (!empty($rel['assets']) && is_array($rel['assets'])) {
                    foreach ($rel['assets'] as $asset) {
                        if (str_ends_with($asset['name'] ?? '', '.zip')) {
                            $zip_url = $asset['browser_download_url'] ?? $zip_url;
                            break;
                        }
                    }
                }

                $newer_releases[] = [
                    'tag_name'     => $tag,
                    'name'         => $rel['name'] ?? $tag,
                    'published_at' => $rel['published_at'] ?? '',
                    'html_url'     => $rel['html_url'] ?? '',
                    'zip_url'      => $zip_url,
                ];
            }
        }

        $response = [
            'success'         => true,
            'current_version' => $current_version,
            'has_update'      => !empty($newer_releases),
            'releases'        => $newer_releases,
        ];
        break;

    case 'apply_update':
        @set_time_limit(120);
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }

        $target_version = trim($data['version'] ?? '');
        $target_zip_url = trim($data['zip_url'] ?? '');

        if (empty($target_zip_url) && !empty($target_version)) {
            $repo = 'kmzk-dev/public-onestorage';
            $api_url = "https://api.github.com/repos/{$repo}/releases/tags/" . rawurlencode($target_version);
            $rel_body = updater_http_get($api_url, [
                'Accept: application/vnd.github+json',
                'User-Agent: OneStorage-Admin/1.0',
            ]);
            if ($rel_body !== false) {
                $rel_data = json_decode($rel_body, true);
                if (is_array($rel_data)) {
                    $target_zip_url = $rel_data['zipball_url'] ?? '';
                    if (!empty($rel_data['assets']) && is_array($rel_data['assets'])) {
                        foreach ($rel_data['assets'] as $asset) {
                            if (str_ends_with($asset['name'] ?? '', '.zip')) {
                                $target_zip_url = $asset['browser_download_url'] ?? $target_zip_url;
                                break;
                            }
                        }
                    }
                }
            }
        }

        if (empty($target_zip_url)) {
            $response = ['success' => false, 'message' => 'アップデート用の ZIP URL が取得できませんでした。'];
            break;
        }

        $dl_res = updater_download_zip($target_zip_url);
        if (!$dl_res['success']) {
            $response = ['success' => false, 'message' => $dl_res['message']];
            break;
        }
        $zip_path = $dl_res['zip_path'];

        $install_dir = dirname(__DIR__); // src/ ルート
        $ext_res = updater_extract_and_apply($zip_path, $install_dir);
        if (!$ext_res['success']) {
            $response = ['success' => false, 'message' => $ext_res['message']];
            break;
        }

        $response = [
            'success' => true,
            'message' => 'アップデートが完了しました。ページを再読み込みします。'
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

/**
 * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
 * セルフアップデータ用ヘルパー関数群
 * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
 */

/**
 * HTTP GET リクエストを実行する (cURL 優先、フォールバック: file_get_contents)
 */
function updater_http_get(string $url, array $headers = []): string|false
{
    if (extension_loaded('curl')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'OneStorage-Admin/1.0',
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($response !== false && $http_code === 200) ? $response : false;
    }

    if (ini_get('allow_url_fopen')) {
        $opts = [
            'http' => [
                'method'          => 'GET',
                'timeout'         => 15,
                'user_agent'      => 'OneStorage-Admin/1.0',
                'follow_location' => 1,
                'max_redirects'   => 5,
            ],
            'ssl' => ['verify_peer' => true],
        ];
        if (!empty($headers)) {
            $opts['http']['header'] = implode("\r\n", $headers);
        }
        return @file_get_contents($url, false, stream_context_create($opts));
    }

    return false;
}

/**
 * アップデート用 ZIP を一時ディレクトリにストリームダウンロード
 */
function updater_download_zip(string $url): array
{
    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        return ['success' => false, 'message' => '無効なダウンロードURLです。'];
    }

    $host = parse_url($url, PHP_URL_HOST);
    $is_allowed_host = in_array($host, ['github.com', 'api.github.com', 'codeload.github.com'], true)
        || (is_string($host) && str_ends_with($host, '.githubusercontent.com'));

    if (!$is_allowed_host) {
        return ['success' => false, 'message' => '許可されていないダウンロード元です (' . htmlspecialchars((string)$host) . ')。'];
    }

    $tmp_path = sys_get_temp_dir() . '/onestorage_update_' . md5(uniqid('', true)) . '.zip';

    if (extension_loaded('curl')) {
        $fp = fopen($tmp_path, 'wb');
        if (!$fp) {
            return ['success' => false, 'message' => '一時ファイルの作成に失敗しました。'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_USERAGENT      => 'OneStorage-Admin/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if ($result === false || $http_code !== 200) {
            @unlink($tmp_path);
            return ['success' => false, 'message' => "ZIPのダウンロードに失敗しました (HTTP {$http_code}): {$error}"];
        }
    } elseif (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create([
            'http' => [
                'timeout'         => 60,
                'user_agent'      => 'OneStorage-Admin/1.0',
                'follow_location' => 1,
                'max_redirects'   => 5,
            ],
            'ssl' => ['verify_peer' => true],
        ]);
        $data = @file_get_contents($url, false, $ctx);
        if ($data === false) {
            return ['success' => false, 'message' => 'ZIPのダウンロードに失敗しました (allow_url_fopen)。'];
        }
        file_put_contents($tmp_path, $data);
    } else {
        return ['success' => false, 'message' => '通信手段がありません (cURL / allow_url_fopen が無効)。'];
    }

    $size = filesize($tmp_path);
    if ($size < 1024) {
        @unlink($tmp_path);
        return ['success' => false, 'message' => 'ダウンロードされたZIPファイルが破損しているか小さすぎます。'];
    }

    return [
        'success'  => true,
        'zip_path' => $tmp_path,
        'size_kb'  => round($size / 1024),
    ];
}

/**
 * ZIPを展開し、データ保護を行いながらWeb公開ルートへ上書きコピー
 */
function updater_extract_and_apply(string $zip_path, string $install_dir): array
{
    if (!class_exists('ZipArchive')) {
        @unlink($zip_path);
        return ['success' => false, 'message' => 'ZipArchive 拡張が無効なため展開できません。'];
    }

    $zip = new ZipArchive();
    $open_res = $zip->open($zip_path);
    if ($open_res !== true) {
        @unlink($zip_path);
        return ['success' => false, 'message' => "ZIPファイルを開けませんでした (エラーコード: {$open_res})。"];
    }

    $extract_dir = sys_get_temp_dir() . '/onestorage_extract_' . md5(uniqid('', true));
    @mkdir($extract_dir, 0755, true);

    if (!$zip->extractTo($extract_dir)) {
        $zip->close();
        @unlink($zip_path);
        updater_recursive_rmdir($extract_dir);
        return ['success' => false, 'message' => 'ZIPの展開に失敗しました。一時ディレクトリの空き容量を確認してください。'];
    }
    $zip->close();
    @unlink($zip_path);

    $source_dir = updater_find_source_root($extract_dir);
    if ($source_dir === null) {
        updater_recursive_rmdir($extract_dir);
        return ['success' => false, 'message' => '展開先からソースルートディレクトリを特定できませんでした。'];
    }

    // installer.php や .installer_done は除外
    $copy_ok = updater_recursive_copy($source_dir, $install_dir, ['installer.php', '.installer_done']);

    updater_recursive_rmdir($extract_dir);

    if (!$copy_ok) {
        return ['success' => false, 'message' => 'ファイルの上書きコピー中にエラーが発生しました。ディレクトリ権限を確認してください。'];
    }

    return ['success' => true];
}

/**
 * 展開先からソースルートを特定
 */
function updater_find_source_root(string $extract_dir): ?string
{
    if (file_exists($extract_dir . '/index.php')) {
        return $extract_dir;
    }

    $items = scandir($extract_dir);
    if ($items === false) return null;

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $sub = $extract_dir . '/' . $item;
        if (!is_dir($sub)) continue;

        if (file_exists($sub . '/index.php')) {
            return $sub;
        }
        // リポジトリルート/src/index.php 構造への対応
        if (is_dir($sub . '/src') && file_exists($sub . '/src/index.php')) {
            return $sub . '/src';
        }
    }

    return null;
}

/**
 * データおよび設定を完全保護しながらディレクトリを再帰的に上書きコピー
 */
function updater_recursive_copy(string $src, string $dst, array $exclude = []): bool
{
    $ok = true;
    $items = scandir($src);
    if ($items === false) {
        return false;
    }

    $protected_files = [
        'auth.php',
        'config.php',
        'cookie_key.php',
        'mfa_secret.php',
        'share_config.php',
        'accept.json',
        '.storage.db',
        '.share.db',
        '.storage.db-journal',
        '.share.db-journal',
        '.installer_done',
        'installer.php',
    ];

    $norm_dst = str_replace('\\', '/', $dst);

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        if (in_array($item, $exclude, true)) continue;

        // data*, share* ディレクトリ（難読化フォルダ含む）やプレビュー・インボックスキャッシュはコピー・上書き除外
        if ($item === 'data-preview' || preg_match('/^(data|share)[A-Za-z0-9]{10,}$/', $item) || preg_match('/^\.(inbox|preview_cache)$/', $item)) {
            continue;
        }

        $src_path = $src . '/' . $item;
        $dst_path = $dst . '/' . $item;

        if (is_dir($src_path)) {
            if (!is_dir($dst_path)) {
                @mkdir($dst_path, 0755, true);
            }
            if (!updater_recursive_copy($src_path, $dst_path, $exclude)) {
                $ok = false;
            }
        } else {
            // コピー先に既存の保護対象ファイルが存在する場合は絶対に上書きしない
            if (file_exists($dst_path)) {
                // 1. 保護対象ファイル名と一致する場合
                if (in_array($item, $protected_files, true)) {
                    continue;
                }
                // 2. config ディレクトリ配下の全ファイル
                if (preg_match('#/config(/.*)?$#i', $norm_dst)) {
                    continue;
                }
                // 3. データベース実体・ジャーナルファイル
                if (str_ends_with($item, '.db') || str_ends_with($item, '.db-journal')) {
                    continue;
                }
            }

            // installer.php や .installer_done は除外
            if ($item === 'installer.php' || $item === '.installer_done') {
                continue;
            }

            if (!@copy($src_path, $dst_path)) {
                $ok = false;
            }
        }
    }

    return $ok;
}

/**
 * ディレクトリを再帰的に削除
 */
function updater_recursive_rmdir(string $dir): void
{
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    if ($items === false) return;

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        is_dir($path) ? updater_recursive_rmdir($path) : @unlink($path);
    }
    @rmdir($dir);
}


