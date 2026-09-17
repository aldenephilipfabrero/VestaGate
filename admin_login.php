<?php
session_start();
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = trim((string)($_POST['password'] ?? ''));

    if ($username === ADMIN_USERNAME && adminPasswordMatches($password)) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        header('Location: admin_dashboard.php');
        exit;
    }

    $loginError = 'Invalid username or password.';
} else {
    $loginError = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .login-card {
            width: min(420px, 90vw);
            background: rgba(0,0,0,0.4);
            border: 1px solid rgba(212,175,55,0.5);
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.35);
            color: white;
        }

        .login-card h1 {
            color: #D4AF37;
            text-align: center;
            margin-bottom: 25px;
            font-size: 1.8rem;
        }

        .form-label {
            color: #ddd;
            font-size: 0.9rem;
        }

        .form-control {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
        }

        .form-control::placeholder {
            color: #bcbcbc;
        }

        .btn-primary {
            width: 100%;
            background: #8B1538;
            border: none;
            margin-top: 10px;
        }

        .btn-primary:hover {
            background: #6f102d;
        }

        .error-box {
            background: rgba(220, 53, 69, 0.15);
            border: 1px solid rgba(220, 53, 69, 0.4);
            color: #ffb3ba;
            border-radius: 8px;
            padding: 10px 12px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <h1>Admin Login</h1>

        <?php if ($loginError !== ''): ?>
            <div class="error-box"><?php echo htmlspecialchars($loginError); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" placeholder="Enter admin username" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" placeholder="Enter admin password" required>
            </div>
            <button type="submit" class="btn btn-primary">Login</button>
        </form>

        <div class="mt-3 text-center">
            <a href="gate_scanner.php" class="btn btn-outline-light w-100">Back to Gate Scanner</a>
        </div>
    </div>
</body>
</html>
