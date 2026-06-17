<?php
session_start();
require_once 'config/database.php';
$db = getDB();

// Ambil semua alat aktif
$alat_result = $db->query("SELECT * FROM alat WHERE status='aktif' ORDER BY kategori, nama_alat");
$alat_list = $alat_result ? $alat_result->fetch_all(MYSQLI_ASSOC) : [];

// Hitung statistik
$stat_alat = $db->query("SELECT COUNT(*) as total FROM alat WHERE status='aktif'")->fetch_assoc()['total'];
$stat_pinjam = $db->query("SELECT COUNT(*) as total FROM peminjaman")->fetch_assoc()['total'];
$stat_aktif = $db->query("SELECT COUNT(*) as total FROM peminjaman WHERE status IN ('disetujui','dipinjam')")->fetch_assoc()['total'];

// Kategori unik
$kategori_list = [];
foreach ($alat_list as $a) {
    if (!in_array($a['kategori'], $kategori_list))
        $kategori_list[] = $a['kategori'];
}

// Icon per kategori
$kategori_icon = [
    'Tenda' => '⛺',
    'Carrier' => '🎒',
    'Sleeping Bag' => '🛌',
    'Masak' => '🍳',
    'Tidur' => '😴',
    'Aksesoris' => '🔦',
    'Pakaian' => '🧥'
];
$default_icon = '🏔️';

