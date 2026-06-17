-- ============================================================
-- DATABASE: sewa_alat_outdoor
-- Sistem Informasi Peminjaman Alat Gunung Berbasis Web
-- ============================================================

CREATE DATABASE IF NOT EXISTS sewa_alat_outdoor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sewa_alat_outdoor;

-- Tabel Admin
CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Alat
CREATE TABLE IF NOT EXISTS alat (
    id_alat INT AUTO_INCREMENT PRIMARY KEY,
    nama_alat VARCHAR(100) NOT NULL,
    kategori VARCHAR(50),
    stok INT NOT NULL DEFAULT 0,
    harga_sewa DECIMAL(10,2) NOT NULL DEFAULT 0,
    deskripsi TEXT,
    foto VARCHAR(255) DEFAULT NULL,
    status ENUM('aktif','nonaktif') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabel Peminjaman
CREATE TABLE IF NOT EXISTS peminjaman (
    id_pinjam INT AUTO_INCREMENT PRIMARY KEY,
    nama_peminjam VARCHAR(100) NOT NULL,
    no_hp VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    id_alat INT NOT NULL,
    jumlah INT NOT NULL DEFAULT 1,
    tanggal_pinjam DATE NOT NULL,
    tanggal_kembali DATE NOT NULL,
    total_hari INT,
    total_biaya DECIMAL(12,2),
    status ENUM('menunggu','disetujui','dipinjam','selesai','ditolak') DEFAULT 'menunggu',
    catatan TEXT,
    alasan_tolak TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_alat) REFERENCES alat(id_alat) ON DELETE CASCADE
);

-- Data Admin Default (password: admin123)
INSERT INTO admin (username, password, nama_lengkap) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator');

-- Data Alat Sample
INSERT INTO alat (nama_alat, kategori, stok, harga_sewa, deskripsi) VALUES
('Tenda Dome 3 Orang', 'Tenda', 5, 50000, 'Tenda dome kapasitas 3 orang, waterproof, cocok untuk pendakian gunung. Dilengkapi dengan flysheet dan groundsheet.'),
('Tenda Dome 2 Orang', 'Tenda', 3, 35000, 'Tenda dome ringan kapasitas 2 orang, ideal untuk solo atau couple trekking.'),
('Carrier 60L', 'Carrier', 8, 40000, 'Carrier/ransel gunung 60 liter dengan frame internal, dilengkapi rain cover. Nyaman untuk perjalanan 3-5 hari.'),
('Carrier 45L', 'Carrier', 6, 30000, 'Carrier 45 liter yang ringan dan ergonomis, cocok untuk pendakian 1-2 hari.'),
('Sleeping Bag -5°C', 'Sleeping Bag', 10, 25000, 'Sleeping bag rating -5°C, bahan polar berkualitas, ukuran kompak saat dikemas.'),
('Sleeping Bag 0°C', 'Sleeping Bag', 8, 20000, 'Sleeping bag rating 0°C, ringan dan hangat untuk pendakian di gunung Indonesia.'),
('Kompor Portable', 'Masak', 7, 20000, 'Kompor portable gas, efisien dan ringan, cocok untuk memasak di alam terbuka.'),
('Nesting Set 2-4 Orang', 'Masak', 5, 15000, 'Set peralatan masak (panci, wajan, spatula) untuk 2-4 orang pendaki.'),
('Matras Gulung', 'Tidur', 15, 10000, 'Matras gulung foam, ringan dan tahan lama, ukuran standar 180x60cm.'),
('Headlamp LED', 'Aksesoris', 12, 15000, 'Headlamp LED terang, tahan air, dengan baterai. Wajib untuk pendakian malam.'),
('Jas Hujan Ponco', 'Pakaian', 10, 10000, 'Jas hujan ponco yang lebar, bisa menutup carrier, waterproof sepenuhnya.'),
('Tongkat Trekking', 'Aksesoris', 20, 10000, 'Sepasang tongkat trekking adjustable, aluminium ringan, dengan grip ergonomis.');
