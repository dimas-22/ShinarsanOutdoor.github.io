<?php
session_start();
require_once '../config/database.php';

// Check if already logged in
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$alert = '';
$error = '';

// Logout message
if (isset($_GET['msg']) && $_GET['msg'] === 'logout') {
    $alert = '<div class="alert alert-success" data-auto-close>👋 Berhasil keluar dari admin panel.</div>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $db = getDB();

    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi!';
    } else {
        $u = $db->real_escape_string($username);
        $result = $db->query("SELECT * FROM admin WHERE username='$u' LIMIT 1");
        $admin = $result ? $result->fetch_assoc() : null;

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['nama_lengkap'] ?? $admin['username'];
            $_SESSION['admin_username'] = $admin['username'];
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Username atau password salah! Silakan coba lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - ShinarsanOutdoor</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="login-page">
    <div class="login-card animate-fade-up">
        <div class="login-logo">
            <div class="logo-icon"><img src="../assets/images/logo.jpeg" alt="Shinarsan Outdoor"></div>
            <h1 class="login-title">Shinarsan Outdoor</h1>
            <p class="login-subtitle">
                Sistem Peminjaman & Manajemen Alat Outdoor
            </p>
        </div>

        <?php if ($alert): ?>
        <?= $alert ?>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-danger">🔒 <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control"
                    placeholder="Masukkan username" required autocomplete="username"
                    value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div style="position:relative;">
                    <input type="password" id="password" name="password" class="form-control"
                        placeholder="Masukkan password" required autocomplete="current-password"
                        style="padding-right:48px;">
                    <button type="button" id="toggle-pass" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:1.1rem;">
                        👁
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:8px;">
                🔐 Masuk ke Admin Panel
            </button>
        </form>


    </div>
</div>

<script>
document.getElementById('toggle-pass').addEventListener('click', function() {
    const p = document.getElementById('password');
    const isPass = p.type === 'password';
    p.type = isPass ? 'text' : 'password';
    this.textContent = isPass ? '🙈' : '👁';
});
</script>
</body>
</html>