// Alert message
$alert = '';
if (isset($_SESSION['alert'])) {
    $alert = $_SESSION['alert'];
    unset($_SESSION['alert']);
}
if (isset($_GET['success'])) {
    $alert = '<div class="alert alert-success" data-auto-close>✅ ' . htmlspecialchars($_GET['success']) . '</div>';
}
if (isset($_GET['error'])) {
    $alert = '<div class="alert alert-danger" data-auto-close>❌ ' . htmlspecialchars($_GET['error']) . '</div>';
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="Sewa alat gunung dan outdoor terpercaya. Tenda, carrier, sleeping bag, kompor, dan perlengkapan pendakian lainnya tersedia dengan harga terjangkau.">
    <title>ShinarsanOutdoor - Sewa Alat Outdoor & Gunung Terpercaya</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

    <!-- ===================== NAVBAR ===================== -->
    <nav class="navbar" id="navbar">
        <div class="nav-container">
            <a href="index.php" class="nav-logo">
                <div class="logo-icon">
                    <img src="assets/images/logo.jpeg" alt="Shinarsan Outdoor">
                </div>
                <div>Shinarsan<span>Outdoor</span></div>
            </a>
            <button class="hamburger" id="hamburger">☰</button>
            <div class="nav-links" id="nav-links">
                <a href="#beranda" class="active">Beranda</a>
                <a href="#katalog">Katalog Alat</a>
                <a href="#cara-sewa">Cara Sewa</a>
                <a href="#kontak">Kontak</a>
                <a href="cek_status.php">🔍 Cek Status</a>
                <a href="peminjaman.php" class="btn-primary"
                    style="border-radius:8px;padding:8px 18px;font-size:.9rem;">📋 Ajukan Sewa</a>
                <a href="admin/login.php" class="btn-admin">🔐 Admin</a>
            </div>
        </div>
    </nav>

    <!-- ===================== HERO ===================== -->
    <section class="hero" id="beranda">
        <div class="mountain-bg"></div>
        <div style="position:relative;z-index:2;">
            <div class="hero-badge"><img src="assets/images/logo.jpeg" alt="Shinarsan Outdoor"> Rental Alat Outdoor Terpercaya</div>
            <h1 class="hero-title">
                Lengkapi Petualanganmu<br>
                dengan <span class="gradient-text">Alat Gunung</span> Terbaik
            </h1>
            <p class="hero-subtitle">
                Sewa perlengkapan pendakian berkualitas dengan harga terjangkau.
                Tenda, carrier, sleeping bag, dan perlengkapan outdoor lainnya siap mendukung ekspedisimu.
            </p>
            <div class="hero-cta">
                <a href="#katalog" class="btn btn-primary btn-lg">
                    🎒 Lihat Katalog Alat
                </a>
                <a href="peminjaman.php" class="btn btn-secondary btn-lg">
                    📋 Ajukan Peminjaman
                </a>
            </div>
            <div class="hero-stats">
                <div class="stat-item">
                    <div class="stat-number" data-count="<?= $stat_alat ?>" data-suffix="+"><?= $stat_alat ?>+</div>
                    <div class="stat-label">Jenis Alat</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" data-count="<?= $stat_pinjam ?>"><?= $stat_pinjam ?></div>
                    <div class="stat-label">Total Sewa</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" data-count="<?= $stat_aktif ?>"><?= $stat_aktif ?></div>
                    <div class="stat-label">Sedang Dipinjam</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">100%</div>
                    <div class="stat-label">Puas Disewa</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===================== FITUR ===================== -->
    <section class="section">
        <div class="container">
            <div class="section-header">
                <div class="section-tag">✨ Keunggulan Kami</div>
                <h2 class="section-title">Kenapa Pilih <span style="color:var(--secondary)">ShinarsanOutdoor</span>?</h2>
                <p class="section-subtitle">Kami hadir untuk memastikan perjalananmu aman, nyaman, dan menyenangkan</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">🏷️</div>
                    <h3 class="feature-title">Harga Terjangkau</h3>
                    <p class="feature-desc">Harga sewa mulai dari Rp 10.000/hari. Lebih hemat daripada beli, lebih
                        praktis untuk perjalanan sesekali.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">⚡</div>
                    <h3 class="feature-title">Proses Mudah</h3>
                    <p class="feature-desc">Ajukan peminjaman online tanpa ribet. Admin akan konfirmasi dalam 1x24 jam.
                        Tidak perlu antri!</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🛡️</div>
                    <h3 class="feature-title">Alat Terawat</h3>
                    <p class="feature-desc">Semua alat dicek dan dibersihkan setelah setiap peminjaman. Kondisi selalu
                        prima dan siap pakai.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📦</div>
                    <h3 class="feature-title">Stok Real-time</h3>
                    <p class="feature-desc">Cek ketersediaan stok langsung di website. Tidak perlu telepon dulu untuk
                        tahu alat tersedia atau tidak.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🤝</div>
                    <h3 class="feature-title">Ramah Komunitas</h3>
                    <p class="feature-desc">Partner resmi komunitas pecinta alam dan Mapala kampus. Diskon khusus untuk
                        anggota komunitas.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📱</div>
                    <h3 class="feature-title">Tracking Status</h3>
                    <p class="feature-desc">Pantau status peminjaman kamu kapan saja. Dari menunggu, disetujui, hingga
                        selesai — semua transparan.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ===================== KATALOG ALAT ===================== -->
    <section class="section section-alt" id="katalog">
        <div class="container">
            <div class="section-header">
                <div class="section-tag">🎒 Koleksi Kami</div>
                <h2 class="section-title">Katalog Alat Outdoor</h2>
                <p class="section-subtitle">Pilih alat yang kamu butuhkan untuk petualangan berikutnya</p>
            </div>

            <?= $alert ?>

            <!-- Filter -->
            <div class="filter-bar">
                <button class="filter-btn active" data-filter="semua">🏔️ Semua</button>
                <?php foreach ($kategori_list as $kat): ?>
                    <button class="filter-btn" data-filter="<?= htmlspecialchars($kat) ?>">
                        <?= $kategori_icon[$kat] ?? $default_icon ?>     <?= htmlspecialchars($kat) ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <?php if (empty($alat_list)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📦</div>
                    <div class="empty-title">Belum ada alat tersedia</div>
                    <p>Hubungi admin untuk informasi lebih lanjut</p>
                </div>
            <?php else: ?>
                <div class="equipment-grid">
                    <?php foreach ($alat_list as $alat): ?>
                        <?php $icon = $kategori_icon[$alat['kategori']] ?? $default_icon; ?>
                        <div class="equipment-card" data-category="<?= htmlspecialchars($alat['kategori']) ?>">
                            <div class="card-image">
                                <?php if ($alat['foto'] && file_exists('assets/images/' . $alat['foto'])): ?>
                                    <img src="assets/images/<?= htmlspecialchars($alat['foto']) ?>"
                                        alt="<?= htmlspecialchars($alat['nama_alat']) ?>">
                                <?php else: ?>
                                    <span style="font-size:4.5rem;position:relative;z-index:1;"><?= $icon ?></span>
                                <?php endif; ?>
                                <span class="card-badge"><?= htmlspecialchars($alat['kategori']) ?></span>
                                <span class="card-stock <?= $alat['stok'] == 0 ? 'empty' : '' ?>">
                                    <?= $alat['stok'] == 0 ? '❌ Habis' : '✅ Stok ' . $alat['stok'] ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <h3 class="card-title"><?= htmlspecialchars($alat['nama_alat']) ?></h3>
                                <p class="card-desc">
                                    <?= htmlspecialchars($alat['deskripsi'] ?? 'Perlengkapan outdoor berkualitas.') ?></p>
                                <div class="card-price">
                                    <div>
                                        <div class="price-amount"><?= formatRupiah($alat['harga_sewa']) ?></div>
                                        <div class="price-unit">per hari</div>
                                    </div>
                                    <?php if ($alat['stok'] > 0): ?>
                                        <button class="btn btn-primary btn-sm btn-rent" data-id="<?= $alat['id_alat'] ?>"
                                            data-nama="<?= htmlspecialchars($alat['nama_alat']) ?>"
                                            data-harga="<?= $alat['harga_sewa'] ?>" data-stok="<?= $alat['stok'] ?>"
                                            data-modal="rent-modal">
                                            Sewa
                                        </button>
                                    <?php else: ?>
                                        <span class="btn btn-sm"
                                            style="background:rgba(239,68,68,0.1);color:var(--danger);border:1px solid rgba(239,68,68,0.2);cursor:default;">Habis</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ===================== CARA SEWA ===================== -->
    <section class="section" id="cara-sewa">
        <div class="container">
            <div class="section-header">
                <div class="section-tag">📋 Panduan</div>
                <h2 class="section-title">Cara Sewa yang Mudah</h2>
                <p class="section-subtitle">Hanya 4 langkah mudah untuk mendapatkan alat yang kamu butuhkan</p>
            </div>
            <div class="steps-grid">
                <div class="step-item">
                    <div class="step-number">1</div>
                    <h3 class="step-title">Pilih Alat</h3>
                    <p class="step-desc">Jelajahi katalog alat kami dan pilih perlengkapan yang sesuai kebutuhanmu</p>
                </div>
                <div class="step-item">
                    <div class="step-number">2</div>
                    <h3 class="step-title">Isi Formulir</h3>
                    <p class="step-desc">Isi data diri, tanggal pinjam dan kembali, lalu submit pengajuan sewa</p>
                </div>
                <div class="step-item">
                    <div class="step-number">3</div>
                    <h3 class="step-title">Tunggu Konfirmasi</h3>
                    <p class="step-desc">Admin akan memproses dan mengkonfirmasi pengajuanmu dalam 1x24 jam</p>
                </div>
                <div class="step-item">
                    <div class="step-number">4</div>
                    <h3 class="step-title">Ambil & Nikmati!</h3>
                    <p class="step-desc">Ambil alat sesuai jadwal, jalani petualangan, dan kembalikan tepat waktu</p>
                </div>
            </div>
            <div style="text-align:center;margin-top:48px;">
                <a href="peminjaman.php" class="btn btn-primary btn-lg">
                    📋 Mulai Ajukan Sewa Sekarang
                </a>
            </div>
        </div>
    </section>

    <!-- ===================== MODAL SEWA ===================== -->
    <div class="modal-overlay" id="rent-modal">
        <div class="modal-box">
            <div class="modal-header">
                <div>
                    <h3 class="modal-title">🎒 Ajukan Sewa Cepat</h3>
                    <p style="font-size:.85rem;color:var(--text-muted);margin-top:4px;">
                        <span id="modal-alat-name"></span> — <strong id="modal-alat-price"
                            style="color:var(--secondary)"></strong>
                    </p>
                </div>
                <span class="modal-close"
                    onclick="document.getElementById('rent-modal').classList.remove('show')">✕</span>
            </div>
            <form action="peminjaman.php" method="GET">
                <input type="hidden" name="from_modal" value="1">
                <div class="form-group">
                    <label class="form-label">Alat yang Dipilih</label>
                    <select name="id_alat" id="id_alat" class="form-control" required>
                        <option value="">-- Pilih Alat --</option>
                        <?php foreach ($alat_list as $a): ?>
                            <?php if ($a['stok'] > 0): ?>
                                <option value="<?= $a['id_alat'] ?>" data-harga="<?= $a['harga_sewa'] ?>">
                                    <?= htmlspecialchars($a['nama_alat']) ?> — <?= formatRupiah($a['harga_sewa']) ?>/hari
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group mb-0">
                        <label class="form-label">Tanggal Pinjam</label>
                        <input type="date" id="tanggal_pinjam" name="tanggal_pinjam" class="form-control" required>
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">Tanggal Kembali</label>
                        <input type="date" id="tanggal_kembali" name="tanggal_kembali" class="form-control" required>
                    </div>
                </div>
                <div id="price-preview" class="price-preview">
                    <div class="price-row"><span>Harga Sewa</span><span id="preview-harga">-</span></div>
                    <div class="price-row"><span>Jumlah</span><span id="preview-jumlah">-</span></div>
                    <div class="price-row"><span>Durasi</span><span id="preview-hari">-</span></div>
                    <div class="price-row total"><span>💰 Estimasi Total</span><span id="preview-total">-</span></div>
                </div>
                <div style="margin-top:20px;">
                    <a href="peminjaman.php" class="btn btn-primary btn-block">
                        📋 Lanjut Isi Data Peminjaman →
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- ===================== FOOTER ===================== -->
    <footer class="footer" id="kontak">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <div class="nav-logo" style="margin-bottom:16px;">
                        <div class="logo-icon"><img src="assets/images/logo.jpeg" alt="Shinarsan Outdoor"></div>
                        <div style="font-family:var(--font-heading);font-size:1.3rem;font-weight:800;">Shinarsan<span
                                style="color:var(--secondary)">Outdoor</span></div>
                    </div>
                    <p>Platform peminjaman alat gunung dan outdoor terpercaya. Kami hadir untuk mendukung petualanganmu
                        dengan perlengkapan berkualitas dan harga terjangkau.</p>
                    <div class="contact-info" style="margin-top:20px;">
                        <div class="contact-item">📍 Jl. Flamboyan 17 No.05</div>
                        <div class="contact-item">📞 +62-823-7133-3442</div>
                        <div class="contact-item">✉️ info @shinarsanOutdoor</div>
                        <div class="contact-item">🕐 Buka: Senin-Sabtu, 08.00-22.00</div>
                    </div>
                </div>
                <div>
                    <div class="footer-title">🔗 Navigasi</div>
                    <ul class="footer-links">
                        <li><a href="#beranda">🏠 Beranda</a></li>
                        <li><a href="#katalog">🎒 Katalog Alat</a></li>
                        <li><a href="#cara-sewa">📋 Cara Sewa</a></li>
                        <li><a href="peminjaman.php">✍️ Ajukan Pinjam</a></li>
                    </ul>
                </div>
                <div>
                    <div class="footer-title">📦 Kategori Alat</div>
                    <ul class="footer-links">
                        <?php foreach (array_slice($kategori_list, 0, 6) as $kat): ?>
                            <li><a href="#katalog"><?= ($kategori_icon[$kat] ?? '🏔️') . ' ' . htmlspecialchars($kat) ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <span>© <?= date('Y') ?> ShinarsanOutdoor. Dibuat dengan ❤️ untuk para pendaki.</span>
                <span>
                    <a href="admin/login.php" style="color:var(--text-muted);">🔐 Admin Panel</a>
                </span>
            </div>
        </div>
    </footer>

    <script src="assets/js/main.js"></script>
</body>

</html>