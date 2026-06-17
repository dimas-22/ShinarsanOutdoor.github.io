<?php
// Sidebar include - used in all admin pages
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="dashboard.php" class="nav-logo" style="font-family:var(--font-heading);font-size:1.2rem;font-weight:800;display:flex;align-items:center;gap:10px;">
            <div class="logo-icon" style="width:36px;height:36px;font-size:1.1rem;"><img src="../assets/images/logo.jpeg" alt="Shinarsan Outdoor"></div>
            <div>Shinarsan<span style="color:var(--secondary)">Outdoor</span></div>
        </a>
        <div style="font-size:0.7rem;color:var(--text-muted);margin-top:4px;margin-left:46px;">Admin Panel</div>
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-label">Menu Utama</div>
        <a href="dashboard.php" class="<?= $current_page=='dashboard.php' ? 'active' : '' ?>">
            <span class="nav-icon">📊</span> Dashboard
        </a>
        <a href="peminjaman.php" class="<?= $current_page=='peminjaman.php' ? 'active' : '' ?>">
            <span class="nav-icon">📋</span> Data Peminjaman
        </a>
        <a href="alat.php" class="<?= $current_page=='alat.php' ? 'active' : '' ?>">
            <span class="nav-icon">🎒</span> Kelola Alat
        </a>

        <div class="sidebar-label" style="margin-top:16px;">Akun</div>
        <a href="../index.php" target="_blank">
            <span class="nav-icon">🌐</span> Lihat Website
        </a>
        <a href="logout.php" class="logout-link">
            <span class="nav-icon">🚪</span> Keluar
        </a>
    </nav>

    <div style="position:absolute;bottom:20px;left:0;right:0;padding:0 20px;">
        <div style="background:rgba(26,122,74,0.1);border:1px solid var(--card-border);border-radius:var(--radius);padding:12px;">
            <div style="font-size:0.75rem;color:var(--text-muted);">Logged in as</div>
            <div style="font-size:0.9rem;font-weight:600;margin-top:2px;"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></div>
        </div>
    </div>
</aside>
