<?php
define('ONESTORAGE_RUNNING', true);
require_once __DIR__ . '/path.php';
require_once __DIR__ . '/functions/helpers.php';
require_once __DIR__ . '/functions/cookie.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!file_exists(AUTH_CONFIG_PATH) || !file_exists(MFA_SECRET_PATH)) {
    redirect('setting.php');
}
if (validate_auth_cookie()) {
    redirect('index.php');
}

$error_message = '';

// バリデーション
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_input = $_POST['user'] ?? '';
    $password_input = $_POST['password'] ?? '';

    if (empty($user_input) || empty($password_input)) {
        $error_message = 'メールアドレスとパスワードを入力してください。';
    } else {
        $config = require AUTH_CONFIG_PATH;
        if ($user_input === $config['user'] && password_verify($password_input, $config['hash'])) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['auth_passed'] = true;
            redirect('mfa_login.php');
        } else {
            $error_message = 'メールアドレスまたはパスワードが間違っています。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン</title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    </style>
</head>

<body class="bg-light">

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7 col-sm-9">
                <div class="p-4 border rounded-3 bg-white shadow-sm">
                    <div class="col-4 mx-auto mb-3">
                        <img class="img-fluid" src="static/img_logo.PNG" alt="">
                    </div>
                    <p class="text-center text-muted h6 ">ユーザーログイン</p>
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <form action="login.php" method="POST">
                        <div class="py-3">
                            <div class="form-floating mb-3">
                                <input type="email" class="form-control" name="user" id="floatingInput" placeholder="name@example.com" required>
                                <label for="floatingInput">メールアドレス</label>
                            </div>
                            <div class="form-floating">
                                <input type="password" class="form-control" name="password" id="floatingPassword" placeholder="Password">
                                <label for="floatingPassword">パスワード</label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">ログイン</button>
                    </form>
                    <div>
                        <p class="text-muted pt-3">
                            <sapn class="fw-bold">パスワードを忘れた場合</sapn></br>
                            契約しているサーバーからauth.phpだけを削除してください。</br>
                            ストレージを維持したまま、安全に再設定することができます。
                        </p>
                    </div>

                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>