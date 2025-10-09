<?php
define('ONESTORAGE_RUNNING', true);
require_once __DIR__ . '/path.php';
require_once __DIR__ . '/functions/helpers.php';
require_once __DIR__ . '/functions/cookie.php';
require_once __DIR__ . '/functions/mfa.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['auth_passed']) || $_SESSION['auth_passed'] !== true) {
    redirect('login.php');
}

$mfa_secret = get_mfa_secret();
if (empty($mfa_secret)) {
    $auth_config = require AUTH_CONFIG_PATH;
    issue_auth_cookie($auth_config['user']);
    unset($_SESSION['auth_passed']);
    redirect('index.php');
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mfa_code = $_POST['mfa_code'] ?? '';

    if (empty($mfa_code)) {
        $error_message = '認証コードを入力してください。';
    } elseif (!preg_match('/^\d{6}$/', $mfa_code)) {
        $error_message = '認証コードは6桁の数字です。';
    } else {
        if (verify_mfa_code($mfa_secret, $mfa_code)) {
            $auth_config = require AUTH_CONFIG_PATH;
            issue_auth_cookie($auth_config['user']);
            unset($_SESSION['auth_passed']);
            redirect('index.php');
        } else {
            $error_message = '認証コードが正しくありません。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>二段階認証</title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .otp-inputs {
            gap: 5px;
            max-width: 300px;
            margin: 0 auto;
        }

        .otp-input {
            width: 45px;
            height: 55px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: bold;
            border: 2px solid #ced4da;
            border-radius: 6px;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .otp-input:focus {
            border-color: #86b7fe;
            outline: 0;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }

        .otp-separator {
            display: flex;
            align-items: center;
            font-size: 1.5rem;
            font-weight: 300;
            color: #6c757d;
            padding: 0 5px;
        }
    </style>
</head>

<body class="bg-light">

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7 col-sm-9">
                <div class="p-4 border rounded-3 bg-white shadow-sm">

                    <p class="h3 py-5 text-center">二段階認証コードの入力</p>
                    <p class="text-center text-muted">認証アプリに表示されている6桁のコードを入力してください。</p>
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <form action="mfa_login.php" method="POST" id="mfaForm">
                        <div class="mb-5">
                            <div class="d-flex justify-content-center otp-inputs">
                                <input type="text" class="otp-input" maxlength="1" pattern="\d" inputmode="numeric" required autofocus data-index="0">
                                <input type="text" class="otp-input" maxlength="1" pattern="\d" inputmode="numeric" required data-index="1">
                                <input type="text" class="otp-input" maxlength="1" pattern="\d" inputmode="numeric" required data-index="2">
                                <input type="text" class="otp-input" maxlength="1" pattern="\d" inputmode="numeric" required data-index="3">
                                <input type="text" class="otp-input" maxlength="1" pattern="\d" inputmode="numeric" required data-index="4">
                                <input type="text" class="otp-input" maxlength="1" pattern="\d" inputmode="numeric" required data-index="5">
                                <input type="hidden" name="mfa_code" id="mfa_code_combined">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100" id="submitBtn">認証</button>
                    </form>

                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const inputs = document.querySelectorAll('.otp-input');
        const combinedInput = document.getElementById('mfa_code_combined');
        const form = document.getElementById('mfaForm');
        const submitBtn = document.getElementById('submitBtn');

        function tryAutoSubmit() {
            const code = Array.from(inputs).map(input => input.value).join('');
            if (code.length === 6 && /^\d{6}$/.test(code)) {
                combinedInput.value = code;
                form.submit();
            }
        }
        
        inputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                if (e.data && /^\d$/.test(e.data) && input.value.length === 1) {
                    if (index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    } else {
                        tryAutoSubmit();
                    }
                }
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && input.value.length === 0 && index > 0) {
                    inputs[index - 1].focus();
                }
            });

            input.addEventListener('paste', (e) => {
                const pasteData = (e.clipboardData || window.clipboardData).getData('text');
                if (/^\d{6}$/.test(pasteData)) {
                    e.preventDefault();
                    pasteData.split('').forEach((char, i) => {
                        if (inputs[i]) {
                            inputs[i].value = char;
                        }
                    });
                    tryAutoSubmit();
                }
            });
        });

        form.addEventListener('submit', (e) => {
            const code = Array.from(inputs).map(input => input.value).join('');
            if (code.length !== 6 || !/^\d{6}$/.test(code)) {
                e.preventDefault();
                alert('認証コードを6桁すべて入力してください。');
            } else {
                combinedInput.value = code;
            }
        });
    });
</script>
</body>

</html>
</body>

</html>