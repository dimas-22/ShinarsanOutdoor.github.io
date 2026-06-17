<?php
session_start();
require_once 'config/database.php';
$db = getDB();

$alert = '';
$success = false;

// Auto-create detail table jika belum ada
$db->query("CREATE TABLE IF NOT EXISTS peminjaman_detail (
    id_detail INT AUTO_INCREMENT PRIMARY KEY,
    id_pinjam INT NOT NULL,
    id_alat INT NOT NULL,
    jumlah INT NOT NULL DEFAULT 1,
    harga_satuan DECIMAL(10,2) NOT NULL DEFAULT 0,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pinjam) REFERENCES peminjaman(id_pinjam) ON DELETE CASCADE,
    FOREIGN KEY (id_alat) REFERENCES alat(id_alat) ON DELETE CASCADE
)");

// Ambil data alat tersedia
$alat_result = $db->query("SELECT * FROM alat WHERE status='aktif' AND stok > 0 ORDER BY kategori, nama_alat");
$alat_list = $alat_result ? $alat_result->fetch_all(MYSQLI_ASSOC) : [];

$preselect_alat      = isset($_GET['id_alat']) ? intval($_GET['id_alat']) : 0;
$preselect_tgl_pinjam   = isset($_GET['tanggal_pinjam'])  ? htmlspecialchars($_GET['tanggal_pinjam'])  : date('Y-m-d');
$preselect_tgl_kembali  = isset($_GET['tanggal_kembali']) ? htmlspecialchars($_GET['tanggal_kembali']) : date('Y-m-d', strtotime('+1 day'));

