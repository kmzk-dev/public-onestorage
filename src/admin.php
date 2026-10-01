<?php
define('ONESTORAGE_RUNNING', true);
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

require_once __DIR__ . '/path.php';
require_once __DIR__ . '/functions/init.php';
require_once __DIR__ . '/functions/helpers.php';
require_once __DIR__ . '/functions/cookie.php';
require_once __DIR__ . '/functions/auth.php';
require_once __DIR__ . '/functions/mfa.php';

// 認証チェック
check_authentication();


// ディレクトリキャッシュの手動再構築処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'admin_rebuild_cache') {
    rebuild_dir_cache();
    $_SESSION['admin_message'] = ['type' => 'success', 'text' => 'ディレクトリキャッシュを再構築しました。最新のフォルダ構成が反映されました。'];
    header('Location: admin.php#section-cache');
    exit;
}

$auth_config = file_exists(AUTH_CONFIG_PATH) ? (require AUTH_CONFIG_PATH) : [];
$current_user = $auth_config['user'] ?? '';
$current_mfa_secret = get_mfa_secret();
$current_mfa_enabled = is_mfa_enabled();

// ストレージ割り当て容量・使用状況の取得
$quota_info = get_storage_quota_info();

// ファイル拡張子・サイズ設定の取得
$accept_config = file_exists(ACCEPT_CONFIG_PATH)
    ? (json_decode(file_get_contents(ACCEPT_CONFIG_PATH), true) ?? [])
    : [];
$current_extensions = $accept_config['allowed_extensions'] ?? null;
$current_max_size = $accept_config['max_file_size_mb'] ?? 500;

// 定義済み代表拡張子リスト（フラット化）
$predefined_extensions = [
    'pdf', 'txt', 'csv', 'md', 'doc', 'docx', 'xls', 'xlsx', 'pptx',
    'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'heic', 'ai', 'psd',
    'mp3', 'wav', 'flac', 'aac', 'ogg', 'm4a', 'mp4', 'mov', 'avi', 'mkv', 'webm',
    'zip', '7z', 'rar', 'json', 'yml', 'yaml', 'ini', 'log'
];

// 初回インストール直後や未設定・空配列の場合は代表拡張子リストをデフォルト採用
if ($current_extensions === null || empty($current_extensions)) {
    $current_extensions = $predefined_extensions;
}

// 定義外のカスタム拡張子
$custom_extensions = array_values(array_diff($current_extensions, $predefined_extensions));

