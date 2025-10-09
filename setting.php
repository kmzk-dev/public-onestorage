<?php
define('ONESTORAGE_RUNNING', true);
// PHPロジック: 初期設定の状態判定とデータフォルダの強制作成
require_once __DIR__ . '/path.php';
require_once __DIR__ . '/functions/helpers.php';
require_once __DIR__ . '/functions/cookie.php';
require_once __DIR__ . '/functions/mfa.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. データフォルダのチェックと強制作成 (data-XXXXXXX)
$data_root_config_exists = file_exists(MAIN_CONFIG_PATH);
$data_root_created_on_load = false;
$initial_data_dir_name = '';

if (!$data_root_config_exists) {
    $htaccess_content = "Order allow,deny\nDeny from all";
    $random_part = generate_random_string(15);
    $data_dir_name = 'data' . $random_part;
    $data_dir_path = __DIR__ . '/' . $data_dir_name;
    
    // configディレクトリの最低限の作成 (htaccess用)
    $config_dir = dirname(AUTH_CONFIG_PATH);
    if (!is_dir($config_dir)) {
        mkdir($config_dir, 0755, true);
    }
    if (is_dir($config_dir) && !file_exists($config_dir . DIRECTORY_SEPARATOR . '.htaccess')) {
        file_put_contents($config_dir . DIRECTORY_SEPARATOR . '.htaccess', $htaccess_content);
    }
    
    // データフォルダとconfig.phpの作成
    if (mkdir($data_dir_path, 0777, true)) {
        // config.phpの作成
        $main_config_content = "<?php\n\n";
        $main_config_content .= "// 自動生成されたデータフォルダのパス\n";
        $main_config_content .= "return ['data_root' => '" . addslashes($data_dir_path) . "'];\n";
        if (file_put_contents(MAIN_CONFIG_PATH, $main_config_content)) {
            $data_root_created_on_load = true;
            $initial_data_dir_name = $data_dir_name;
            
            // 強制作成後はDATA_ROOTを定義
            define('DATA_ROOT', $data_dir_path);
            if (!file_put_contents($data_dir_path . DIRECTORY_SEPARATOR . '.htaccess', $htaccess_content)) {
                // エラーは無視
            }
        }
    }
} elseif ($data_root_config_exists && !defined('DATA_ROOT')) {
    // config.phpが存在する場合、DATA_ROOTを定義して以降のチェックに備える
    $main_config = require MAIN_CONFIG_PATH;
    define('DATA_ROOT', $main_config['data_root']);
}

// 2. 現在の設定状態の判定
$setup_status = [
    // 4ステップに更新
    'storage_data' => file_exists(MAIN_CONFIG_PATH) && file_exists(COOKIE_KEY_PATH), // config.php & cookie_key.php
    'storage_accept' => file_exists(ACCEPT_CONFIG_PATH), // accept.json
    'account' => file_exists(AUTH_CONFIG_PATH),
    'mfa' => file_exists(MFA_SECRET_PATH)
];

// 全ての設定が完了していればログイン画面へリダイレクト
if ($setup_status['storage_data'] && $setup_status['storage_accept'] && $setup_status['account'] && $setup_status['mfa']) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        session_destroy();
    }
    redirect('login.php');
}

// ----------------------------------------------------------------------------------
// HTML描画
// ----------------------------------------------------------------------------------

// モーダルで使用するためのユーザー名を取得（MFAキー生成時に必要）
$current_user_email = '';
if ($setup_status['account']) {
    $auth_config = require AUTH_CONFIG_PATH;
    $current_user_email = $auth_config['user'] ?? '';
}