// ===== PROSES SUBMIT =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_peminjam  = trim($_POST['nama_peminjam'] ?? '');
    $no_hp          = trim($_POST['no_hp'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $tanggal_pinjam = $_POST['tanggal_pinjam'] ?? '';
    $tanggal_kembali= $_POST['tanggal_kembali'] ?? '';
    $catatan        = trim($_POST['catatan'] ?? '');
    $items_raw      = $_POST['items'] ?? [];

    $errors = [];
    if (empty($nama_peminjam)) $errors[] = 'Nama peminjam wajib diisi';
    if (empty($no_hp))         $errors[] = 'Nomor HP wajib diisi';
    if (empty($tanggal_pinjam))  $errors[] = 'Tanggal pinjam wajib diisi';
    if (empty($tanggal_kembali)) $errors[] = 'Tanggal kembali wajib diisi';
    if ($tanggal_pinjam && $tanggal_kembali && $tanggal_kembali <= $tanggal_pinjam)
        $errors[] = 'Tanggal kembali harus setelah tanggal pinjam';

    $hari = ($tanggal_pinjam && $tanggal_kembali) ? hitungHari($tanggal_pinjam, $tanggal_kembali) : 0;

    // Aggregate item (gabungkan duplikat)
    $aggregated = [];
    foreach ($items_raw as $item) {
        $id_alat = intval($item['id_alat'] ?? 0);
        $jumlah  = intval($item['jumlah']  ?? 0);
        if ($id_alat <= 0 || $jumlah < 1) continue;
        $aggregated[$id_alat] = ($aggregated[$id_alat] ?? 0) + $jumlah;
    }

    $valid_items = [];
    if (empty($aggregated)) {
        $errors[] = 'Pilih minimal 1 alat yang ingin dipinjam';
    } else {
        foreach ($aggregated as $id_alat => $jumlah) {
            $res = $db->query("SELECT * FROM alat WHERE id_alat=$id_alat AND status='aktif'");
            $a   = $res ? $res->fetch_assoc() : null;
            if (!$a) {
                $errors[] = "Alat tidak ditemukan (ID: $id_alat)";
            } elseif ($a['stok'] < $jumlah) {
                $errors[] = "Stok <strong>" . htmlspecialchars($a['nama_alat']) . "</strong> tidak mencukupi. Tersedia: {$a['stok']} unit";
            } else {
                $valid_items[] = [
                    'id_alat'     => $id_alat,
                    'jumlah'      => $jumlah,
                    'harga_satuan'=> $a['harga_sewa'],
                    'subtotal'    => $a['harga_sewa'] * $jumlah * $hari,
                    'nama'        => $a['nama_alat'],
                ];
            }
        }
    }

    // Handle file upload Bukti DP
    $bukti_dp_query = "NULL";
    if (empty($errors) && isset($_FILES['bukti_dp']) && $_FILES['bukti_dp']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['bukti_dp']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp','pdf'];
        if (in_array($ext, $allowed)) {
            $filename = 'dp_' . time() . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['bukti_dp']['tmp_name'], 'assets/uploads/dp/' . $filename)) {
                $bukti_dp_query = "'$filename'";
            } else {
                $errors[] = 'Gagal mengunggah file bukti DP.';
            }
        } else {
            $errors[] = 'Format bukti DP tidak didukung (harus JPG/PNG/WebP/PDF).';
        }
    }

    if (empty($errors)) {
        $total_biaya = array_sum(array_column($valid_items, 'subtotal'));
        $first       = $valid_items[0];

        $nama_s    = $db->real_escape_string($nama_peminjam);
        $hp_s      = $db->real_escape_string($no_hp);
        $email_s   = $db->real_escape_string($email);
        $catatan_s = $db->real_escape_string($catatan);

        $sql = "INSERT INTO peminjaman
                (nama_peminjam,no_hp,email,id_alat,jumlah,tanggal_pinjam,tanggal_kembali,total_hari,total_biaya,catatan,bukti_dp)
                VALUES ('$nama_s','$hp_s','$email_s',{$first['id_alat']},{$first['jumlah']},
                        '$tanggal_pinjam','$tanggal_kembali',$hari,$total_biaya,'$catatan_s', $bukti_dp_query)";

        if ($db->query($sql)) {
            $id_pinjam = $db->insert_id;
            foreach ($valid_items as $vi) {
                $db->query("INSERT INTO peminjaman_detail (id_pinjam,id_alat,jumlah,harga_satuan,subtotal)
                            VALUES ($id_pinjam,{$vi['id_alat']},{$vi['jumlah']},{$vi['harga_satuan']},{$vi['subtotal']})");
            }
            $success = true;
            $alert   = '<div class="alert alert-success" data-auto-close>
                ✅ Pengajuan sewa berhasil! ID Peminjaman: <strong>#'.str_pad($id_pinjam,4,'0',STR_PAD_LEFT).'</strong>.
                Admin akan mengkonfirmasi dalam 1x24 jam. Terima kasih!
            </div>';
        } else {
            $errors[] = 'Gagal menyimpan data: ' . $db->error;
        }
    }

    if (!empty($errors))
        $alert = '<div class="alert alert-danger">❌ ' . implode('<br>• ', $errors) . '</div>';
}

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
    <meta name="description" content="Formulir pengajuan peminjaman alat gunung. Pilih beberapa alat sekaligus.">
    <title>Ajukan Peminjaman - SepakatOutdoor</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .form-page { min-height:100vh; padding:100px 24px 60px; background:var(--bg-dark); }
        .form-page-container { max-width:820px; margin:0 auto; }
        .form-title-area { text-align:center; margin-bottom:40px; }
        .back-link {
            display:inline-flex; align-items:center; gap:8px;
            color:var(--text-muted); font-size:.9rem; margin-bottom:20px; transition:var(--transition);
        }
        .back-link:hover { color:var(--text-secondary); }
        .success-card {
            background:var(--card-bg); border:1px solid rgba(16,185,129,.3);
            border-radius:var(--radius-lg); padding:48px; text-align:center;
        }
        .success-icon { font-size:4rem; margin-bottom:20px; }

        /* Item Rows */
        .item-row {
            background:rgba(255,255,255,.025);
            border:1px solid var(--card-border);
            border-radius:var(--radius);
            padding:16px;
            margin-bottom:10px;
            transition:border-color .2s;
        }
        .item-row:hover { border-color:rgba(26,122,74,.4); }
        .item-row-grid {
            display:grid;
            grid-template-columns:24px 1fr 110px 130px 36px;
            gap:12px;
            align-items:end;
        }
        @media(max-width:640px){
            .item-row-grid { grid-template-columns:20px 1fr 80px 32px; }
            .item-price-col { display:none; }
        }
        .item-num { color:var(--text-muted); font-size:.8rem; font-weight:700; padding-bottom:10px; }
        .remove-btn {
            width:32px; height:32px; border:none; border-radius:6px;
            background:rgba(239,68,68,.1); color:#fca5a5;
            cursor:pointer; font-size:.95rem; display:flex; align-items:center; justify-content:center;
            transition:var(--transition); flex-shrink:0;
        }
        .remove-btn:hover { background:rgba(239,68,68,.28); }
        .item-subtotal-val {
            font-weight:700; color:var(--secondary); font-size:.88rem;
            padding:8px 0 4px; min-height:36px; display:flex; align-items:center;
        }
        .add-item-btn {
            width:100%; margin-top:10px; border:2px dashed var(--card-border);
            background:transparent; color:var(--secondary); border-radius:var(--radius);
            padding:12px; font-size:.9rem; font-weight:600; cursor:pointer; transition:var(--transition);
        }
        .add-item-btn:hover { border-color:var(--secondary); background:rgba(26,122,74,.08); }

        /* Price preview */
        .price-section { margin-top:20px; }
        .price-preview {
            background:rgba(26,122,74,.06); border:1px solid rgba(26,122,74,.2);
            border-radius:var(--radius); padding:16px 20px;
        }
        .price-row { display:flex; justify-content:space-between; padding:6px 0; font-size:.88rem; }
        .price-row.total { border-top:1px solid rgba(26,122,74,.3); margin-top:6px; padding-top:10px; font-weight:800; font-size:1rem; color:var(--secondary); }
        .breakdown-item { color:var(--text-muted); font-size:.8rem; }
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
        <button class="hamburger">☰</button>
        <div class="nav-links">
            <a href="index.php">Beranda</a>
            <a href="index.php#katalog">Katalog Alat</a>
            <a href="peminjaman.php" class="active">Ajukan Sewa</a>
            <a href="cek_status.php">🔍 Cek Status</a>
            <a href="admin/login.php" class="btn-admin">🔐 Admin</a>
        </div>
    </div>
