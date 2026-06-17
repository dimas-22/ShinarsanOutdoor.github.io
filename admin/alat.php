<?php
require_once 'auth.php';
require_once '../config/database.php';
$db = getDB();

$alert = '';

// ===== HAPUS ALAT =====
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    // Cek apakah ada peminjaman aktif
    $cek = $db->query("SELECT COUNT(*) as c FROM peminjaman WHERE id_alat=$id AND status IN ('menunggu','disetujui','dipinjam')")->fetch_assoc()['c'];
    if ($cek > 0) {
        $alert = '<div class="alert alert-danger">❌ Tidak bisa menghapus alat yang sedang dalam proses peminjaman aktif!</div>';
    } else {
        // Hapus foto jika ada
        $alat_del = $db->query("SELECT foto FROM alat WHERE id_alat=$id")->fetch_assoc();
        if ($alat_del && $alat_del['foto'] && file_exists('../assets/images/' . $alat_del['foto'])) {
            unlink('../assets/images/' . $alat_del['foto']);
        }
        $db->query("DELETE FROM alat WHERE id_alat=$id");
        $alert = '<div class="alert alert-success" data-auto-close>✅ Alat berhasil dihapus!</div>';
    }
}

// ===== TAMBAH / EDIT ALAT =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_alat    = intval($_POST['id_alat'] ?? 0);
    $nama_alat  = trim($_POST['nama_alat'] ?? '');
    $kategori   = trim($_POST['kategori'] ?? '');
    $stok       = intval($_POST['stok'] ?? 0);
    $harga_sewa = floatval(str_replace(['.', ','], ['', '.'], $_POST['harga_sewa'] ?? 0));
    $deskripsi  = trim($_POST['deskripsi'] ?? '');
    $status     = $_POST['status'] ?? 'aktif';

    $errors = [];
    if (empty($nama_alat)) $errors[] = 'Nama alat wajib diisi';
    if (empty($kategori)) $errors[] = 'Kategori wajib diisi';
    if ($stok < 0) $errors[] = 'Stok tidak boleh negatif';
    if ($harga_sewa <= 0) $errors[] = 'Harga sewa harus diisi';

    if (empty($errors)) {
        // Handle upload foto
        $foto_name = null;
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                $errors[] = 'Format foto tidak didukung (jpg, jpeg, png, webp)';
            } elseif ($_FILES['foto']['size'] > 2 * 1024 * 1024) {
                $errors[] = 'Ukuran foto maksimal 2MB';
            } else {
                $foto_name = 'alat_' . time() . '.' . $ext;
                $dest = '../assets/images/' . $foto_name;
                if (!is_dir('../assets/images')) mkdir('../assets/images', 0755, true);
                move_uploaded_file($_FILES['foto']['tmp_name'], $dest);
            }
        }

        if (empty($errors)) {
            $nama_s = $db->real_escape_string($nama_alat);
            $kat_s  = $db->real_escape_string($kategori);
            $desk_s = $db->real_escape_string($deskripsi);
            $stat_s = $db->real_escape_string($status);

            if ($id_alat > 0) {
                // Update
                $foto_sql = $foto_name ? ", foto='$foto_name'" : '';
                $db->query("UPDATE alat SET nama_alat='$nama_s', kategori='$kat_s', stok=$stok, 
                            harga_sewa=$harga_sewa, deskripsi='$desk_s', status='$stat_s' $foto_sql 
                            WHERE id_alat=$id_alat");
                $alert = '<div class="alert alert-success" data-auto-close>✅ Data alat berhasil diperbarui!</div>';
            } else {
                // Insert
                $foto_sql = $foto_name ? "'$foto_name'" : 'NULL';
                $db->query("INSERT INTO alat (nama_alat, kategori, stok, harga_sewa, deskripsi, foto, status)
                            VALUES ('$nama_s','$kat_s',$stok,$harga_sewa,'$desk_s',$foto_sql,'$stat_s')");
                $alert = '<div class="alert alert-success" data-auto-close>✅ Alat baru berhasil ditambahkan!</div>';
            }
        }
    }

    if (!empty($errors)) {
        $alert = '<div class="alert alert-danger">❌ ' . implode('<br>• ', $errors) . '</div>';
    }
}

// ===== AMBIL DATA EDIT =====
$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $res = $db->query("SELECT * FROM alat WHERE id_alat=$edit_id");
    $edit_data = $res ? $res->fetch_assoc() : null;
}

// ===== AMBIL SEMUA ALAT =====
$search = isset($_GET['q']) ? $db->real_escape_string(trim($_GET['q'])) : '';
$filter_kat = isset($_GET['kategori']) ? $db->real_escape_string($_GET['kategori']) : '';

