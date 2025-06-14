<?php
// Veritabanı bağlantısı için gerekli bilgiler
$host = 'localhost';
$dbname = 'diabetes';  // Veritabanı adı
$username = 'root';  // Veritabanı kullanıcı adı
$password = 'ibrahiM72+-*'; // Veritabanı şifresi

$conn = new mysqli($host, $username, $password, $dbname);
// PDO ile veritabanına bağlantı kurma
if ($conn->connect_error) {
    die("Bağlantı hatası: " . $conn->connect_error);
}