</nav>

<div class="form-page">
<div class="form-page-container">
    <a href="index.php" class="back-link">← Kembali ke Beranda</a>

    <?php if ($success): ?>
    <!-- SUCCESS -->
    <div class="success-card animate-fade-up">
        <div class="success-icon">🎉</div>
        <h2 style="font-family:var(--font-heading);font-size:1.8rem;font-weight:800;margin-bottom:12px;">Pengajuan Berhasil!</h2>
        <p style="color:var(--text-muted);margin-bottom:24px;">Pengajuanmu sedang diproses admin. Kamu akan dihubungi via nomor HP yang terdaftar.</p>
        <?= $alert ?>
        <div style="background:rgba(26,122,74,.1);border:1px solid rgba(26,122,74,.3);border-radius:var(--radius);padding:16px;margin:16px 0 24px;text-align:center;">
            <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:4px;">Simpan nomor ini untuk cek status:</div>
            <div style="font-family:monospace;font-size:1.8rem;font-weight:800;color:var(--secondary);letter-spacing:4px;">#<?= str_pad($id_pinjam??0,4,'0',STR_PAD_LEFT) ?></div>
        </div>
        <div style="display:flex;gap:16px;justify-content:center;flex-wrap:wrap;">
            <a href="cek_status.php?id=<?= $id_pinjam??'' ?>" class="btn btn-primary">🔍 Cek Status</a>
            <a href="peminjaman.php" class="btn btn-secondary">📋 Ajukan Lagi</a>
            <a href="index.php" class="btn btn-secondary">🏠 Beranda</a>
        </div>
    </div>

    <?php else: ?>
    <!-- FORM -->
    <div class="form-title-area">
        <div class="section-tag">📋 Pengajuan</div>
        <h1 style="font-family:var(--font-heading);font-size:2rem;font-weight:800;margin:12px 0 8px;">Formulir Peminjaman Alat</h1>
        <p style="color:var(--text-muted);">Kamu bisa sewa beberapa alat sekaligus dalam satu pengajuan</p>
    </div>

    <?= $alert ?>

    <div class="form-card animate-fade-up">
        <form method="POST" action="peminjaman.php" enctype="multipart/form-data">

            <!-- Data Peminjam -->
            <h3 style="font-family:var(--font-heading);font-size:1.05rem;font-weight:700;color:var(--secondary);margin-bottom:20px;">👤 Data Peminjam</h3>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="nama_peminjam">Nama Lengkap <span>*</span></label>
                    <input type="text" id="nama_peminjam" name="nama_peminjam" class="form-control"
                        placeholder="Masukkan nama lengkap" required
                        value="<?= isset($_POST['nama_peminjam']) ? htmlspecialchars($_POST['nama_peminjam']) : '' ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="no_hp">Nomor HP/WhatsApp <span>*</span></label>
                    <input type="tel" id="no_hp" name="no_hp" class="form-control"
                        placeholder="08XX-XXXX-XXXX" required
                        value="<?= isset($_POST['no_hp']) ? htmlspecialchars($_POST['no_hp']) : '' ?>">
                    <div class="form-hint">Untuk konfirmasi dan info pengambilan</div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="email">Email (Opsional)</label>
                <input type="email" id="email" name="email" class="form-control"
                    placeholder="email@example.com"
                    value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
            </div>

            <hr class="separator">

            <!-- Periode Sewa -->
            <h3 style="font-family:var(--font-heading);font-size:1.05rem;font-weight:700;color:var(--secondary);margin:0 0 20px;">📅 Periode Sewa</h3>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="tanggal_pinjam">Tanggal Pinjam <span>*</span></label>
                    <input type="date" id="tanggal_pinjam" name="tanggal_pinjam" class="form-control" required
                        value="<?= isset($_POST['tanggal_pinjam']) ? $_POST['tanggal_pinjam'] : $preselect_tgl_pinjam ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="tanggal_kembali">Tanggal Kembali <span>*</span></label>
                    <input type="date" id="tanggal_kembali" name="tanggal_kembali" class="form-control" required
                        value="<?= isset($_POST['tanggal_kembali']) ? $_POST['tanggal_kembali'] : $preselect_tgl_kembali ?>">
                </div>
            </div>

            <hr class="separator">

            <!-- Daftar Alat -->
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <div>
                    <h3 style="font-family:var(--font-heading);font-size:1.05rem;font-weight:700;color:var(--secondary);margin:0;">🎒 Daftar Alat yang Disewa</h3>
                    <p style="color:var(--text-muted);font-size:.82rem;margin-top:4px;">Tambah beberapa alat sekaligus dalam satu pengajuan</p>
                </div>
                <span id="items-count-badge" style="background:rgba(26,122,74,.15);color:var(--secondary);border-radius:20px;padding:3px 12px;font-size:.8rem;font-weight:700;">1 alat</span>
            </div>

            <!-- Header kolom -->
            <div style="display:grid;grid-template-columns:24px 1fr 110px 130px 36px;gap:12px;padding:0 4px;margin-bottom:6px;" class="item-header-row">
                <div></div>
                <div style="font-size:.75rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Pilih Alat</div>
                <div style="font-size:.75rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Jumlah</div>
                <div style="font-size:.75rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Subtotal/hari</div>
                <div></div>
            </div>

            <div id="items-container">
                <!-- Baris pertama (server-side rendered) -->
                <div class="item-row" data-idx="0">
                    <div class="item-row-grid">
                        <div class="item-num">1</div>
                        <div>
                            <select name="items[0][id_alat]" class="form-control alat-select" required>
                                <option value="">-- Pilih Alat --</option>
                                <?php foreach ($alat_list as $a): ?>
                                <option value="<?= $a['id_alat'] ?>"
                                    data-harga="<?= $a['harga_sewa'] ?>"
                                    data-stok="<?= $a['stok'] ?>"
                                    <?= ($preselect_alat == $a['id_alat']) ? 'selected' : '' ?>>
                                    <?= ($kategori_icon[$a['kategori']] ?? '🏔️') . ' ' . htmlspecialchars($a['nama_alat']) ?>
                                    — Rp <?= number_format($a['harga_sewa'],0,',','.') ?>/hari (Stok: <?= $a['stok'] ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-hint stok-hint" style="margin-top:4px;"></div>
                        </div>
                        <div>
                            <input type="number" name="items[0][jumlah]" class="form-control jumlah-input" min="1" value="1" required>
                        </div>
                        <div class="item-price-col">
                            <div class="item-subtotal-val">-</div>
                        </div>
                        <div>
                            <button type="button" class="remove-btn" style="opacity:0;pointer-events:none;" title="Hapus">✕</button>
                        </div>
                    </div>
                </div>
            </div>

            <button type="button" id="add-item-btn" class="add-item-btn">➕ Tambah Alat Lagi</button>

            <!-- Price Preview -->
            <div class="price-section">
                <div class="price-preview" id="price-preview">
                    <div class="price-row">
                        <span>⏱ Durasi Sewa</span>
                        <span id="preview-hari" style="font-weight:700;">-</span>
                    </div>
                    <div id="breakdown-container"></div>
                    <div class="price-row total">
                        <span>💰 Estimasi Total Biaya</span>
                        <span id="preview-total">-</span>
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin-top:24px;">
                <label class="form-label">Metode Pembayaran DP</label>
                <div style="background:rgba(255,255,255,0.05);border:1px solid var(--card-border);border-radius:var(--radius);padding:16px;margin-bottom:16px;text-align:center;">
                    <div style="font-weight:700;color:var(--text-primary);margin-bottom:12px;">Scan QRIS di bawah ini untuk membayar uang muka (DP):</div>
                    <div style="background:#fff;display:inline-block;padding:10px;border-radius:8px;">
                        <img src="assets/images/qris.png" alt="QRIS Sepakat Outdoor" style="max-width:100%;width:250px;height:auto;border-radius:4px;">
                    </div>
                </div>

                <label class="form-label" for="bukti_dp">Upload Bukti Transfer DP</label>
                <input type="file" id="bukti_dp" name="bukti_dp" class="form-control" accept="image/*,.pdf" style="padding:10px;background:#fff;" required>
                <div class="form-hint" style="margin-top:4px;">Format didukung: JPG, PNG, PDF. (Sebagai jaminan uang muka)</div>
            </div>

            <div class="form-group" style="margin-top:16px;">
                <label class="form-label" for="catatan">Catatan Tambahan (Opsional)</label>
                <textarea id="catatan" name="catatan" class="form-control"
                    placeholder="Informasi tambahan seperti kondisi khusus, pertanyaan, dll..."><?= isset($_POST['catatan']) ? htmlspecialchars($_POST['catatan']) : '' ?></textarea>
            </div>

            <hr class="separator">

            <div style="background:rgba(245,158,11,.05);border:1px solid rgba(245,158,11,.2);border-radius:var(--radius);padding:16px;margin-bottom:24px;">
                <p style="font-size:.85rem;color:var(--text-muted);line-height:1.8;">
                    ⚠️ <strong style="color:var(--secondary);">Syarat &amp; Ketentuan:</strong><br>
                    • Peminjam bertanggung jawab atas kerusakan alat selama dipinjam.<br>
                    • Keterlambatan pengembalian dikenakan denda sesuai kebijakan.<br>
                    • Pengajuan diproses oleh admin dalam 1x24 jam pada hari kerja.<br>
                    • Alat harus dikembalikan dalam kondisi bersih dan lengkap.
                </p>
            </div>

            <button type="submit" class="btn btn-primary btn-lg btn-block">📤 Kirim Pengajuan Peminjaman</button>
        </form>
    </div>
    <?php endif; ?>
</div>
</div>

<script>
// Data alat dari server
const ALAT_DATA = <?= json_encode($alat_list) ?>;
const KAT_ICON  = <?= json_encode($kategori_icon) ?>;

let itemCount = 1;

function fmt(n) {
    return 'Rp ' + Math.round(n).toLocaleString('id-ID');
}

function buildOptions(selectedId = 0) {
    let h = '<option value="">-- Pilih Alat --</option>';
    ALAT_DATA.forEach(a => {
        const icon = KAT_ICON[a.kategori] ?? '🏔️';
        const harga = parseInt(a.harga_sewa).toLocaleString('id-ID');
        const sel = (a.id_alat == selectedId) ? 'selected' : '';
        h += `<option value="${a.id_alat}" data-harga="${a.harga_sewa}" data-stok="${a.stok}" ${sel}>
            ${icon} ${a.nama_alat} — Rp ${harga}/hari (Stok: ${a.stok})
        </option>`;
    });
    return h;
}

function createRow(idx) {
    const div = document.createElement('div');
    div.className = 'item-row';
    div.dataset.idx = idx;
    div.innerHTML = `
        <div class="item-row-grid">
            <div class="item-num">${idx + 1}</div>
            <div>
                <select name="items[${idx}][id_alat]" class="form-control alat-select" required>
                    ${buildOptions()}
                </select>
                <div class="form-hint stok-hint" style="margin-top:4px;"></div>
            </div>
            <div>
                <input type="number" name="items[${idx}][jumlah]" class="form-control jumlah-input" min="1" value="1" required>
            </div>
            <div class="item-price-col">
                <div class="item-subtotal-val">-</div>
            </div>
            <div>
                <button type="button" class="remove-btn" title="Hapus">✕</button>
            </div>
        </div>`;
    return div;
}

function getHari() {
    const p = document.getElementById('tanggal_pinjam')?.value;
    const k = document.getElementById('tanggal_kembali')?.value;
    if (!p || !k) return 0;
    const diff = (new Date(k) - new Date(p)) / 86400000;
    return Math.max(0, Math.round(diff));
}

function refreshAll() {
    const hari = getHari();
    document.getElementById('preview-hari').textContent = hari > 0 ? hari + ' hari' : '-';

    const rows = document.querySelectorAll('.item-row');
    let grandTotal = 0;
    const breakdownLines = [];

    rows.forEach(row => {
        const sel    = row.querySelector('.alat-select');
        const jInput = row.querySelector('.jumlah-input');
        const subEl  = row.querySelector('.item-subtotal-val');
        const hint   = row.querySelector('.stok-hint');
        const opt    = sel?.options[sel.selectedIndex];
        const harga  = opt ? parseFloat(opt.dataset.harga || 0) : 0;
        const stok   = opt ? parseInt(opt.dataset.stok || 0)  : 0;
        const jumlah = parseInt(jInput?.value || 0);
        const nama   = (opt && opt.value) ? opt.text.split(' —')[0].trim() : '';

        if (jInput && stok > 0) jInput.max = stok;
        if (hint && stok > 0 && opt?.value) hint.textContent = 'Stok tersedia: ' + stok;
        else if (hint) hint.textContent = '';

        if (harga > 0 && jumlah > 0) {
            const perHari = harga * jumlah;
            const total   = perHari * (hari || 1);
            if (subEl) subEl.textContent = fmt(perHari) + '/hari';
            grandTotal += perHari * hari;
            if (nama) breakdownLines.push(`<div class="price-row breakdown-item"><span>${nama} × ${jumlah} unit</span><span>${fmt(harga)} × ${jumlah} × ${hari} hari = <strong>${fmt(total)}</strong></span></div>`);
        } else if (subEl) subEl.textContent = '-';
    });

    document.getElementById('breakdown-container').innerHTML = breakdownLines.join('');
    document.getElementById('preview-total').textContent = grandTotal > 0 ? fmt(grandTotal) : '-';

    // Badge count
    const badge = document.getElementById('items-count-badge');
    if (badge) badge.textContent = rows.length + (rows.length > 1 ? ' alat' : ' alat');
}

function reindex() {
    document.querySelectorAll('.item-row').forEach((row, i) => {
        const n = row.querySelector('.item-num');
        if (n) n.textContent = i + 1;
        // rename fields
        row.querySelector('.alat-select').name = `items[${i}][id_alat]`;
        row.querySelector('.jumlah-input').name = `items[${i}][jumlah]`;
    });
}

function syncRemoveButtons() {
    const rows = document.querySelectorAll('.item-row');
    rows.forEach((row, i) => {
        const btn = row.querySelector('.remove-btn');
        if (!btn) return;
        if (rows.length === 1) { btn.style.opacity='0'; btn.style.pointerEvents='none'; }
        else                   { btn.style.opacity='1'; btn.style.pointerEvents='auto'; }
    });
}

function bindRow(row) {
    row.querySelector('.alat-select').addEventListener('change', refreshAll);
    row.querySelector('.jumlah-input').addEventListener('input', refreshAll);
    row.querySelector('.remove-btn').addEventListener('click', () => {
        row.remove(); reindex(); syncRemoveButtons(); refreshAll();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    // Bind initial row
    document.querySelectorAll('.item-row').forEach(bindRow);
    document.getElementById('tanggal_pinjam')?.addEventListener('change', refreshAll);
    document.getElementById('tanggal_kembali')?.addEventListener('change', refreshAll);

    document.getElementById('add-item-btn').addEventListener('click', () => {
        const row = createRow(itemCount++);
        document.getElementById('items-container').appendChild(row);
        bindRow(row);
        syncRemoveButtons();
        refreshAll();
        row.querySelector('.alat-select').focus();
    });

    refreshAll();
});
</script>
<script src="assets/js/main.js"></script>
</body>
</html>
