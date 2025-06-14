<?php

// Oturumları ve hata gösterimini başlat
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- 1. VERİTABANI BAĞLANTI BİLGİLERİ ---
$servername = "localhost";
$username = "root";
$password = "ibrahiM72+-*"; // Lütfen KENDİ MySQL şifrenizle güncelleyin!
$dbname = "SimpleMarketDB";

// Bağlantıyı oluştur ve karakter setini ayarla
$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Veritabanı bağlantısı başarısız: " . $conn->connect_error);
}

// --- 2. FORM İŞLEMLERİ (POST KONTROLÜ) ---
$message = []; // Kullanıcıya gösterilecek mesajlar için dizi

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {

    // Tüm form işlemleri burada yönetilir...
    switch ($_POST['action']) {
        case 'satis_yap':
            // ... (Bu kısım değiştirilmedi) ...
            $urun_id = (int)$_POST['urun_id']; $miktar = (int)$_POST['miktar']; $personel_id = (int)$_POST['personel_id'];
            if ($urun_id > 0 && $miktar > 0 && $personel_id > 0) {
                $stock_check_stmt = $conn->prepare("SELECT Stok, SatışFiyatı FROM Ürün WHERE ÜrünID = ?"); $stock_check_stmt->bind_param("i", $urun_id); $stock_check_stmt->execute(); $result = $stock_check_stmt->get_result()->fetch_assoc(); $current_stock = $result['Stok']; $birim_fiyat = $result['SatışFiyatı']; $stock_check_stmt->close();
                if ($current_stock >= $miktar) {
                    $conn->begin_transaction(); try {
                        $stmt = $conn->prepare("INSERT INTO Satış (PersonelID, ÜrünID, Miktar, BirimFiyat) VALUES (?, ?, ?, ?)"); $stmt->bind_param("iiid", $personel_id, $urun_id, $miktar, $birim_fiyat); if (!$stmt->execute()) { throw new Exception("Satış eklenirken hata: " . $stmt->error); } $stmt->close();
                        $update_stock_stmt = $conn->prepare("UPDATE Ürün SET Stok = Stok - ? WHERE ÜrünID = ?"); $update_stock_stmt->bind_param("ii", $miktar, $urun_id); if (!$update_stock_stmt->execute()) { throw new Exception("Stok güncellenirken hata: " . $update_stock_stmt->error); } $update_stock_stmt->close();
                        $conn->commit(); $message = ['type' => 'success', 'text' => 'Satış başarıyla gerçekleştirildi ve stok güncellendi.'];
                    } catch (Exception $e) { $conn->rollback(); $message = ['type' => 'danger', 'text' => 'HATA: ' . htmlspecialchars($e->getMessage())]; }
                } else { $message = ['type' => 'danger', 'text' => 'Yetersiz stok! Mevcut stok: ' . $current_stock]; }
            } else { $message = ['type' => 'danger', 'text' => 'Lütfen tüm satış alanlarını doldurun.']; }
            break;

        case 'alim_yap':
            // ... (Bu kısım değiştirilmedi) ...
            $urun_id = (int)$_POST['urun_id']; $tedarikci_id = (int)$_POST['tedarikci_id']; $miktar = (int)$_POST['miktar']; $personel_id = (int)$_POST['personel_id'];
            if ($urun_id > 0 && $tedarikci_id > 0 && $miktar > 0 && $personel_id > 0) {
                $conn->begin_transaction(); try {
                    $fiyat_sorgu = $conn->prepare("SELECT AlışFiyatı FROM Ürün WHERE ÜrünID = ?"); $fiyat_sorgu->bind_param("i", $urun_id); $fiyat_sorgu->execute(); $birim_fiyat = $fiyat_sorgu->get_result()->fetch_assoc()['AlışFiyatı']; $fiyat_sorgu->close();
                    $stmt = $conn->prepare("INSERT INTO Alım (TedarikçiID, PersonelID, ÜrünID, Miktar, BirimFiyat) VALUES (?, ?, ?, ?, ?)"); $stmt->bind_param("iiiid", $tedarikci_id, $personel_id, $urun_id, $miktar, $birim_fiyat); if (!$stmt->execute()) { throw new Exception("Alım eklenirken hata: " . $stmt->error); } $stmt->close();
                    $update_stock_stmt = $conn->prepare("UPDATE Ürün SET Stok = Stok + ? WHERE ÜrünID = ?"); $update_stock_stmt->bind_param("ii", $miktar, $urun_id); if (!$update_stock_stmt->execute()) { throw new Exception("Stok güncellenirken hata: " . $update_stock_stmt->error); } $update_stock_stmt->close();
                    $conn->commit(); $message = ['type' => 'success', 'text' => 'Alım işlemi başarıyla eklendi ve stok güncellendi.'];
                } catch (Exception $e) { $conn->rollback(); $message = ['type' => 'danger', 'text' => 'HATA: ' . htmlspecialchars($e->getMessage())]; }
            } else { $message = ['type' => 'danger', 'text' => 'Lütfen tüm alım alanlarını doldurun.']; }
            break;

        case 'yeni_urun_ekle':
            // ... (Bu kısım değiştirilmedi) ...
            $ad = trim($_POST['ad']); $kategori_id = (int)$_POST['kategori_id']; $barkod = trim($_POST['barkod']); $alis_fiyati = (float)str_replace(',', '.', $_POST['alis_fiyati']); $satis_fiyati = (float)str_replace(',', '.', $_POST['satis_fiyati']); $stok = (int)$_POST['stok'];
            if (!empty($ad) && $kategori_id > 0 && !empty($barkod) && $alis_fiyati > 0 && $satis_fiyati > 0) {
                $stmt = $conn->prepare("CALL sp_UrunEkle(?, ?, ?, ?, ?, ?)"); $stmt->bind_param("sisddi", $ad, $kategori_id, $barkod, $alis_fiyati, $satis_fiyati, $stok);
                if ($stmt->execute()) { $message = ['type' => 'success', 'text' => 'Yeni ürün başarıyla eklendi.']; } else { $message = ['type' => 'danger', 'text' => 'HATA: ' . htmlspecialchars($stmt->error)]; }
                $stmt->close();
            } else { $message = ['type' => 'danger', 'text' => 'Lütfen tüm ürün alanlarını doldurun ve geçerli değerler girin.']; }
            break;

        case 'urun_guncelle':
            // ... (Bu kısım değiştirilmedi) ...
            $urun_id = (int)$_POST['urun_id']; $ad = trim($_POST['ad']); $kategori_id = (int)$_POST['kategori_id']; $barkod = trim($_POST['barkod']); $alis_fiyati = (float)str_replace(',', '.', $_POST['alis_fiyati']); $satis_fiyati = (float)str_replace(',', '.', $_POST['satis_fiyati']);
            if ($urun_id > 0 && !empty($ad) && $kategori_id > 0 && !empty($barkod) && $alis_fiyati > 0 && $satis_fiyati > 0) {
                $stmt = $conn->prepare("CALL sp_UrunGuncelle(?, ?, ?, ?, ?, ?)"); $stmt->bind_param("isisdd", $urun_id, $ad, $kategori_id, $barkod, $alis_fiyati, $satis_fiyati);
                if ($stmt->execute()) { $message = ['type' => 'success', 'text' => 'Ürün bilgileri başarıyla güncellendi.']; } else { $message = ['type' => 'danger', 'text' => 'HATA: ' . htmlspecialchars($stmt->error)]; }
                $stmt->close();
            } else { $message = ['type' => 'danger', 'text' => 'Lütfen tüm ürün alanlarını doldurun ve geçerli değerler girin.']; }
            break;
        
        case 'urun_sil':
            // ... (Bu kısım değiştirilmedi) ...
            $urun_id = (int)$_POST['urun_id'];
            if ($urun_id > 0) {
                $stmt = $conn->prepare("CALL sp_UrunSil(?)"); $stmt->bind_param("i", $urun_id);
                if ($stmt->execute()) {
                     if ($stmt->affected_rows > 0) { $message = ['type' => 'success', 'text' => 'Ürün başarıyla silindi.']; }
                     else { $message = ['type' => 'warning', 'text' => 'Silinecek ürün bulunamadı.']; }
                } else { $message = ['type' => 'danger', 'text' => 'HATA: Ürün silinemedi. Bu ürünle ilgili satış veya alım kaydı olabilir. (' . htmlspecialchars($stmt->error) . ')']; }
                $stmt->close();
            } else { $message = ['type' => 'danger', 'text' => 'Geçersiz Ürün ID.']; }
            break;
        
        case 'yeni_personel_ekle':
            // ... (Bu kısım değiştirilmedi) ...
            $ad = trim($_POST['ad']); $soyad = trim($_POST['soyad']); $pozisyon = trim($_POST['pozisyon']);
            if (!empty($ad) && !empty($soyad) && !empty($pozisyon)) {
                $stmt = $conn->prepare("INSERT INTO Personel (Ad, Soyad, Pozisyon) VALUES (?, ?, ?)"); $stmt->bind_param("sss", $ad, $soyad, $pozisyon);
                if ($stmt->execute()) { $message = ['type' => 'success', 'text' => 'Yeni personel eklendi.']; } else { $message = ['type' => 'danger', 'text' => 'HATA: ' . htmlspecialchars($stmt->error)]; }
                $stmt->close();
            } else { $message = ['type' => 'danger', 'text' => 'Lütfen tüm personel alanlarını doldurun.']; }
            break;
        
        case 'personel_guncelle':
            // ... (Bu kısım değiştirilmedi) ...
            $personel_id = (int)$_POST['personel_id']; $ad = trim($_POST['ad']); $soyad = trim($_POST['soyad']); $pozisyon = trim($_POST['pozisyon']);
            if ($personel_id > 0 && !empty($ad) && !empty($soyad) && !empty($pozisyon)) {
                $stmt = $conn->prepare("UPDATE Personel SET Ad = ?, Soyad = ?, Pozisyon = ? WHERE PersonelID = ?"); $stmt->bind_param("sssi", $ad, $soyad, $pozisyon, $personel_id);
                if ($stmt->execute()) { $message = ['type' => 'success', 'text' => 'Personel bilgileri başarıyla güncellendi.']; } else { $message = ['type' => 'danger', 'text' => 'HATA: ' . htmlspecialchars($stmt->error)]; }
                $stmt->close();
            } else { $message = ['type' => 'danger', 'text' => 'Lütfen tüm personel alanlarını doldurun.']; }
            break;
        
        case 'personel_sil':
            // ... (Bu kısım değiştirilmedi, çünkü mevcut hali en doğru yöntemdir) ...
            $personel_id = (int)$_POST['personel_id'];
            if ($personel_id > 0) {
                // Not: Burada bir yordam çağırmak yerine direkt DELETE kullanmak,
                // foreign key kısıtlamalarının doğal olarak çalışmasını sağlar.
                // Bu en güvenli yoldur.
                $stmt = $conn->prepare("DELETE FROM Personel WHERE PersonelID = ?"); $stmt->bind_param("i", $personel_id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) { $message = ['type' => 'success', 'text' => 'Personel başarıyla silindi.']; } else { $message = ['type' => 'warning', 'text' => 'Silinecek personel bulunamadı.']; }
                } else { $message = ['type' => 'danger', 'text' => 'HATA: Personel silinemedi. Bu personel daha önce işlem (satış/alım) yapmış olabilir ve bu nedenle silinemez. (' . htmlspecialchars($stmt->error) . ')']; }
                $stmt->close();
            } else { $message = ['type' => 'danger', 'text' => 'Geçersiz Personel ID.']; }
            break;
        
        case 'yeni_kategori_ekle':
            // ... (Bu kısım değiştirilmedi) ...
            $kategori_ad = trim($_POST['kategori_ad']);
            if (!empty($kategori_ad)) {
                $stmt = $conn->prepare("INSERT INTO Kategori (Ad) VALUES (?)"); $stmt->bind_param("s", $kategori_ad);
                if ($stmt->execute()) { $message = ['type' => 'success', 'text' => 'Yeni kategori eklendi.']; } else { $message = ['type' => 'danger', 'text' => 'HATA: ' . htmlspecialchars($stmt->error)]; }
                $stmt->close();
            } else { $message = ['type' => 'danger', 'text' => 'Kategori adı boş olamaz.']; }
            break;

        // ===== YENİ EKLENEN KISIM: KATEGORİ SİLME İŞLEMİ =====
        case 'kategori_sil':
            $kategori_id = (int)$_POST['kategori_id'];
            if ($kategori_id > 0) {
                // sp_KategoriSil saklı yordamını çağır.
                // Bu yordam, veritabanındaki tetikleyiciyi (trigger) harekete geçirecek.
                $stmt = $conn->prepare("CALL sp_KategoriSil(?)");
                $stmt->bind_param("i", $kategori_id);
                if ($stmt->execute()) {
                    // affected_rows, silinen ürün sayısını da içerebileceği için 1'den büyük olabilir.
                    // Sadece kategorinin silinip silinmediğini kontrol etmek yeterlidir.
                     $message = ['type' => 'success', 'text' => 'Kategori ve içindeki tüm ürünler başarıyla silindi.'];
                } else {
                    // Hata genellikle, silinmeye çalışılan ürünlerden birinin satış/alım kaydı olmasından kaynaklanır.
                    $message = ['type' => 'danger', 'text' => 'HATA: Kategori silinemedi. Bu kategoriye ait ürünlerden en az biri satış/alım işleminde kullanılmış olabilir. (' . htmlspecialchars($stmt->error) . ')'];
                }
                $stmt->close();
            } else {
                $message = ['type' => 'danger', 'text' => 'Geçersiz Kategori ID.'];
            }
            break;

        case 'yeni_tedarikci_ekle':
            // ... (Bu kısım değiştirilmedi) ...
            $firma_adi = trim($_POST['firma_adi']); $telefon = trim($_POST['telefon']); $email = trim($_POST['email']);
            if (!empty($firma_adi)) {
                $stmt = $conn->prepare("INSERT INTO Tedarikçi (FirmaAdı, Telefon, Email) VALUES (?, ?, ?)"); $stmt->bind_param("sss", $firma_adi, $telefon, $email);
                if ($stmt->execute()) { $message = ['type' => 'success', 'text' => 'Yeni tedarikçi eklendi.']; } else { $message = ['type' => 'danger', 'text' => 'HATA: ' . htmlspecialchars($stmt->error)]; }
                $stmt->close();
            } else { $message = ['type' => 'danger', 'text' => 'Firma adı boş olamaz.']; }
            break;
    }

    // Mesajı oturuma kaydet ve sayfayı yeniden yönlendir
    $_SESSION['message'] = $message;
    $redirect_url = $_SERVER['PHP_SELF'];
    if (isset($_POST['current_tab'])) {
        $redirect_url .= '#' . $_POST['current_tab'];
    }
    header("Location: " . $redirect_url);
    exit();
}

