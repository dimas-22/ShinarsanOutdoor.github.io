<?php
session_start();
require_once 'config/database.php';
$db = getDB();

$result = null;
$searched = false;
$id_cari = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['id'])) {
    $id_input = isset($_POST['id_pinjam']) ? trim($_POST['id_pinjam']) : (isset($_GET['id']) ? trim($_GET['id']) : '');
    $id_pinjam = intval(ltrim($id_input, '#0'));
    $id_cari = $id_input;

    if ($id_pinjam > 0) {
        $searched = true;
        $res = $db->query("
            SELECT p.*, a.nama_alat, a.kategori, a.harga_sewa
            FROM peminjaman p
            JOIN alat a ON p.id_alat = a.id_alat
            WHERE p.id_pinjam = $id_pinjam
        ");
        $result = $res ? $res->fetch_assoc() : null;
    }
}

$badge_class = [
    'menunggu'  => 'badge-menunggu',
    'disetujui' => 'badge-disetujui',
    'dipinjam'  => 'badge-dipinjam',
    'selesai'   => 'badge-selesai',
    'ditolak'   => 'badge-ditolak',
];
$badge_label = [
    'menunggu'  => '⏳ Menunggu Konfirmasi',
    'disetujui' => '✅ Disetujui',
    'dipinjam'  => '🎒 Sedang Dipinjam',
    'selesai'   => '🏁 Selesai / Dikembalikan',
    'ditolak'   => '❌ Ditolak',
];
$status_desc = [
    'menunggu'  => 'Pengajuanmu sedang ditinjau oleh admin. Harap tunggu konfirmasi dalam 1×24 jam pada hari kerja.',
    'disetujui' => 'Pengajuanmu telah disetujui! Silakan hubungi kami untuk mengambil alat sesuai jadwal.',
    'dipinjam'  => 'Alat sedang dalam masa peminjamanmu. Jangan lupa kembalikan tepat waktu!',
    'selesai'   => 'Peminjaman telah selesai. Terima kasih sudah menggunakan layanan SepakatOutdoor!',
    'ditolak'   => 'Maaf, pengajuanmu tidak dapat diproses. Silakan hubungi kami untuk informasi lebih lanjut.',
];
$status_icon = [
    'menunggu'  => '⏳', 'disetujui' => '✅', 'dipinjam' => '🎒',
    'selesai'   => '🏁', 'ditolak'   => '❌',
];
$kategori_icon = [
    'Tenda'=>'⛺','Carrier'=>'🎒','Sleeping Bag'=>'🛌',
    'Masak'=>'🍳','Tidur'=>'😴','Aksesoris'=>'🔦','Pakaian'=>'🧥'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Cek status peminjaman alat gunung SepakatOutdoor. Masukkan ID peminjaman untuk melihat status terkini.">
    <title>Cek Status Peminjaman - SepakatOutdoor</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .status-page {
            min-height: 100vh;
            padding: 100px 24px 60px;
            background: var(--bg-dark);
        }
        .status-container { max-width: 680px; margin: 0 auto; }
        .status-header { text-align: center; margin-bottom: 40px; }

        /* Search Box */
        .search-form-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-lg);
            padding: 32px;
            margin-bottom: 32px;
            box-shadow: var(--shadow);
        }
        .id-input-group {
            display: flex;
            gap: 12px;
        }
        .id-input-prefix {
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(26,122,74,0.15);
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            padding: 12px 16px;
            font-weight: 700;
            color: var(--text-secondary);
            font-size: 1rem;
            white-space: nowrap;
        }

        /* Result Card */
        .result-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            animation: fadeInUp 0.6s ease;
        }
        .result-header {
            padding: 24px 28px;
            border-bottom: 1px solid var(--card-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }
        .result-id {
            font-family: monospace;
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        .result-body { padding: 28px; }
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            font-size: 0.92rem;
        }
        .info-row:last-child { border: none; }
        .info-label { color: var(--text-muted); }
        .info-value { font-weight: 600; text-align: right; }

        /* Status Timeline */
        .status-timeline {
            display: flex;
            justify-content: space-between;
            margin: 28px 0;
            position: relative;
        }
        .status-timeline::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 32px;
            right: 32px;
            height: 2px;
            background: var(--card-border);
            z-index: 0;
        }
        .timeline-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            z-index: 1;
            flex: 1;
        }
        .timeline-dot {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--card-border);
            border: 2px solid var(--card-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            transition: var(--transition);
        }
        .timeline-dot.active {
            background: var(--primary);
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(26,122,74,0.25);
        }
        .timeline-dot.done {
            background: rgba(16,185,129,0.2);
            border-color: var(--success);
        }
        .timeline-dot.rejected {
            background: rgba(239,68,68,0.2);
            border-color: var(--danger);
        }
        .timeline-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            text-align: center;
            font-weight: 500;
        }
        .timeline-label.active { color: var(--primary-light); }

        /* Total Box */
        .total-box {
            background: rgba(26,122,74,0.1);
            border: 1px solid rgba(26,122,74,0.3);
            border-radius: var(--radius);
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 20px 0 0;
        }
        .total-label { color: var(--text-muted); font-size: 0.9rem; }
        .total-amount {
            font-family: var(--font-heading);
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--secondary);
        }

        /* Not found */
        .not-found {
            background: var(--card-bg);
            border: 1px solid rgba(239,68,68,0.3);
            border-radius: var(--radius-lg);
            padding: 48px;
            text-align: center;
            animation: fadeInUp 0.5s ease;
        }
        .not-found-icon { font-size: 3.5rem; margin-bottom: 16px; }

        /* Tips */
        .tips-card {
            background: rgba(59,130,246,0.05);
            border: 1px solid rgba(59,130,246,0.2);
            border-radius: var(--radius);
            padding: 16px 20px;
            margin-top: 24px;
        }
        .tips-title { font-size: 0.85rem; font-weight: 600; color: #93c5fd; margin-bottom: 8px; }
        .tips-list { list-style: disc; padding-left: 18px; font-size: 0.82rem; color: var(--text-muted); line-height: 1.8; }
    </style>
</head>
<body>
<!-- NAVBAR -->
<nav class="navbar scrolled">
    <div class="nav-container">
        <a href="index.php" class="nav-logo">
            <div class="logo-icon"><img src="assets/images/logo.jpeg" alt="Shinarsan Outdoor"></div>
            <div>Shinarsan<span>Outdoor</span></div>
        </a>
        <button class="hamburger" id="hamburger">☰</button>
        <div class="nav-links" id="nav-links">
            <a href="index.php">Beranda</a>
            <a href="index.php#katalog">Katalog Alat</a>
            <a href="peminjaman.php">Ajukan Sewa</a>
            <a href="cek_status.php" class="active">Cek Status</a>
            <a href="admin/login.php" class="btn-admin">🔐 Admin</a>
        </div>
    </div>
</nav>

<div class="status-page">
    <div class="status-container">

        <!-- Header -->
        <div class="status-header">
            <div class="section-tag">🔍 Lacak Peminjaman</div>
            <h1 style="font-family:var(--font-heading);font-size:2rem;font-weight:800;margin:12px 0 8px;">
                Cek Status Peminjaman
            </h1>
            <p style="color:var(--text-muted);">Masukkan nomor ID peminjaman yang kamu terima setelah mengajukan sewa</p>
        </div>

        <!-- Search Form -->
        <div class="search-form-card">
            <form method="POST" action="cek_status.php">
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">Nomor ID Peminjaman</label>
                    <div class="id-input-group">
                        <div class="id-input-prefix">#</div>
                        <input type="text" name="id_pinjam" class="form-control"
                            placeholder="Contoh: 0001 atau 1"
                            value="<?= htmlspecialchars($id_cari) ?>"
                            style="font-size:1.1rem;font-family:monospace;letter-spacing:2px;"
                            autofocus required>
                    </div>
                    <div class="form-hint" style="margin-top: 6px;">
                        💡 ID peminjaman tercantum dalam konfirmasi saat kamu mengajukan sewa
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block" id="btn-cek">
                    🔍 Cek Status Sekarang
                </button>
            </form>
        </div>

        <!-- Results -->
        <?php if ($searched): ?>

            <?php if ($result): ?>
            <!-- FOUND -->
            <div class="result-card">
                <div class="result-header">
                    <div>
                        <div style="font-family:var(--font-heading);font-size:1.15rem;font-weight:800;">
                            <?= htmlspecialchars($result['nama_peminjam']) ?>
                        </div>
                        <div class="result-id">ID Peminjaman: #<?= str_pad($result['id_pinjam'], 4, '0', STR_PAD_LEFT) ?></div>
                    </div>
                    <span class="badge <?= $badge_class[$result['status']] ?? 'badge-menunggu' ?>" style="font-size:0.85rem;padding:6px 16px;">
                        <?= $badge_label[$result['status']] ?? $result['status'] ?>
                    </span>
                </div>

                <div class="result-body">
                    <!-- Status Timeline -->
                    <?php
                    $steps = ['menunggu', 'disetujui', 'dipinjam', 'selesai'];
                    $step_icons = ['⏳', '✅', '🎒', '🏁'];
                    $step_labels = ['Menunggu', 'Disetujui', 'Dipinjam', 'Selesai'];
                    $cur = $result['status'];
                    $cur_idx = array_search($cur, $steps);
                    ?>
                    <?php if ($cur !== 'ditolak'): ?>
                    <div class="status-timeline">
                        <?php foreach ($steps as $i => $step): ?>
                        <div class="timeline-step">
                            <div class="timeline-dot
                                <?= $i == $cur_idx ? 'active' : ($i < $cur_idx ? 'done' : '') ?>">
                                <?= $step_icons[$i] ?>
                            </div>
                            <div class="timeline-label <?= $i == $cur_idx ? 'active' : '' ?>">
                                <?= $step_labels[$i] ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Status Description -->
                    <div style="background:rgba(<?= $cur === 'ditolak' ? '239,68,68' : ($cur === 'selesai' ? '156,163,175' : '26,122,74') ?>,0.08);border:1px solid rgba(<?= $cur === 'ditolak' ? '239,68,68' : ($cur === 'selesai' ? '156,163,175' : '26,122,74') ?>,0.2);border-radius:var(--radius);padding:16px;margin-bottom:24px;text-align:center;">
                        <div style="font-size:2rem;margin-bottom:8px;"><?= $status_icon[$cur] ?? '📋' ?></div>
                        <p style="font-size:0.9rem;color:var(--text-muted);line-height:1.7;">
                            <?= $status_desc[$cur] ?? '' ?>
                        </p>
                    </div>

                    <!-- Detail Info -->
                    <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:.06em;color:var(--primary-light);font-weight:700;margin-bottom:12px;">
                        🎒 Detail Peminjaman
                    </div>

                    <div class="info-row">
                        <span class="info-label">Nama Peminjam</span>
                        <span class="info-value"><?= htmlspecialchars($result['nama_peminjam']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">No. HP</span>
                        <span class="info-value">
                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/','',$result['no_hp']) ?>" target="_blank" style="color:var(--secondary);">
                                <?= htmlspecialchars($result['no_hp']) ?> 💬
                            </a>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Alat Dipinjam</span>
                        <span class="info-value">
                            <?= ($kategori_icon[$result['kategori']] ?? '🏔️') . ' ' . htmlspecialchars($result['nama_alat']) ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Jumlah Unit</span>
                        <span class="info-value"><?= $result['jumlah'] ?> unit</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Tanggal Pinjam</span>
                        <span class="info-value"><?= formatTanggal($result['tanggal_pinjam']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Tanggal Kembali</span>
                        <span class="info-value"><?= formatTanggal($result['tanggal_kembali']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Durasi Sewa</span>
                        <span class="info-value"><?= $result['total_hari'] ?> hari</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Diajukan Pada</span>
                        <span class="info-value" style="font-size:0.82rem;color:var(--text-muted);">
                            <?= date('d M Y, H:i', strtotime($result['created_at'])) ?>
                        </span>
                    </div>

                    <?php if (!empty($result['catatan'])): ?>
                    <div class="info-row">
                        <span class="info-label">Catatanmu</span>
                        <span class="info-value" style="font-size:0.85rem;"><?= htmlspecialchars($result['catatan']) ?></span>
                    </div>
                    <?php endif; ?>

                    <!-- Total -->
                    <div class="total-box">
                        <div>
                            <div class="total-label">Total Biaya Sewa</div>
                            <div style="font-size:0.75rem;color:var(--text-muted);margin-top:2px;">
                                <?= formatRupiah($result['harga_sewa']) ?> × <?= $result['jumlah'] ?> unit × <?= $result['total_hari'] ?> hari
                            </div>
                        </div>
                        <div class="total-amount"><?= formatRupiah($result['total_biaya']) ?></div>
                    </div>

                    <!-- Actions -->
                    <div style="display:flex;gap:12px;margin-top:24px;flex-wrap:wrap;">
                        <a href="peminjaman.php" class="btn btn-primary" style="flex:1;justify-content:center;">
                            📋 Ajukan Sewa Lagi
                        </a>
                        <a href="https://wa.me/62895614800845?text=Halo%20SepakatOutdoor%2C%20saya%20ingin%20menanyakan%20status%20peminjaman%20%23<?= str_pad($result['id_pinjam'],4,'0',STR_PAD_LEFT) ?>"
                           target="_blank" class="btn btn-secondary" style="flex:1;justify-content:center;border-color:rgba(37,211,102,0.3);color:#6ee7b7;">
                            💬 Hubungi via WhatsApp
                        </a>
                    </div>
                </div>
            </div>

            <?php else: ?>
            <!-- NOT FOUND -->
            <div class="not-found">
                <div class="not-found-icon">🔍</div>
                <h3 style="font-family:var(--font-heading);font-size:1.4rem;font-weight:800;margin-bottom:8px;">
                    Peminjaman Tidak Ditemukan
                </h3>
                <p style="color:var(--text-muted);margin-bottom:24px;">
                    ID peminjaman <strong style="color:var(--text-primary);">#<?= htmlspecialchars($id_cari) ?></strong> tidak ditemukan di sistem kami.
                </p>
                <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
                    <a href="cek_status.php" class="btn btn-primary">🔄 Coba Lagi</a>
                    <a href="peminjaman.php" class="btn btn-secondary">📋 Ajukan Baru</a>
                </div>
            </div>
            <?php endif; ?>

        <?php endif; ?>

        <!-- Tips Box -->
        <?php if (!$searched || !$result): ?>
        <div class="tips-card">
            <div class="tips-title">ℹ️ Tips Menemukan ID Peminjaman</div>
            <ul class="tips-list">
                <li>ID peminjaman ditampilkan setelah kamu berhasil mengisi formulir peminjaman</li>
                <li>Formatnya berupa angka 4 digit, contoh: <strong style="color:var(--text-secondary);">#0001</strong></li>
                <li>Jika lupa ID, hubungi admin melalui WhatsApp dengan menyebutkan nama dan tanggal peminjaman</li>
                <li>Admin merespons dalam 1×24 jam pada hari kerja (Senin-Sabtu)</li>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Back Link -->
        <div style="text-align:center;margin-top:32px;">
            <a href="index.php" style="color:var(--text-muted);font-size:0.9rem;">← Kembali ke Beranda</a>
        </div>

    </div>
</div>

<script src="assets/js/main.js"></script>
<script>
// Loading effect on submit
document.querySelector('form').addEventListener('submit', function() {
    const btn = document.getElementById('btn-cek');
    btn.textContent = '🔄 Mencari...';
    btn.disabled = true;
});
</script>
</body>
</html>
