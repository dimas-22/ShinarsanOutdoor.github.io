<?php
require_once 'config/database.php';
$db = getDB();
$hash = password_hash('admin123', PASSWORD_BCRYPT);
$db->query("UPDATE admin SET password='$hash' WHERE username='admin'");
echo "Password berhasil diupdate!<br>";
echo "Username: admin<br>";
echo "Password: admin123<br>";
echo "Hash: $hash<br>";
echo "<br><a href='admin/login.php'>→ Ke Halaman Login</a>";
