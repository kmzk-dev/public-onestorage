<?php
// share.php: ゲスト用一時共有エントリポイント
define('ONESTORAGE_RUNNING', true);

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow, noarchive');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/path.php';
require_once __DIR__ . '/functions/helpers.php';
require_once __DIR__ . '/functions/db.php';

// SHARE BOX 設定の読み込み（プライベート側の config.php は意図的に読み込まない）
if (!file_exists(SHARE_CONFIG_PATH)) {
    http_response_code(404);
    render_error_page('共有リンクが見つかりません', 'この共有リンクは無効か、既に削除されています。');
    exit;
}

$share_config = require SHARE_CONFIG_PATH;
if (!isset($share_config['share_root']) || !is_dir($share_config['share_root'])) {
    http_response_code(404);
    render_error_page('共有リンクが見つかりません', 'この共有リンクは無効か、既に削除されています。');
    exit;
}

define('SHARE_ROOT', $share_config['share_root']);
define('SHARE_ENCRYPTION_ENABLED', true);
define('SHARE_ENCRYPTION_KEY_SEED', $share_config['encryption_key_seed'] ?? '');

// 1. トークンの取得と検証
$token = trim($_GET['token'] ?? '');
if (empty($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(404);
    render_error_page('無効な共有リンク', '指定されたURLが正しくないか、有効期限が切れています。');
    exit;
}

$share = get_valid_share($token);
if (!$share) {
    http_response_code(404);
    render_error_page('共有リンクが見つかりません', 'この共有リンクは無効か、既に削除されています。');
    exit;
}

$folder_name = $share['folder_name'];
$share_base_dir = realpath(SHARE_ROOT . '/' . $folder_name);
if (!$share_base_dir || !str_starts_with($share_base_dir, SHARE_ROOT) || !is_dir($share_base_dir)) {
    http_response_code(404);
    render_error_page('フォルダが見つかりません', '共有対象のフォルダが存在しません。');
    exit;
}

// 2. パスワード保護の検証
$has_password = !empty($share['password_hash']);
$auth_session_key = 'shared_auth_' . $token;
$is_authenticated = !$has_password || !empty($_SESSION[$auth_session_key]);

if (!$is_authenticated) {
    $password_error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['share_password'])) {
        $input_password = (string)$_POST['share_password'];
        $fail_count_key = 'share_fail_' . $token;
        $fail_count = $_SESSION[$fail_count_key] ?? 0;

        if ($fail_count >= 5) {
            $password_error = 'パスワードの入力試行回数が上限に達しました。しばらく待ってから再度お試しください。';
        } elseif (password_verify($input_password, $share['password_hash']) || password_verify(trim($input_password), $share['password_hash'])) {
            $_SESSION[$auth_session_key] = true;
            unset($_SESSION[$fail_count_key]);
            header('Location: share.php?token=' . urlencode($token));
            exit;
        } else {
            $_SESSION[$fail_count_key] = $fail_count + 1;
            $password_error = 'パスワードが正しくありません。';
        }
    }

    render_password_page($share['folder_name'], $token, $password_error);
    exit;
}

// 3. アクションの処理（ダウンロード / プレビュー / 一覧表示）
$action = $_GET['action'] ?? 'list';
$sub_path = $_GET['sub'] ?? '';

if ($action === 'download' || $action === 'view') {
    if (empty($sub_path) || str_starts_with(basename($sub_path), '.') || str_contains($sub_path, '..')) {
        http_response_code(403);
        die('Access Denied: Invalid path.');
    }

    $target_file_path = realpath($share_base_dir . '/' . $sub_path);

    // Chroot境界チェック & 隠しファイル除外
    if (
        $target_file_path === false ||
        !str_starts_with($target_file_path, $share_base_dir) ||
        is_dir($target_file_path) ||
        str_contains(str_replace($share_base_dir, '', $target_file_path), DIRECTORY_SEPARATOR . '.')
    ) {
        http_response_code(403);
        die('Access Denied: Invalid path.');
    }

    $file_name = basename($target_file_path);

    // レスポンスヘッダーの設定
    if ($action === 'view') {
        $mime_types = [
            'pdf'  => 'application/pdf',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'txt'  => 'text/plain; charset=utf-8',
        ];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $content_type = $mime_types[$ext] ?? 'application/octet-stream';
        header('Content-Type: ' . $content_type);
        header('Content-Disposition: inline; filename="' . rawurlencode($file_name) . '"');
    } else {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . rawurlencode($file_name) . '"');
    }

    // 出力処理（暗号化ストリーム復号）
    if (!empty(SHARE_ENCRYPTION_KEY_SEED)) {
        $encryption_key = hash('sha256', SHARE_ENCRYPTION_KEY_SEED, true);
        decrypt_file_stream($target_file_path, $encryption_key);
    } else {
        http_response_code(500);
        render_error_page('復号エラー', '共有暗号化キーが設定されていません。');
    }
    exit;
}