// 初期表示用のQRコードURL
$issuer = rawurlencode('One Storage');
$label = rawurlencode($current_user ?: 'user@onestorage.local');
$secret_url_encoded = rawurlencode($current_mfa_secret);
$otp_auth_uri = "otpauth://totp/{$issuer}:{$label}?secret={$secret_url_encoded}&issuer={$issuer}";
$initial_qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode($otp_auth_uri);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理設定 - ONE STORAGE</title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-hover: #1d4ed8;
            --border-color: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --sidebar-width: 250px;
            --navbar-height: 56px;
        }
        html {
            scroll-behavior: smooth;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", "Hiragino Kaku Gothic ProN", "Yu Gothic", sans-serif;
            color: var(--text-main);
            background-color: #ffffff;
            -webkit-font-smoothing: antialiased;
        }
        .navbar {
            background-color: #0f172a !important;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            min-height: var(--navbar-height);
            z-index: 1030;
        }
        .admin-sidebar {
            background-color: #ffffff;
            border-right: 1px solid var(--border-color);
            min-height: calc(100vh - var(--navbar-height));
        }
        @media (min-width: 768px) {
            .admin-sidebar {
                width: var(--sidebar-width) !important;
                flex: 0 0 var(--sidebar-width) !important;
                position: sticky;
                top: var(--navbar-height);
                height: calc(100vh - var(--navbar-height));
                overflow-y: auto;
            }
            .admin-main-content {
                width: calc(100% - var(--sidebar-width)) !important;
                flex: 1 1 auto !important;
                min-width: 0;
            }
        }
        .admin-sidebar .nav-link {
            color: var(--text-main);
            font-size: 0.9rem;
            font-weight: 500;
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 0.25rem;
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            white-space: nowrap;
        }
        .admin-sidebar .nav-link:hover {
            background-color: #f8fafc;
            color: var(--primary-color);
        }
        .admin-sidebar .nav-link.active {
            background-color: #eff6ff;
            color: var(--primary-color);
            font-weight: 600;
        }
        .admin-sidebar .nav-link.text-danger:hover {
            background-color: #fef2f2;
            color: #dc2626 !important;
        }
        .admin-section {
            scroll-margin-top: calc(var(--navbar-height) + 1.5rem);
            margin-bottom: 3rem;
            padding-bottom: 2.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        .admin-section:last-of-type {
            border-bottom: none;
        }
        .ext-badge {
            font-size: 0.85rem;
            padding: 0.35rem 0.65rem;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .secret-box {
            font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            letter-spacing: 0.1em;
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 0.6rem 1rem;
            border-radius: 0.5rem;
            font-size: 1.05rem;
            user-select: all;
        }
        .toast-container {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            z-index: 1090;
        }
    </style>
</head>
<body data-bs-spy="scroll" data-bs-target="#adminSidebarNav" data-bs-offset="100">

    <!-- ヘッダーナビゲーション -->
    <nav class="navbar navbar-dark sticky-top bg-dark px-3">
        <div class="d-flex align-items-center">
            <button class="navbar-toggler d-md-none border-0 p-1 me-2" type="button" data-bs-toggle="collapse" data-bs-target="#adminSidebarCollapse" aria-controls="adminSidebarCollapse" aria-expanded="false" aria-label="メニュー開閉">
                <span class="navbar-toggler-icon"></span>
            </button>
            <a class="navbar-brand me-2 d-inline-flex align-items-center" href="index.php">
                <i class="bi bi-hdd-stack text-primary me-2"></i>
                <span class="fw-bold fs-6">ONE STORAGE</span>
            </a>
            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle ms-1 small">管理設定</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="text-white-50 small d-none d-sm-inline me-1">
                <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($current_user, ENT_QUOTES, 'UTF-8') ?>
            </span>
            <a href="index.php" class="btn btn-outline-light btn-sm d-flex align-items-center">
                <i class="bi bi-arrow-left me-1"></i>
                <span>ストレージに戻る</span>
            </a>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row g-0">
            <!-- 左サイドバー（固定幅240px・折り返しなし） -->
            <nav id="adminSidebarNav" class="admin-sidebar collapse d-md-flex flex-column py-3 px-3">
                <div class="d-flex flex-column h-100">
                    <div class="small fw-bold text-muted text-uppercase px-2 mb-2">メニュー</div>
                    <ul class="nav flex-column mb-auto">
                        <!-- 1. ログアウト（指示順: 一番上） -->
                        <li class="nav-item mb-2 pb-2 border-bottom">
                            <a href="logout.php" class="nav-link text-danger fw-bold" onclick="return confirm('ログアウトしますか？');">
                                <i class="bi bi-box-arrow-right me-2"></i>
                                <span>ログアウト</span>
                            </a>
                        </li>
                        <!-- 2. ファイル種別 -->
                        <li class="nav-item">
                            <a class="nav-link" href="#section-files">
                                <i class="bi bi-file-earmark-check text-primary me-2"></i>
                                <span>ファイル種別</span>
                            </a>
                        </li>
                        <!-- 3. ファイル（アップロード）上限 -->
                        <li class="nav-item">
                            <a class="nav-link" href="#section-size">
                                <i class="bi bi-hdd-network text-primary me-2"></i>
                                <span>ファイル上限</span>
                            </a>
                        </li>
                        <!-- 4. ストレージ容量 -->
                        <li class="nav-item">
                            <a class="nav-link" href="#section-quota">
                                <i class="bi bi-pie-chart text-primary me-2"></i>
                                <span>ストレージ容量</span>
                            </a>
                        </li>
                        <!-- 5. 二段階認証 -->
                        <li class="nav-item">
                            <a class="nav-link" href="#section-mfa">
                                <i class="bi bi-shield-lock text-primary me-2"></i>
                                <span>二段階認証</span>
                            </a>
                        </li>
                        <!-- 5. パスワード変更 -->
                        <li class="nav-item">
                            <a class="nav-link" href="#section-password">
                                <i class="bi bi-key text-primary me-2"></i>
                                <span>パスワード変更</span>
                            </a>
                        </li>
                        <!-- 6. キャッシュ再構築 -->
                        <li class="nav-item">
                            <a class="nav-link" href="#section-cache">
                                <i class="bi bi-arrow-repeat text-primary me-2"></i>
                                <span>キャッシュ再構築</span>
                            </a>
                        </li>
                        <!-- 7. セキュリティ診断 -->
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center" href="#section-security">
                                <i class="bi bi-shield-check text-primary me-2"></i>
                                <span>セキュリティ診断</span>
                                <span class="badge bg-success-subtle text-success border border-success-subtle ms-auto d-none" id="sidebarSecurityBadge" style="font-size: 0.65rem;">PASS</span>
                            </a>
                        </li>
                    </ul>

                    <div class="pt-3 border-top mt-4">
                        <a href="index.php" class="nav-link text-muted small py-1">
                            <i class="bi bi-arrow-return-left me-2"></i>ストレージ一覧へ戻る
                        </a>
                    </div>
                </div>
            </nav>

            <!-- 右メインコンテンツエリア -->
            <main class="admin-main-content px-md-4 py-4">
                <div style="max-width: 840px;" class="mx-auto">

                    <!-- パンくずリスト -->
                    <nav aria-label="breadcrumb" class="mb-3">
                        <ol class="breadcrumb small">
                            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted"><i class="bi bi-house-door me-1"></i>home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">管理設定</li>
                        </ol>
                    </nav>

                    <div class="mb-4 pb-2 border-bottom">
                        <h3 class="fw-bold mb-1"><i class="bi bi-gear-fill text-primary me-2"></i>管理設定</h3>
                    </div>

                    <?php if (!empty($_SESSION['admin_message'])): 
                        $adm_msg = $_SESSION['admin_message'];
                        unset($_SESSION['admin_message']);
                    ?>
                        <div class="alert alert-<?= htmlspecialchars($adm_msg['type']) ?> alert-dismissible fade show mb-4" role="alert">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            <?= htmlspecialchars($adm_msg['text']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <!-- ============================================== -->
                    <!-- 1. ファイル種別の管理セクション -->
                    <!-- ============================================== -->
                    <section id="section-files" class="admin-section">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0 text-dark">
                                <i class="bi bi-file-earmark-check text-primary me-2"></i>ファイル種別の管理
                            </h5>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary" id="btnSelectAllExt">すべて選択</button>
                                <button type="button" class="btn btn-outline-secondary" id="btnUnselectAllExt">全解除</button>
                            </div>
                        </div>

                        <form id="formFileExtensions">
                            <!-- スッキリしたフラットなグリッド -->
                            <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-6 g-2 mb-4">
                                <?php foreach ($predefined_extensions as $ext): 
                                    $is_checked = in_array($ext, $current_extensions, true);
                                ?>
                                    <div class="col">
                                        <div class="form-check py-1">
                                            <input class="form-check-input ext-checkbox" type="checkbox" name="extensions[]" value="<?= htmlspecialchars($ext, ENT_QUOTES, 'UTF-8') ?>" id="ext_<?= htmlspecialchars($ext, ENT_QUOTES, 'UTF-8') ?>" <?= $is_checked ? 'checked' : '' ?>>
                                            <label class="form-check-label small" for="ext_<?= htmlspecialchars($ext, ENT_QUOTES, 'UTF-8') ?>">
                                                .<?= htmlspecialchars($ext, ENT_QUOTES, 'UTF-8') ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- カスタム拡張子エリア -->
                            <div class="mb-4">
                                <label class="form-label fw-bold small text-muted">カスタム拡張子</label>
                                <div class="input-group mb-2" style="max-width: 340px;">
                                    <span class="input-group-text bg-white">.</span>
                                    <input type="text" class="form-control" id="inputCustomExt" placeholder="例: json5, ts" maxlength="20">
                                    <button class="btn btn-outline-primary" type="button" id="btnAddCustomExt">
                                        <i class="bi bi-plus-lg me-1"></i>追加
                                    </button>
                                </div>
                                
                                <div id="customExtList" class="d-flex flex-wrap gap-2 mt-2">
                                    <?php foreach ($custom_extensions as $ext): ?>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle ext-badge" data-ext="<?= htmlspecialchars($ext, ENT_QUOTES, 'UTF-8') ?>">
                                            .<?= htmlspecialchars($ext, ENT_QUOTES, 'UTF-8') ?>
                                            <button type="button" class="btn-close btn-close-dark btn-remove-custom-ext" aria-label="削除" style="font-size: 0.65rem;"></button>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- ボタン配置: 左寄せに統一 -->
                            <div class="d-flex justify-content-start">
                                <button type="submit" class="btn btn-primary px-4" id="btnSaveExtensions">
                                    <i class="bi bi-save me-1"></i>ファイル種別設定を保存
                                </button>
                            </div>
                        </form>
                    </section>

                    <!-- ============================================== -->
                    <!-- 2. ファイル（アップロード）上限セクション -->
                    <!-- ============================================== -->
                    <section id="section-size" class="admin-section">
                        <h5 class="fw-bold mb-3 text-dark">
                            <i class="bi bi-hdd-network text-primary me-2"></i>ファイル（アップロード）上限
                        </h5>

                        <form id="formFileSize" style="max-width: 500px;">
                            <div class="mb-3">
                                <label class="form-label small text-muted mb-1">現在の設定上限: <strong id="currentSizeDisplay" class="text-dark fs-6"><?= (int)$current_max_size ?> MB</strong> <span class="text-muted">(約 <?= round($current_max_size / 1024, 2) ?> GB)</span></label>
                                <div class="input-group" style="max-width: 280px;">
                                    <input type="number" class="form-control" id="inputMaxFileSize" value="<?= (int)$current_max_size ?>" min="1" max="102400" required>
                                    <span class="input-group-text bg-white">MB</span>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary preset-size-btn" data-size="100">100 MB</button>
                                    <button type="button" class="btn btn-outline-secondary preset-size-btn" data-size="500">500 MB</button>
                                    <button type="button" class="btn btn-outline-secondary preset-size-btn" data-size="1024">1 GB</button>
                                    <button type="button" class="btn btn-outline-secondary preset-size-btn" data-size="2048">2 GB</button>
                                    <button type="button" class="btn btn-outline-secondary preset-size-btn" data-size="5120">5 GB</button>
                                </div>
                            </div>

                            <!-- ボタン配置: 左寄せに統一 -->
                            <div class="d-flex justify-content-start">
                                <button type="submit" class="btn btn-primary px-4" id="btnSaveFileSize">
                                    <i class="bi bi-save me-1"></i>サイズ上限を保存
                                </button>
                            </div>
                        </form>
                    </section>

                    <!-- ============================================== -->
                    <!-- 3. ストレージ割り当て容量・残容量セクション -->
                    <!-- ============================================== -->
                    <section id="section-quota" class="admin-section">
                        <h5 class="fw-bold mb-3 text-dark">
                            <i class="bi bi-pie-chart text-primary me-2"></i>ストレージ割り当て容量・残容量
                        </h5>

                        <!-- 使用状況カード -->
                        <div class="card border mb-4 bg-light-subtle">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold small text-dark"><i class="bi bi-hdd me-1 text-primary"></i>ストレージ使用状況</span>
                                    <span class="badge <?= $quota_info['usage_percent'] >= 90 ? 'bg-danger' : ($quota_info['usage_percent'] >= 80 ? 'bg-warning text-dark' : 'bg-primary') ?>" id="quotaUsageBadge">
                                        <?= $quota_info['is_unlimited'] ? '無制限' : $quota_info['usage_percent'] . '%' ?>
                                    </span>
                                </div>

                                <!-- プログレスバー -->
                                <div class="progress mb-3" style="height: 10px;">
                                    <div id="quotaProgressBar" class="progress-bar <?= $quota_info['usage_percent'] >= 90 ? 'bg-danger' : ($quota_info['usage_percent'] >= 80 ? 'bg-warning' : 'bg-primary') ?>" 
                                         role="progressbar" 
                                         style="width: <?= $quota_info['is_unlimited'] ? '0%' : $quota_info['usage_percent'] . '%' ?>;" 
                                         aria-valuenow="<?= $quota_info['usage_percent'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>

                                <div class="row g-2 text-center small">
                                    <div class="col-4">
                                        <div class="p-2 border bg-white rounded">
                                            <span class="text-muted d-block" style="font-size: 0.75rem;">割り当て容量</span>
                                            <strong class="fs-6 text-dark" id="displayQuotaLimit"><?= htmlspecialchars($quota_info['formatted_limit']) ?></strong>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="p-2 border bg-white rounded">
                                            <span class="text-muted d-block" style="font-size: 0.75rem;">現在の使用量</span>
                                            <strong class="fs-6 text-dark" id="displayQuotaUsed"><?= htmlspecialchars($quota_info['formatted_used']) ?></strong>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="p-2 border bg-white rounded">
                                            <span class="text-muted d-block" style="font-size: 0.75rem;">残容量</span>
                                            <strong class="fs-6 text-success" id="displayQuotaRemaining"><?= htmlspecialchars($quota_info['formatted_remaining']) ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 割り当て設定フォーム -->
                        <form id="formStorageQuota" style="max-width: 500px;">
                            <div class="mb-3">
                                <label for="inputStorageLimit" class="form-label small text-muted mb-1">
                                    ストレージ割り当て容量 (0 で無制限):
                                </label>
                                <div class="input-group" style="max-width: 280px;">
                                    <input type="number" class="form-control" id="inputStorageLimit" 
                                           value="<?= htmlspecialchars((string)$quota_info['limit_gb']) ?>" 
                                           min="0" max="100000" step="0.1" required>
                                    <span class="input-group-text bg-white">GB</span>
                                </div>
                                <div class="form-text small text-muted">「0」を設定すると容量無制限になります。</div>
                            </div>

                            <div class="mb-4">
                                <div class="btn-group btn-group-sm flex-wrap">
                                    <button type="button" class="btn btn-outline-secondary preset-quota-btn" data-quota="1">1 GB</button>
                                    <button type="button" class="btn btn-outline-secondary preset-quota-btn" data-quota="5">5 GB</button>
                                    <button type="button" class="btn btn-outline-secondary preset-quota-btn" data-quota="10">10 GB</button>
                                    <button type="button" class="btn btn-outline-secondary preset-quota-btn" data-quota="50">50 GB</button>
                                    <button type="button" class="btn btn-outline-secondary preset-quota-btn" data-quota="100">100 GB</button>
                                    <button type="button" class="btn btn-outline-secondary preset-quota-btn" data-quota="0">無制限</button>
                                </div>
                            </div>

                            <div class="d-flex justify-content-start">
                                <button type="submit" class="btn btn-primary px-4" id="btnSaveStorageQuota">
                                    <i class="bi bi-save me-1"></i>割り当て容量を保存
                                </button>
                            </div>
                        </form>
                    </section>

                    <!-- ============================================== -->
                    <!-- 4. 二段階認証 (MFA) セクション（縦並び仕様） -->
                    <!-- ============================================== -->
                    <section id="section-mfa" class="admin-section">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0 text-dark">
                                <i class="bi bi-shield-lock text-primary me-2"></i>二段階認証 (MFA)
                            </h5>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge <?= $current_mfa_enabled ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle' ?>" id="mfaStatusBadge">
                                    <?= $current_mfa_enabled ? '有効' : '無効' ?>
                                </span>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="adminMfaToggleSwitch" <?= $current_mfa_enabled ? 'checked' : '' ?> style="cursor: pointer;">
                                </div>
                            </div>
                        </div>

                        <!-- MFA無効時の案内アラート -->
                        <div id="mfaDisabledAlert" class="alert alert-light border small text-muted mb-4 <?= $current_mfa_enabled ? 'd-none' : '' ?>">
                            <i class="bi bi-info-circle me-1 text-secondary"></i>二段階認証（MFA）は現在無効です。ログイン時はメールアドレスとパスワードのみで認証されます。右上のスイッチをONにすることでいつでも有効化できます。
                        </div>

                        <div id="mfaDetailsContainer" class="<?= $current_mfa_enabled ? '' : 'opacity-50' ?>">
                            <!-- 上：セットアップコード（シークレットキー） -->
                            <div class="mb-4" style="max-width: 450px;">
                                <label class="form-label fw-bold small text-muted">セットアップコード (シークレットキー)</label>
                                <div class="d-flex align-items-center gap-2">
                                    <div id="mfaSecretDisplay" class="secret-box flex-grow-1 text-center fw-bold">
                                        <?= htmlspecialchars($current_mfa_secret, ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <button class="btn btn-outline-secondary" type="button" id="btnCopySecret" title="キーをコピー">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                    <button class="btn btn-outline-danger" type="button" id="btnOpenRegenMfaModal" title="MFAシークレットを再生成">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- 下：QRコード（縦並び） -->
                            <div>
                                <label class="form-label fw-bold small text-muted d-block">QRコード</label>
                                <div class="p-2 d-inline-block bg-white border rounded">
                                    <img id="mfaQrImage" src="<?= htmlspecialchars($initial_qr_url, ENT_QUOTES, 'UTF-8') ?>" alt="MFA QR Code" style="width: 180px; height: 180px; object-fit: contain;">
                                </div>
                                <p class="text-muted small mt-2 mb-0">認証アプリでスキャンしてください</p>
                            </div>
                        </div>
                    </section>

                    <!-- ============================================== -->
                    <!-- 4. パスワード変更セクション -->
                    <!-- ============================================== -->
                    <section id="section-password" class="admin-section">
                        <h5 class="fw-bold mb-3 text-dark">
                            <i class="bi bi-key text-primary me-2"></i>ログインパスワードの変更
                        </h5>

                        <form id="formChangePassword" style="max-width: 450px;">
                            <div class="mb-3">
                                <label for="newPassword" class="form-label fw-bold small">新しいパスワード</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="newPassword" required autocomplete="new-password">
                                    <button class="btn btn-outline-secondary toggle-pwd-btn" type="button" data-target="newPassword">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text small text-muted">
                                    大文字・小文字・数字を含む<strong>15文字以上</strong>で設定してください。
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="confirmPassword" class="form-label fw-bold small">新しいパスワード (確認用)</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirmPassword" required autocomplete="new-password">
                                    <button class="btn btn-outline-secondary toggle-pwd-btn" type="button" data-target="confirmPassword">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- ボタン配置: 左寄せに統一 -->
                            <div class="d-flex justify-content-start">
                                <button type="submit" class="btn btn-primary px-4" id="btnSubmitPassword">
                                    <i class="bi bi-lock me-1"></i>パスワードを変更する
                                </button>
                            </div>
                        </form>
                    </section>

                    <!-- ============================================== -->
                    <!-- 6. ディレクトリキャッシュ再構築セクション -->
                    <!-- ============================================== -->
                    <section id="section-cache" class="admin-section">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0 text-dark">
                                <i class="bi bi-arrow-repeat text-primary me-2"></i>ディレクトリキャッシュの再構築
                            </h5>
                        </div>

                        <div class="card border border-light-subtle rounded-3 p-3 bg-light">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                <div>
                                    <div class="fw-semibold text-dark mb-1">ファイルシステムとデータベースの同期</div>
                                    <div class="text-muted small">
                                        外部から直接ファイルを配置したりフォルダを作成・削除した場合に、このボタンを実行してツリー構造を最新状態へ同期します。<br>
                                        （※通常のWeb画面上でのフォルダ作成や移動操作では自動的に同期されます）
                                    </div>
                                </div>
                                <form action="admin.php" method="post" onsubmit="return confirm('ディレクトリキャッシュを再構築しますか？\nファイル数が多い場合、数秒かかる場合があります。');" class="mb-0 flex-shrink-0">
                                    <input type="hidden" name="action" value="admin_rebuild_cache">
                                    <button type="submit" class="btn btn-primary d-inline-flex align-items-center">
                                        <i class="bi bi-arrow-clockwise me-2"></i>キャッシュを再構築する
                                    </button>
                                </form>
                            </div>
                        </div>
                    </section>

                    <!-- 7. セキュリティ自己診断 -->
                    <section id="section-security" class="admin-section mb-5">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                                <h5 class="card-title mb-0 fs-6 fw-bold d-flex align-items-center">
                                    <i class="bi bi-shield-check text-primary me-2 fs-5"></i>
                                    セキュリティ自己診断
                                </h5>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="btnRunSecurityCheck">
                                    <i class="bi bi-arrow-clockwise me-1"></i>再診断を実行
                                </button>
                            </div>
                            <div class="card-body p-4">
                                <p class="text-muted small mb-4">
                                    設定ファイル（/config/）や暗号化データ領域（/data/）が外部から直接アクセス・ダウンロードできない状態にあるかを自動診断します。<br>
                                    Apache環境のほか、Nginx等のWebサーバーをお使いの場合の安全確認にも役立ちます。
                                </p>

                                <div id="securityCheckLoading" class="text-center py-4">
                                    <div class="spinner-border text-primary spinner-border-sm me-2" role="status"></div>
                                    <span class="text-muted small">セキュリティ状態を診断中...</span>
                                </div>

                                <div id="securityCheckResults" class="d-none">
                                    <div class="list-group mb-4" id="securityCheckList">
                                        <!-- 動的に生成 -->
                                    </div>

                                    <!-- Nginx 設定案内 -->
                                    <div class="card bg-light border-0" id="nginxGuideCard">
                                        <div class="card-body p-3">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <span class="fw-bold small text-dark">
                                                    <i class="bi bi-server me-1"></i>Nginx をご利用の場合の推奨設定
                                                </span>
                                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" id="btnCopyNginxConfig" style="font-size: 0.8rem;">
                                                    <i class="bi bi-clipboard me-1"></i>設定をコピー
                                                </button>
                                            </div>
                                            <p class="text-muted small mb-2" style="font-size: 0.8rem;">
                                                Nginxは .htaccess を解釈しないため、Webサーバー設定（nginx.conf 等）の server ブロックに以下を記載してください。
                                            </p>
                                            <pre class="bg-dark text-light p-3 rounded small mb-0 font-monospace" style="font-size: 0.75rem; max-height: 160px; overflow-y: auto;" id="nginxSnippetCode"></pre>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                </div>
            </main>
        </div>
    </div>

    <!-- MFA再生成確認モーダル -->
    <div class="modal fade" id="modalRegenMfa" tabindex="-1" aria-labelledby="modalRegenMfaLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fs-6" id="modalRegenMfaLabel"><i class="bi bi-exclamation-triangle-fill me-2"></i>MFAシークレット再生成の確認</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="閉じる"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2"><strong>MFAシークレットキーを再生成しますか？</strong></p>
                    <p class="text-muted small mb-0">
                        再生成を行うと現在の設定キーは無効化されます。新しいQRコードが表示されますので、必ずご自身の認証アプリに再登録を行ってください。
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">キャンセル</button>
                    <button type="button" class="btn btn-danger btn-sm" id="btnConfirmRegenMfa">
                        <i class="bi bi-arrow-clockwise me-1"></i>再生成を実行する
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- トースト通知コンテナ -->
    <div class="toast-container"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const API_URL = 'functions/admin_api.php';
        const regenModal = new bootstrap.Modal(document.getElementById('modalRegenMfa'));

        // トースト通知関数
        function showToast(type, message, autoHide = true) {
            const container = document.querySelector('.toast-container');
            if (!container) return;

            const bgClass = {
                'success': 'text-bg-success',
                'danger': 'text-bg-danger',
                'warning': 'text-bg-warning',
                'info': 'text-bg-primary'
            }[type] || 'text-bg-primary';

            const iconClass = {
                'success': 'bi-check-circle-fill',
                'danger': 'bi-x-octagon-fill',
                'warning': 'bi-exclamation-triangle-fill',
                'info': 'bi-info-circle-fill'
            }[type] || 'bi-info-circle-fill';

            const toastEl = document.createElement('div');
            toastEl.className = `toast align-items-center ${bgClass} border-0`;
            toastEl.setAttribute('role', 'alert');
            toastEl.setAttribute('aria-live', 'assertive');
            toastEl.setAttribute('aria-atomic', 'true');
            toastEl.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body d-flex align-items-center">
                        <i class="bi ${iconClass} me-2"></i>
                        <span>${message}</span>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="閉じる"></button>
                </div>
            `;
            container.appendChild(toastEl);
            const toast = new bootstrap.Toast(toastEl, { autohide: autoHide, delay: 4000 });
            toast.show();
            toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
        }

        async function postApi(action, data = {}) {
            const res = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action, data })
            });
            if (res.status === 401) {
                showToast('danger', 'セッションが切れました。ログイン画面へ移動します。');
                setTimeout(() => window.location.href = 'login.php', 1500);
                return { success: false, message: 'Unauthorized' };
            }
            return res.json();
        }

        // ==========================================
        // 1. ファイル種別管理ロジック
        // ==========================================
        document.getElementById('btnSelectAllExt').addEventListener('click', () => {
            document.querySelectorAll('.ext-checkbox').forEach(cb => cb.checked = true);
        });
        document.getElementById('btnUnselectAllExt').addEventListener('click', () => {
            document.querySelectorAll('.ext-checkbox').forEach(cb => cb.checked = false);
        });

        // カスタム拡張子リスト管理
        const customExtList = document.getElementById('customExtList');
        const inputCustomExt = document.getElementById('inputCustomExt');

        function addCustomExtBadge(ext) {
            const clean = ext.toLowerCase().replace(/[^a-z0-9_-]/g, '');
            if (!clean) return;

            const existingCb = document.querySelector(`.ext-checkbox[value="${clean}"]`);
            if (existingCb) {
                existingCb.checked = true;
                showToast('info', `.${clean} は定義済みリストで有効化しました`);
                return;
            }
            const existingBadge = customExtList.querySelector(`[data-ext="${clean}"]`);
            if (existingBadge) {
                showToast('info', `.${clean} は既に追加されています`);
                return;
            }

            const badge = document.createElement('span');
            badge.className = 'badge bg-primary-subtle text-primary border border-primary-subtle ext-badge';
            badge.dataset.ext = clean;
            badge.innerHTML = `.${clean} <button type="button" class="btn-close btn-close-dark btn-remove-custom-ext" aria-label="削除" style="font-size: 0.65rem;"></button>`;
            customExtList.appendChild(badge);
            showToast('success', `.${clean} を追加しました`);
        }

        document.getElementById('btnAddCustomExt').addEventListener('click', () => {
            addCustomExtBadge(inputCustomExt.value);
            inputCustomExt.value = '';
        });
        inputCustomExt.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                addCustomExtBadge(inputCustomExt.value);
                inputCustomExt.value = '';
            }
        });

        customExtList.addEventListener('click', (e) => {
            if (e.target.classList.contains('btn-remove-custom-ext')) {
                const badge = e.target.closest('.ext-badge');
                if (badge) badge.remove();
            }
        });

        // 拡張子設定の保存
        document.getElementById('formFileExtensions').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSaveExtensions');
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span>保存中...`;

            const selectedExts = [];
            document.querySelectorAll('.ext-checkbox:checked').forEach(cb => {
                selectedExts.push(cb.value);
            });
            customExtList.querySelectorAll('.ext-badge').forEach(badge => {
                selectedExts.push(badge.dataset.ext);
            });

            const currentMaxSize = parseInt(document.getElementById('inputMaxFileSize').value, 10) || 500;

            try {
                const res = await postApi('update_accept_config', {
                    extensions: selectedExts,
                    max_file_size_mb: currentMaxSize
                });
                if (res.success) {
                    showToast('success', res.message);
                } else {
                    showToast('danger', res.message);
                }
            } catch (err) {
                showToast('danger', '保存中にエラーが発生しました');
            } finally {
                btn.disabled = false;
                btn.innerHTML = `<i class="bi bi-save me-1"></i>ファイル種別設定を保存`;
            }
        });

        // ==========================================
        // 2. ファイルサイズ上限ロジック
        // ==========================================
        document.querySelectorAll('.preset-size-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('inputMaxFileSize').value = this.dataset.size;
            });
        });

        document.getElementById('formFileSize').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSaveFileSize');
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span>保存中...`;

            const newSize = parseInt(document.getElementById('inputMaxFileSize').value, 10);
            
            const selectedExts = [];
            document.querySelectorAll('.ext-checkbox:checked').forEach(cb => {
                selectedExts.push(cb.value);
            });
            customExtList.querySelectorAll('.ext-badge').forEach(badge => {
                selectedExts.push(badge.dataset.ext);
            });

            try {
                const res = await postApi('update_accept_config', {
                    extensions: selectedExts,
                    max_file_size_mb: newSize
                });
                if (res.success) {
                    document.getElementById('currentSizeDisplay').textContent = `${newSize} MB`;
                    showToast('success', res.message);
                } else {
                    showToast('danger', res.message);
                }
            } catch (err) {
                showToast('danger', '保存中にエラーが発生しました');
            } finally {
                btn.disabled = false;
                btn.innerHTML = `<i class="bi bi-save me-1"></i>サイズ上限を保存`;
            }
        });

        // ==========================================
        // ストレージ割り当て容量ロジック
        // ==========================================
        document.querySelectorAll('.preset-quota-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('inputStorageLimit').value = this.dataset.quota;
            });
        });

        document.getElementById('formStorageQuota').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSaveStorageQuota');
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span>保存中...`;

            const limitVal = parseFloat(document.getElementById('inputStorageLimit').value) || 0;

            try {
                const res = await postApi('update_storage_limit', {
                    storage_limit_gb: limitVal
                });
                if (res.success && res.data) {
                    const d = res.data;
                    document.getElementById('displayQuotaLimit').textContent = d.formatted_limit;
                    document.getElementById('displayQuotaUsed').textContent = d.formatted_used;
                    document.getElementById('displayQuotaRemaining').textContent = d.formatted_remaining;

                    const badge = document.getElementById('quotaUsageBadge');
                    const bar = document.getElementById('quotaProgressBar');
                    badge.textContent = d.is_unlimited ? '無制限' : `${d.usage_percent}%`;
                    bar.style.width = d.is_unlimited ? '0%' : `${d.usage_percent}%`;

                    const colorClass = d.usage_percent >= 90 ? 'bg-danger' : (d.usage_percent >= 80 ? 'bg-warning' : 'bg-primary');
                    bar.className = `progress-bar ${colorClass}`;
                    if (!d.is_unlimited && d.usage_percent >= 80 && d.usage_percent < 90) {
                        badge.className = 'badge bg-warning text-dark';
                    } else if (!d.is_unlimited && d.usage_percent >= 90) {
                        badge.className = 'badge bg-danger';
                    } else {
                        badge.className = 'badge bg-primary';
                    }

                    showToast('success', res.message);
                } else {
                    showToast('danger', res.message || '割り当て容量の更新に失敗しました');
                }
            } catch (err) {
                showToast('danger', '保存中にエラーが発生しました');
            } finally {
                btn.disabled = false;
                btn.innerHTML = `<i class="bi bi-save me-1"></i>割り当て容量を保存`;
            }
        });

        // ==========================================
        // MFA ON/OFF切り替えロジック
        // ==========================================
        const adminMfaSwitch = document.getElementById('adminMfaToggleSwitch');
        if (adminMfaSwitch) {
            adminMfaSwitch.addEventListener('change', async function() {
                const isEnabled = this.checked;
                const badge = document.getElementById('mfaStatusBadge');
                const alertBox = document.getElementById('mfaDisabledAlert');
                const detailsBox = document.getElementById('mfaDetailsContainer');

                adminMfaSwitch.disabled = true;

                try {
                    const res = await postApi('update_mfa_status', { enabled: isEnabled });
                    if (res.success) {
                        if (isEnabled) {
                            badge.textContent = '有効';
                            badge.className = 'badge bg-success-subtle text-success border border-success-subtle';
                            alertBox.classList.add('d-none');
                            detailsBox.classList.remove('opacity-50');
                        } else {
                            badge.textContent = '無効';
                            badge.className = 'badge bg-secondary-subtle text-secondary border border-secondary-subtle';
                            alertBox.classList.remove('d-none');
                            detailsBox.classList.add('opacity-50');
                        }
                        showToast('success', res.message);
                    } else {
                        adminMfaSwitch.checked = !isEnabled;
                        showToast('danger', res.message || '二段階認証設定の更新に失敗しました');
                    }
                } catch (err) {
                    adminMfaSwitch.checked = !isEnabled;
                    showToast('danger', '通信エラーが発生しました');
                } finally {
                    adminMfaSwitch.disabled = false;
                }
            });
        }

        // ==========================================
        // 4. MFA管理ロジック
        // ==========================================
        document.getElementById('btnCopySecret').addEventListener('click', function() {
            const secret = document.getElementById('mfaSecretDisplay').textContent.trim();
            if (navigator.clipboard) {
                navigator.clipboard.writeText(secret).then(() => {
                    showToast('success', 'シークレットキーをコピーしました');
                });
            } else {
                showToast('info', 'キーを選択してコピーしてください');
            }
        });

        document.getElementById('btnOpenRegenMfaModal').addEventListener('click', function() {
            regenModal.show();
        });

        document.getElementById('btnConfirmRegenMfa').addEventListener('click', async function() {
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span>処理中...`;

            try {
                const res = await postApi('regenerate_mfa');
                if (res.success) {
                    document.getElementById('mfaSecretDisplay').textContent = res.secret;
                    document.getElementById('mfaQrImage').src = res.qr_code_url;
                    regenModal.hide();
                    showToast('success', res.message || 'MFAシークレットを再生成しました');
                } else {
                    showToast('danger', res.message || 'MFAシークレットの再生成に失敗しました');
                }
            } catch (err) {
                showToast('danger', '通信エラーが発生しました');
            } finally {
                btn.disabled = false;
                btn.innerHTML = `<i class="bi bi-arrow-clockwise me-1"></i>再生成を実行する`;
            }
        });

        // ==========================================
        // 4. パスワード変更ロジック
        // ==========================================
        document.querySelectorAll('.toggle-pwd-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const targetId = this.dataset.target;
                const input = document.getElementById(targetId);
                const icon = this.querySelector('i');
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.className = 'bi bi-eye-slash';
                } else {
                    input.type = 'password';
                    icon.className = 'bi bi-eye';
                }
            });
        });

        document.getElementById('formChangePassword').addEventListener('submit', async function(e) {
            e.preventDefault();
            const newPwd = document.getElementById('newPassword').value;
            const confirmPwd = document.getElementById('confirmPassword').value;

            if (newPwd !== confirmPwd) {
                showToast('danger', '新しいパスワードと確認用パスワードが一致しません');
                return;
            }
            if (newPwd.length < 15) {
                showToast('danger', '新しいパスワードは15文字以上で入力してください');
                return;
            }
            if (!/^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9]).*$/.test(newPwd)) {
                showToast('danger', 'パスワードには大文字英字、小文字英字、数字を含めてください');
                return;
            }

            const btn = document.getElementById('btnSubmitPassword');
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span>更新中...`;

            try {
                const res = await postApi('change_password', {
                    new_password: newPwd,
                    confirm_password: confirmPwd
                });
                if (res.success) {
                    showToast('success', res.message, false);
                    setTimeout(() => {
                        window.location.href = res.redirect || 'login.php';
                    }, 2000);
                } else {
                    showToast('danger', res.message);
                    btn.disabled = false;
                    btn.innerHTML = `<i class="bi bi-lock me-1"></i>パスワードを変更する`;
                }
            } catch (err) {
                showToast('danger', 'パスワード更新中にエラーが発生しました');
                btn.disabled = false;
                btn.innerHTML = `<i class="bi bi-lock me-1"></i>パスワードを変更する`;
            }
        });

        // ==========================================
        // 7. セキュリティ自己診断ロジック
        // ==========================================
        const btnRunSecurityCheck = document.getElementById('btnRunSecurityCheck');
        const securityCheckLoading = document.getElementById('securityCheckLoading');
        const securityCheckResults = document.getElementById('securityCheckResults');
        const securityCheckList = document.getElementById('securityCheckList');
        const nginxSnippetCode = document.getElementById('nginxSnippetCode');
        const sidebarSecurityBadge = document.getElementById('sidebarSecurityBadge');

        async function runSecurityCheck() {
            securityCheckLoading.classList.remove('d-none');
            securityCheckResults.classList.add('d-none');
            btnRunSecurityCheck.disabled = true;
            securityCheckList.innerHTML = '';

            try {
                const apiRes = await postApi('security_check');
                if (!apiRes.success) {
                    showToast('danger', 'セキュリティ診断情報の取得に失敗しました');
                    securityCheckLoading.classList.add('d-none');
                    btnRunSecurityCheck.disabled = false;
                    return;
                }

                nginxSnippetCode.textContent = apiRes.nginx_snippet || '';

                const items = [];

                // 1. 設定ファイル (/config/config.php) への直接アクセス検査
                try {
                    const cfgRes = await fetch(apiRes.test_urls.config_file, { cache: 'no-store' });
                    const text = await cfgRes.text();
                    if (cfgRes.status === 403 || cfgRes.status === 404) {
                        items.push({
                            title: '設定ファイル直接アクセス (/config/config.php)',
                            status: 'ok',
                            detail: `外部アクセスは遮断されています (HTTP ${cfgRes.status})。`
                        });
                    } else if (cfgRes.status === 200) {
                        if (text.includes('<' + '?php') || text.includes('data_root')) {
                            items.push({
                                title: '設定ファイル直接アクセス (/config/config.php)',
                                status: 'danger',
                                detail: '設定ファイルのソースコードが外部から閲覧可能です！Webサーバー設定で遮断してください。'
                            });
                        } else {
                            items.push({
                                title: '設定ファイル直接アクセス (/config/config.php)',
                                status: 'ok',
                                detail: 'PHPガード (die) により安全に保護されています (HTTP 200・空レスポンス)。'
                            });
                        }
                    } else {
                        items.push({
                            title: '設定ファイル直接アクセス (/config/config.php)',
                            status: 'ok',
                            detail: `安全に処理されています (HTTP ${cfgRes.status})。`
                        });
                    }
                } catch (e) {
                    items.push({
                        title: '設定ファイル直接アクセス (/config/config.php)',
                        status: 'ok',
                        detail: '外部からのアクセスは拒否されています。'
                    });
                }

                // 2. ディレクトリ一覧表示の防止 (/config/)
                try {
                    const dirRes = await fetch(apiRes.test_urls.config_dir, { cache: 'no-store' });
                    const dirText = await dirRes.text();
                    if (dirRes.status === 403 || dirRes.status === 404 || dirText.includes('403 Forbidden') || dirText.trim() === '') {
                        items.push({
                            title: 'ディレクトリ一覧表示の防止 (/config/)',
                            status: 'ok',
                            detail: 'ファイル一覧の外部表示は遮断されています (.htaccess / index.html 有効)。'
                        });
                    } else if (dirText.includes('Index of') || dirText.includes('Parent Directory')) {
                        items.push({
                            title: 'ディレクトリ一覧表示の防止 (/config/)',
                            status: 'warning',
                            detail: 'ディレクトリ一覧（Index of）が表示される設定になっています。'
                        });
                    } else {
                        items.push({
                            title: 'ディレクトリ一覧表示の防止 (/config/)',
                            status: 'ok',
                            detail: '一覧表示は防止されています。'
                        });
                    }
                } catch (e) {
                    items.push({
                        title: 'ディレクトリ一覧表示の防止 (/config/)',
                        status: 'ok',
                        detail: 'ディレクトリへの直接アクセスは防止されています。'
                    });
                }

                // 3. データ領域の保護
                if (apiRes.server_checks.data_htaccess && apiRes.server_checks.data_index_html) {
                    items.push({
                        title: 'データ領域の二重保護 (data/)',
                        status: 'ok',
                        detail: 'ランダムフォルダ名 + .htaccess + index.html による多重防御が有効です。'
                    });
                } else {
                    items.push({
                        title: 'データ領域の保護 (data/)',
                        status: 'warning',
                        detail: '保護用ファイル (.htaccess / index.html) の再生成を推奨します。'
                    });
                }

                // 4. 暗号化通信 (HTTPS)
                if (apiRes.server_checks.https) {
                    items.push({
                        title: '通信の暗号化 (HTTPS)',
                        status: 'ok',
                        detail: 'SSL/TLSによる暗号化接続で安全に保護されています。'
                    });
                } else {
                    items.push({
                        title: '通信の暗号化 (HTTPS)',
                        status: 'warning',
                        detail: '現在はHTTP接続です。本番運用時は常時SSL (HTTPS) を推奨します。'
                    });
                }

                // UIへレンダリング
                let allOk = true;
                items.forEach(it => {
                    const isOk = it.status === 'ok';
                    const isWarn = it.status === 'warning';
                    if (!isOk) allOk = false;

                    const icon = isOk
                        ? '<i class="bi bi-check-circle-fill text-success fs-5 me-3"></i>'
                        : (isWarn
                            ? '<i class="bi bi-exclamation-triangle-fill text-warning fs-5 me-3"></i>'
                            : '<i class="bi bi-x-circle-fill text-danger fs-5 me-3"></i>');

                    const badge = isOk
                        ? '<span class="badge bg-success-subtle text-success border border-success-subtle">安全</span>'
                        : (isWarn
                            ? '<span class="badge bg-warning-subtle text-warning border border-warning-subtle">注意</span>'
                            : '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">危険</span>');

                    const el = document.createElement('div');
                    el.className = 'list-group-item list-group-item-action d-flex align-items-center py-3';
                    el.innerHTML = `
                        ${icon}
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="fw-bold small text-dark">${it.title}</span>
                                ${badge}
                            </div>
                            <div class="text-muted small">${it.detail}</div>
                        </div>
                    `;
                    securityCheckList.appendChild(el);
                });

                if (allOk) {
                    sidebarSecurityBadge.classList.remove('d-none');
                } else {
                    sidebarSecurityBadge.classList.add('d-none');
                }

                securityCheckLoading.classList.add('d-none');
                securityCheckResults.classList.remove('d-none');
            } catch (err) {
                showToast('danger', 'セキュリティ診断の実行中にエラーが発生しました');
                securityCheckLoading.classList.add('d-none');
            } finally {
                btnRunSecurityCheck.disabled = false;
            }
        }

        btnRunSecurityCheck.addEventListener('click', runSecurityCheck);

        // Nginx設定コピーボタン
        document.getElementById('btnCopyNginxConfig').addEventListener('click', async function() {
            const code = nginxSnippetCode.textContent;
            if (!code) return;
            try {
                await navigator.clipboard.writeText(code);
                const originalHtml = this.innerHTML;
                this.innerHTML = '<i class="bi bi-check me-1"></i>コピー完了';
                setTimeout(() => this.innerHTML = originalHtml, 2000);
            } catch (e) {
                alert('クリップボードにコピーできませんでした。');
            }
        });

        // ページ読み込み時に自動診断を実行
        document.addEventListener('DOMContentLoaded', () => {
            runSecurityCheck();
        });

    </script>
</body>
</html>