// Oturumda bekleyen bir mesaj varsa al ve temizle
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// --- 3. GÖRÜNÜM İÇİN VERİLERİ ÇEKME (DEĞİŞTİRİLMEDİ) ---
$urun_arama_sorgusu = "";
if (isset($_GET['urun_ara']) && !empty(trim($_GET['urun_ara']))) { $search_term = '%' . trim($_GET['urun_ara']) . '%'; $urun_arama_sorgusu = " WHERE Ürün.Ad LIKE ? OR Ürün.Barkod LIKE ?"; }
$urunler_sql = "SELECT Ürün.*, Kategori.Ad as KategoriAd FROM Ürün JOIN Kategori ON Ürün.KategoriID = Kategori.KategoriID" . $urun_arama_sorgusu . " ORDER BY Ürün.Ad ASC";
$urunler_stmt = $conn->prepare($urunler_sql);
if (!empty($urun_arama_sorgusu)) { $urunler_stmt->bind_param("ss", $search_term, $search_term); }
$urunler_stmt->execute();
$urunler = $urunler_stmt->get_result();
$kategoriler = $conn->query("SELECT * FROM Kategori ORDER BY Ad ASC");
$tedarikciler = $conn->query("SELECT * FROM Tedarikçi ORDER BY FirmaAdı ASC");
$personeller = $conn->query("SELECT * FROM Personel ORDER BY Ad ASC");
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$satis_raporu_where = "";
if (!empty($start_date) && !empty($end_date)) { $satis_raporu_where = " WHERE s.Tarih BETWEEN ? AND ?"; }
else if (!empty($start_date)) { $satis_raporu_where = " WHERE s.Tarih >= ?"; }
else if (!empty($end_date)) { $satis_raporu_where = " WHERE s.Tarih <= ?"; }
$satis_raporu_sql = "SELECT s.SatışID, s.Tarih, u.Ad AS UrunAdi, CONCAT(p.Ad, ' ', p.Soyad) AS PersonelAdi, s.Miktar, s.BirimFiyat, (s.Miktar * s.BirimFiyat) AS ToplamTutar FROM Satış s JOIN Ürün u ON s.ÜrünID = u.ÜrünID JOIN Personel p ON s.PersonelID = p.PersonelID " . $satis_raporu_where . " ORDER BY s.Tarih DESC";
$satis_raporu_stmt = $conn->prepare($satis_raporu_sql);
if (!empty($start_date) && !empty($end_date)) { $satis_raporu_stmt->bind_param("ss", $start_date, $end_date); }
else if (!empty($start_date)) { $satis_raporu_stmt->bind_param("s", $start_date); }
else if (!empty($end_date)) { $satis_raporu_stmt->bind_param("s", $end_date); }
$satis_raporu_stmt->execute();
$satis_raporu = $satis_raporu_stmt->get_result();
$total_products = $conn->query("SELECT COUNT(ÜrünID) AS total FROM Ürün")->fetch_assoc()['total'];
$total_personnel = $conn->query("SELECT COUNT(PersonelID) AS total FROM Personel")->fetch_assoc()['total'];
$total_categories = $conn->query("SELECT COUNT(KategoriID) AS total FROM Kategori")->fetch_assoc()['total'];
$total_suppliers = $conn->query("SELECT COUNT(TedarikçiID) AS total FROM Tedarikçi")->fetch_assoc()['total'];

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Market Yönetim Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f8f9fa; } .container { margin-top: 20px; } .card { margin-bottom: 20px; border-radius: 10px; overflow: hidden; } .card-header { font-weight: bold; background-color: #f0f2f5; border-bottom: 1px solid #e9ecef; color: #343a40;} .low-stock { background-color: #fff0f1 !important; font-weight: bold; } .nav-tabs .nav-link { color: #495057; } .nav-tabs .nav-link.active { color: #0d6efd; border-color: #dee2e6 #dee2e6 #fff; } .table tfoot th { text-align: right; } .scroll-to-top { position: fixed; bottom: 20px; right: 20px; display: none; z-index: 99; opacity: 0.7; transition: opacity 0.3s ease-in-out; } .scroll-to-top:hover { opacity: 1; } .hero-section { background: linear-gradient(45deg, #007bff, #0056b3); color: white; padding: 60px 0; border-radius: 15px; margin-bottom: 30px; text-align: center; box-shadow: 0 8px 16px rgba(0,0,0,0.2); } .hero-section h1 { font-size: 3.5rem; font-weight: 700; margin-bottom: 15px; } .hero-section p { font-size: 1.2rem; opacity: 0.9; } .stat-card { text-align: center; padding: 25px; background-color: #ffffff; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); transition: transform 0.2s ease-in-out; } .stat-card:hover { transform: translateY(-5px); } .stat-card .icon { font-size: 3rem; color: #007bff; margin-bottom: 15px; } .stat-card h3 { font-size: 2.5rem; font-weight: 700; color: #343a40; } .stat-card p { font-size: 1.1rem; color: #6c757d; margin-bottom: 0; } .info-section { background-color: #e9f5ff; padding: 40px; border-radius: 15px; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-top: 30px; } .info-section h2 { color: #0056b3; margin-bottom: 25px; } .info-section ul { list-style: none; padding: 0; columns: 2; -webkit-columns: 2; -moz-columns: 2; max-width: 700px; margin: 0 auto; } .info-section ul li { background-color: #ffffff; margin-bottom: 10px; padding: 12px 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); text-align: left; display: flex; align-items: center; } .info-section ul li i { margin-right: 15px; color: #007bff; font-size: 1.5rem; } @media (max-width: 768px) { .hero-section h1 { font-size: 2.5rem; } .hero-section p { font-size: 1rem; } .stat-card .icon { font-size: 2.5rem; } .stat-card h3 { font-size: 2rem; } .info-section ul { columns: 1; } }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="#"><i class="bi bi-shop"></i> SimpleMarketDB</a></div></nav>

    <div class="container">
        <?php if (!empty($message)): ?>
        <div class="alert alert-<?= htmlspecialchars($message['type']) ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message['text']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <ul class="nav nav-tabs" id="myTab" role="tablist">
          <li class="nav-item" role="presentation"><button class="nav-link active" id="home-tab" data-bs-toggle="tab" data-bs-target="#home-tab-pane" type="button" role="tab" aria-controls="home-tab-pane" aria-selected="true"><i class="bi bi-house-door-fill"></i> Ana Sayfa</button></li>
          <li class="nav-item" role="presentation"><button class="nav-link" id="dashboard-tab" data-bs-toggle="tab" data-bs-target="#dashboard-tab-pane" type="button" role="tab" aria-controls="dashboard-tab-pane" aria-selected="false"><i class="bi bi-grid-fill"></i> Genel Bakış</button></li>
          <li class="nav-item" role="presentation"><button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports-tab-pane" type="button" role="tab" aria-controls="reports-tab-pane" aria-selected="false"><i class="bi bi-graph-up"></i> Satış Raporları</button></li>
          <li class="nav-item" role="presentation"><button class="nav-link" id="inventory-tab" data-bs-toggle="tab" data-bs-target="#inventory-tab-pane" type="button" role="tab" aria-controls="inventory-tab-pane" aria-selected="false"><i class="bi bi-boxes"></i> Envanter Yönetimi</button></li>
          <li class="nav-item" role="presentation"><button class="nav-link" id="personnel-tab" data-bs-toggle="tab" data-bs-target="#personnel-tab-pane" type="button" role="tab" aria-controls="personnel-tab-pane" aria-selected="false"><i class="bi bi-people-fill"></i> Personel Yönetimi</button></li>
        </ul>

        <div class="tab-content" id="myTabContent">
            <!-- Ana Sayfa (Değiştirilmedi) -->
            <div class="tab-pane fade show active" id="home-tab-pane" role="tabpanel" aria-labelledby="home-tab"><div class="pt-3"><div class="hero-section"><h1><i class="bi bi-shop"></i> SimpleMarketDB Yönetim Paneli</h1><p>İşletmenizi daha akıllıca yönetin. Stoktan satışa, personel takibine kadar her şey tek bir yerde!</p></div><div class="row"><div class="col-md-6 col-lg-3"><div class="stat-card"><div class="icon"><i class="bi bi-box-seam"></i></div><h3><?= $total_products ?></h3><p>Toplam Ürün Çeşidi</p></div></div><div class="col-md-6 col-lg-3"><div class="stat-card"><div class="icon"><i class="bi bi-people-fill"></i></div><h3><?= $total_personnel ?></h3><p>Toplam Personel Sayısı</p></div></div><div class="col-md-6 col-lg-3"><div class="stat-card"><div class="icon"><i class="bi bi-tags-fill"></i></div><h3><?= $total_categories ?></h3><p>Toplam Kategori</p></div></div><div class="col-md-6 col-lg-3"><div class="stat-card"><div class="icon"><i class="bi bi-truck-flatbed"></i></div><h3><?= $total_suppliers ?></h3><p>Toplam Tedarikçi</p></div></div></div><div class="info-section"><h2>Neler Yapabilirsiniz?</h2><ul><li><i class="bi bi-graph-up"></i> Detaylı satış raporları oluşturun.</li><li><i class="bi bi-plus-circle"></i> Yeni ürünleri kolayca stoğa ekleyin.</li><li><i class="bi bi-cart-check"></i> Anında satış işlemleri gerçekleştirin.</li><li><i class="bi bi-person-plus"></i> Yeni personel kayıtlarını yapın.</li><li><i class="bi bi-building"></i> Tedarikçi bilgilerinizi güncel tutun.</li><li><i class="bi bi-list-check"></i> Mevcut stok durumunuzu anlık takip edin.</li><li><i class="bi bi-funnel"></i> Satış geçmişini tarihlere göre filtreleyin.</li><li><i class="bi bi-barcode-scan"></i> Ürünleri barkod veya isimle arayın.</li></ul></div></div></div>
            <!-- Genel Bakış (Değiştirilmedi) -->
            <div class="tab-pane fade" id="dashboard-tab-pane" role="tabpanel" aria-labelledby="dashboard-tab"><div class="pt-3"><div class="card"><div class="card-header bg-primary text-white"><i class="bi bi-box-seam"></i> Ürün Stok Durumu</div><div class="card-body"><form method="GET" class="mb-3"><div class="input-group"><input type="text" class="form-control" placeholder="Ürün adı veya barkod ile ara..." name="urun_ara" value="<?= htmlspecialchars($_GET['urun_ara'] ?? '') ?>"><button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i> Ara</button><?php if (isset($_GET['urun_ara']) && !empty(trim($_GET['urun_ara']))) : ?><a href="index.php#dashboard-tab" class="btn btn-outline-danger"><i class="bi bi-x-circle"></i> Temizle</a><?php endif; ?></div></form><div class="table-responsive"><table class="table table-striped table-hover"><thead><tr><th>Ürün Adı</th><th>Kategori</th><th>Barkod</th><th>Alış Fiyatı</th><th>Satış Fiyatı</th><th>Stok Adedi</th><th class="text-center">İşlemler</th></tr></thead><tbody><?php if ($urunler->num_rows > 0) : $urunler->data_seek(0); while ($row = $urunler->fetch_assoc()) : ?><tr class="<?= $row['Stok'] < 10 ? 'table-warning' : '' ?>"><td><?= htmlspecialchars($row['Ad']) ?></td><td><?= htmlspecialchars($row['KategoriAd']) ?></td><td><?= htmlspecialchars($row['Barkod']) ?></td><td><?= number_format($row['AlışFiyatı'], 2, ',', '.') ?> ₺</td><td><?= number_format($row['SatışFiyatı'], 2, ',', '.') ?> ₺</td><td><?= $row['Stok'] ?><?php if ($row['Stok'] < 10) : ?> <span class="badge bg-danger ms-2">Düşük Stok!</span><?php endif; ?></td><td class="text-center"><button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editUrunModal" data-urun-id="<?= $row['ÜrünID'] ?>" data-ad="<?= htmlspecialchars($row['Ad']) ?>" data-kategori-id="<?= $row['KategoriID'] ?>" data-barkod="<?= htmlspecialchars($row['Barkod']) ?>" data-alis-fiyati="<?= number_format($row['AlışFiyatı'], 2, ',', '.') ?>" data-satis-fiyati="<?= number_format($row['SatışFiyatı'], 2, ',', '.') ?>" title="Güncelle"><i class="bi bi-pencil-square"></i></button><form method="POST" class="d-inline" onsubmit="return confirm('Bu ürünü silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.');"><input type="hidden" name="action" value="urun_sil"><input type="hidden" name="current_tab" value="dashboard-tab"><input type="hidden" name="urun_id" value="<?= $row['ÜrünID'] ?>"><button type="submit" class="btn btn-sm btn-danger" title="Sil"><i class="bi bi-trash-fill"></i></button></form></td></tr><?php endwhile; else : ?><tr><td colspan="7" class="text-center">Kayıtlı ürün bulunamadı veya arama sonucu yok.</td></tr><?php endif; ?></tbody></table></div></div></div><div class="row"><div class="col-lg-6"><div class="card"><div class="card-header bg-success text-white"><i class="bi bi-cart-check"></i> Satış Yap</div><div class="card-body"><form method="POST"><input type="hidden" name="action" value="satis_yap"><input type="hidden" name="current_tab" value="dashboard-tab"> <div class="mb-3"><label class="form-label">Personel</label><select class="form-select" name="personel_id" required><option value="">Seçin</option><?php $personeller->data_seek(0); while ($p = $personeller->fetch_assoc()) { echo "<option value='{$p['PersonelID']}'>" . htmlspecialchars($p['Ad']) . " " . htmlspecialchars($p['Soyad']) . "</option>"; } ?></select></div><div class="mb-3"><label class="form-label">Ürün</label><select class="form-select" name="urun_id" required><option value="">Seçin</option><?php $urunler->data_seek(0); while ($row = $urunler->fetch_assoc()) { echo "<option value='{$row['ÜrünID']}'>" . htmlspecialchars($row['Ad']) . " (Stok: {$row['Stok']})</option>"; } ?></select></div><div class="mb-3"><label class="form-label">Miktar</label><input type="number" class="form-control" name="miktar" min="1" required></div><button type="submit" class="btn btn-success w-100"><i class="bi bi-check-circle"></i> Satışı Gerçekleştir</button></form></div></div></div><div class="col-lg-6"><div class="card"><div class="card-header bg-info text-white"><i class="bi bi-truck"></i> Alım Yap</div><div class="card-body"><form method="POST"><input type="hidden" name="action" value="alim_yap"><input type="hidden" name="current_tab" value="dashboard-tab"> <div class="mb-3"><label class="form-label">Personel</label><select class="form-select" name="personel_id" required><option value="">Seçin</option><?php $personeller->data_seek(0); while ($p = $personeller->fetch_assoc()) { echo "<option value='{$p['PersonelID']}'>" . htmlspecialchars($p['Ad']) . " " . htmlspecialchars($p['Soyad']) . "</option>"; } ?></select></div><div class="mb-3"><label class="form-label">Ürün</label><select class="form-select" name="urun_id" required><option value="">Seçin</option><?php $urunler->data_seek(0); while ($row = $urunler->fetch_assoc()) { echo "<option value='{$row['ÜrünID']}'>" . htmlspecialchars($row['Ad']) . "</option>"; } ?></select></div><div class="mb-3"><label class="form-label">Tedarikçi</label><select class="form-select" name="tedarikci_id" required><option value="">Seçin</option><?php $tedarikciler->data_seek(0); while ($row = $tedarikciler->fetch_assoc()) { echo "<option value='{$row['TedarikçiID']}'>" . htmlspecialchars($row['FirmaAdı']) . "</option>"; } ?></select></div><div class="mb-3"><label class="form-label">Miktar</label><input type="number" class="form-control" name="miktar" min="1" required></div><button type="submit" class="btn btn-info w-100"><i class="bi bi-plus-circle"></i> Stoğa Ekle</button></form></div></div></div></div></div></div>
            <!-- Raporlar (Değiştirilmedi) -->
            <div class="tab-pane fade" id="reports-tab-pane" role="tabpanel" aria-labelledby="reports-tab"><div class="pt-3"><div class="card"><div class="card-header bg-dark text-white"><i class="bi bi-receipt-cutoff"></i> Satış Geçmişi</div><div class="card-body"><form method="GET" class="mb-3"><div class="row g-2"><div class="col-md-4"><label for="start_date" class="form-label visually-hidden">Başlangıç Tarihi</label><input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>"></div><div class="col-md-4"><label for="end_date" class="form-label visually-hidden">Bitiş Tarihi</label><input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>"></div><div class="col-md-4"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Filtrele</button><?php if (!empty($start_date) || !empty($end_date)): ?><a href="index.php?tab=reports-tab" class="btn btn-outline-danger w-100 mt-2"><i class="bi bi-x-circle"></i> Filtreyi Temizle</a><?php endif; ?></div></div></form><div class="table-responsive"><table class="table table-bordered table-hover"><thead class="table-light"><tr><th>#ID</th><th>Tarih</th><th>Ürün</th><th>Personel</th><th class="text-end">Miktar</th><th class="text-end">Birim Fiyat</th><th class="text-end">Toplam Tutar</th></tr></thead><tbody><?php $toplam_ciro = 0; $toplam_satilan_urun = 0; if ($satis_raporu && $satis_raporu->num_rows > 0): while($row = $satis_raporu->fetch_assoc()): $toplam_ciro += $row['ToplamTutar']; $toplam_satilan_urun += $row['Miktar']; ?><tr><td><?= $row['SatışID'] ?></td><td><?= date('d.m.Y H:i', strtotime($row['Tarih'])) ?></td><td><?= htmlspecialchars($row['UrunAdi']) ?></td><td><?= htmlspecialchars($row['PersonelAdi']) ?></td><td class="text-end"><?= $row['Miktar'] ?></td><td class="text-end"><?= number_format($row['BirimFiyat'], 2, ',', '.') ?> ₺</td><td class="text-end fw-bold"><?= number_format($row['ToplamTutar'], 2, ',', '.') ?> ₺</td></tr><?php endwhile; else: ?><tr><td colspan="7" class="text-center">Henüz hiç satış yapılmamış veya filtrelenen tarihlerde satış bulunmuyor.</td></tr><?php endif; ?></tbody><tfoot class="table-group-divider"><tr><th colspan="4">GENEL TOPLAM</th><th class="text-end"><?= $toplam_satilan_urun ?> adet</th><th colspan="1"></th><th class="text-end fs-5"><?= number_format($toplam_ciro, 2, ',', '.') ?> ₺</th></tr></tfoot></table></div></div></div></div></div>
            
            <!-- ===== ENVANTER YÖNETİMİ SEKMESİ (DEĞİŞTİRİLEN KISIM) ===== -->
            <div class="tab-pane fade" id="inventory-tab-pane" role="tabpanel" aria-labelledby="inventory-tab">
                <div class="pt-3">
                    <div class="row">
                        <div class="col-lg-7">
                            <div class="card">
                                <div class="card-header"><i class="bi bi-plus-square"></i> Yeni Ürün Ekle</div>
                                <div class="card-body">
                                    <form method="POST"><input type="hidden" name="action" value="yeni_urun_ekle"><input type="hidden" name="current_tab" value="inventory-tab"> <div class="row"><div class="col-md-6 mb-3"><label class="form-label">Ürün Adı</label><input type="text" class="form-control" name="ad" required></div><div class="col-md-6 mb-3"><label class="form-label">Kategori</label><select class="form-select" name="kategori_id" required><option value="">Seçin</option><?php $kategoriler->data_seek(0); while($row = $kategoriler->fetch_assoc()) { echo "<option value='{$row['KategoriID']}'>".htmlspecialchars($row['Ad'])."</option>"; } ?></select></div><div class="col-md-4 mb-3"><label class="form-label">Barkod</label><input type="text" class="form-control" name="barkod" required></div><div class="col-md-4 mb-3"><label class="form-label">Alış Fiyatı</label><input type="text" class="form-control" name="alis_fiyati" placeholder="10,50" required></div><div class="col-md-4 mb-3"><label class="form-label">Satış Fiyatı</label><input type="text" class="form-control" name="satis_fiyati" placeholder="15,90" required></div><div class="col-md-4 mb-3"><label class="form-label">Başlangıç Stoğu</label><input type="number" class="form-control" name="stok" min="0" value="0" required></div></div><button type="submit" class="btn btn-primary w-100"><i class="bi bi-save"></i> Ürünü Kaydet</button></form>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-5">
                             <div class="card">
                                <div class="card-header"><i class="bi bi-tags-fill"></i> Yeni Kategori Ekle</div>
                                <div class="card-body">
                                    <form method="POST"><input type="hidden" name="action" value="yeni_kategori_ekle"><input type="hidden" name="current_tab" value="inventory-tab"> <div class="mb-3"><label class="form-label">Kategori Adı</label><input type="text" class="form-control" name="kategori_ad" required></div><button type="submit" class="btn btn-secondary w-100">Kaydet</button></form>
                                </div>
                            </div>
                            <!-- ===== YENİ EKLENEN KISIM: KATEGORİ LİSTESİ VE SİLME ===== -->
                            <div class="card mt-3">
                                <div class="card-header"><i class="bi bi-list-check"></i> Mevcut Kategoriler</div>
                                <div class="card-body p-0">
                                    <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                                        <table class="table table-striped table-hover mb-0">
                                            <tbody>
                                                <?php $kategoriler->data_seek(0); if($kategoriler->num_rows > 0): while($kat = $kategoriler->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($kat['Ad']) ?></td>
                                                    <td class="text-end">
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Bu kategoriyi silmek istediğinizden emin misiniz?\n\nDİKKAT: Bu işlem, bu kategoriye ait TÜM ÜRÜNLERİ kalıcı olarak silecektir! (Eğer ürünlerin satış kaydı yoksa)\n\nBu işlem geri alınamaz.');">
                                                            <input type="hidden" name="action" value="kategori_sil">
                                                            <input type="hidden" name="current_tab" value="inventory-tab">
                                                            <input type="hidden" name="kategori_id" value="<?= $kat['KategoriID'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger" title="Kategoriyi ve içindeki ürünleri sil">
                                                                <i class="bi bi-trash-fill"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                                <?php endwhile; else: ?>
                                                <tr><td class="text-center p-3">Kayıtlı kategori bulunmuyor.</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <!-- ===== YENİ KISIM BİTİŞ ===== -->
                            <div class="card mt-3">
                                <div class="card-header"><i class="bi bi-building-add"></i> Yeni Tedarikçi Ekle</div>
                                <div class="card-body">
                                    <form method="POST"><input type="hidden" name="action" value="yeni_tedarikci_ekle"><input type="hidden" name="current_tab" value="inventory-tab"> <div class="mb-2"><label class="form-label">Firma Adı</label><input type="text" class="form-control" name="firma_adi" required></div><div class="mb-2"><label class="form-label">Telefon</label><input type="text" class="form-control" name="telefon"></div><div class="mb-2"><label class="form-label">Email</label><input type="email" class="form-control" name="email"></div><button type="submit" class="btn btn-secondary w-100">Kaydet</button></form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Personel Yönetimi (Değiştirilmedi) -->
            <div class="tab-pane fade" id="personnel-tab-pane" role="tabpanel" aria-labelledby="personnel-tab"><div class="pt-3"><div class="row"><div class="col-lg-7"><div class="card"><div class="card-header"><i class="bi bi-list-ul"></i> Kayıtlı Personeller</div><div class="card-body"><div class="table-responsive"><table class="table table-striped table-hover"><thead><tr><th>#</th><th>Ad</th><th>Soyad</th><th>Pozisyon</th><th class="text-center">İşlemler</th></tr></thead><tbody><?php $personeller->data_seek(0); if($personeller->num_rows > 0): while($p = $personeller->fetch_assoc()): ?><tr><td><?= $p['PersonelID'] ?></td><td><?= htmlspecialchars($p['Ad']) ?></td><td><?= htmlspecialchars($p['Soyad']) ?></td><td><?= htmlspecialchars($p['Pozisyon']) ?></td><td class="text-center"><button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editPersonelModal" data-personel-id="<?= $p['PersonelID'] ?>" data-ad="<?= htmlspecialchars($p['Ad']) ?>" data-soyad="<?= htmlspecialchars($p['Soyad']) ?>" data-pozisyon="<?= htmlspecialchars($p['Pozisyon']) ?>" title="Güncelle"><i class="bi bi-pencil-square"></i></button><form method="POST" class="d-inline" onsubmit="return confirm('Bu personeli silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.');"><input type="hidden" name="action" value="personel_sil"><input type="hidden" name="current_tab" value="personnel-tab"><input type="hidden" name="personel_id" value="<?= $p['PersonelID'] ?>"><button type="submit" class="btn btn-sm btn-danger" title="Sil"><i class="bi bi-trash-fill"></i></button></form></td></tr><?php endwhile; else: ?><tr><td colspan="5" class="text-center">Kayıtlı personel bulunmuyor.</td></tr><?php endif; ?></tbody></table></div></div></div></div><div class="col-lg-5"><div class="card"><div class="card-header"><i class="bi bi-person-plus-fill"></i> Yeni Personel Ekle</div><div class="card-body"><form method="POST"><input type="hidden" name="action" value="yeni_personel_ekle"><input type="hidden" name="current_tab" value="personnel-tab"><div class="mb-3"><label class="form-label">Ad</label><input type="text" class="form-control" name="ad" required></div><div class="mb-3"><label class="form-label">Soyad</label><input type="text" class="form-control" name="soyad" required></div><div class="mb-3"><label class="form-label">Pozisyon</label><input type="text" class="form-control" name="pozisyon" required></div><button type="submit" class="btn btn-primary w-100"><i class="bi bi-save"></i> Personeli Kaydet</button></form></div></div></div></div></div></div>
        </div>
    </div>

    <!-- Modallar (Değiştirilmedi) -->
    <div class="modal fade" id="editUrunModal" tabindex="-1" aria-labelledby="editUrunModalLabel" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="editUrunModalLabel">Ürün Bilgilerini Düzenle</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><form method="POST"><div class="modal-body"><input type="hidden" name="action" value="urun_guncelle"><input type="hidden" name="current_tab" value="dashboard-tab"><input type="hidden" name="urun_id" id="editUrunId"><div class="mb-3"><label for="editUrunAd" class="form-label">Ürün Adı</label><input type="text" class="form-control" id="editUrunAd" name="ad" required></div><div class="mb-3"><label for="editKategoriId" class="form-label">Kategori</label><select class="form-select" id="editKategoriId" name="kategori_id" required><option value="">Seçin...</option><?php $kategoriler->data_seek(0); while ($kat = $kategoriler->fetch_assoc()) { echo "<option value='{$kat['KategoriID']}'>" . htmlspecialchars($kat['Ad']) . "</option>"; } ?></select></div><div class="mb-3"><label for="editBarkod" class="form-label">Barkod</label><input type="text" class="form-control" id="editBarkod" name="barkod" required></div><div class="row"><div class="col-6 mb-3"><label for="editAlisFiyati" class="form-label">Alış Fiyatı</label><input type="text" class="form-control" id="editAlisFiyati" name="alis_fiyati" placeholder="10,50" required></div><div class="col-6 mb-3"><label for="editSatisFiyati" class="form-label">Satış Fiyatı</label><input type="text" class="form-control" id="editSatisFiyati" name="satis_fiyati" placeholder="15,90" required></div></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button><button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Değişiklikleri Kaydet</button></div></form></div></div></div>
    <div class="modal fade" id="editPersonelModal" tabindex="-1" aria-labelledby="editPersonelModalLabel" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="editPersonelModalLabel">Personel Bilgilerini Düzenle</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><form method="POST"><div class="modal-body"><input type="hidden" name="action" value="personel_guncelle"><input type="hidden" name="current_tab" value="personnel-tab"><input type="hidden" name="personel_id" id="editPersonelId"><div class="mb-3"><label for="editPersonelAd" class="form-label">Ad</label><input type="text" class="form-control" id="editPersonelAd" name="ad" required></div><div class="mb-3"><label for="editSoyad" class="form-label">Soyad</label><input type="text" class="form-control" id="editSoyad" name="soyad" required></div><div class="mb-3"><label for="editPozisyon" class="form-label">Pozisyon</label><input type="text" class="form-control" id="editPozisyon" name="pozisyon" required></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button><button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Değişiklikleri Kaydet</button></div></form></div></div></div>

    <a href="#" class="btn btn-dark scroll-to-top" id="scrollToTopBtn" title="Yukarı Kaydır"><i class="bi bi-arrow-up-circle-fill"></i></a>
    <footer class="text-center text-muted p-4"><p>© <?= date("Y") ?> Simple Market Yönetim Sistemi</p></footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // JavaScript kodunda herhangi bir değişiklik gerekmemektedir.
            const hash = window.location.hash.substring(1);
            const activeTabId = (hash && document.getElementById(hash)) ? hash : 'home-tab';
            const triggerEl = document.getElementById(activeTabId);
            if (triggerEl) { new bootstrap.Tab(triggerEl).show(); }
            document.querySelectorAll('#myTab button').forEach(tabButton => { tabButton.addEventListener('shown.bs.tab', event => { history.pushState(null, null, '#' + event.target.id); }); });
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function() {
                    const activeTabButton = document.querySelector('#myTab button.active');
                    if (activeTabButton) {
                        let hiddenInput = this.querySelector('input[name="current_tab"]');
                        if (!hiddenInput) { hiddenInput = document.createElement('input'); hiddenInput.type = 'hidden'; hiddenInput.name = 'current_tab'; this.appendChild(hiddenInput); }
                        hiddenInput.value = activeTabButton.id;
                    }
                });
            });
            const mybutton = document.getElementById("scrollToTopBtn");
            window.onscroll = () => { mybutton.style.display = (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) ? "block" : "none"; };
            mybutton.addEventListener('click', e => { e.preventDefault(); window.scrollTo({top: 0, behavior: 'smooth'}); });
            const editPersonelModal = document.getElementById('editPersonelModal');
            if (editPersonelModal) {
                editPersonelModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    editPersonelModal.querySelector('#editPersonelId').value = button.getAttribute('data-personel-id');
                    editPersonelModal.querySelector('#editPersonelAd').value = button.getAttribute('data-ad');
                    editPersonelModal.querySelector('#editSoyad').value = button.getAttribute('data-soyad');
                    editPersonelModal.querySelector('#editPozisyon').value = button.getAttribute('data-pozisyon');
                });
            }
            const editUrunModal = document.getElementById('editUrunModal');
            if (editUrunModal) {
                editUrunModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    editUrunModal.querySelector('#editUrunId').value = button.getAttribute('data-urun-id');
                    editUrunModal.querySelector('#editUrunAd').value = button.getAttribute('data-ad');
                    editUrunModal.querySelector('#editKategoriId').value = button.getAttribute('data-kategori-id');
                    editUrunModal.querySelector('#editBarkod').value = button.getAttribute('data-barkod');
                    editUrunModal.querySelector('#editAlisFiyati').value = button.getAttribute('data-alis-fiyati');
                    editUrunModal.querySelector('#editSatisFiyati').value = button.getAttribute('data-satis-fiyati');
                });
            }
        });
    </script>
</body>
</html>
<?php
// Veritabanı bağlantısını kapat
$conn->close();
?>