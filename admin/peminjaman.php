<?php
require_once 'auth.php';
require_once '../config/database.php';
$db = getDB();

$alert = '';

// ===== UBAH STATUS =====
if (isset($_GET['aksi']) && isset($_GET['id'])) {
    $id    = intval($_GET['id']);
    $aksi  = $_GET['aksi'];
    $valid = ['setujui','tolak','proses','selesai'];

    if (in_array($aksi, $valid)) {
        $status_map = [
            'setujui' => 'disetujui',
            'tolak'   => 'ditolak',
            'proses'  => 'dipinjam',
            'selesai' => 'selesai',
        ];
        $new_status = $status_map[$aksi];
        $db->query("UPDATE peminjaman SET status='$new_status' WHERE id_pinjam=$id");

        // Cek apakah ada detail multi-item
        $detail_items = [];
        $dr = $db->query("SELECT id_alat, jumlah FROM peminjaman_detail WHERE id_pinjam=$id");
        if ($dr && $dr->num_rows > 0) {
            $detail_items = $dr->fetch_all(MYSQLI_ASSOC);
        }

        // Jika disetujui => kurangi stok
        if ($aksi === 'setujui') {
            if (!empty($detail_items)) {
                foreach ($detail_items as $di) {
                    $db->query("UPDATE alat SET stok = stok - {$di['jumlah']} WHERE id_alat={$di['id_alat']} AND stok >= {$di['jumlah']}");
                }
            } else {
                $pm_row = $db->query("SELECT id_alat, jumlah FROM peminjaman WHERE id_pinjam=$id")->fetch_assoc();
                if ($pm_row) $db->query("UPDATE alat SET stok = stok - {$pm_row['jumlah']} WHERE id_alat={$pm_row['id_alat']} AND stok >= {$pm_row['jumlah']}");
            }
        }
        // Jika selesai => kembalikan stok
        if ($aksi === 'selesai') {
            if (!empty($detail_items)) {
                foreach ($detail_items as $di) {
                    $db->query("UPDATE alat SET stok = stok + {$di['jumlah']} WHERE id_alat={$di['id_alat']}");
                }
            } else {
                $pm_row = $db->query("SELECT id_alat, jumlah FROM peminjaman WHERE id_pinjam=$id")->fetch_assoc();
                if ($pm_row) $db->query("UPDATE alat SET stok = stok + {$pm_row['jumlah']} WHERE id_alat={$pm_row['id_alat']}");
            }
        }

        $label = ['setujui'=>'disetujui','tolak'=>'ditolak','proses'=>'dipinjam','selesai'=>'selesai'];
        $alert = '<div class="alert alert-success" data-auto-close>✅ Status peminjaman #' . str_pad($id,4,'0',STR_PAD_LEFT) . ' berhasil diubah menjadi <strong>' . $label[$aksi] . '</strong>!</div>';
    }
}

// ===== HAPUS =====
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    // Cek status — hanya boleh hapus yang ditolak atau menunggu
    $cek = $db->query("SELECT status FROM peminjaman WHERE id_pinjam=$id")->fetch_assoc();
    if ($cek && in_array($cek['status'], ['menunggu', 'ditolak'])) {
        $db->query("DELETE FROM peminjaman WHERE id_pinjam=$id");
        $alert = '<div class="alert alert-success" data-auto-close>🗑️ Data peminjaman #' . str_pad($id,4,'0',STR_PAD_LEFT) . ' berhasil dihapus.</div>';
    } else {
        $alert = '<div class="alert alert-danger">❌ Tidak bisa menghapus peminjaman yang sedang aktif/selesai.</div>';
    }
}

// ===== FILTER =====
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$search        = isset($_GET['q']) ? $db->real_escape_string(trim($_GET['q'])) : '';

$where = "1=1";
if ($filter_status) $where .= " AND p.status='" . $db->real_escape_string($filter_status) . "'";
if ($search)        $where .= " AND (p.nama_peminjam LIKE '%$search%' OR p.no_hp LIKE '%$search%' OR a.nama_alat LIKE '%$search%' OR a2.nama_alat LIKE '%$search%')";

