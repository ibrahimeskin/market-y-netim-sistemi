<?php
// Veritabanı bağlantısı için gerekli bilgiler
$host = 'localhost';
$dbname = 'elektrikliaraclar2';  // Veritabanı adı
$username = 'root';  // Veritabanı kullanıcı adı
$password = 'ibrahiM72+-*'; // Veritabanı şifresi

// PDO ile veritabanına bağlantı kurma
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    // PDO hata modunu ayarlamak
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Bağlantı hatası: " . $e->getMessage();
    exit;
}

// SQL sorgularını yazma

// Hizmet veren şirketleri alıyoruz
$sqlHizmetVerenSirket = "SELECT DISTINCT hizmet_veren_sirket FROM araclar";

// Fiyatları alıyoruz
$sqlFiyat = "SELECT DISTINCT fiyat FROM araclar WHERE fiyat > 0";  // 0'ları hariç tutuyoruz

// Markaları alıyoruz
$sqlMarka = "SELECT DISTINCT marka FROM araclar";

// Araçlar tablosundaki toplam araç sayısını alıyoruz
$sqlToplam = "SELECT COUNT(*) AS total_count FROM araclar";

// Verileri tutacak diziler
$hizmetVerenSirketler = [];
$fiyatlar = [];
$markalar = [];
$toplam = 0;

try {
    // Hizmet veren şirketleri çekiyoruz
    $stmt = $pdo->query($sqlHizmetVerenSirket);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $hizmetVerenSirketler[] = $row['hizmet_veren_sirket']; // Verileri diziye ekliyoruz
    }

    // Fiyatları çekiyoruz
    $stmt = $pdo->query($sqlFiyat);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $fiyatlar[] = $row['fiyat']; // Verileri diziye ekliyoruz
    }

    // Markaları çekiyoruz
    $stmt = $pdo->query($sqlMarka);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $markalar[] = $row['marka']; // Verileri diziye ekliyoruz
    }

    // Araçlar tablosundaki toplam araç sayısını çekiyoruz
    $stmt = $pdo->query($sqlToplam);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $toplam = $row['total_count']; // Araç sayısını alıyoruz

} catch (PDOException $e) {
    echo "Sorgu hatası: " . $e->getMessage();
}

// Veriyi dışa aktarma
return [
    'hizmet_veren_sirketler' => $hizmetVerenSirketler,
    'fiyatlar' => $fiyatlar,
    'markalar' => $markalar,
    'toplam' => $toplam
];
?>