// 許可する拡張子の初期リスト
$default_extensions = [
    'pdf', 'txt', 'csv', 'md', 
    'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 
    'mp3', 'wav', 'flac', 'aac', 'ogg', 'm4a',
    'mp4', 'mov', 'avi', 'mkv', 'webm',
    'zip', '7z', 'rar',
    'xls', 'xlsx', 'doc', 'docx', 'pptx', 'heic',
    'json', 'yml', 'yaml', 'ini', 'log',
    'ai', 'psd',
];
$default_max_size = 750;
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>初期設定 - ONE STORAGE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .setting-step { display: none; margin-top: 20px; border-left: 5px solid #0d6efd; padding-left: 20px; }
        .is-active { display: block; }
        .status-icon { width: 24px; text-align: center; }
        .setup-status-list { cursor: default; }
    </style>
</head>

<body class="bg-white">

    <div class="container my-3">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="p-1">
                    <div class="col-4 mx-auto">
                        <img class="img-fluid" src="static/img_logo.PNG" alt="">
                    </div>
                    <p class="text-center text-muted">あなただけのストレージサービスで</br>プライバシーを守りましょう</p>

                    <div class="p-2">
                        <span class="mb-3"><i class="fa-solid fa-clipboard-list me-2"></i>本サービスはMFAが必須です。</br>設定を始める前にGoogleAuthenticator等のTOTP方式の認証アプリをご自身のスマートフォンにインストールしてください。</span>
                        <hr>
                        <div class="py-2 px-3 bg-light">
                            <ul class="list-unstyled setup-status-list">
                                <li class="mb-2"><span id="statusStorageData" class="status-icon me-2"></span> ストレージフォルダ/基本設定ファイル作成</li>
                                <li class="mb-2"><span id="statusStorageAccept" class="status-icon me-2"></span> 利用できる拡張子の設定</li>
                                <li class="mb-2"><span id="statusAccount" class="status-icon me-2"></span> ユーザーアカウント登録</li>
                                <li class="mb-2"><span id="statusMfa" class="status-icon me-2"></span> 二段階認証:MFAの登録</li>
                            </ul>
                        </div>
                    </div>
                    <div id="errorMessage" class="alert alert-danger mt-4 d-none"></div>
                    <div id="setupStepsContainer">
                        <div id="stepStorageData" class="setting-step">
                            <p class="text-muted fw-bold">STEP1: システムが動作するための設定を行います</p>
                            <button id="btnCreateStorageData" class="btn btn-primary">設定を開始する</button>
                        </div>
                        <div id="stepStorageAccept" class="setting-step">
                            <p class="text-muted">STEP2: アップロードを許可するファイル拡張子を選択してください</p>
                            <button id="btnOpenAcceptModal" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#acceptModal">拡張子を設定</button>
                        </div>
                        <div id="stepAccount" class="setting-step">
                            <p class="text-muted">STEP3: ログインするためのメールアドレスとパスワードを登録してください</p>
                            <button id="btnOpenAccountModal" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#accountModal">アカウントを登録</button>
                        </div>
                        <div id="stepMfa" class="setting-step">
                            <h4 class="mb-3">STEP4: 二段階認証:MFAの登録</h4>
                            <p class="text-muted">GoogleAuthenticator等のTOTPに対応している認証アプリを使ってアカウントを保護してください</p>
                            <button id="btnOpenMfaModal" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#mfaModal">MFAキーを生成・表示</button>
                        </div>
                        <div id="stepComplete" class="setting-step text-center">
                            <h4 class="mb-4 text-success"><i class="fa-solid fa-check-circle me-2"></i>すべての設定が完了しました</h4>
                            <a href="login.php" id="btnLogin" class="btn btn-lg btn-primary" disabled>ログイン画面へ進む</a>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="acceptModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-dark text-light">
                    <h5 class="modal-title">利用できる拡張子の設定</h5>
                </div>
                <form id="formAcceptExtensions">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">ファイルサイズ上限 (MB):</label>
                            <input type="number" id="maxFileSize" name="maxFileSize" class="form-control" value="<?= $default_max_size ?>" min="1" required>
                        </div>
                        <label class="form-label">許可する拡張子:</label>
                        <div class="row">
                            <?php 
                            $chunks = array_chunk($default_extensions, ceil(count($default_extensions) / 3));
                            foreach ($chunks as $chunk): ?>
                            <div class="col-4">
                                <?php foreach ($chunk as $ext): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="extensions[]" value="<?= htmlspecialchars($ext) ?>" id="ext_<?= htmlspecialchars($ext) ?>" checked>
                                        <label class="form-check-label" for="ext_<?= htmlspecialchars($ext) ?>">.<?= htmlspecialchars($ext) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button><button type="submit" class="btn btn-primary" id="btnSaveAccept">設定する</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="accountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-dark text-light">
                    <h5 class="modal-title">ユーザーアカウント登録</h5>
                </div>
                <form id="formRegisterAccount">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="user" class="form-label">メールアドレス:</label> 
                            <input type="email" id="modalUser" name="user" class="form-control" value="<?= htmlspecialchars($current_user_email) ?>" required>
                        </div>
                        <div class="mb-4">
                            <label for="password" class="form-label">パスワード:</label>
                            <input type="password" id="modalPassword" name="password" class="form-control" autocomplete="new-password" required>
                            <div class="form-text">大文字/小文字/数字を含む15桁以上で設定してください。</div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button><button type="submit" class="btn btn-primary" id="btnRegisterAccount">登録する</button></div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="mfaModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-dark text-light">
                    <h5 class="modal-title">二段階認証:MFAの登録</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <p class="text-muted">Google Authenticatorアプリで以下のQRコードをスキャンするか、セットアップコードを手動入力してください。</p>
                    
                    <div id="mfaQrcodeContainer" class="mb-3">
                        <img id="mfaQrcodeImage" src="" alt="MFA QR Code" class="img-fluid border p-2" style="width: 200px; height: 200px;">
                    </div>
                    
                    <h6 class="mt-4">セットアップコード:</h6>
                    <div class="mb-4 p-3 bg-light border text-center">
                        <h4 class="text-break mb-0 font-monospace" id="mfaSecretDisplay">キーを生成してください</h4>
                    </div>
                    
                    <h6 class="mt-4">手動登録手順:</h6>
                    <ol class="small text-start">
                        <li>Google Authenticatorアプリを開きます。</li>
                        <li>「設定キーを入力」を選択します。</li>
                        <li>アカウント名に「One Storage」など任意の名前を入力します。</li>
                        <li>キーに上記セットアップコードを入力し、「時間ベース」を選択して保存します。</li>
                    </ol>

                </div>
                <div class="modal-footer"><button type="button" class="btn btn-success w-100" data-bs-dismiss="modal" id="btnMfaNext">MFA登録を完了</button></div>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const API_URL = 'functions/setting.php';
        const setupStatus = <?= json_encode($setup_status) ?>;
        const CURRENT_USER_EMAIL = '<?= htmlspecialchars($current_user_email, ENT_QUOTES, 'UTF-8') ?>';
        
        // 4ステップ構成
        const STEPS = ['storage_data', 'storage_accept', 'account', 'mfa'];
        const STEP_DIVS = {
            'storage_data': 'stepStorageData',
            'storage_accept': 'stepStorageAccept',
            'account': 'stepAccount',
            'mfa': 'stepMfa'
        };
        const STATUS_ICONS = {
            'pending': '<i class="fa-solid fa-circle-xmark text-danger"></i>',
            'in_progress': '<i class="fa-solid fa-spinner fa-spin-pulse text-info"></i>',
            'completed': '<i class="fa-solid fa-check-circle text-success"></i>',
            'error': '<i class="fa-solid fa-circle-xmark text-danger"></i>',
        };

        const statusElements = {
            'storage_data': document.getElementById('statusStorageData'),
            'storage_accept': document.getElementById('statusStorageAccept'),
            'account': document.getElementById('statusAccount'),
            'mfa': document.getElementById('statusMfa')
        };
        const errorMessageDiv = document.getElementById('errorMessage');
        const btnLogin = document.getElementById('btnLogin');
        const acceptModal = new bootstrap.Modal(document.getElementById('acceptModal'));
        const accountModal = new bootstrap.Modal(document.getElementById('accountModal'));
        const mfaModal = new bootstrap.Modal(document.getElementById('mfaModal'));

        // --- UIヘルパー関数 ---

        function updateStatusDisplay() {
            for (const key of STEPS) {
                const statusEl = statusElements[key];
                if (!statusEl) continue;

                if (setupStatus[key]) {
                    statusEl.innerHTML = STATUS_ICONS.completed;
                } else {
                    statusEl.innerHTML = STATUS_ICONS.pending;
                }
            }
        }

        function showStep(stepKey) {
            document.querySelectorAll('.setting-step').forEach(div => div.classList.remove('is-active'));
            const targetDivId = STEP_DIVS[stepKey] || 'stepComplete';
            const targetDiv = document.getElementById(targetDivId);
            if (targetDiv) {
                targetDiv.classList.add('is-active');
            }
        }
        
        function showError(message) {
            errorMessageDiv.textContent = 'エラー: ' + message;
            errorMessageDiv.classList.remove('d-none');
        }
        
        function hideError() {
            errorMessageDiv.classList.add('d-none');
        }
        
        function setButtonLoading(button, isLoading) {
            const originalText = button.dataset.originalText || button.textContent;
            if (isLoading) {
                button.dataset.originalText = originalText;
                button.disabled = true;
                button.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> 処理中...`;
            } else {
                button.disabled = false;
                button.innerHTML = originalText;
            }
        }

        // --- ステップ管理ロジック ---

        function findNextStep() {
            for (const step of STEPS) {
                if (!setupStatus[step]) {
                    return step;
                }
            }
            return 'complete';
        }

        function advanceSetup() {
            updateStatusDisplay(); // ステータスパネルを更新
            hideError();
            const nextStep = findNextStep();
            
            if (nextStep === 'complete') {
                showStep('complete');
                finalizeSetup();
            } else {
                showStep(nextStep);
            }
        }
        
        async function fetchApi(action, data = {}) {
            const body = JSON.stringify({ action, data });
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: body
            });
            return response.json();
        }

        // --- イベントリスナー ---

        // Step 1: 基本設定ファイル作成
        document.getElementById('btnCreateStorageData').addEventListener('click', async function() {
            setButtonLoading(this, true);
            statusElements['storage_data'].innerHTML = STATUS_ICONS.in_progress;
            
            const result = await fetchApi('create_storage_data_config');
            
            setButtonLoading(this, false);
            if (result.success) {
                setupStatus.storage_data = true;
                statusElements['storage_data'].innerHTML = STATUS_ICONS.completed;
                advanceSetup();
            } else {
                statusElements['storage_data'].innerHTML = STATUS_ICONS.error;
                showError(result.message);
            }
        });

        // Step 2: 拡張子設定 モーダル内の保存ボタン
        document.getElementById('formAcceptExtensions').addEventListener('submit', async function(e) {
            e.preventDefault();
            const button = document.getElementById('btnSaveAccept');
            setButtonLoading(button, true);
            statusElements['storage_accept'].innerHTML = STATUS_ICONS.in_progress;
            
            const formData = new FormData(this);
            const extensions = formData.getAll('extensions[]');
            const maxFileSize = parseInt(document.getElementById('maxFileSize').value);
            
            const result = await fetchApi('create_storage_accept_config', { 
                extensions, 
                max_file_size_mb: maxFileSize 
            });
            
            setButtonLoading(button, false);
            if (result.success) {
                acceptModal.hide();
                setupStatus.storage_accept = true;
                statusElements['storage_accept'].innerHTML = STATUS_ICONS.completed;
                advanceSetup();
            } else {
                statusElements['storage_accept'].innerHTML = STATUS_ICONS.error;
                showError(result.message);
            }
        });
        
        // Step 3: アカウント登録 モーダル内の登録ボタン
        document.getElementById('formRegisterAccount').addEventListener('submit', async function(e) {
            e.preventDefault();
            const button = document.getElementById('btnRegisterAccount');
            setButtonLoading(button, true);
            statusElements['account'].innerHTML = STATUS_ICONS.in_progress;
            
            const user = document.getElementById('modalUser').value;
            const password = document.getElementById('modalPassword').value;
            
            const result = await fetchApi('register_account', { user, password });
            
            setButtonLoading(button, false);
            if (result.success) {
                accountModal.hide();
                setupStatus.account = true;
                statusElements['account'].innerHTML = STATUS_ICONS.completed;
                // MFA登録のためにユーザー名を更新
                window.CURRENT_USER_EMAIL = user; 
                advanceSetup();
            } else {
                statusElements['account'].innerHTML = STATUS_ICONS.error;
                showError(result.message);
            }
        });

        // Step 4: MFAキー生成 モーダルを開く前にAPIをコール
        document.getElementById('btnOpenMfaModal').addEventListener('click', async function() {
            setButtonLoading(this, true);
            statusElements['mfa'].innerHTML = STATUS_ICONS.in_progress;
            hideError();
            
            // ユーザー名をAPIに渡し、QRコードURLを生成させる
            const userEmail = window.CURRENT_USER_EMAIL || document.getElementById('modalUser').value;
            const result = await fetchApi('generate_mfa_secret', { user: userEmail });
            
            setButtonLoading(this, false);
            if (result.success && result.secret) {
                // UIの更新
                document.getElementById('mfaSecretDisplay').textContent = result.secret;
                document.getElementById('mfaQrcodeImage').src = result.qr_code_url;
                
                // モーダル表示
                mfaModal.show();
            } else {
                statusElements['mfa'].innerHTML = STATUS_ICONS.error;
                showError(result.message);
            }
        });
        
        // Step 4: MFA登録完了 モーダル内の完了ボタン
        document.getElementById('btnMfaNext').addEventListener('click', function() {
            // MFAキーが画面に表示され、ユーザーが手動登録を終えたと仮定し、ステータスを更新して次へ
            setupStatus.mfa = true;
            statusElements['mfa'].innerHTML = STATUS_ICONS.completed;
            advanceSetup();
        });


        // Step 5: 最終処理（ログインクッキー発行）
        async function finalizeSetup() {
            const result = await fetchApi('finalize_setup');
            
            if (result.success) {
                btnLogin.disabled = false;
                btnLogin.textContent = 'ログイン画面へ進む';
                // 完了後、自動的にログイン画面に遷移
                setTimeout(() => {
                    window.location.href = 'login.php';
                }, 1000); 
            } else {
                showError('最終処理に失敗しました。:' + result.message);
            }
        }
        
        // --- 初期化処理 ---
        document.addEventListener('DOMContentLoaded', () => {
            // 初期状態のアイコン設定
            if (setupStatus.storage_data) {
                statusElements['storage_data'].innerHTML = STATUS_ICONS.completed;
            }
            if (setupStatus.storage_accept) {
                statusElements['storage_accept'].innerHTML = STATUS_ICONS.completed;
            }
            if (setupStatus.account) {
                statusElements['account'].innerHTML = STATUS_ICONS.completed;
            }
            if (setupStatus.mfa) {
                statusElements['mfa'].innerHTML = STATUS_ICONS.completed;
            }
            
            advanceSetup();
        });
    </script>
</body>

</html>