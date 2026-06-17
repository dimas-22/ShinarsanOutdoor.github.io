<?php
require_once 'auth.php';
require_once '../config/database.php';
$db = getDB();

// Statistik Dashboard
$stat_alat     = $db->query("SELECT COUNT(*) as c FROM alat WHERE status='aktif'")->fetch_assoc()['c'];
$stat_stok     = $db->query("SELECT SUM(stok) as c FROM alat WHERE status='aktif'")->fetch_assoc()['c'] ?? 0;
$stat_menunggu = $db->query("SELECT COUNT(*) as c FROM peminjaman WHERE status='menunggu'")->fetch_assoc()['c'];
$stat_dipinjam = $db->query("SELECT COUNT(*) as c FROM peminjaman WHERE status IN ('disetujui','dipinjam')")->fetch_assoc()['c'];
$stat_selesai  = $db->query("SELECT COUNT(*) as c FROM peminjaman WHERE status='selesai'")->fetch_assoc()['c'];
$stat_total    = $db->query("SELECT COUNT(*) as c FROM peminjaman")->fetch_assoc()['c'];
$stat_pendapatan = $db->query("SELECT COALESCE(SUM(total_biaya),0) as c FROM peminjaman WHERE status='selesai'")->fetch_assoc()['c'];

// Peminjaman terbaru (10 terakhir)
$recent_result = $db->query("
    SELECT p.*, a.nama_alat, a.kategori, a.harga_sewa
    FROM peminjaman p
    JOIN alat a ON p.id_alat = a.id_alat
    ORDER BY p.created_at DESC LIMIT 10
");
$recent_list = $recent_result ? $recent_result->fetch_all(MYSQLI_ASSOC) : [];

// Alat dengan stok menipis (stok <= 2)
$low_stock = $db->query("SELECT * FROM alat WHERE stok <= 2 AND status='aktif' ORDER BY stok ASC");
$low_stock_list = $low_stock ? $low_stock->fetch_all(MYSQLI_ASSOC) : [];

$badge_class = [
    'menunggu'  => 'badge-menunggu',
    'disetujui' => 'badge-disetujui',
    'dipinjam'  => 'badge-dipinjam',
    'selesai'   => 'badge-selesai',
    'ditolak'   => 'badge-ditolak',
];
$badge_label = [
    'menunggu'  => '⏳ Menunggu',
    'disetujui' => '✅ Disetujui',
    'dipinjam'  => '🎒 Dipinjam',
    'selesai'   => '🏁 Selesai',
    'ditolak'   => '❌ Ditolak',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - ShinarsanOutdoor</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="admin-layout">

    <!-- =================== SIDEBAR =================== -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- =================== CONTENT =================== -->
    <div class="admin-content">
        <!-- Topbar -->
        <div class="admin-topbar">
            <div style="display:flex;align-items:center;gap:16px;">
                <button class="sidebar-toggle" style="background:none;border:none;cursor:pointer;font-size:1.3rem;color:var(--text-primary);">☰</button>
                <div class="topbar-title">📊 Dashboard</div>
            </div>
            <div class="topbar-right">
                <?php if ($stat_menunggu > 0): ?>
                <a href="peminjaman.php?status=menunggu" style="background:rgba(245,158,11,0.15);border:1px solid rgba(245,158,11,0.3);color:#fcd34d;padding:6px 14px;border-radius:20px;font-size:0.8rem;font-weight:600;">
                    ⏳ <?= $stat_menunggu ?> Menunggu Konfirmasi
                </a>
                <?php endif; ?>
                <div class="admin-user">
                    <div class="admin-avatar"><?= strtoupper(substr($_SESSION['admin_name'], 0, 1)) ?></div>
                    <span><?= htmlspecialchars($_SESSION['admin_name']) ?></span>
                </div>
            </div>
        </div>

        <div class="admin-main">
            <!-- Welcome -->
            <div style="margin-bottom:28px;">
                <h2 style="font-family:var(--font-heading);font-size:1.5rem;font-weight:800;">
                    Selamat datang, <?= htmlspecialchars($_SESSION['admin_name']) ?>! 👋
                </h2>
                <p style="color:var(--text-muted);font-size:0.9rem;">
                    <?= date('l, d F Y') ?> — Berikut ringkasan sistem peminjaman hari ini.
                </p>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon green">🎒</div>
                    <div class="stat-info">
                        <div class="stat-value"><?= $stat_alat ?></div>
                        <div class="stat-label">Jenis Alat Aktif</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon yellow">⏳</div>
                    <div class="stat-info">
                        <div class="stat-value"><?= $stat_menunggu ?></div>
                        <div class="stat-label">Menunggu Konfirmasi</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon blue">🏕️</div>
                    <div class="stat-info">
                        <div class="stat-value"><?= $stat_dipinjam ?></div>
                        <div class="stat-label">Sedang Dipinjam</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">🏁</div>
                    <div class="stat-info">
                        <div class="stat-value"><?= $stat_selesai ?></div>
                        <div class="stat-label">Selesai Dikembalikan</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon yellow">💰</div>
                    <div class="stat-info">
                        <div class="stat-value" style="font-size:1.2rem;"><?= formatRupiah($stat_pendapatan) ?></div>
                        <div class="stat-label">Total Pendapatan</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon blue">📦</div>
                    <div class="stat-info">
                        <div class="stat-value"><?= $stat_stok ?></div>
                        <div class="stat-label">Total Unit Stok</div>
                    </div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;align-items:start;">

                <!-- Peminjaman Terbaru -->
                <div class="data-card">
                    <div class="data-card-header">
                        <div class="data-card-title">📋 Peminjaman Terbaru</div>
                        <a href="peminjaman.php" class="btn btn-sm" style="background:rgba(26,122,74,0.15);color:var(--text-secondary);border:1px solid var(--card-border);">
                            Lihat Semua →
                        </a>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Peminjam</th>
                                    <th>Alat</th>
                                    <th>Tgl Pinjam</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_list)): ?>
                                <tr><td colspan="6" class="text-center" style="padding:40px;color:var(--text-muted);">Belum ada peminjaman</td></tr>
                                <?php else: ?>
                                <?php foreach ($recent_list as $pm): ?>
                                <tr>
                                    <td><span style="font-family:monospace;color:var(--text-muted);font-size:0.8rem;">#<?= str_pad($pm['id_pinjam'],4,'0',STR_PAD_LEFT) ?></span></td>
                                    <td>
                                        <div style="font-weight:600;font-size:0.9rem;"><?= htmlspecialchars($pm['nama_peminjam']) ?></div>
                                        <div style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($pm['no_hp']) ?></div>
                                    </td>
                                    <td style="font-size:0.85rem;"><?= htmlspecialchars($pm['nama_alat']) ?></td>
                                    <td style="font-size:0.8rem;color:var(--text-muted);"><?= formatTanggal($pm['tanggal_pinjam']) ?></td>
                                    <td><span class="badge <?= $badge_class[$pm['status']] ?? 'badge-menunggu' ?>"><?= $badge_label[$pm['status']] ?? $pm['status'] ?></span></td>
                                    <td>
                                        <a href="peminjaman.php?detail=<?= $pm['id_pinjam'] ?>" class="btn btn-sm" style="background:rgba(59,130,246,0.15);color:#93c5fd;border:1px solid rgba(59,130,246,0.3);">Detail</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Stok Menipis -->
                <div class="data-card">
                    <div class="data-card-header">
                        <div class="data-card-title">⚠️ Stok Menipis</div>
                        <a href="alat.php" class="btn btn-sm" style="background:rgba(245,158,11,0.15);color:#fcd34d;border:1px solid rgba(245,158,11,0.3);">Kelola</a>
                    </div>
                    <?php if (empty($low_stock_list)): ?>
                    <div class="empty-state" style="padding:40px 20px;">
                        <div class="empty-icon">✅</div>
                        <div class="empty-title">Semua stok aman!</div>
                    </div>
                    <?php else: ?>
                    <div style="padding:8px 0;">
                        <?php foreach ($low_stock_list as $a): ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 20px;border-bottom:1px solid var(--card-border);">
                            <div>
                                <div style="font-size:0.9rem;font-weight:600;"><?= htmlspecialchars($a['nama_alat']) ?></div>
                                <div style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($a['kategori']) ?></div>
                            </div>
                            <span style="background:<?= $a['stok']==0 ? 'rgba(239,68,68,0.15)' : 'rgba(245,158,11,0.15)' ?>;color:<?= $a['stok']==0 ? '#fca5a5' : '#fcd34d' ?>;border:1px solid <?= $a['stok']==0 ? 'rgba(239,68,68,0.3)' : 'rgba(245,158,11,0.3)' ?>;padding:4px 12px;border-radius:20px;font-size:0.8rem;font-weight:700;">
                                <?= $a['stok'] == 0 ? 'Habis' : $a['stok'] . ' unit' ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

            </div><!-- end grid -->

        </div><!-- admin-main -->
    </div><!-- admin-content -->
</div>

<script src="../assets/js/main.js"></script>
</body>
</html>