// 4. ファイル一覧の取得
$files = [];
$items = array_diff(scandir($share_base_dir), ['.', '..']);
natsort($items);

foreach ($items as $item) {
    if ($item === 'index.html' || str_starts_with($item, '.')) continue;
    $p = $share_base_dir . DIRECTORY_SEPARATOR . $item;
    if (is_dir($p)) continue; // 初期フェーズはフォルダ直下のファイルのみ
    $size = filesize($p);
    $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
    $can_view = in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'txt'], true);

    $files[] = [
        'name'           => $item,
        'size'           => $size,
        'formatted_size' => format_bytes($size),
        'can_view'       => $can_view,
        'ext'            => $ext,
    ];
}

// 5. ゲストUIのレンダリング（ゼロ依存・メニューなし・極限シンプル）
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>共有フォルダ: <?= htmlspecialchars($share['folder_name']) ?> - ONE STORAGE</title>
    <style>
        :root {
            --primary: #0284c7;
            --primary-hover: #0369a1;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            line-height: 1.5;
            padding: 1.5rem 1rem;
        }
        .container {
            max-width: 680px;
            margin: 0 auto;
        }
        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .header {
            margin-bottom: 1.25rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }
        .title-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.25rem;
        }
        .folder-icon {
            font-size: 1.5rem;
        }
        h1 {
            font-size: 1.25rem;
            font-weight: 700;
            word-break: break-all;
        }
        .meta-info {
            font-size: 0.825rem;
            color: var(--text-muted);
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .meta-item {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        .file-list {
            list-style: none;
        }
        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f5f9;
            gap: 0.75rem;
        }
        .file-item:last-child {
            border-bottom: none;
        }
        .file-info {
            min-width: 0;
            flex: 1;
        }
        .file-name {
            font-size: 0.95rem;
            font-weight: 500;
            word-break: break-all;
            display: block;
        }
        .file-size {
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        .actions {
            display: flex;
            gap: 0.5rem;
            flex-shrink: 0;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.4rem 0.85rem;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.15s ease;
        }
        .btn-primary {
            background-color: var(--primary);
            color: #ffffff;
        }
        .btn-primary:hover {
            background-color: var(--primary-hover);
        }
        .btn-outline {
            background-color: transparent;
            border-color: var(--border);
            color: var(--text-main);
        }
        .btn-outline:hover {
            background-color: #f1f5f9;
        }
        .empty-state {
            text-align: center;
            padding: 2.5rem 1rem;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        .footer {
            margin-top: 2rem;
            text-align: center;
            font-size: 0.75rem;
            color: var(--text-muted);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <header class="header">
                <div class="title-row">
                    <span class="folder-icon">📁</span>
                    <h1><?= htmlspecialchars($share['folder_name']) ?></h1>
                </div>
            </header>

            <?php if (empty($files)): ?>
                <div class="empty-state">
                    このフォルダにはダウンロード可能なファイルがありません。
                </div>
            <?php else: ?>
                <ul class="file-list">
                    <?php foreach ($files as $file): ?>
                        <li class="file-item">
                            <div class="file-info">
                                <span class="file-name"><?= htmlspecialchars($file['name']) ?></span>
                                <span class="file-size"><?= htmlspecialchars($file['formatted_size']) ?></span>
                            </div>
                            <div class="actions">
                                <?php if ($file['can_view']): ?>
                                    <a href="share.php?token=<?= urlencode($token) ?>&action=view&sub=<?= urlencode($file['name']) ?>"
                                       class="btn btn-outline" target="_blank">プレビュー</a>
                                <?php endif; ?>
                                <a href="share.php?token=<?= urlencode($token) ?>&action=download&sub=<?= urlencode($file['name']) ?>"
                                   class="btn btn-primary">ダウンロード</a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <footer class="footer">
            <span>ONE STORAGE — セキュア一時共有</span>
        </footer>
    </div>
</body>
</html>
<?php

// ----------------------------------------------------
// エラー画面描画用関数
// ----------------------------------------------------
function render_error_page(string $title, string $message): void {
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title><?= htmlspecialchars($title) ?> - ONE STORAGE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem 1rem;
            margin: 0;
        }
        .error-card {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 2.25rem 2rem;
            width: 100%;
            max-width: 440px;
            text-align: center;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.06);
        }
        .icon-circle {
            width: 56px;
            height: 56px;
            background-color: #fee2e2;
            color: #ef4444;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin: 0 auto 1.25rem auto;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="icon-circle">
            <i class="bi bi-shield-x"></i>
        </div>
        <h1 class="h5 fw-bold mb-2"><?= htmlspecialchars($title) ?></h1>
        <p class="text-muted small mb-0 lh-base"><?= htmlspecialchars($message) ?></p>
        <div class="text-center mt-4 pt-3 border-top">
            <span class="text-muted small">ONE STORAGE — セキュア一時共有</span>
        </div>
    </div>
</body>
</html>
<?php
}

// ----------------------------------------------------
// パスワード入力画面描画用関数
// ----------------------------------------------------
function render_password_page(string $folder_name, string $token, string $error_message = ''): void {
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>パスワード保護 - ONE STORAGE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #0284c7;
            --primary-hover: #0369a1;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem 1rem;
            margin: 0;
        }
        .password-card {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 2.25rem 2rem;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.06);
        }
        .icon-circle {
            width: 56px;
            height: 56px;
            background-color: #e0f2fe;
            color: #0284c7;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin: 0 auto 1.25rem auto;
        }
        .btn-primary-custom {
            background-color: #0284c7;
            border-color: #0284c7;
            color: #ffffff;
            font-weight: 600;
            padding: 0.65rem 1rem;
            border-radius: 8px;
            transition: all 0.15s ease;
        }
        .btn-primary-custom:hover {
            background-color: #0369a1;
            border-color: #0369a1;
            color: #ffffff;
        }
        .form-control:focus {
            border-color: #0284c7;
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
        }
        .input-group-text {
            background-color: #f8fafc;
            border-color: #cbd5e1;
        }
    </style>
</head>
<body>
    <div class="password-card">
        <div class="text-center mb-4">
            <div class="icon-circle">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            <h1 class="h5 fw-bold mb-2">パスワード保護</h1>
            <p class="text-muted small mb-0 lh-base">
                共有フォルダ「<strong class="text-dark"><?= htmlspecialchars($folder_name) ?></strong>」を閲覧するには、パスワードを入力してください。
            </p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger d-flex align-items-center py-2 px-3 small mb-3 border-0 bg-danger-subtle text-danger rounded-3" role="alert">
                <i class="bi bi-exclamation-circle-fill me-2 flex-shrink-0"></i>
                <div><?= htmlspecialchars($error_message) ?></div>
            </div>
        <?php endif; ?>

        <form action="share.php?token=<?= urlencode($token) ?>" method="post" autocomplete="off">
            <div class="mb-3">
                <label for="share_password" class="form-label small fw-semibold text-secondary mb-1">パスワード</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-key-fill text-muted"></i></span>
                    <input type="password" id="share_password" name="share_password" class="form-control" placeholder="パスワードを入力" required autofocus autocomplete="current-password">
                    <button class="btn btn-outline-secondary" type="button" id="togglePasswordBtn" title="パスワードを表示/非表示" aria-label="パスワードを表示/非表示">
                        <i class="bi bi-eye" id="togglePasswordIcon"></i>
                    </button>
                </div>
                <div class="form-text text-muted small mt-1">
                    <i class="bi bi-info-circle me-1"></i>アルファベット大文字・小文字・数字・特殊記号をご利用いただけます。
                </div>
            </div>
            <button type="submit" class="btn btn-primary-custom w-100 mt-2">
                <i class="bi bi-unlock-fill me-1"></i>認証して開く
            </button>
        </form>

        <div class="text-center mt-4 pt-3 border-top">
            <span class="text-muted small">ONE STORAGE — セキュア一時共有</span>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleBtn = document.getElementById('togglePasswordBtn');
        const pwdInput = document.getElementById('share_password');
        const pwdIcon = document.getElementById('togglePasswordIcon');

        if (toggleBtn && pwdInput && pwdIcon) {
            toggleBtn.addEventListener('click', function() {
                if (pwdInput.type === 'password') {
                    pwdInput.type = 'text';
                    pwdIcon.classList.remove('bi-eye');
                    pwdIcon.classList.add('bi-eye-slash');
                } else {
                    pwdInput.type = 'password';
                    pwdIcon.classList.remove('bi-eye-slash');
                    pwdIcon.classList.add('bi-eye');
                }
                pwdInput.focus();
            });
        }
    });
    </script>
</body>
</html>
<?php
}
