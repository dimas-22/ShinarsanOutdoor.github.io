<?php
// ============================================================
// Konfigurasi Database
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sewa_alat_outdoor');

function getDB() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die('<div style="font-family:sans-serif;padding:40px;text-align:center;">
                <h2 style="color:#e74c3c;">⚠️ Koneksi Database Gagal</h2>
                <p>Pastikan MySQL berjalan dan database <strong>sewa_alat_outdoor</strong> sudah dibuat.</p>
                <p>Error: ' . $conn->connect_error . '</p>
                <a href="../setup.sql" style="color:#2ecc71;">Download setup.sql</a>
            </div>');
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

// Helper: Sanitize input
function sanitize($data) {
    $db = getDB();
    return htmlspecialchars(strip_tags(trim($db->real_escape_string($data))));
}

// Helper: Format Rupiah
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Helper: Format Tanggal Indonesia
function formatTanggal($tanggal) {
    $bulan = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',
        5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',
        9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
    ];
    $t = strtotime($tanggal);
    return date('d', $t) . ' ' . $bulan[date('n', $t)] . ' ' . date('Y', $t);
}

// Helper: Hitung hari
function hitungHari($tgl_pinjam, $tgl_kembali) {
    $diff = (strtotime($tgl_kembali) - strtotime($tgl_pinjam)) / (60*60*24);
    return max(1, $diff);
}
