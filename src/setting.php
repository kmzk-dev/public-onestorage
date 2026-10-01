<?php
define('ONESTORAGE_RUNNING', true);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/path.php';
require_once __DIR__ . '/functions/helpers.php';
require_once __DIR__ . '/functions/cookie.php';
require_once __DIR__ . '/functions/mfa.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// データフォルダと設定ファイルの存在チェックのみ行う
if (file_exists(MAIN_CONFIG_PATH) && !defined('DATA_ROOT')) {
    $main_config = require MAIN_CONFIG_PATH;
    define('DATA_ROOT', $main_config['data_root']);
}

// すでに全ての設定が完了していればログイン画面へリダイレクト（プレビューモード時を除く）
$setup_completed = file_exists(AUTH_CONFIG_PATH)
    && file_exists(MAIN_CONFIG_PATH)
    && file_exists(MFA_SECRET_PATH);

$is_preview = isset($_GET['preview']) || (getenv('SKIP_AUTH') === '1' || (isset($_ENV['SKIP_AUTH']) && $_ENV['SKIP_AUTH'] === '1'));

if ($setup_completed && !$is_preview) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        session_destroy();
    }
    redirect('login.php');
}

// 既存のアカウントがあればメールアドレスを取得
$current_user_email = '';
if (file_exists(AUTH_CONFIG_PATH)) {
    $auth_config = require AUTH_CONFIG_PATH;
    $current_user_email = $auth_config['user'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>初期設定 - ONE STORAGE</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #ffffff; color: #212529; }
        .setup-container { max-width: 480px; margin: 40px auto 80px; padding: 0 15px; }
        .secret-display { font-family: monospace; letter-spacing: 2px; font-size: 1.05rem; }
        .qr-wrapper { width: 180px; height: 180px; margin: 0 auto; display: flex; align-items: center; justify-content: center; }
        .progress { height: 12px; border-radius: 6px; }
    </style>
</head>
<body>

<div class="setup-container">
    <!-- タイトル -->
    <div class="mb-4 text-center">
        <h3 class="fw-bold mb-1">ONE STORAGE</h3>
        <p class="text-muted small">初期セットアップ</p>
    </div>

    <div id="errorMessage" class="alert alert-danger d-none" role="alert"></div>

    <!-- 15秒プログレッシブ待機画面 -->
    <div id="progressScreen" class="d-none text-center py-4">
        <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <h5 class="fw-bold mb-2" id="progressTitle">システムをセットアップしています...</h5>
        <p class="text-muted small mb-4" id="progressStatus">暗号化ストレージ環境を初期化中...</p>

        <!-- プログレスバー -->
        <div class="progress mb-3 bg-light border">
            <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                 role="progressbar" style="width: 0%; transition: width 0.3s linear;"></div>
        </div>

        <div class="d-flex justify-content-between text-muted small px-1 mb-4">
            <span id="progressPercent">0%</span>
            <span id="countdownText">残り 15 秒...</span>
        </div>

        <div class="alert alert-light border small text-muted text-start">
            <i class="fa-solid fa-circle-info text-primary me-2"></i>
            サーバーの安全な初期化とキャッシュ安定化を行っています。画面を閉じずにお待ちください。
        </div>
    </div>

    <!-- 入力フォーム -->
    <form id="setupForm" novalidate>
        <!-- 1. メールアドレス -->
        <div class="mb-3">
            <label for="userEmail" class="form-label fw-bold small">メールアドレス</label>
            <input type="email" class="form-control" id="userEmail" name="user"
                   value="<?= htmlspecialchars($current_user_email, ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="admin@example.com" required autocomplete="email">
        </div>

        <!-- 2. パスワード (確認欄なし) -->
        <div class="mb-4">
            <label for="userPassword" class="form-label fw-bold small">パスワード</label>
            <input type="password" class="form-control" id="userPassword" name="password"
                   placeholder="大文字・小文字・数字含む15桁以上" required autocomplete="new-password">
            <div class="form-text small text-muted">大文字英字、小文字英字、数字をすべて含む15桁以上</div>
        </div>

        <hr class="my-4">

        <!-- 3. 二段階認証 (MFA) -->
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="form-label fw-bold small mb-0">二段階認証（MFA）</label>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" id="mfaEnableSwitch" checked>
                    <label class="form-check-label small fw-bold" for="mfaEnableSwitch" id="mfaSwitchLabel">有効 (推奨)</label>
                </div>
            </div>

            <!-- MFA有効時の設定エリア -->
            <div id="mfaDetailsArea">
                <p class="text-muted small mb-3">Google Authenticator等の認証アプリでQRコードを読み取ってください。</p>

                <div class="qr-wrapper mb-3 border p-1 rounded">
                    <div id="qrLoading" class="text-muted small">
                        <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                        生成中...
                    </div>
                    <img id="mfaQrcodeImage" src="" alt="MFA QR Code" class="img-fluid d-none" style="width: 170px; height: 170px;">
                </div>

                <div class="text-center mb-3">
                    <span class="d-block small text-muted mb-1">セットアップコード:</span>
                    <div class="p-2 bg-light border rounded mb-2">
                        <span id="mfaSecretDisplay" class="secret-display fw-bold text-dark">取得中...</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnCopySecret">
                        <i class="fa-regular fa-copy me-1"></i>コードをコピー
                    </button>
                </div>

                <!-- MFA設定完了チェック欄 -->
                <div class="form-check p-3 bg-light border rounded mt-3">
                    <input class="form-check-input ms-0 me-2" type="checkbox" id="mfaCompletedCheck" required>
                    <label class="form-check-label fw-bold small" for="mfaCompletedCheck">
                        認証アプリへの登録を完了しました
                    </label>
                </div>
            </div>

            <!-- MFA無効時の案内エリア -->
            <div id="mfaDisabledNotice" class="alert alert-light border small text-muted d-none">
                <i class="fa-solid fa-circle-info text-secondary me-2"></i>二段階認証はスキップされます。インストール完了後、いつでも「管理設定」から有効化できます。
            </div>

            <input type="hidden" id="mfaSecretValue" name="mfaSecret" value="">
        </div>

        <!-- 送信ボタン -->
        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary py-2 fw-bold" id="btnSubmit">
                設定を完了してログインへ進む
            </button>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const API_URL = 'functions/setting.php';
let g_mfaSecret = '';

async function fetchApi(action, data = {}) {
    const response = await fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, data })
    });
    return response.json();
}