// ===== DETAIL VIEW =====
$detail = null;
$detail_items = [];
if (isset($_GET['detail'])) {
    $did = intval($_GET['detail']);
    $dres = $db->query("
        SELECT p.*, a.nama_alat, a.kategori, a.harga_sewa, a.foto
        FROM peminjaman p JOIN alat a ON p.id_alat = a.id_alat
        WHERE p.id_pinjam=$did
    ");
    $detail = $dres ? $dres->fetch_assoc() : null;
    // Ambil semua item detail
    $dr2 = $db->query("
        SELECT pd.*, a.nama_alat, a.kategori, a.harga_sewa
        FROM peminjaman_detail pd
        JOIN alat a ON pd.id_alat = a.id_alat
        WHERE pd.id_pinjam=$did
        ORDER BY pd.id_detail
    ");
    $detail_items = ($dr2 && $dr2->num_rows > 0) ? $dr2->fetch_all(MYSQLI_ASSOC) : [];
}

// ===== AMBIL DATA LIST =====
$result = $db->query("
    SELECT p.*,
        a.nama_alat, a.kategori, a.harga_sewa,
        GROUP_CONCAT(DISTINCT a2.nama_alat ORDER BY a2.nama_alat SEPARATOR ', ') as alat_names,
        COUNT(DISTINCT pd.id_detail) as jumlah_jenis
    FROM peminjaman p
    JOIN alat a ON p.id_alat = a.id_alat
    LEFT JOIN peminjaman_detail pd ON p.id_pinjam = pd.id_pinjam
    LEFT JOIN alat a2 ON pd.id_alat = a2.id_alat
    WHERE $where
    GROUP BY p.id_pinjam
    ORDER BY p.created_at DESC
");
$list = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Count per status
$counts = [];
foreach (['menunggu','disetujui','dipinjam','selesai','ditolak',''] as $s) {
    $q = $s ? "SELECT COUNT(*) as c FROM peminjaman WHERE status='$s'" : "SELECT COUNT(*) as c FROM peminjaman";
    $counts[$s] = $db->query($q)->fetch_assoc()['c'];
}

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
    <title>Data Peminjaman - Admin ShinarsanOutdoor</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ---- Status Tab Bar ---- */
        .status-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .status-tab {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 20px;
            font-size: 0.82rem;
            font-weight: 600;
            border: 1px solid var(--card-border);
            background: var(--card-bg);
            color: var(--text-muted);
            cursor: pointer;
            text-decoration: none;
            transition: var(--transition);
        }
        .status-tab:hover { color: var(--text-primary); border-color: var(--secondary); }
        .status-tab.active { background: var(--primary); color: #fff; border-color: var(--primary); }
        .status-tab .tab-count {
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            padding: 1px 7px;
            font-size: 0.75rem;
        }
        .status-tab:not(.active) .tab-count {
            background: rgba(255,255,255,0.06);
        }

        /* ---- Action Buttons per Status ---- */
        .aksi-group { display: flex; gap: 6px; flex-wrap: wrap; }
        .btn-aksi {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: var(--transition);
            display: inline-flex; align-items: center; gap: 4px;
        }
        .btn-setujui { background: rgba(16,185,129,0.15); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.3); }
        .btn-setujui:hover { background: rgba(16,185,129,0.3); }
        .btn-proses { background: rgba(59,130,246,0.15); color: #93c5fd; border: 1px solid rgba(59,130,246,0.3); }
        .btn-proses:hover { background: rgba(59,130,246,0.3); }
        .btn-selesai { background: rgba(139,92,246,0.15); color: #c4b5fd; border: 1px solid rgba(139,92,246,0.3); }
        .btn-selesai:hover { background: rgba(139,92,246,0.3); }
        .btn-tolak { background: rgba(239,68,68,0.12); color: #fca5a5; border: 1px solid rgba(239,68,68,0.25); }
        .btn-tolak:hover { background: rgba(239,68,68,0.25); }
        .btn-detail { background: rgba(250,204,21,0.12); color: #fde68a; border: 1px solid rgba(250,204,21,0.25); }
        .btn-detail:hover { background: rgba(250,204,21,0.25); }
        .btn-hapus { background: rgba(239,68,68,0.08); color: #fca5a5; border: 1px solid rgba(239,68,68,0.2); }

        /* ---- Detail Panel ---- */
        .detail-overlay {
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.65);
            backdrop-filter: blur(4px);
            z-index: 999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .detail-overlay.show { display: flex; }
        .detail-panel {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 620px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
        }
        .detail-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 20px 24px;
            border-bottom: 1px solid var(--card-border);
            position: sticky; top: 0; background: var(--card-bg); z-index: 2;
        }
        .detail-body { padding: 24px; }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            font-size: 0.9rem;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: var(--text-muted); }
        .detail-value { font-weight: 600; text-align: right; }
        .detail-total {
            background: rgba(26,122,74,0.12);
            border: 1px solid rgba(26,122,74,0.25);
            border-radius: var(--radius);
            padding: 14px 18px;
            margin: 18px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .detail-total .label { color: var(--text-muted); font-size: 0.85rem; }
        .detail-total .value { font-size: 1.25rem; font-weight: 800; color: var(--secondary); }

        /* ---- Empty state ---- */
        .table-empty {
            text-align: center;
            padding: 64px 20px;
            color: var(--text-muted);
        }
        .table-empty .icon { font-size: 3rem; margin-bottom: 12px; }
        .table-empty .title { font-size: 1rem; font-weight: 600; margin-bottom: 6px; color: var(--text-secondary); }
    </style>
</head>
<body>
<div class="admin-layout">

    <?php include 'includes/sidebar.php'; ?>

    <div class="admin-content">
        <!-- Topbar -->
        <div class="admin-topbar">
            <div style="display:flex;align-items:center;gap:16px;">
                <button class="sidebar-toggle" style="background:none;border:none;cursor:pointer;font-size:1.3rem;color:var(--text-primary);">☰</button>
                <div class="topbar-title">📋 Data Peminjaman</div>
            </div>
            <div class="admin-user">
                <div class="admin-avatar"><?= strtoupper(substr($_SESSION['admin_name'],0,1)) ?></div>
                <span><?= htmlspecialchars($_SESSION['admin_name']) ?></span>
            </div>
        </div>

        <div class="admin-main">
            <?= $alert ?>

            <!-- Status Tabs -->
            <div class="status-tabs">
                <a href="peminjaman.php<?= $search ? '?q='.urlencode($_GET['q']) : '' ?>"
                   class="status-tab <?= !$filter_status ? 'active' : '' ?>">
                    🗂️ Semua <span class="tab-count"><?= $counts[''] ?></span>
                </a>
                <a href="peminjaman.php?status=menunggu<?= $search ? '&q='.urlencode($_GET['q']) : '' ?>"
                   class="status-tab <?= $filter_status=='menunggu' ? 'active' : '' ?>"
                   style="<?= $filter_status=='menunggu' ? '' : ($counts['menunggu']>0 ? 'color:#fcd34d;border-color:rgba(245,158,11,0.4);' : '') ?>">
                    ⏳ Menunggu <span class="tab-count"><?= $counts['menunggu'] ?></span>
                </a>
                <a href="peminjaman.php?status=disetujui<?= $search ? '&q='.urlencode($_GET['q']) : '' ?>"
                   class="status-tab <?= $filter_status=='disetujui' ? 'active' : '' ?>">
                    ✅ Disetujui <span class="tab-count"><?= $counts['disetujui'] ?></span>
                </a>
                <a href="peminjaman.php?status=dipinjam<?= $search ? '&q='.urlencode($_GET['q']) : '' ?>"
                   class="status-tab <?= $filter_status=='dipinjam' ? 'active' : '' ?>">
                    🎒 Dipinjam <span class="tab-count"><?= $counts['dipinjam'] ?></span>
                </a>
                <a href="peminjaman.php?status=selesai<?= $search ? '&q='.urlencode($_GET['q']) : '' ?>"
                   class="status-tab <?= $filter_status=='selesai' ? 'active' : '' ?>">
                    🏁 Selesai <span class="tab-count"><?= $counts['selesai'] ?></span>
                </a>
                <a href="peminjaman.php?status=ditolak<?= $search ? '&q='.urlencode($_GET['q']) : '' ?>"
                   class="status-tab <?= $filter_status=='ditolak' ? 'active' : '' ?>">
                    ❌ Ditolak <span class="tab-count"><?= $counts['ditolak'] ?></span>
                </a>
            </div>

            <!-- Data Card -->
            <div class="data-card">
                <div class="data-card-header">
                    <div class="data-card-title">
                        📦 Daftar Peminjaman
                        <span style="font-size:0.8rem;font-weight:400;color:var(--text-muted);">(<?= count($list) ?> data)</span>
                    </div>
                    <!-- Search -->
                    <form method="GET" style="display:flex;gap:8px;align-items:center;">
                        <?php if ($filter_status): ?>
                        <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
                        <?php endif; ?>
                        <div class="search-box">
                            <span class="search-icon">🔍</span>
                            <input type="text" name="q" class="form-control"
                                   placeholder="Cari nama / HP / alat..."
                                   value="<?= htmlspecialchars($search) ?>"
                                   style="padding:8px 12px 8px 36px;font-size:0.85rem;">
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary">Cari</button>
                        <?php if ($search): ?>
                        <a href="peminjaman.php<?= $filter_status ? '?status='.$filter_status : '' ?>" class="btn btn-sm btn-secondary">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Peminjam</th>
                                <th>Alat</th>
                                <th>Tgl Pinjam</th>
                                <th>Tgl Kembali</th>
                                <th>Durasi</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th style="min-width:200px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($list)): ?>
                            <tr><td colspan="9">
                                <div class="table-empty">
                                    <div class="icon">📋</div>
                                    <div class="title">Tidak ada data peminjaman</div>
                                    <p>Belum ada catatan peminjaman<?= $filter_status ? ' dengan status ini' : '' ?>.</p>
                                </div>
                            </td></tr>
                            <?php else: ?>
                            <?php foreach ($list as $pm): ?>
                            <tr>
                                <td>
                                    <span style="font-family:monospace;color:var(--text-muted);font-size:0.8rem;">
                                        #<?= str_pad($pm['id_pinjam'],4,'0',STR_PAD_LEFT) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight:600;font-size:0.9rem;"><?= htmlspecialchars($pm['nama_peminjam']) ?></div>
                                    <div style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($pm['no_hp']) ?></div>
                                </td>
                                <td>
                                    <?php
                                        $display_alat = ($pm['jumlah_jenis'] > 1 && $pm['alat_names'])
                                            ? $pm['alat_names']
                                            : $pm['nama_alat'];
                                        $display_alat = mb_strlen($display_alat) > 45 ? mb_substr($display_alat, 0, 45) . '…' : $display_alat;
                                    ?>
                                    <div style="font-size:0.85rem;font-weight:500;"><?= htmlspecialchars($display_alat) ?></div>
                                    <div style="font-size:0.75rem;color:var(--text-muted);">
                                        <?php if ($pm['jumlah_jenis'] > 1): ?>
                                            🎒 <?= $pm['jumlah_jenis'] ?> jenis alat
                                        <?php else: ?>
                                            <?= ($kategori_icon[$pm['kategori']] ?? '🏔️') . ' ' . htmlspecialchars($pm['kategori']) ?>
                                            &nbsp;·&nbsp; <?= $pm['jumlah'] ?> unit
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="font-size:0.82rem;color:var(--text-muted);"><?= formatTanggal($pm['tanggal_pinjam']) ?></td>
                                <td style="font-size:0.82rem;color:var(--text-muted);"><?= formatTanggal($pm['tanggal_kembali']) ?></td>
                                <td style="font-size:0.85rem;text-align:center;">
                                    <span style="background:rgba(59,130,246,0.1);color:#93c5fd;padding:2px 8px;border-radius:10px;font-size:0.78rem;">
                                        <?= $pm['total_hari'] ?> hari
                                    </span>
                                </td>
                                <td style="font-size:0.85rem;font-weight:700;color:var(--secondary);">
                                    <?= formatRupiah($pm['total_biaya']) ?>
                                </td>
                                <td>
                                    <span class="badge <?= $badge_class[$pm['status']] ?? 'badge-menunggu' ?>">
                                        <?= $badge_label[$pm['status']] ?? $pm['status'] ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="aksi-group">
                                        <!-- Detail -->
                                        <a href="peminjaman.php?detail=<?= $pm['id_pinjam'] ?><?= $filter_status ? '&status='.$filter_status : '' ?><?= $search ? '&q='.urlencode($search) : '' ?>"
                                           class="btn-aksi btn-detail">🔍</a>

                                        <?php if ($pm['status'] === 'menunggu'): ?>
                                            <a href="peminjaman.php?aksi=setujui&id=<?= $pm['id_pinjam'] ?><?= $filter_status ? '&status='.$filter_status : '' ?>"
                                               class="btn-aksi btn-setujui"
                                               onclick="return confirm('Setujui peminjaman ini? Stok alat akan berkurang.')">✅ Setujui</a>
                                            <a href="peminjaman.php?aksi=tolak&id=<?= $pm['id_pinjam'] ?><?= $filter_status ? '&status='.$filter_status : '' ?>"
                                               class="btn-aksi btn-tolak"
                                               onclick="return confirm('Tolak pengajuan peminjaman ini?')">❌ Tolak</a>

                                        <?php elseif ($pm['status'] === 'disetujui'): ?>
                                            <a href="peminjaman.php?aksi=proses&id=<?= $pm['id_pinjam'] ?><?= $filter_status ? '&status='.$filter_status : '' ?>"
                                               class="btn-aksi btn-proses"
                                               onclick="return confirm('Tandai alat sudah diambil (Dipinjam)?')">🎒 Dipinjam</a>

                                        <?php elseif ($pm['status'] === 'dipinjam'): ?>
                                            <a href="peminjaman.php?aksi=selesai&id=<?= $pm['id_pinjam'] ?><?= $filter_status ? '&status='.$filter_status : '' ?>"
                                               class="btn-aksi btn-selesai"
                                               onclick="return confirm('Tandai alat sudah dikembalikan? Stok akan bertambah kembali.')">🏁 Selesai</a>

                                        <?php elseif (in_array($pm['status'], ['menunggu','ditolak'])): ?>
                                            <a href="peminjaman.php?hapus=<?= $pm['id_pinjam'] ?><?= $filter_status ? '&status='.$filter_status : '' ?>"
                                               class="btn-aksi btn-hapus"
                                               onclick="return confirm('Hapus data peminjaman ini?')">🗑️</a>
                                        <?php endif; ?>

                                        <?php if ($pm['status'] === 'ditolak'): ?>
                                            <a href="peminjaman.php?hapus=<?= $pm['id_pinjam'] ?><?= $filter_status ? '&status='.$filter_status : '' ?>"
                                               class="btn-aksi btn-hapus"
                                               onclick="return confirm('Hapus data ini?')">🗑️</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div><!-- /data-card -->

        </div><!-- /admin-main -->
    </div><!-- /admin-content -->
</div>

<!-- ============= DETAIL MODAL ============= -->
<?php if ($detail): ?>
<div class="detail-overlay show" id="detail-modal">
    <div class="detail-panel animate-fade-up">
        <div class="detail-header">
            <div>
                <div style="font-family:var(--font-heading);font-size:1.1rem;font-weight:800;">
                    📋 Detail Peminjaman
                    <span style="font-family:monospace;font-size:0.9rem;color:var(--text-muted);">
                        #<?= str_pad($detail['id_pinjam'],4,'0',STR_PAD_LEFT) ?>
                    </span>
                </div>
                <div style="margin-top:4px;">
                    <span class="badge <?= $badge_class[$detail['status']] ?? 'badge-menunggu' ?>">
                        <?= $badge_label[$detail['status']] ?? $detail['status'] ?>
                    </span>
                </div>
            </div>
            <a href="peminjaman.php?<?= $filter_status ? 'status='.$filter_status.'&' : '' ?><?= $search ? 'q='.urlencode($search) : '' ?>"
               style="font-size:1.4rem;color:var(--text-muted);line-height:1;text-decoration:none;">✕</a>
        </div>
        <div class="detail-body">

            <!-- Peminjam -->
            <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:.06em;color:var(--primary);font-weight:700;margin-bottom:10px;">
                👤 Data Peminjam
            </div>
            <div class="detail-row">
                <span class="detail-label">Nama Lengkap</span>
                <span class="detail-value"><?= htmlspecialchars($detail['nama_peminjam']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">No. HP / WA</span>
                <span class="detail-value">
                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/','',$detail['no_hp']) ?>"
                       target="_blank" style="color:var(--secondary);">
                        <?= htmlspecialchars($detail['no_hp']) ?> 💬
                    </a>
                </span>
            </div>
            <?php if (!empty($detail['email'])): ?>
            <div class="detail-row">
                <span class="detail-label">Email</span>
                <span class="detail-value"><?= htmlspecialchars($detail['email']) ?></span>
            </div>
            <?php endif; ?>

            <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:.06em;color:var(--primary);font-weight:700;margin:20px 0 10px;">
                📅 Waktu Peminjaman
            </div>
            <div class="detail-row">
                <span class="detail-label">Tanggal Pinjam</span>
                <span class="detail-value"><?= formatTanggal($detail['tanggal_pinjam']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Tanggal Kembali</span>
                <span class="detail-value"><?= formatTanggal($detail['tanggal_kembali']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Durasi</span>
                <span class="detail-value"><?= $detail['total_hari'] ?> hari</span>
            </div>

            <!-- Daftar Alat -->
            <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:.06em;color:var(--primary);font-weight:700;margin:20px 0 10px;">
                🎒 Daftar Alat (<?= !empty($detail_items) ? count($detail_items) : 1 ?> item)
            </div>
            <?php if (!empty($detail_items)): ?>
                <?php foreach ($detail_items as $di): ?>
                <div style="background:rgba(255,255,255,.03);border:1px solid var(--card-border);border-radius:8px;padding:10px 14px;margin-bottom:8px;">
                    <div style="display:flex;justify-content:space-between;align-items:start;gap:12px;">
                        <div>
                            <div style="font-weight:600;font-size:.88rem;">
                                <?= ($kategori_icon[$di['kategori']] ?? '🏔️') . ' ' . htmlspecialchars($di['nama_alat']) ?>
                            </div>
                            <div style="font-size:.75rem;color:var(--text-muted);margin-top:2px;">
                                <?= $di['jumlah'] ?> unit × <?= formatRupiah($di['harga_sewa']) ?>/hari
                            </div>
                        </div>
                        <div style="text-align:right;flex-shrink:0;">
                            <div style="font-weight:700;color:var(--secondary);font-size:.9rem;"><?= formatRupiah($di['subtotal']) ?></div>
                            <div style="font-size:.7rem;color:var(--text-muted);">total <?= $detail['total_hari'] ?> hari</div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="detail-row">
                    <span class="detail-label"><?= htmlspecialchars($detail['nama_alat']) ?></span>
                    <span class="detail-value"><?= $detail['jumlah'] ?> unit × <?= formatRupiah($detail['harga_sewa']) ?></span>
                </div>
            <?php endif; ?>

            <!-- Total -->
            <div class="detail-total">
                <div>
                    <div class="label">Total Biaya Sewa</div>
                    <div style="font-size:0.75rem;color:var(--text-muted);margin-top:2px;">
                        <?= !empty($detail_items) ? count($detail_items) . ' jenis alat' : ($detail['jumlah'] . ' unit') ?>
                        × <?= $detail['total_hari'] ?> hari
                    </div>
                </div>
                <div class="value"><?= formatRupiah($detail['total_biaya']) ?></div>
            </div>

            <?php if (!empty($detail['catatan'])): ?>
            <div style="background:rgba(255,255,255,0.04);border:1px solid var(--card-border);border-radius:var(--radius);padding:12px 14px;margin-bottom:16px;">
                <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:4px;">📝 Catatan dari Peminjam</div>
                <div style="font-size:0.9rem;"><?= nl2br(htmlspecialchars($detail['catatan'])) ?></div>
            </div>
            <?php endif; ?>

            <?php if (!empty($detail['bukti_dp'])): ?>
            <div style="background:rgba(26,122,74,0.05);border:1px solid rgba(26,122,74,0.2);border-radius:var(--radius);padding:12px 14px;margin-bottom:16px;">
                <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:8px;">💳 Bukti Transfer DP</div>
                <?php $ext = strtolower(pathinfo($detail['bukti_dp'], PATHINFO_EXTENSION)); ?>
                <?php if (in_array($ext, ['pdf'])): ?>
                    <a href="../assets/uploads/dp/<?= htmlspecialchars($detail['bukti_dp']) ?>" target="_blank" class="btn btn-secondary btn-sm" style="display:inline-block;">📄 Lihat PDF Bukti DP</a>
                <?php else: ?>
                    <a href="../assets/uploads/dp/<?= htmlspecialchars($detail['bukti_dp']) ?>" target="_blank">
                        <img src="../assets/uploads/dp/<?= htmlspecialchars($detail['bukti_dp']) ?>" alt="Bukti DP" style="max-width:100%;max-height:200px;border-radius:4px;border:1px solid var(--card-border);">
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:16px;">
                🕐 Diajukan: <?= date('d M Y H:i', strtotime($detail['created_at'])) ?>
            </div>

            <!-- Action Buttons in Detail -->
            <div style="display:flex;flex-wrap:wrap;gap:10px;">
                <?php if ($detail['status'] === 'menunggu'): ?>
                    <a href="peminjaman.php?aksi=setujui&id=<?= $detail['id_pinjam'] ?>"
                       class="btn btn-primary" style="flex:1;"
                       onclick="return confirm('Setujui peminjaman ini?')">✅ Setujui</a>
                    <a href="peminjaman.php?aksi=tolak&id=<?= $detail['id_pinjam'] ?>"
                       class="btn btn-secondary" style="flex:1;border-color:rgba(239,68,68,0.3);color:#fca5a5;"
                       onclick="return confirm('Tolak pengajuan ini?')">❌ Tolak</a>
                <?php elseif ($detail['status'] === 'disetujui'): ?>
                    <a href="peminjaman.php?aksi=proses&id=<?= $detail['id_pinjam'] ?>"
                       class="btn btn-primary" style="flex:1;"
                       onclick="return confirm('Tandai alat sudah diambil?')">🎒 Tandai Dipinjam</a>
                <?php elseif ($detail['status'] === 'dipinjam'): ?>
                    <a href="peminjaman.php?aksi=selesai&id=<?= $detail['id_pinjam'] ?>"
                       class="btn btn-primary" style="flex:1;background:rgba(139,92,246,0.8);"
                       onclick="return confirm('Tandai alat sudah dikembalikan?')">🏁 Tandai Selesai</a>
                <?php endif; ?>
                <a href="peminjaman.php?<?= $filter_status ? 'status='.$filter_status.'&' : '' ?><?= $search ? 'q='.urlencode($search) : '' ?>"
                   class="btn btn-secondary" style="flex:0 0 auto;">Tutup</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="../assets/js/main.js"></script>
</body>
</html>
