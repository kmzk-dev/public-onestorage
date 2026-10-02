<?php
/**
 * ONE STORAGE - ワンファイルインストーラー & アップデータ
 *
 * 使い方:
 *   1. このファイル (installer.php) をサーバーの公開ディレクトリにアップロード
 *   2. ブラウザで https://yourdomain.com/installer.php にアクセス
 *   3. 画面の指示に従って新規インストールまたはアップデートを実行
 *
 * 必要な PHP 拡張: curl または allow_url_fopen, ZipArchive, pdo_sqlite
 * 対応 PHP バージョン: 8.0 以上
 */

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// 設定
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
const GITHUB_REPO        = 'kmzk-dev/public-onestorage'; // GitHub リポジトリ (owner/repo)
/**
 * インストール対象のバージョン（Git タグ名）
 * - 特定バージョンを固定する場合: 例 'v1.2.1'
 * - 常に最新版を対象とする場合: 'latest'
 */
const TARGET_VERSION     = 'v1.2.5'; 
const INSTALL_LOCK_FILE  = __DIR__ . '/.installer_done';
const INSTALLER_TIMEOUT  = 25; // 秒 (一般的なサーバー制限より余裕を持たせる)
const MIN_PHP_VERSION    = '8.0.0';

// 既にインストール済みならブロック
if (file_exists(INSTALL_LOCK_FILE)) {
    http_response_code(403);
    die('<h1>403 Forbidden</h1><p>インストール・更新は既に完了しています。セキュリティのため <code>installer.php</code> を削除してください。<br>アップデートを行う場合は、サーバーから <code>.installer_done</code> を削除した上で再アクセスしてください。</p>');
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// AJAX API ハンドラ (POST リクエスト)
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');
    $input  = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    switch ($action) {
        case 'check':
            echo json_encode(run_checks());
            break;
        case 'fetch_release':
            echo json_encode(fetch_latest_release_info());
            break;
        case 'download':
            $url = $input['zip_url'] ?? '';
            echo json_encode(download_zip($url));
            break;
        case 'extract':
            $zip_path = $input['zip_path'] ?? '';
            echo json_encode(extract_and_install($zip_path));
            break;
        case 'finalize':
            echo json_encode(finalize());
            break;
        default:
            echo json_encode(['success' => false, 'message' => '不明なアクションです。']);
    }
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// バックエンド処理関数
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

/**
 * PHP環境および既存データの保護チェックを行い、結果を返す
 */
function run_checks(): array
{
    $checks = [];
    $all_ok = true;

    // PHP バージョン
    $php_ok = version_compare(PHP_VERSION, MIN_PHP_VERSION, '>=');
    $checks[] = [
        'name'    => 'PHP バージョン (' . PHP_VERSION . ')',
        'ok'      => $php_ok,
        'message' => $php_ok ? 'OK' : MIN_PHP_VERSION . ' 以上が必要です。',
    ];
    if (!$php_ok) $all_ok = false;

    // ZipArchive
    $zip_ok = class_exists('ZipArchive');
    $checks[] = [
        'name'    => 'ZipArchive 拡張',
        'ok'      => $zip_ok,
        'message' => $zip_ok ? 'OK' : 'zip PHP拡張が無効です。サーバー管理者にお問い合わせください。',
    ];
    if (!$zip_ok) $all_ok = false;

    // cURL または allow_url_fopen
    $http_ok = extension_loaded('curl') || ini_get('allow_url_fopen');
    $checks[] = [
        'name'    => 'HTTP 通信 (cURL / allow_url_fopen)',
        'ok'      => $http_ok,
        'message' => $http_ok ? 'OK' : 'cURL 拡張または allow_url_fopen が必要です。',
    ];
    if (!$http_ok) $all_ok = false;

    // PDO + SQLite
    $pdo_ok = extension_loaded('pdo') && extension_loaded('pdo_sqlite');
    $checks[] = [
        'name'    => 'PDO SQLite 拡張',
        'ok'      => $pdo_ok,
        'message' => $pdo_ok ? 'OK' : 'pdo_sqlite PHP拡張が無効です。',
    ];
    if (!$pdo_ok) $all_ok = false;

    // 書き込みパーミッション
    $dir_writable = is_writable(__DIR__);
    $checks[] = [
        'name'    => 'ディレクトリ書き込み権限 (' . basename(__DIR__) . '/)',
        'ok'      => $dir_writable,
        'message' => $dir_writable ? 'OK' : '公開ディレクトリへの書き込み権限がありません。',
    ];
    if (!$dir_writable) $all_ok = false;

    // 一時ディレクトリ
    $tmp_ok = is_writable(sys_get_temp_dir());
    $checks[] = [
        'name'    => '一時ディレクトリ書き込み権限',
        'ok'      => $tmp_ok,
        'message' => $tmp_ok ? 'OK' : '一時ディレクトリ (' . sys_get_temp_dir() . ') に書き込めません。',
    ];
    if (!$tmp_ok) $all_ok = false;

    // 既存インストールの判定とデータ・認証情報の保護チェック
    $is_existing_install = file_exists(__DIR__ . '/index.php');
    $has_auth_config     = file_exists(__DIR__ . '/config/auth.php');
    $has_main_config     = file_exists(__DIR__ . '/config/config.php');

    if ($is_existing_install && $has_auth_config && $has_main_config) {
        $checks[] = [
            'name'    => 'モード判定: アップデートモード',
            'ok'      => true,
            'message' => '既存の ONE STORAGE を検出しました。設定（config/）および保存データは完全に保護・維持されます。',
            'is_update' => true,
        ];
    } elseif ($is_existing_install) {
        $checks[] = [
            'name'    => 'モード判定: 既存ファイル上書きインストール',
            'ok'      => true,
            'message' => '既存のファイルが見つかりました。プログラムファイルは最新版へ上書き更新されます。',
            'warning' => true,
        ];
    } else {
        $checks[] = [
            'name'    => 'モード判定: 新規クリーンインストール',
            'ok'      => true,
            'message' => '新規インストールとしてセットアップを開始します。',
        ];
    }

    return ['success' => true, 'all_ok' => $all_ok, 'checks' => $checks];
}

/**
 * GitHub Releases API から最新リリースの ZIP URL を取得する
 */
function fetch_latest_release_info(): array
{
    // TARGET_VERSION が指定されていればそのタグの情報を、'latest' なら最新リリースを取得
    $target = trim(TARGET_VERSION);
    if (!empty($target) && $target !== 'latest') {
        $api_url = 'https://api.github.com/repos/' . GITHUB_REPO . '/releases/tags/' . rawurlencode($target);
    } else {
        $api_url = 'https://api.github.com/repos/' . GITHUB_REPO . '/releases/latest';
    }

    $response = http_get($api_url, ['Accept: application/vnd.github+json', 'User-Agent: OneStorage-Installer/1.0']);

    if ($response === false) {
        return ['success' => false, 'message' => "GitHub API へのアクセスに失敗しました (対象: {$target})。サーバーの外部通信設定を確認してください。"];
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($data)) {
        return ['success' => false, 'message' => 'GitHub API のレスポンスを解析できませんでした。'];
    }

    // zipball_url: リリースのソースコードZIP
    $zip_url = $data['zipball_url'] ?? '';
    $version = $data['tag_name'] ?? ($target !== 'latest' ? $target : 'unknown');

    // assets に release ZIP があればそちらを優先
    if (!empty($data['assets'])) {
        foreach ($data['assets'] as $asset) {
            if (str_ends_with($asset['name'], '.zip')) {
                $zip_url = $asset['browser_download_url'];
                break;
            }
        }
    }

    if (empty($zip_url)) {
        return ['success' => false, 'message' => "バージョン {$version} のリリース ZIP ファイルが見つかりませんでした。"];
    }

    return [
        'success' => true,
        'version' => $version,
        'zip_url' => $zip_url,
        'message' => "バージョン {$version} のパッケージが見つかりました。",
    ];
}

/**
 * ZIP ファイルをサーバーの一時ディレクトリにダウンロードする
 */
function download_zip(string $url): array
{
    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        return ['success' => false, 'message' => '無効な URL です。'];
    }

    // github.com / api.github.com のみ許可
    $host = parse_url($url, PHP_URL_HOST);
    if (!in_array($host, ['github.com', 'api.github.com', 'codeload.github.com'], true)) {
        return ['success' => false, 'message' => '許可されていないダウンロード元です。'];
    }

    $tmp_path = sys_get_temp_dir() . '/onestorage_installer_' . md5(uniqid('', true)) . '.zip';

    // cURL でダウンロード（リダイレクト追跡つき）
    if (extension_loaded('curl')) {
        $ch = curl_init($url);
        $fp = fopen($tmp_path, 'wb');
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => INSTALLER_TIMEOUT,
            CURLOPT_USERAGENT      => 'OneStorage-Installer/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if ($result === false || $http_code !== 200) {
            @unlink($tmp_path);
            return ['success' => false, 'message' => "ダウンロードに失敗しました (HTTP {$http_code}): {$error}"];
        }
    } elseif (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create([
            'http' => [
                'timeout'        => INSTALLER_TIMEOUT,
                'user_agent'     => 'OneStorage-Installer/1.0',
                'follow_location' => 1,
                'max_redirects'  => 5,
            ],
            'ssl'  => ['verify_peer' => true],
        ]);
        $data = @file_get_contents($url, false, $ctx);
        if ($data === false) {
            return ['success' => false, 'message' => 'ファイルのダウンロードに失敗しました (allow_url_fopen)。'];
        }
        file_put_contents($tmp_path, $data);
    } else {
        return ['success' => false, 'message' => 'HTTP 通信手段がありません (cURL, allow_url_fopen ともに無効)。'];
    }

    $file_size = filesize($tmp_path);
    if ($file_size < 1024) {
        @unlink($tmp_path);
        return ['success' => false, 'message' => 'ダウンロードされたファイルが小さすぎます。ネットワーク障害の可能性があります。'];
    }

    return [
        'success'  => true,
        'zip_path' => $tmp_path,
        'size_kb'  => round($file_size / 1024),
        'message'  => "ダウンロード完了 (" . round($file_size / 1024) . " KB)",
    ];
}

