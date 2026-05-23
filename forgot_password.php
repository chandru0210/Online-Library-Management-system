<?php
session_start();
require_once 'config.php';

$error   = '';
$success = '';
$step    = 1; // 1 = enter username, 2 = reset password
$user    = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['find_user'])) {
        $username = trim(mysqli_real_escape_string($conn, $_POST['username']));
        if (empty($username)) {
            $error = "Please enter your username.";
        } else {
            $sql = "SELECT * FROM users WHERE username = '$username'";
            $result = mysqli_query($conn, $sql);
            if ($result && mysqli_num_rows($result) === 1) {
                $user = mysqli_fetch_assoc($result);
                $step = 2;
            } else {
                $error = "No account found with that username.";
            }
        }
    } elseif (isset($_POST['reset_password'])) {
        $uid   = (int)$_POST['user_id'];
        $pass  = trim($_POST['new_password']);
        $cpass = trim($_POST['confirm_password']);

        if (empty($pass) || empty($cpass)) {
            $error = "Please fill in both password fields.";
            $step  = 2;
            $user  = ['id' => $uid, 'name' => $_POST['user_name']];
        } elseif ($pass !== $cpass) {
            $error = "Passwords do not match.";
            $step  = 2;
            $user  = ['id' => $uid, 'name' => $_POST['user_name']];
        } elseif (strlen($pass) < 4) {
            $error = "Password must be at least 4 characters.";
            $step  = 2;
            $user  = ['id' => $uid, 'name' => $_POST['user_name']];
        } else {
            $safe_pass = mysqli_real_escape_string($conn, $pass);
            $upd = "UPDATE users SET password = '$safe_pass' WHERE id = $uid";
            if (mysqli_query($conn, $upd)) {
                $success = "Password reset successfully! You can now <a href='login.php'>login</a>.";
            } else {
                $error = "Something went wrong. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password – Library System</title>
    <style>
        <?= COMMON_STYLE ?>

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .card {
            background: white;
            border-radius: 20px;
            padding: 50px 45px;
            width: 440px;
            max-width: 95vw;
            box-shadow: 0 25px 60px rgba(0,0,0,0.3);
        }

        .card-icon {
            width: 64px;
            height: 64px;
            background: var(--primary-light);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 20px;
        }

        .card h2 { font-size: 24px; margin-bottom: 8px; }
        .card p.sub { color: var(--text-muted); font-size: 14px; margin-bottom: 28px; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 700; font-size: 13px; margin-bottom: 7px; font-family: "Nunito", sans-serif; }
        .form-group input {
            width: 100%;
            padding: 13px 16px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 15px;
            font-family: "Lato", sans-serif;
            outline: none;
            transition: border-color 0.2s;
        }
        .form-group input:focus { border-color: var(--primary); }

        .submit-btn {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #4A6CF7, #7B5EA7);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-family: "Nunito", sans-serif;
            font-weight: 800;
            cursor: pointer;
        }
        .submit-btn:hover { opacity: 0.9; }

        .back-link { text-align: center; margin-top: 16px; font-size: 14px; }
        .back-link a { color: var(--primary); font-weight: 700; text-decoration: none; }

        .user-info {
            background: var(--primary-light);
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 14px;
            color: var(--primary-dark);
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-icon">🔑</div>

        <?php if ($success): ?>
            <h2>Success!</h2>
            <p class="sub">Your password has been updated.</p>
            <div class="alert alert-success"><?= $success ?></div>
        <?php elseif ($step === 1): ?>
            <h2>Forgot Password?</h2>
            <p class="sub">Enter your username to reset your password.</p>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" placeholder="Enter your username" required autofocus>
                </div>
                <button type="submit" name="find_user" class="submit-btn">Find Account</button>
            </form>

        <?php elseif ($step === 2): ?>
            <h2>Reset Password</h2>
            <p class="sub">Create a new password for your account.</p>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="user-info">
                Account: <strong><?= htmlspecialchars($user['name']) ?></strong>
            </div>

            <form method="POST">
                <input type="hidden" name="user_id"   value="<?= $user['id'] ?>">
                <input type="hidden" name="user_name" value="<?= htmlspecialchars($user['name']) ?>">
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" placeholder="Enter new password" required>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" placeholder="Confirm new password" required>
                </div>
                <button type="submit" name="reset_password" class="submit-btn">Reset Password</button>
            </form>
        <?php endif; ?>

        <div class="back-link">
            <a href="login.php">← Back to Login</a>
        </div>
    </div>
</body>
</html>
