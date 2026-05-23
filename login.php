<?php
session_start();
require_once 'config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') header("Location: admin.php");
    else header("Location: member.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim(mysqli_real_escape_string($conn, $_POST['username']));
    $password = trim(mysqli_real_escape_string($conn, $_POST['password']));

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        $sql = "SELECT * FROM users WHERE username = '$username' AND password = '$password'";
        $result = mysqli_query($conn, $sql);

        if ($result && mysqli_num_rows($result) === 1) {
            $user = mysqli_fetch_assoc($result);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['name']      = $user['name'];
            $_SESSION['role']      = $user['role'];

            if ($user['role'] === 'admin') header("Location: admin.php");
            else header("Location: member.php");
            exit();
        } else {
            $error = "Invalid username or password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – Online Library Management</title>
    <style>
        <?= COMMON_STYLE ?>

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .login-wrapper {
            display: flex;
            width: 900px;
            max-width: 95vw;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(0,0,0,0.3);
        }

        .login-left {
            flex: 1;
            background: linear-gradient(160deg, #4A6CF7 0%, #7B5EA7 100%);
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
        }

        .login-left .logo-ring {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 28px;
            border: 3px solid rgba(255,255,255,0.3);
        }

        .login-left .logo-ring img {
            width: 85px;
            height: 85px;
            object-fit: contain;
            border-radius: 50%;
        }

        .login-left h1 {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        .login-left p {
            font-size: 14px;
            opacity: 0.8;
            line-height: 1.7;
        }

        .divider-line {
            width: 50px;
            height: 3px;
            background: rgba(255,255,255,0.5);
            border-radius: 2px;
            margin: 18px auto;
        }

        .feature-list {
            list-style: none;
            margin-top: 24px;
            text-align: left;
        }

        .feature-list li {
            font-size: 13px;
            padding: 6px 0;
            opacity: 0.9;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .feature-list li::before {
            content: "✦";
            font-size: 10px;
            color: #fbbf24;
        }

        .login-right {
            flex: 1;
            background: white;
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-right h2 {
            font-size: 28px;
            color: var(--text);
            margin-bottom: 6px;
        }

        .login-right .subtitle {
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 32px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 700;
            font-size: 13px;
            color: var(--text);
            margin-bottom: 7px;
            font-family: "Nunito", sans-serif;
        }

        .form-group input {
            width: 100%;
            padding: 13px 16px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 15px;
            font-family: "Lato", sans-serif;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }

        .form-group input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(74,108,247,0.15);
        }

        .login-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #4A6CF7, #7B5EA7);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-family: "Nunito", sans-serif;
            font-weight: 800;
            cursor: pointer;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            margin-top: 8px;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(74,108,247,0.4);
        }

        .forgot-link {
            text-align: center;
            margin-top: 18px;
        }

        .forgot-link a {
            color: var(--primary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
        }

        .forgot-link a:hover { text-decoration: underline; }

        .default-creds {
            background: var(--primary-light);
            border-radius: 10px;
            padding: 12px 16px;
            margin-top: 24px;
            font-size: 13px;
            color: var(--text-muted);
            border: 1px dashed var(--primary);
        }

        .default-creds strong { color: var(--primary); }

        @media (max-width: 768px) {
            .login-left { display: none; }
            .login-right { padding: 40px 30px; }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <!-- Left Panel -->
        <div class="login-left">
            <div class="logo-ring">
                <img src="logo.jpeg" alt="Logo" onerror="this.style.display='none';this.parentElement.innerHTML='📚';">
            </div>
            <h1>ONLINE LIBRARY<br>MANAGEMENT</h1>
            <div class="divider-line"></div>
            <p>Your complete digital library solution for managing books, members, and resources efficiently.</p>
            <ul class="feature-list">
                <li>Manage Books & Inventory</li>
                <li>Issue & Return Tracking</li>
                <li>Member Management</li>
                <li>Admin & Member Roles</li>
            </ul>
        </div>

        <!-- Right Panel -->
        <div class="login-right">
            <h2>Welcome Back 👋</h2>
            <p class="subtitle">Sign in to access your library portal</p>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Enter your username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>
                <button type="submit" class="login-btn">Sign In →</button>
            </form>

            <div class="forgot-link">
                <a href="forgot_password.php">Forgot Password?</a>
            </div>

                   </div>
    </div>
</body>
</html>