$where = "1=1";
if ($search) $where .= " AND (nama_alat LIKE '%$search%' OR deskripsi LIKE '%$search%')";
if ($filter_kat) $where .= " AND kategori='$filter_kat'";

$alat_result = $db->query("SELECT * FROM alat WHERE $where ORDER BY status DESC, kategori, nama_alat");
$alat_list = $alat_result ? $alat_result->fetch_all(MYSQLI_ASSOC) : [];

// Kategori list
$kat_result = $db->query("SELECT DISTINCT kategori FROM alat ORDER BY kategori");
$kat_list = $kat_result ? $kat_result->fetch_all(MYSQLI_ASSOC) : [];

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
    <title>Kelola Alat - Admin SepakatOutdoor</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="admin-layout">
    <?php include 'includes/sidebar.php'; ?>

    <div class="admin-content">
        <!-- Topbar -->
        <div class="admin-topbar">
            <div style="display:flex;align-items:center;gap:16px;">
                <button class="sidebar-toggle" style="background:none;border:none;cursor:pointer;font-size:1.3rem;color:var(--text-primary);">☰</button>
                <div class="topbar-title">🎒 Kelola Data Alat</div>
            </div>
            <div class="admin-user">
                <div class="admin-avatar"><?= strtoupper(substr($_SESSION['admin_name'],0,1)) ?></div>
                <span><?= htmlspecialchars($_SESSION['admin_name']) ?></span>
            </div>
        </div>

        <div class="admin-main">
            <?= $alert ?>

            <div style="display:grid;grid-template-columns:1fr 380px;gap:24px;align-items:start;">

                <!-- ============ TABLE ALAT ============ -->
                <div>
                    <div class="data-card">
                        <div class="data-card-header">
                            <div class="data-card-title">📦 Daftar Alat (<?= count($alat_list) ?>)</div>
                            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                                <!-- Search -->
                                <form method="GET" style="display:flex;gap:8px;">
                                    <div class="search-box">
                                        <span class="search-icon">🔍</span>
                                        <input type="text" name="q" id="table-search" class="form-control" 
                                               placeholder="Cari alat..." value="<?= htmlspecialchars($search) ?>"
                                               style="padding:8px 12px 8px 36px;font-size:0.85rem;">
                                    </div>
                                    <select name="kategori" class="form-control" style="padding:8px;font-size:0.85rem;width:auto;" onchange="this.form.submit()">
                                        <option value="">Semua Kategori</option>
                                        <?php foreach ($kat_list as $k): ?>
                                        <option value="<?= htmlspecialchars($k['kategori']) ?>" <?= $filter_kat==$k['kategori']?'selected':'' ?>>
                                            <?= htmlspecialchars($k['kategori']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary">Cari</button>
                                    <?php if ($search || $filter_kat): ?>
                                    <a href="alat.php" class="btn btn-sm btn-secondary">Reset</a>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Alat</th>
                                        <th>Kategori</th>
                                        <th>Stok</th>
                                        <th>Harga/Hari</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($alat_list)): ?>
                                    <tr><td colspan="7">
                                        <div class="empty-state">
                                            <div class="empty-icon">📦</div>
                                            <div class="empty-title">Belum ada alat</div>
                                            <p>Tambahkan alat baru menggunakan formulir di samping</p>
                                        </div>
                                    </td></tr>
                                    <?php else: ?>
                                    <?php foreach ($alat_list as $i => $a): ?>
                                    <tr>
                                        <td style="color:var(--text-muted);font-size:0.8rem;"><?= $i+1 ?></td>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:10px;">
                                                <?php if ($a['foto'] && file_exists('../assets/images/'.$a['foto'])): ?>
                                                <img src="../assets/images/<?= htmlspecialchars($a['foto']) ?>" 
                                                     style="width:36px;height:36px;object-fit:cover;border-radius:8px;">
                                                <?php else: ?>
                                                <div style="width:36px;height:36px;background:rgba(26,122,74,0.15);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">
                                                    <?= $kategori_icon[$a['kategori']] ?? '🏔️' ?>
                                                </div>
                                                <?php endif; ?>
                                                <div>
                                                    <div style="font-weight:600;font-size:0.9rem;"><?= htmlspecialchars($a['nama_alat']) ?></div>
                                                    <div style="font-size:0.75rem;color:var(--text-muted);max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                                        <?= htmlspecialchars($a['deskripsi'] ?? '-') ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span style="font-size:0.85rem;"><?= ($kategori_icon[$a['kategori']] ?? '🏔️') . ' ' . htmlspecialchars($a['kategori']) ?></span></td>
                                        <td>
                                            <span style="font-weight:700;color:<?= $a['stok']==0?'var(--danger)':($a['stok']<=2?'var(--warning)':'var(--success)') ?>">
                                                <?= $a['stok'] ?>
                                            </span>
                                        </td>
                                        <td style="font-size:0.85rem;color:var(--secondary);font-weight:600;"><?= formatRupiah($a['harga_sewa']) ?></td>
                                        <td>
                                            <?php if ($a['status']=='aktif'): ?>
                                            <span class="badge badge-dipinjam">✅ Aktif</span>
                                            <?php else: ?>
                                            <span class="badge badge-ditolak">❌ Nonaktif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-btns">
                                                <a href="alat.php?edit=<?= $a['id_alat'] ?>" class="btn btn-sm" 
                                                   style="background:rgba(59,130,246,0.15);color:#93c5fd;border:1px solid rgba(59,130,246,0.3);">
                                                    ✏️ Edit
                                                </a>
                                                <a href="alat.php?hapus=<?= $a['id_alat'] ?>" 
                                                   class="btn btn-sm btn-confirm-delete"
                                                   style="background:rgba(239,68,68,0.15);color:#fca5a5;border:1px solid rgba(239,68,68,0.3);">
                                                    🗑️
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ============ FORM TAMBAH/EDIT ============ -->
                <div class="data-card" style="position:sticky;top:80px;">
                    <div class="data-card-header">
                        <div class="data-card-title">
                            <?= $edit_data ? '✏️ Edit Alat' : '➕ Tambah Alat Baru' ?>
                        </div>
                        <?php if ($edit_data): ?>
                        <a href="alat.php" class="btn btn-sm btn-secondary">✕ Batal</a>
                        <?php endif; ?>
                    </div>
                    <div style="padding: 20px;">
                        <form method="POST" action="alat.php" enctype="multipart/form-data">
                            <input type="hidden" name="id_alat" value="<?= $edit_data['id_alat'] ?? 0 ?>">

                            <div class="form-group">
                                <label class="form-label">Nama Alat <span>*</span></label>
                                <input type="text" name="nama_alat" class="form-control" required
                                    placeholder="Contoh: Tenda Dome 3 Orang"
                                    value="<?= htmlspecialchars($edit_data['nama_alat'] ?? '') ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Kategori <span>*</span></label>
                                <select name="kategori" class="form-control" required>
                                    <option value="">-- Pilih kategori --</option>
                                    <?php
                                    $cats = ['Tenda','Carrier','Sleeping Bag','Masak','Tidur','Aksesoris','Pakaian'];
                                    foreach ($cats as $c):
                                        $sel = ($edit_data['kategori'] ?? '') == $c ? 'selected' : '';
                                    ?>
                                    <option value="<?= $c ?>" <?= $sel ?>><?= ($kategori_icon[$c]??'🏔️') . ' ' . $c ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                <div class="form-group">
                                    <label class="form-label">Stok <span>*</span></label>
                                    <input type="number" name="stok" class="form-control" min="0" required
                                        value="<?= $edit_data['stok'] ?? 0 ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Harga/Hari (Rp) <span>*</span></label>
                                    <input type="number" name="harga_sewa" class="form-control" min="0" required
                                        value="<?= $edit_data['harga_sewa'] ?? '' ?>" placeholder="50000">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Deskripsi</label>
                                <textarea name="deskripsi" class="form-control" rows="3"
                                    placeholder="Deskripsi singkat alat..."><?= htmlspecialchars($edit_data['deskripsi'] ?? '') ?></textarea>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Foto Alat</label>
                                <?php if (!empty($edit_data['foto']) && file_exists('../assets/images/'.$edit_data['foto'])): ?>
                                <div style="margin-bottom:8px;">
                                    <img src="../assets/images/<?= htmlspecialchars($edit_data['foto']) ?>" 
                                         style="width:60px;height:60px;object-fit:cover;border-radius:8px;border:1px solid var(--card-border);">
                                </div>
                                <?php endif; ?>
                                <input type="file" name="foto" class="form-control" accept="image/*"
                                    style="padding:8px;">
                                <div class="form-hint">JPG/PNG/WEBP, maks 2MB. Kosongkan jika tidak ingin mengubah foto.</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control">
                                    <option value="aktif" <?= ($edit_data['status']??'aktif')=='aktif'?'selected':'' ?>>✅ Aktif</option>
                                    <option value="nonaktif" <?= ($edit_data['status']??'')=='nonaktif'?'selected':'' ?>>❌ Nonaktif</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary btn-block">
                                <?= $edit_data ? '💾 Simpan Perubahan' : '➕ Tambah Alat' ?>
                            </button>
                        </form>
                    </div>
                </div>

            </div><!-- end grid -->
        </div><!-- admin-main -->
    </div><!-- admin-content -->
</div>

<script src="../assets/js/main.js"></script>
</body>
</html>