/**
 * ダウンロードした ZIP を展開し、ユーザーデータを保護しながら必要なファイルをインストール先にコピーする
 */
function extract_and_install(string $zip_path): array
{
    if (empty($zip_path) || !file_exists($zip_path)) {
        return ['success' => false, 'message' => 'ZIP ファイルが見つかりません。'];
    }

    $zip = new ZipArchive();
    $open_result = $zip->open($zip_path);
    if ($open_result !== true) {
        return ['success' => false, 'message' => "ZIP ファイルを開けませんでした (ZipArchive エラーコード: {$open_result})。"];
    }

    // 一時展開先
    $extract_dir = sys_get_temp_dir() . '/onestorage_extract_' . md5(uniqid('', true));
    @mkdir($extract_dir, 0755, true);

    if (!$zip->extractTo($extract_dir)) {
        $zip->close();
        @unlink($zip_path);
        recursive_rmdir($extract_dir);
        return ['success' => false, 'message' => 'ZIP の展開に失敗しました。ディスク容量や権限を確認してください。'];
    }
    $zip->close();
    @unlink($zip_path);

    // GitHub の zipball は先頭に "owner-repo-XXXXXXX/" のようなディレクトリが入るので検出する
    $source_dir = find_source_root($extract_dir);
    if ($source_dir === null) {
        recursive_rmdir($extract_dir);
        return ['success' => false, 'message' => 'ZIP 内にソースファイルが見つかりませんでした。'];
    }

    // インストール先 (installer.php と同じディレクトリ)
    $install_dir = __DIR__;

    // コピーすべきパス（installer.php 自身は除外）
    $copy_ok = recursive_copy($source_dir, $install_dir, [basename(__FILE__)]);

    recursive_rmdir($extract_dir);

    if (!$copy_ok) {
        return ['success' => false, 'message' => 'ファイルのコピー中にエラーが発生しました。書き込み権限を確認してください。'];
    }

    return ['success' => true, 'message' => 'ファイルの展開・配置が完了しました。'];
}