function showError(msg) {
    const el = document.getElementById('errorMessage');
    el.textContent = 'エラー: ' + msg;
    el.classList.remove('d-none');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function hideError() {
    document.getElementById('errorMessage').classList.add('d-none');
}

// ページ読み込み時にMFAキーを自動生成・表示
async function loadMfaSecret(userEmail = '') {
    try {
        const result = await fetchApi('generate_mfa_secret', { user: userEmail || 'OneStorage' });
        if (result.success && result.secret) {
            g_mfaSecret = result.secret;
            document.getElementById('mfaSecretValue').value = result.secret;
            document.getElementById('mfaSecretDisplay').textContent = result.secret;

            const qrImg = document.getElementById('mfaQrcodeImage');
            qrImg.src = result.qr_code_url;
            qrImg.onload = () => {
                document.getElementById('qrLoading').classList.add('d-none');
                qrImg.classList.remove('d-none');
            };
        } else {
            document.getElementById('qrLoading').textContent = 'QRコードの生成に失敗しました';
        }
    } catch (e) {
        document.getElementById('qrLoading').textContent = '通信エラーが発生しました';
    }
}

// セットアップコードのコピー
document.getElementById('btnCopySecret').addEventListener('click', async function() {
    if (!g_mfaSecret) return;
    try {
        await navigator.clipboard.writeText(g_mfaSecret);
        const originalText = this.innerHTML;
        this.innerHTML = '<i class="fa-solid fa-check me-1"></i>コピー完了';
        setTimeout(() => { this.innerHTML = originalText; }, 2000);
    } catch (err) {
        alert('コード: ' + g_mfaSecret);
    }
});

// メールアドレス変更時にQRコードのラベルを更新
document.getElementById('userEmail').addEventListener('blur', function() {
    const email = this.value.trim();
    if (email && g_mfaSecret) {
        const issuer = encodeURIComponent('One Storage');
        const label = encodeURIComponent(email);
        const otpauth = `otpauth://totp/${issuer}:${label}?secret=${encodeURIComponent(g_mfaSecret)}&issuer=${issuer}`;
        const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(otpauth);
        document.getElementById('mfaQrcodeImage').src = qrUrl;
    }
});

// 15秒プログレスバー待機アニメーション
function startProgressAnimation() {
    document.getElementById('setupForm').classList.add('d-none');
    document.getElementById('progressScreen').classList.remove('d-none');
    window.scrollTo({ top: 0, behavior: 'smooth' });

    const totalSeconds = 15;
    let elapsed = 0;
    const intervalMs = 250;
    const steps = (totalSeconds * 1000) / intervalMs;

    const progressBar = document.getElementById('progressBar');
    const progressPercent = document.getElementById('progressPercent');
    const countdownText = document.getElementById('countdownText');
    const progressStatus = document.getElementById('progressStatus');
    const progressTitle = document.getElementById('progressTitle');

    const timer = setInterval(() => {
        elapsed++;
        const pct = Math.min(100, Math.round((elapsed / steps) * 100));
        progressBar.style.width = pct + '%';
        progressPercent.textContent = pct + '%';

        const remainingSec = Math.max(0, Math.ceil(totalSeconds - (elapsed * intervalMs / 1000)));
        countdownText.textContent = `残り ${remainingSec} 秒...`;

        // 進行状況に応じたメッセージ変化
        if (remainingSec > 10) {
            progressStatus.textContent = '暗号化ストレージ環境を初期化中...';
        } else if (remainingSec > 5) {
            progressStatus.textContent = 'SQLiteデータベース構造を最適化中...';
        } else if (remainingSec > 1) {
            progressStatus.textContent = 'サーバーキャッシュと認証情報を安定化中...';
        } else {
            progressStatus.textContent = 'セットアップ完了！ログイン画面へ移動します...';
        }

        if (elapsed >= steps) {
            clearInterval(timer);
            progressBar.classList.remove('progress-bar-animated');
            progressBar.classList.add('bg-success');
            progressTitle.textContent = 'セットアップ完了！';
            setTimeout(() => {
                window.location.href = 'login.php';
            }, 800);
        }
    }, intervalMs);
}

// MFAスイッチの切り替えイベント
document.getElementById('mfaEnableSwitch').addEventListener('change', function() {
    const isEnabled = this.checked;
    const label = document.getElementById('mfaSwitchLabel');
    const detailsArea = document.getElementById('mfaDetailsArea');
    const disabledNotice = document.getElementById('mfaDisabledNotice');
    const completedCheck = document.getElementById('mfaCompletedCheck');

    if (isEnabled) {
        label.textContent = '有効 (推奨)';
        label.className = 'form-check-label small fw-bold text-dark';
        detailsArea.classList.remove('d-none');
        disabledNotice.classList.add('d-none');
        completedCheck.required = true;
    } else {
        label.textContent = '無効 (後で設定可能)';
        label.className = 'form-check-label small fw-bold text-muted';
        detailsArea.classList.add('d-none');
        disabledNotice.classList.remove('d-none');
        completedCheck.required = false;
        completedCheck.checked = false;
    }
});

// フォーム送信
document.getElementById('setupForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    hideError();

    const user = document.getElementById('userEmail').value.trim();
    const password = document.getElementById('userPassword').value;
    const mfaEnabled = document.getElementById('mfaEnableSwitch').checked;
    const mfaCompleted = document.getElementById('mfaCompletedCheck').checked;

    // クライアント側バリデーション
    if (!user || !user.includes('@')) {
        showError('正しいメールアドレスを入力してください。');
        return;
    }
    if (password.length < 15) {
        showError('パスワードは15桁以上で設定してください。');
        return;
    }
    if (!/^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9]).*$/.test(password)) {
        showError('パスワードには大文字英字、小文字英字、数字をすべて含めてください。');
        return;
    }
    if (mfaEnabled && !mfaCompleted) {
        showError('二段階認証（MFA）の「認証アプリへの登録を完了しました」にチェックを入れてください。');
        return;
    }

    const btn = document.getElementById('btnSubmit');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>設定中...';

    try {
        const result = await fetchApi('setup_all', {
            user,
            password,
            mfa_secret: g_mfaSecret,
            mfa_enabled: mfaEnabled
        });

        if (result.success) {
            // 15秒プログレス待機画面を開始
            startProgressAnimation();
        } else {
            btn.disabled = false;
            btn.innerHTML = '設定を完了してログインへ進む';
            showError(result.message);
        }
    } catch (err) {
        btn.disabled = false;
        btn.innerHTML = '設定を完了してログインへ進む';
        showError('サーバーとの通信に失敗しました。');
    }
});

// 初期化実行
document.addEventListener('DOMContentLoaded', () => {
    const initialEmail = document.getElementById('userEmail').value.trim();
    loadMfaSecret(initialEmail);
});
</script>
</body>
</html>