/**
 * インストール完了処理: ロックファイルを作成し、適切な画面へのリダイレクト URL を返す
 */
function finalize(): array
{
    // ロックファイルを作成してインストーラーを無効化
    file_put_contents(INSTALL_LOCK_FILE, date('Y-m-d H:i:s') . PHP_EOL);

    // 既存アカウントが存在する場合はログイン画面（または直接メイン画面）、新規なら設定画面へ
    $has_existing_account = file_exists(__DIR__ . '/config/auth.php');
    if ($has_existing_account) {
        $redirect_url = dirname($_SERVER['SCRIPT_NAME']) . '/login.php';
        $message = 'アップデートが完了しました。ログイン画面へ移動します。';
    } else {
        $redirect_url = dirname($_SERVER['SCRIPT_NAME']) . '/setting.php';
        $message = 'インストールが完了しました。初期設定画面に移動します。';
    }

    $redirect_url = str_replace('//', '/', $redirect_url);

    return [
        'success'      => true,
        'redirect_url' => $redirect_url,
        'message'      => $message,
    ];
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// ユーティリティ関数
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

/**
 * HTTP GET リクエストを実行する (cURL 優先、フォールバック: file_get_contents)
 */
function http_get(string $url, array $headers = []): string|false
{
    if (extension_loaded('curl')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_USERAGENT      => 'OneStorage-Installer/1.0',
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($response !== false && $http_code === 200) ? $response : false;
    }

    if (ini_get('allow_url_fopen')) {
        $opts = ['http' => ['method' => 'GET', 'timeout' => 10, 'user_agent' => 'OneStorage-Installer/1.0']];
        if (!empty($headers)) {
            $opts['http']['header'] = implode("\r\n", $headers);
        }
        return @file_get_contents($url, false, stream_context_create($opts));
    }

    return false;
}

/**
 * GitHub zipball 展開後の実際のソースルートディレクトリを見つける
 */
function find_source_root(string $extract_dir): ?string
{
    if (file_exists($extract_dir . '/index.php')) {
        return $extract_dir;
    }

    $items = scandir($extract_dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $sub = $extract_dir . '/' . $item;
        if (!is_dir($sub)) continue;

        if (file_exists($sub . '/index.php')) {
            return $sub;
        }
        // GitHub zipball（リポジトリルート/src/index.php）の構造にも対応
        if (is_dir($sub . '/src') && file_exists($sub . '/src/index.php')) {
            return $sub . '/src';
        }
    }

    return null;
}

/**
 * ディレクトリを再帰的にコピーする（ユーザーデータ・既存設定の保護ガード付き）
 *
 * @param string[] $exclude ファイル名の除外リスト
 */
function recursive_copy(string $src, string $dst, array $exclude = []): bool
{
    $ok = true;
    $items = scandir($src);

    // コピー先で絶対に上書きしてはならない重要設定・DBファイル
    $protected_files = [
        'auth.php',
        'config.php',
        'cookie_key.php',
        'mfa_secret.php',
        'share_config.php',
        'accept.json',
        '.storage.db',
        '.share.db',
    ];

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        if (in_array($item, $exclude, true)) continue;

        // data-preview またはランダム文字列付きのデータ・シェアディレクトリ（実際のユーザーデータ）は除外
        if ($item === 'data-preview' || preg_match('/^(data|share)[A-Za-z0-9]{10,}$/', $item)) {
            continue;
        }

        $src_path = $src . '/' . $item;
        $dst_path = $dst . '/' . $item;

        if (is_dir($src_path)) {
            if (!is_dir($dst_path)) {
                @mkdir($dst_path, 0755, true);
            }
            if (!recursive_copy($src_path, $dst_path, $exclude)) {
                $ok = false;
            }
        } else {
            // コピー先に既存の保護対象ファイルが存在する場合は絶対に上書きしない
            if (file_exists($dst_path) && in_array($item, $protected_files, true)) {
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
 * ディレクトリを再帰的に削除する
 */
function recursive_rmdir(string $dir): void
{
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        is_dir($path) ? recursive_rmdir($path) : @unlink($path);
    }
    @rmdir($dir);
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// HTML フロントエンド
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ONE STORAGE - インストーラー & アップデータ</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .installer-card { max-width: 680px; margin: 40px auto; }
        .step-icon { width: 28px; text-align: center; display: inline-block; }
        .log-box { background: #1e1e1e; color: #d4d4d4; font-family: monospace; font-size: 0.85rem;
                   padding: 1rem; border-radius: 6px; max-height: 240px; overflow-y: auto; }
        .log-box .log-ok   { color: #4ec9b0; }
        .log-box .log-err  { color: #f44747; }
        .log-box .log-warn { color: #dcdcaa; }
        .log-box .log-info { color: #9cdcfe; }
        .check-row { display: flex; align-items: flex-start; gap: 8px; padding: 6px 0; }
        .progress { height: 8px; }
    </style>
</head>
<body>
<div class="container">
    <div class="installer-card card shadow-sm">
        <div class="card-header bg-dark text-white py-3">
            <h5 class="mb-0"><i class="fa-solid fa-box-open me-2"></i>ONE STORAGE インストーラー</h5>
        </div>
        <div class="card-body p-4">

            <!-- ステップ表示 -->
            <div class="d-flex justify-content-between mb-4 text-center small text-muted" id="stepIndicator">
                <div class="flex-fill" id="ind-check">
                    <i class="fa-solid fa-circle-check me-1"></i>環境チェック
                </div>
                <div class="flex-fill" id="ind-download">
                    <i class="fa-solid fa-cloud-arrow-down me-1"></i>ダウンロード
                </div>
                <div class="flex-fill" id="ind-extract">
                    <i class="fa-solid fa-file-zipper me-1"></i>展開・配置
                </div>
                <div class="flex-fill" id="ind-done">
                    <i class="fa-solid fa-flag-checkered me-1"></i>完了
                </div>
            </div>
            <div class="progress mb-4">
                <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                     id="progressBar" role="progressbar" style="width: 0%"></div>
            </div>

            <!-- メインコンテンツエリア -->
            <div id="mainContent">

                <!-- PHASE 1: 環境チェック -->
                <div id="phaseCheck">
                    <p class="text-muted">実行を開始する前に、サーバーの動作環境とデータ保護状態を確認します。</p>
                    <div id="checkResults" class="mb-3"></div>
                    <div id="errorAlert" class="alert alert-danger d-none"></div>
                    <div id="warningAlert" class="alert alert-warning d-none"></div>
                    <button class="btn btn-primary" id="btnStartCheck">
                        <i class="fa-solid fa-magnifying-glass me-2"></i>環境チェックを開始
                    </button>
                    <button class="btn btn-success d-none" id="btnProceedToDownload">
                        <i class="fa-solid fa-arrow-right me-2"></i>インストール / 更新を開始する
                    </button>
                </div>

                <!-- PHASE 2: ダウンロード -->
                <div id="phaseDownload" class="d-none">
                    <p class="text-muted">GitHub から最新バージョンをダウンロードします。</p>
                    <div id="releaseInfo" class="alert alert-info d-none"></div>
                    <div class="log-box" id="downloadLog"></div>
                    <div class="mt-3">
                        <button class="btn btn-success d-none" id="btnProceedToExtract">
                            <i class="fa-solid fa-arrow-right me-2"></i>展開・配置へ進む
                        </button>
                    </div>
                </div>

                <!-- PHASE 3: 展開・配置 -->
                <div id="phaseExtract" class="d-none">
                    <p class="text-muted">ダウンロードしたファイルを展開し、既存データを保護しながら配置します。</p>
                    <div class="log-box" id="extractLog"></div>
                    <div class="mt-3">
                        <button class="btn btn-success d-none" id="btnFinalize">
                            <i class="fa-solid fa-flag-checkered me-2"></i>処理を完了する
                        </button>
                    </div>
                </div>

                <!-- PHASE 4: 完了 -->
                <div id="phaseDone" class="d-none text-center py-3">
                    <i class="fa-solid fa-circle-check text-success fa-4x mb-3"></i>
                    <h4 class="text-success" id="doneTitle">処理が完了しました！</h4>
                    <p class="text-muted">
                        セキュリティのため、<strong>installer.php をサーバーから削除してください。</strong>
                    </p>
                    <div class="alert alert-warning text-start">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>
                        <strong>重要:</strong> 削除しないと第三者に意図しない操作を実行される恐れがあります。
                        FTP またはサーバーのファイルマネージャーから <code>installer.php</code> を削除してください。
                    </div>
                    <a href="login.php" class="btn btn-lg btn-primary" id="btnGoSetup">
                        <i class="fa-solid fa-arrow-right-to-bracket me-2"></i>次へ進む
                    </a>
                </div>

            </div><!-- /mainContent -->
        </div><!-- /card-body -->
        <div class="card-footer text-muted small text-center">
            ONE STORAGE Installer &mdash; PHP <?= PHP_VERSION ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// ユーティリティ
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
const progressBar = document.getElementById('progressBar');

function setProgress(pct) {
    progressBar.style.width = pct + '%';
}

function setIndicatorActive(id) {
    document.querySelectorAll('#stepIndicator > div').forEach(el => {
        el.classList.remove('fw-bold', 'text-primary');
    });
    const el = document.getElementById(id);
    if (el) { el.classList.add('fw-bold', 'text-primary'); }
}

function showPhase(phaseId) {
    ['phaseCheck','phaseDownload','phaseExtract','phaseDone'].forEach(id => {
        document.getElementById(id).classList.add('d-none');
    });
    document.getElementById(phaseId).classList.remove('d-none');
}

function appendLog(logBoxId, message, type = 'info') {
    const box  = document.getElementById(logBoxId);
    const line = document.createElement('div');
    line.className = 'log-' + type;
    const now = new Date().toTimeString().slice(0,8);
    line.textContent = `[${now}] ${message}`;
    box.appendChild(line);
    box.scrollTop = box.scrollHeight;
}

async function callApi(action, extra = {}) {
    const res  = await fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, ...extra }),
    });
    return res.json();
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// PHASE 1: 環境チェック
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
document.getElementById('btnStartCheck').addEventListener('click', async function () {
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>チェック中...';
    setProgress(10);
    setIndicatorActive('ind-check');

    const result = await callApi('check');
    this.classList.add('d-none');

    const container = document.getElementById('checkResults');
    container.innerHTML = '';
    let hasError = false;
    let hasWarning = false;

    for (const c of result.checks) {
        const row = document.createElement('div');
        row.className = 'check-row';
        const icon = c.ok
            ? (c.is_update
                ? '<i class="fa-solid fa-arrows-rotate text-primary step-icon"></i>'
                : '<i class="fa-solid fa-circle-check text-success step-icon"></i>')
            : '<i class="fa-solid fa-circle-xmark text-danger step-icon"></i>';
        const warn = c.warning
            ? '<i class="fa-solid fa-triangle-exclamation text-warning step-icon"></i>'
            : '';
        row.innerHTML = (c.warning ? warn : icon) +
            `<div><strong>${c.name}</strong> <span class="text-muted small d-block">${c.message}</span></div>`;
        container.appendChild(row);
        if (!c.ok) hasError = true;
        if (c.warning) hasWarning = true;
    }

    if (hasError) {
        const el = document.getElementById('errorAlert');
        el.textContent = '環境チェックに失敗しました。上記のエラーを解消してから再試行してください。';
        el.classList.remove('d-none');
        this.disabled  = false;
        this.innerHTML = '<i class="fa-solid fa-rotate-right me-2"></i>再チェック';
        this.classList.remove('d-none');
    } else {
        if (hasWarning) {
            const el = document.getElementById('warningAlert');
            el.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-2"></i>注意項目を確認の上、処理を続行してください。';
            el.classList.remove('d-none');
        }
        document.getElementById('btnProceedToDownload').classList.remove('d-none');
        setProgress(25);
    }
});

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// PHASE 2: ダウンロード
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
let g_zip_path = '';

document.getElementById('btnProceedToDownload').addEventListener('click', async function () {
    showPhase('phaseDownload');
    setIndicatorActive('ind-download');
    setProgress(30);

    appendLog('downloadLog', 'GitHub からリリース情報を取得中...', 'info');
    const releaseResult = await callApi('fetch_release');

    if (!releaseResult.success) {
        appendLog('downloadLog', 'エラー: ' + releaseResult.message, 'err');
        return;
    }

    appendLog('downloadLog', `バージョン ${releaseResult.version} を検出しました。`, 'ok');
    appendLog('downloadLog', `ダウンロード URL: ${releaseResult.zip_url}`, 'info');
    setProgress(40);

    appendLog('downloadLog', 'ZIP ファイルをダウンロード中... (しばらくお待ちください)', 'info');
    const dlResult = await callApi('download', { zip_url: releaseResult.zip_url });

    if (!dlResult.success) {
        appendLog('downloadLog', 'エラー: ' + dlResult.message, 'err');
        return;
    }

    g_zip_path = dlResult.zip_path;
    appendLog('downloadLog', `ダウンロード完了 (${dlResult.size_kb} KB)`, 'ok');
    setProgress(60);

    document.getElementById('btnProceedToExtract').classList.remove('d-none');
});

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// PHASE 3: 展開・配置
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
document.getElementById('btnProceedToExtract').addEventListener('click', async function () {
    showPhase('phaseExtract');
    setIndicatorActive('ind-extract');
    setProgress(65);

    appendLog('extractLog', 'ZIP ファイルを展開中...', 'info');
    const result = await callApi('extract', { zip_path: g_zip_path });

    if (!result.success) {
        appendLog('extractLog', 'エラー: ' + result.message, 'err');
        return;
    }

    appendLog('extractLog', result.message, 'ok');
    appendLog('extractLog', 'ファイルの配置が完了しました（既存設定・データ領域は保護されました）。', 'ok');
    setProgress(85);

    document.getElementById('btnFinalize').classList.remove('d-none');
});

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// PHASE 4: 完了
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
document.getElementById('btnFinalize').addEventListener('click', async function () {
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>完了処理中...';

    const result = await callApi('finalize');

    if (!result.success) {
        appendLog('extractLog', 'エラー: ' + result.message, 'err');
        this.disabled = false;
        this.innerHTML = '<i class="fa-solid fa-flag-checkered me-2"></i>再試行';
        return;
    }

    setProgress(100);
    setIndicatorActive('ind-done');
    showPhase('phaseDone');

    if (result.redirect_url) {
        document.getElementById('btnGoSetup').href = result.redirect_url;
    }
});
</script>
</body>
</html>