<?php
// Ensure error reporting is on for development
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

include 'data3.php'; // Your database connection file

// --- Data Aggregation Arrays ---
$kpi_data = [];
$chart_data = [];
$table_data = [];

// --- KPI Data ---
$sql_total_pharmacies = "SELECT COUNT(*) AS total FROM eczane";
$result = $conn->query($sql_total_pharmacies);
$kpi_data['total_pharmacies'] = $result ? $result->fetch_assoc()['total'] ?? 0 : 0;

$sql_total_patients = "SELECT COUNT(*) AS total FROM hasta";
$result = $conn->query($sql_total_patients);
$kpi_data['total_patients'] = $result ? $result->fetch_assoc()['total'] ?? 0 : 0;

$sql_total_drugs_types = "SELECT COUNT(DISTINCT ilac_ad) AS total FROM ilac"; // Count distinct drug names
$result = $conn->query($sql_total_drugs_types);
$kpi_data['total_drug_types'] = $result ? $result->fetch_assoc()['total'] ?? 0 : 0;

$sql_avg_stock = "SELECT AVG(stok) AS avg_val FROM ilac WHERE stok > 0";
$result = $conn->query($sql_avg_stock);
$kpi_data['avg_stock'] = $result ? $result->fetch_assoc()['avg_val'] ?? 0 : 0;

$sql_total_prescriptions = "SELECT COUNT(*) AS total FROM recete";
$result = $conn->query($sql_total_prescriptions);
$kpi_data['total_prescriptions'] = $result ? $result->fetch_assoc()['total'] ?? 0 : 0;

$sql_total_doctors = "SELECT COUNT(*) AS total FROM doktor";
$result = $conn->query($sql_total_doctors);
$kpi_data['total_doctors'] = $result ? $result->fetch_assoc()['total'] ?? 0 : 0;

$sql_avg_drug_price = "SELECT AVG(satis_fiyat) AS avg_val FROM ilac WHERE satis_fiyat > 0";
$result = $conn->query($sql_avg_drug_price);
$kpi_data['avg_drug_price'] = $result ? $result->fetch_assoc()['avg_val'] ?? 0 : 0;

// NEW KPI: Average Items per Prescription
$sql_avg_items_per_prescription = "SELECT AVG(ilac_adet) AS avg_val FROM recete WHERE ilac_adet > 0";
$result = $conn->query($sql_avg_items_per_prescription);
$kpi_data['avg_items_per_prescription'] = $result ? $result->fetch_assoc()['avg_val'] ?? 0 : 0;

// NEW KPI: Out-of-Stock Drugs (distinct drug names)
$sql_out_of_stock_drugs = "SELECT COUNT(DISTINCT ilac_ad) AS total FROM ilac WHERE stok = 0";
$result = $conn->query($sql_out_of_stock_drugs);
$kpi_data['out_of_stock_drugs'] = $result ? $result->fetch_assoc()['total'] ?? 0 : 0;

// NEW KPI: Total Revenue (Last 30 Days) - Also used for chart below
$sql_revenue_last_30_days_total = "
    SELECT SUM(i.satis_fiyat * r.ilac_adet) AS total_revenue
    FROM recete r
    JOIN ilac i ON r.ilac_id = i.ilac_id
    WHERE r.recete_tarih >= CURDATE() - INTERVAL 29 DAY AND r.recete_tarih <= CURDATE()"; // Assuming recete_tarih is DATE
$result = $conn->query($sql_revenue_last_30_days_total);
$kpi_data['revenue_last_30_days'] = $result ? $result->fetch_assoc()['total_revenue'] ?? 0 : 0;

// NEW KPI: Total Active Personnel
$sql_active_personnel = "SELECT COUNT(*) as total FROM personel WHERE isbasi_tarihi <= CURDATE()"; // Simple active check
$result = $conn->query($sql_active_personnel);
$kpi_data['active_personnel'] = $result ? $result->fetch_assoc()['total'] ?? 0 : 0;


// --- Chart Data ---

// 1. Drugs per Manufacturer (Pie Chart)
$sql_drugs_per_manufacturer = "
    SELECT u.uretici_ad, COUNT(i.ilac_id) AS drug_count
    FROM ilac i JOIN uretici u ON i.uretici_id = u.uretici_id
    GROUP BY u.uretici_ad ORDER BY drug_count DESC LIMIT 7";
$result = $conn->query($sql_drugs_per_manufacturer);
$chart_data['manufacturer_labels'] = []; $chart_data['manufacturer_drug_counts'] = [];
if ($result) while ($row = $result->fetch_assoc()) {
    $chart_data['manufacturer_labels'][] = $row['uretici_ad'];
    $chart_data['manufacturer_drug_counts'][] = (int)$row['drug_count'];
}

// 2. Patient Age Distribution (Bar Chart)
$sql_patient_age_dist = "
SELECT CASE WHEN (YEAR(CURDATE()) - YEAR(dog_tar)) < 18 THEN '0-17' WHEN (YEAR(CURDATE()) - YEAR(dog_tar)) BETWEEN 18 AND 30 THEN '18-30' WHEN (YEAR(CURDATE()) - YEAR(dog_tar)) BETWEEN 31 AND 45 THEN '31-45' WHEN (YEAR(CURDATE()) - YEAR(dog_tar)) BETWEEN 46 AND 60 THEN '46-60' WHEN (YEAR(CURDATE()) - YEAR(dog_tar)) > 60 THEN '60+' ELSE 'Bilinmiyor' END AS age_group, COUNT(*) AS count
FROM hasta WHERE dog_tar IS NOT NULL AND dog_tar > '1900-01-01' AND dog_tar <= CURDATE()
GROUP BY age_group ORDER BY FIELD(age_group, '0-17', '18-30', '31-45', '46-60', '60+', 'Bilinmiyor')";
$result = $conn->query($sql_patient_age_dist);
$chart_data['patient_age_labels'] = []; $chart_data['patient_age_counts'] = [];
if ($result) while ($row = $result->fetch_assoc()) {
    $chart_data['patient_age_labels'][] = $row['age_group'];
    $chart_data['patient_age_counts'][] = (int)$row['count'];
}

// 3. Drug Stock Level Categories (Doughnut Chart)
$sql_drug_stock_categories = "
SELECT CASE WHEN stok = 0 THEN 'Stokta Yok' WHEN stok > 0 AND stok < 20 THEN 'Çok Düşük (<20)' WHEN stok >= 20 AND stok < 100 THEN 'Düşük (20-99)' WHEN stok >= 100 AND stok < 500 THEN 'Orta (100-499)' ELSE 'Yüksek (500+)' END AS stock_category, COUNT(DISTINCT ilac_ad) AS count
FROM ilac GROUP BY stock_category ORDER BY FIELD(stock_category, 'Stokta Yok', 'Çok Düşük (<20)', 'Düşük (20-99)', 'Orta (100-499)', 'Yüksek (500+)')";
$result = $conn->query($sql_drug_stock_categories);
$chart_data['stock_category_labels'] = []; $chart_data['stock_category_counts'] = [];
if ($result) while ($row = $result->fetch_assoc()) {
    $chart_data['stock_category_labels'][] = $row['stock_category'];
    $chart_data['stock_category_counts'][] = (int)$row['count'];
}

// 4. Patient Gender Distribution (Pie Chart)
$sql_patient_gender = "
    SELECT CASE WHEN cinsiyet = 'E' THEN 'Erkek' WHEN cinsiyet = 'K' THEN 'Kadın' ELSE 'Belirtilmemiş' END AS gender, COUNT(*) AS count
    FROM hasta GROUP BY gender ORDER BY FIELD(gender, 'Erkek', 'Kadın', 'Belirtilmemiş')";
$result = $conn->query($sql_patient_gender);
$chart_data['patient_gender_labels'] = []; $chart_data['patient_gender_counts'] = [];
if ($result) while ($row = $result->fetch_assoc()) {
    $chart_data['patient_gender_labels'][] = $row['gender'];
    $chart_data['patient_gender_counts'][] = (int)$row['count'];
}

// 5. Top 5 Most Prescribed Drugs (Value/Count) - Modified for value
$sql_top_prescribed_drugs = "
    SELECT i.ilac_ad, SUM(r.ilac_adet) AS total_quantity, SUM(r.ilac_adet * i.satis_fiyat) as total_value
    FROM recete r JOIN ilac i ON r.ilac_id = i.ilac_id
    GROUP BY i.ilac_ad ORDER BY total_value DESC LIMIT 5"; // Order by value
$result = $conn->query($sql_top_prescribed_drugs);
$chart_data['top_drug_value_labels'] = []; $chart_data['top_drug_value_counts'] = [];
if ($result) while ($row = $result->fetch_assoc()) {
    $chart_data['top_drug_value_labels'][] = $row['ilac_ad'];
    $chart_data['top_drug_value_counts'][] = (float)$row['total_value'];
}

// 6. Prescriptions in the Last 30 Days (Line Chart, daily)
$sql_prescriptions_last_30_days = "
    SELECT DATE_FORMAT(recete_tarih, '%Y-%m-%d') AS prescription_date, COUNT(recete_id) AS count
    FROM recete WHERE recete_tarih >= CURDATE() - INTERVAL 29 DAY AND recete_tarih <= CURDATE()
    GROUP BY prescription_date ORDER BY prescription_date ASC";
$result = $conn->query($sql_prescriptions_last_30_days);
$temp_labels = []; $temp_counts = [];
for ($i = 29; $i >= 0; $i--) { // Iterate for last 30 days (0 to 29)
    $date = date('d M', strtotime("-$i days"));
    $db_date = date('Y-m-d', strtotime("-$i days"));
    $temp_labels[] = $date;
    $temp_counts[$db_date] = 0;
}
if ($result) while ($row = $result->fetch_assoc()) {
    if (array_key_exists($row['prescription_date'], $temp_counts)) {
        $temp_counts[$row['prescription_date']] = (int)$row['count'];
    }
}
$chart_data['prescription_30day_labels'] = $temp_labels;
$chart_data['prescription_30day_counts'] = array_values($temp_counts);

// 7. Manufacturer Agreement Status (Doughnut Chart)
$sql_agreement_status = "
    SELECT CASE WHEN anlasma_baslangic IS NULL OR anlasma_bitis IS NULL THEN 'Anlaşma Yok' WHEN CURDATE() BETWEEN anlasma_baslangic AND anlasma_bitis THEN 'Aktif' WHEN CURDATE() < anlasma_baslangic THEN 'Başlamadı' WHEN CURDATE() > anlasma_bitis THEN 'Süresi Doldu' ELSE 'Diğer' END AS status, COUNT(DISTINCT uretici_id) AS count
    FROM ilac GROUP BY status ORDER BY FIELD(status, 'Aktif', 'Başlamadı', 'Süresi Doldu', 'Anlaşma Yok', 'Diğer')";
$result = $conn->query($sql_agreement_status);
$chart_data['agreement_status_labels'] = []; $chart_data['agreement_status_counts'] = [];
if ($result) while ($row = $result->fetch_assoc()) {
    $chart_data['agreement_status_labels'][] = $row['status'];
    $chart_data['agreement_status_counts'][] = (int)$row['count'];
}

// 8. Prescriptions by Doctor Specialization - Modified
$sql_prescriptions_by_specialization = "
    SELECT d.uzmanlik, COUNT(r.recete_id) AS prescription_count
    FROM recete r JOIN doktor d ON r.doktor_id = d.doktor_id
    WHERE d.uzmanlik IS NOT NULL AND d.uzmanlik != ''
    GROUP BY d.uzmanlik ORDER BY prescription_count DESC LIMIT 7";
$result = $conn->query($sql_prescriptions_by_specialization);
$chart_data['doc_spec_labels'] = []; $chart_data['doc_spec_counts'] = [];
if ($result) while ($row = $result->fetch_assoc()) {
    $chart_data['doc_spec_labels'][] = $row['uzmanlik'];
    $chart_data['doc_spec_counts'][] = (int)$row['prescription_count'];
}

// 9. NEW: Revenue Trend (Last 30 Days) - Line Chart
$sql_revenue_trend_30_days = "
    SELECT DATE_FORMAT(r.recete_tarih, '%Y-%m-%d') AS sale_date, SUM(i.satis_fiyat * r.ilac_adet) AS daily_revenue
    FROM recete r JOIN ilac i ON r.ilac_id = i.ilac_id
    WHERE r.recete_tarih >= CURDATE() - INTERVAL 29 DAY AND r.recete_tarih <= CURDATE()
    GROUP BY sale_date ORDER BY sale_date ASC";
$result = $conn->query($sql_revenue_trend_30_days);
$temp_rev_labels = []; $temp_rev_counts = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('d M', strtotime("-$i days"));
    $db_date = date('Y-m-d', strtotime("-$i days"));
    $temp_rev_labels[] = $date;
    $temp_rev_counts[$db_date] = 0;
}
if ($result) while ($row = $result->fetch_assoc()) {
    if (array_key_exists($row['sale_date'], $temp_rev_counts)) {
        $temp_rev_counts[$row['sale_date']] = (float)$row['daily_revenue'];
    }
}
$chart_data['revenue_30day_labels'] = $temp_rev_labels;
$chart_data['revenue_30day_counts'] = array_values($temp_rev_counts);

// 10. NEW: Profit Margin by Drug (Top 5 profitable) - Requires alis_fiyat
$sql_top_profit_drugs = "
    SELECT i.ilac_ad, SUM((i.satis_fiyat - i.alis_fiyat) * r.ilac_adet) as total_profit
    FROM recete r JOIN ilac i ON r.ilac_id = i.ilac_id
    WHERE i.alis_fiyat > 0 AND i.satis_fiyat > i.alis_fiyat
    GROUP BY i.ilac_ad ORDER BY total_profit DESC LIMIT 5";
$result = $conn->query($sql_top_profit_drugs);
$chart_data['top_profit_drug_labels'] = []; $chart_data['top_profit_drug_values'] = [];
if($result) while($row = $result->fetch_assoc()){
    $chart_data['top_profit_drug_labels'][] = $row['ilac_ad'];
    $chart_data['top_profit_drug_values'][] = (float)$row['total_profit'];
}


// --- Table Data ---
$table_data = [];

$low_stock_threshold = 20; // Lowered threshold for more critical view
$sql_low_stock_drugs = "
    SELECT i.ilac_ad, i.stok, u.uretici_ad, i.barkod, i.alis_fiyat, i.satis_fiyat
    FROM ilac i LEFT JOIN uretici u ON i.uretici_id = u.uretici_id
    WHERE i.stok < {$low_stock_threshold} AND i.stok >= 0 ORDER BY i.stok ASC, i.ilac_ad ASC LIMIT 10";
$result = $conn->query($sql_low_stock_drugs);
$table_data['low_stock_drugs'] = [];
if ($result) while ($row = $result->fetch_assoc()) $table_data['low_stock_drugs'][] = $row;

// Recent Prescriptions (last 5 with total value)
$sql_recent_prescriptions = "
    SELECT r.recete_id, h.hasta_ad_soyad, d.doktor_ad_soyad, i.ilac_ad, r.ilac_adet, DATE_FORMAT(r.recete_tarih, '%d.%m.%Y') as recete_tarih_formatted, (i.satis_fiyat * r.ilac_adet) as total_value
    FROM recete r JOIN hasta h ON r.hasta_tckn = h.hasta_tckn JOIN doktor d ON r.doktor_id = d.doktor_id JOIN ilac i ON r.ilac_id = i.ilac_id
    ORDER BY r.recete_tarih DESC, r.recete_id DESC LIMIT 5";
$result = $conn->query($sql_recent_prescriptions);
$table_data['recent_prescriptions'] = [];
if ($result) while ($row = $result->fetch_assoc()) $table_data['recent_prescriptions'][] = $row;

// NEW: Top 5 Most Valuable Patients
$sql_top_valuable_patients = "
    SELECT h.hasta_ad_soyad, SUM(i.satis_fiyat * r.ilac_adet) AS total_spent, COUNT(DISTINCT r.recete_id) as total_prescriptions
    FROM recete r JOIN hasta h ON r.hasta_tckn = h.hasta_tckn JOIN ilac i ON r.ilac_id = i.ilac_id
    GROUP BY h.hasta_tckn, h.hasta_ad_soyad ORDER BY total_spent DESC LIMIT 5";
$result = $conn->query($sql_top_valuable_patients);
$table_data['top_valuable_patients'] = [];
if ($result) while ($row = $result->fetch_assoc()) $table_data['top_valuable_patients'][] = $row;

// NEW: Expiring Agreements (next 60 days)
$sql_expiring_agreements = "
    SELECT u.uretici_ad, i.ilac_ad, i.anlasma_bitis
    FROM ilac i JOIN uretici u ON i.uretici_id = u.uretici_id
    WHERE i.anlasma_bitis BETWEEN CURDATE() AND CURDATE() + INTERVAL 60 DAY
    ORDER BY i.anlasma_bitis ASC LIMIT 5";
$result = $conn->query($sql_expiring_agreements);
$table_data['expiring_agreements'] = [];
if($result) while($row = $result->fetch_assoc()) $table_data['expiring_agreements'][] = $row;

$conn->close();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kapsamlı Eczane Analiz ve Yönetim Paneli</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="dist/css/adminlte.min.css">
    <script src="plugins/jquery/jquery.min.js"></script>
    <script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script> {/* For time scale */}
    <script src="dist/js/adminlte.min.js"></script>
    <style>
        .info-box .info-box-icon { font-size: 38px; width: 80px; height: 80px; line-height: 80px;}
        .info-box .info-box-content { padding: 10px 15px; margin-left: 80px;}
        .info-box-text {font-size: 0.9rem; text-transform: uppercase; font-weight: 500; color: #555;}
        .info-box-number {font-size: 1.6rem; font-weight: 600;}
        .card-title { font-weight: 600; font-size: 1.1rem; }
        .chart-container { position: relative; height:330px; width:100%; padding-top:15px; }
        .table-responsive-sm { max-height: 300px; } /* Slightly reduced */
        .main-footer { background-color: #f4f6f9; border-top: 1px solid #dee2e6; padding: 1rem; text-align:center; }
        .nav-sidebar .nav-link p { white-space: normal; }
        .brand-link .brand-image { float: none; line-height: .8; margin-left: .8rem; margin-right: .5rem; margin-top: -3px; max-height: 33px; width: auto; }
        .kpi-box { margin-bottom: 20px; } /* Increased margin for KPIs */
        .table th, .table td { vertical-align: middle; font-size:0.9rem; }
        .badge { font-size: 0.8rem; padding: .3em .6em; }
        .content-wrapper { background-color: #eef1f4; } /* Lighter background */
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
    <div class="wrapper">

        <nav class="main-header navbar navbar-expand navbar-white navbar-light border-bottom-0">
            <ul class="navbar-nav">
                <li class="nav-item"><a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a></li>
                <li class="nav-item d-none d-sm-inline-block"><a href="#" class="nav-link font-weight-bold">Ana Panel</a></li>
            </ul>
            <span class="navbar-brand mx-auto d-block text-center" style="font-weight:bold; font-size: 1.4rem;">
                <i class="fas fa-laptop-medical text-danger"></i> PharmAnalytics Pro
            </span>
            <ul class="navbar-nav">
                 <li class="nav-item"><a class="nav-link" data-widget="fullscreen" href="#" role="button"><i class="fas fa-expand-arrows-alt"></i></a></li>
            </ul>
        </nav>

        <aside class="main-sidebar sidebar-dark-danger elevation-4"> {/* Theme color */}
            <a href="pharmacy_dashboard.php" class="brand-link">
                <img src="dist/img/AdminLTELogo.png" alt="Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
                <span class="brand-text font-weight-light">PharmAnalytics</span>
            </a>
            <div class="sidebar">
                <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                    <div class="image"><img src="dist/img/user_avatar.png" class="img-circle elevation-2" alt="User Image"></div> {/* Generic avatar */}
                    <div class="info"><a href="#" class="d-block">Yönetici Panel</a></div>
                </div>
                <nav class="mt-2">
                    <ul class="nav nav-pills nav-sidebar flex-column nav-flat nav-child-indent" data-widget="treeview" role="menu" data-accordion="false">
                        <li class="nav-item"><a href="pharmacy_dashboard.php" class="nav-link active"><i class="nav-icon fas fa-chart-pie"></i><p>Genel Bakış</p></a></li>
                        <li class="nav-header">TEMEL OPERASYONLAR</li>
                        <li class="nav-item">
                            <a href="#" class="nav-link"><i class="nav-icon fas fa-pills"></i><p>İlaç Yönetimi<i class="fas fa-angle-left right"></i></p></a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item"><a href="#" class="nav-link"><i class="far fa-circle nav-icon text-primary"></i><p>Tüm İlaçlar</p></a></li>
                                <li class="nav-item"><a href="#" class="nav-link"><i class="far fa-circle nav-icon text-warning"></i><p>Stok Takibi</p></a></li>
                                <li class="nav-item"><a href="#" class="nav-link"><i class="far fa-circle nav-icon text-info"></i><p>Sipariş Yönetimi</p></a></li>
                            </ul>
                        </li>
                        <li class="nav-item"><a href="#" class="nav-link"><i class="nav-icon fas fa-file-invoice-dollar"></i><p>Satış & Reçeteler</p></a></li>
                        <li class="nav-item"><a href="#" class="nav-link"><i class="nav-icon fas fa-users-cog"></i><p>Hasta Yönetimi</p></a></li>
                        <li class="nav-item"><a href="#" class="nav-link"><i class="nav-icon fas fa-user-doctor"></i><p>Doktor Veritabanı</p></a></li> {/* Changed icon */}
                        <li class="nav-item"><a href="#" class="nav-link"><i class="nav-icon fas fa-truck-moving"></i><p>Tedarikçi İlişkileri</p></a></li>
                        <li class="nav-header">ANALİTİK VE RAPORLAMA</li>
                        <li class="nav-item"><a href="#" class="nav-link"><i class="nav-icon fas fa-dollar-sign"></i><p>Finansal Raporlar</p></a></li>
                        <li class="nav-item"><a href="#" class="nav-link"><i class="nav-icon fas fa-boxes"></i><p>Envanter Analizi</p></a></li>
                        <li class="nav-item"><a href="#" class="nav-link"><i class="nav-icon fas fa-user-friends"></i><p>Personel Performansı</p></a></li>
                         <li class="nav-item">
                            <a href="index.php" class="nav-link">
                                <i class="nav-icon fa fa-home" aria-hidden="true"></i>
                                <p>Ana Sayfaya Dön</p>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </aside>

        <div class="content-wrapper p-3">
            <div class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6"><h1 class="m-0 text-dark">Eczane Performans Paneli</h1></div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="#">Ana Panel</a></li>
                                <li class="breadcrumb-item active">Performans</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <section class="content">
                <div class="container-fluid">
                    <!-- KPIs Row (using info-box) -->
                    <div class="row">
                        <div class="col-md-3 col-sm-6 col-12 kpi-box">
                            <div class="info-box shadow-sm"><span class="info-box-icon bg-danger"><i class="fas fa-prescription-bottle-alt"></i></span><div class="info-box-content"><span class="info-box-text">Toplam Reçete</span><span class="info-box-number"><?php echo htmlspecialchars($kpi_data['total_prescriptions']); ?></span></div></div>
                        </div>
                        <div class="col-md-3 col-sm-6 col-12 kpi-box">
                            <div class="info-box shadow-sm"><span class="info-box-icon bg-success"><i class="fas fa-money-bill-wave"></i></span><div class="info-box-content"><span class="info-box-text">Son 30G Ciro</span><span class="info-box-number"><?php echo htmlspecialchars(number_format($kpi_data['revenue_last_30_days'],0)); ?> <small>TL</small></span></div></div>
                        </div>
                        <div class="col-md-3 col-sm-6 col-12 kpi-box">
                            <div class="info-box shadow-sm"><span class="info-box-icon bg-info"><i class="fas fa-users"></i></span><div class="info-box-content"><span class="info-box-text">Toplam Hasta</span><span class="info-box-number"><?php echo htmlspecialchars($kpi_data['total_patients']); ?></span></div></div>
                        </div>
                        <div class="col-md-3 col-sm-6 col-12 kpi-box">
                            <div class="info-box shadow-sm"><span class="info-box-icon bg-warning"><i class="fas fa-capsules"></i></span><div class="info-box-content"><span class="info-box-text">İlaç Çeşidi</span><span class="info-box-number"><?php echo htmlspecialchars($kpi_data['total_drug_types']); ?></span></div></div>
                        </div>
                    </div>
                     <div class="row">
                         <div class="col-md-3 col-sm-6 col-12 kpi-box">
                            <div class="info-box shadow-sm"><span class="info-box-icon bg-primary"><i class="fas fa-user-md"></i></span><div class="info-box-content"><span class="info-box-text">Kayıtlı Doktor</span><span class="info-box-number"><?php echo htmlspecialchars($kpi_data['total_doctors']); ?></span></div></div>
                        </div>
                        <div class="col-md-3 col-sm-6 col-12 kpi-box">
                            <div class="info-box shadow-sm"><span class="info-box-icon bg-purple"><i class="fas
                            fa-box-open"></i></span><div class="info-box-content"><span class="info-box-text">Ort. İlaç Stoğu</span><span class="info-box-number"><?php echo htmlspecialchars(round($kpi_data['avg_stock'], 1)); ?></span></div></div>
                        </div>
                        <div class="col-md-3 col-sm-6 col-12 kpi-box">
                            <div class="info-box shadow-sm"><span class="info-box-icon bg-orange"><i class="fas fa-tags"></i></span><div class="info-box-content"><span class="info-box-text">Ort. İlaç Fiyatı</span><span class="info-box-number"><?php echo htmlspecialchars(number_format($kpi_data['avg_drug_price'], 2)); ?> <small>TL</small></span></div></div>
                        </div>
                        <div class="col-md-3 col-sm-6 col-12 kpi-box">
                            <div class="info-box shadow-sm"><span class="info-box-icon bg-maroon"><i class="fas fa-ban"></i></span><div class="info-box-content"><span class="info-box-text">Stoğu Biten İlaç</span><span class="info-box-number"><?php echo htmlspecialchars($kpi_data['out_of_stock_drugs']); ?></span></div></div>
                        </div>
                    </div>

                    <!-- Row 1: Core Financials & Operations -->
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card card-danger card-outline">
                                <div class="card-header"><h3 class="card-title"><i class="fas fa-chart-line"></i> Son 30 Günlük Ciro ve Reçete Trendi</h3></div>
                                <div class="card-body"><div class="chart-container" style="height:350px;"><canvas id="revenueAndPrescriptionTrendChart"></canvas></div></div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card card-success card-outline">
                                <div class="card-header"><h3 class="card-title"><i class="fas fa-chart-pie"></i> Hasta Cinsiyet Dağılımı</h3></div>
                                <div class="card-body"><div class="chart-container" style="height:150px"><canvas id="patientGenderChart"></canvas></div></div>
                            </div>
                             <div class="card card-warning card-outline mt-3">
                                <div class="card-header"><h3 class="card-title"><i class="fas fa-birthday-cake"></i> Hasta Yaş Grupları</h3></div>
                                <div class="card-body"><div class="chart-container" style="height:150px"><canvas id="patientAgeDistributionChart"></canvas></div></div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 2: Drug & Inventory Insights -->
                    <div class="row">
                        <div class="col-md-5">
                             <div class="card card-info card-outline">
                                <div class="card-header"><h3 class="card-title"><i class="fas fa-cubes"></i> İlaç Stok Seviyeleri (Tür Bazlı)</h3></div>
                                <div class="card-body"><div class="chart-container"><canvas id="drugStockCategoriesChart"></canvas></div></div>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <div class="card card-purple card-outline">
                                <div class="card-header"><h3 class="card-title"><i class="fas fa-hand-holding-usd"></i> En Çok Ciro Getiren İlaçlar (Top 5)</h3></div>
                                <div class="card-body"><div class="chart-container"><canvas id="topDrugsByValueChart"></canvas></div></div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 3: Manufacturer & Doctor Insights -->
                    <div class="row">
                         <div class="col-md-6">
                            <div class="card card-teal card-outline">
                                <div class="card-header"><h3 class="card-title"><i class="fas fa-industry"></i> Üreticiye Göre İlaç Çeşitliliği (Top 7)</h3></div>
                                <div class="card-body"><div class="chart-container"><canvas id="drugsPerManufacturerChart"></canvas></div></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card card-olive card-outline">
                                <div class="card-header"><h3 class="card-title"><i class="fas fa-stethoscope"></i> Doktor Uzmanlık Alanına Göre Reçete Yoğunluğu</h3></div>
                                <div class="card-body"><div class="chart-container"><canvas id="doctorSpecializationVolumeChart"></canvas></div></div>
                            </div>
                        </div>
                    </div>
                     <div class="row">
                        <div class="col-md-6">
                             <div class="card card-maroon card-outline">
                                <div class="card-header"><h3 class="card-title"><i class="fas fa-file-signature"></i> Üretici Anlaşma Durumları</h3></div>
                                <div class="card-body"><div class="chart-container" style="height:300px"><canvas id="agreementStatusChart"></canvas></div></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card card-indigo card-outline">
                                <div class="card-header"><h3 class="card-title"><i class="fas fa-hand-holding-medical"></i> En Karlı İlaçlar (Top 5)</h3></div>
                                <div class="card-body"><div class="chart-container" style="height:300px"><canvas id="topProfitableDrugsChart"></canvas></div></div>
                            </div>
                        </div>
                    </div>


                    <!-- Row 4: Operational Tables -->
                    <div class="row">
                        <div class="col-lg-7">
                            <div class="card card-dark card-outline">
                                <div class="card-header"><h3 class="card-title"><i class="fas fa-receipt"></i> Son Reçeteler ve Değerleri</h3></div>
                                <div class="card-body p-0 table-responsive table-responsive-sm">
                                    <table class="table table-sm table-striped table-hover">
                                        <thead><tr><th>ID</th><th>Hasta</th><th>Doktor</th><th>İlaç</th><th>Adet</th><th>Tarih</th><th>Değer (TL)</th></tr></thead>
                                        <tbody>
                                            <?php if (!empty($table_data['recent_prescriptions'])): ?>
                                                <?php foreach ($table_data['recent_prescriptions'] as $rec): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($rec['recete_id']); ?></td>
                                                    <td><?php echo htmlspecialchars($rec['hasta_ad_soyad']); ?></td>
                                                    <td><?php echo htmlspecialchars($rec['doktor_ad_soyad']); ?></td>
                                                    <td><?php echo htmlspecialchars($rec['ilac_ad']); ?></td>
                                                    <td><span class="badge bg-info"><?php echo htmlspecialchars($rec['ilac_adet']); ?></span></td>
                                                    <td><?php echo htmlspecialchars($rec['recete_tarih_formatted']); ?></td>
                                                    <td class="text-right font-weight-bold"><?php echo htmlspecialchars(number_format($rec['total_value'],2)); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr><td colspan="7" class="text-center py-3">Son reçete bulunmamaktadır.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="card card-danger card-outline">
                                <div class="card-header"><h3 class="card-title"><i class="fas fa-exclamation-triangle"></i> Kritik Stok Seviyesindeki İlaçlar (< <?php echo $low_stock_threshold; ?>)</h3></div>
                                <div class="card-body p-0 table-responsive table-responsive-sm" style="height: 180px;">
                                    <table class="table table-sm table-head-fixed table-hover">
                                        <thead><tr><th>İlaç</th><th>Stok</th><th>Üretici</th><th>Sipariş</th></tr></thead>
                                        <tbody>
                                            <?php if (!empty($table_data['low_stock_drugs'])): ?>
                                                <?php foreach ($table_data['low_stock_drugs'] as $drug): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($drug['ilac_ad']); ?></td>
                                                    <td><span class="badge bg-danger font-weight-bold"><?php echo htmlspecialchars($drug['stok']); ?></span></td>
                                                    <td><?php echo htmlspecialchars($drug['uretici_ad'] ?? 'N/A'); ?></td>
                                                    <td><a href="#" class="btn btn-xs btn-outline-primary" title="Sipariş Oluştur"><i class="fas fa-plus-circle"></i></a></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr><td colspan="4" class="text-center py-3">Kritik stokta ilaç yok.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                             <div class="card card-warning card-outline mt-2">
                                <div class="card-header"><h3 class="card-title"><i class="far fa-calendar-times"></i> Yaklaşan Anlaşma Bitişleri (60 Gün)</h3></div>
                                <div class="card-body p-0 table-responsive table-responsive-sm" style="height: 180px;">
                                    <table class="table table-sm table-head-fixed table-hover">
                                        <thead><tr><th>Üretici</th><th>İlaç</th><th>Bitiş Tarihi</th></tr></thead>
                                        <tbody>
                                            <?php if (!empty($table_data['expiring_agreements'])): ?>
                                                <?php foreach ($table_data['expiring_agreements'] as $agree): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($agree['uretici_ad']); ?></td>
                                                    <td><?php echo htmlspecialchars($agree['ilac_ad']); ?></td>
                                                    <td><span class="badge bg-warning"><?php echo htmlspecialchars(date('d.m.Y', strtotime($agree['anlasma_bitis']))); ?></span></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr><td colspan="3" class="text-center py-3">Yaklaşan anlaşma bitişi yok.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                     <div class="row">
                        <div class="col-12">
                             <div class="card card-fuchsia card-outline">
                                <div class="card-header"><h3 class="card-title"><i class="fas fa-medal"></i> En Değerli Hastalar (Harcama Bazlı Top 5)</h3></div>
                                <div class="card-body p-0 table-responsive table-responsive-sm">
                                    <table class="table table-sm table-striped table-hover">
                                        <thead><tr><th>#</th><th>Hasta Adı Soyadı</th><th>Toplam Harcama (TL)</th><th>Toplam Reçete Sayısı</th></tr></thead>
                                        <tbody>
                                            <?php if (!empty($table_data['top_valuable_patients'])): $rank = 1; ?>
                                                <?php foreach ($table_data['top_valuable_patients'] as $patient): ?>
                                                <tr>
                                                    <td><?php echo $rank++; ?>.</td>
                                                    <td><i class="fas fa-user-tag text-primary mr-2"></i><?php echo htmlspecialchars($patient['hasta_ad_soyad']); ?></td>
                                                    <td class="text-right font-weight-bold"><?php echo htmlspecialchars(number_format($patient['total_spent'], 2)); ?></td>
                                                    <td class="text-center"><span class="badge bg-success"><?php echo htmlspecialchars($patient['total_prescriptions']); ?></span></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr><td colspan="4" class="text-center py-3">Veri bulunamadı.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </section>
        </div>

        <footer class="main-footer">
            <strong>Telif Hakkı © <?php echo date("Y"); ?> <a href="#">PharmAnalytics Pro Solutions</a>.</strong> Tüm hakları saklıdır.
            <div class="float-right d-none d-sm-inline">Versiyon 4.0</div>
        </footer>
    </div>

<script>
$(function () {
    Chart.defaults.font.family = "'Source Sans Pro', 'Helvetica Neue', Helvetica, Arial, sans-serif";
    Chart.defaults.font.size = 11; // Slightly smaller for more density
    Chart.defaults.color = '#555';
    Chart.defaults.borderColor = 'rgba(0,0,0,0.07)';
    Chart.defaults.plugins.legend.labels.boxWidth = 12;
    Chart.defaults.plugins.legend.labels.padding = 10;


    const appColors = {
        primary: '#007bff', success: '#28a745', info: '#17a2b8', warning: '#ffc107',
        danger: '#dc3545', secondary: '#6c757d', purple: '#6f42c1', teal: '#20c997',
        pink: '#e83e8c', orange: '#fd7e14', indigo: '#6610f2', olive: '#3d9970',
        maroon: '#d81b60', lightblue: '#3c8dbc', fuchsia: '#f012be', lime: '#01ff70'
    };

    const chartPalette = [
        appColors.primary, appColors.success, appColors.info, appColors.warning, appColors.danger,
        appColors.purple, appColors.teal, appColors.orange, appColors.pink, appColors.olive,
        appColors.maroon, appColors.lightblue, appColors.fuchsia, appColors.lime
    ];

    function formatCurrency(value) {
        return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(value);
    }
    function formatNumber(value) {
        return new Intl.NumberFormat('tr-TR').format(value);
    }

    // --- Initialize Charts ---

    // Combined Revenue and Prescription Trend Chart
    const revenueAndPrescriptionTrendCtx = document.getElementById('revenueAndPrescriptionTrendChart')?.getContext('2d');
    if (revenueAndPrescriptionTrendCtx) {
        new Chart(revenueAndPrescriptionTrendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_data['revenue_30day_labels'] ?? []); ?>,
                datasets: [
                    {
                        label: 'Günlük Ciro (TL)',
                        data: <?php echo json_encode($chart_data['revenue_30day_counts'] ?? []); ?>,
                        borderColor: appColors.danger,
                        backgroundColor: appColors.danger + '1A', // Very transparent
                        yAxisID: 'yRevenue',
                        tension: 0.3,
                        pointRadius: 2,
                        fill: true
                    },
                    {
                        label: 'Günlük Reçete Sayısı',
                        data: <?php echo json_encode($chart_data['prescription_30day_counts'] ?? []); ?>,
                        borderColor: appColors.primary,
                        backgroundColor: appColors.primary + '1A',
                        yAxisID: 'yPrescriptions',
                        tension: 0.3,
                        pointRadius: 2,
                        type: 'bar', // Can mix types
                        order: 1 // Draw bars behind lines
                    }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                scales: {
                    x: { grid: { display: false } },
                    yRevenue: {
                        type: 'linear', position: 'left',
                        title: { display: true, text: 'Ciro (TL)', color: appColors.danger },
                        ticks: { color: appColors.danger, callback: function(value) { return formatCurrency(value); } },
                        grid: { drawOnChartArea: false } // Only display grid for primary axis
                    },
                    yPrescriptions: {
                        type: 'linear', position: 'right',
                        title: { display: true, text: 'Reçete Sayısı', color: appColors.primary },
                        ticks: { color: appColors.primary, stepSize: 1 },
                        grid: { display: false }
                    }
                },
                plugins: {
                    legend: { position: 'top'},
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) { label += ': '; }
                                if (context.parsed.y !== null) {
                                    if (context.dataset.yAxisID === 'yRevenue') {
                                        label += formatCurrency(context.parsed.y);
                                    } else {
                                        label += formatNumber(context.parsed.y);
                                    }
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }


    // Patient Gender Chart (Pie)
    const patientGenderCtx = document.getElementById('patientGenderChart')?.getContext('2d');
    if (patientGenderCtx) { new Chart(patientGenderCtx, { type: 'pie', data: { labels: <?php echo json_encode($chart_data['patient_gender_labels'] ?? []); ?>, datasets: [{ data: <?php echo json_encode($chart_data['patient_gender_counts'] ?? []); ?>, backgroundColor: [appColors.info, appColors.pink, appColors.secondary], borderWidth:0 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels:{padding:10} } } } }); }

    // Patient Age Distribution Chart (Bar - small)
    const patientAgeCtx = document.getElementById('patientAgeDistributionChart')?.getContext('2d');
    if (patientAgeCtx) { new Chart(patientAgeCtx, { type: 'bar', data: { labels: <?php echo json_encode($chart_data['patient_age_labels'] ?? []); ?>, datasets: [{ label: 'Hasta Sayısı', data: <?php echo json_encode($chart_data['patient_age_counts'] ?? []); ?>, backgroundColor: appColors.warning + 'B3', borderColor: appColors.warning, borderWidth: 1 }] }, options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks:{precision:0} } }, plugins: { legend: { display: false } } } }); }

    // Drug Stock Categories Chart (Doughnut)
    const drugStockCtx = document.getElementById('drugStockCategoriesChart')?.getContext('2d');
    if (drugStockCtx) { new Chart(drugStockCtx, { type: 'doughnut', data: { labels: <?php echo json_encode($chart_data['stock_category_labels'] ?? []); ?>, datasets: [{ data: <?php echo json_encode($chart_data['stock_category_counts'] ?? []); ?>, backgroundColor: [appColors.maroon, appColors.danger, appColors.orange, appColors.info, appColors.success], borderWidth:1, borderColor:'#fff' }] }, options: { responsive: true, maintainAspectRatio: false, cutout: '60%', plugins: { legend: { position: 'right', labels:{padding:10} } } } }); }

    // Top Drugs by Value Chart (Horizontal Bar)
    const topDrugsByValueCtx = document.getElementById('topDrugsByValueChart')?.getContext('2d');
    if (topDrugsByValueCtx) { new Chart(topDrugsByValueCtx, { type: 'bar', data: { labels: <?php echo json_encode($chart_data['top_drug_value_labels'] ?? []); ?>, datasets: [{ label: 'Toplam Ciro (TL)', data: <?php echo json_encode($chart_data['top_drug_value_counts'] ?? []); ?>, backgroundColor: chartPalette.slice(0,5).map(c => c + 'B3'), borderColor: chartPalette.slice(0,5), borderWidth: 1 }] }, options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, scales: { x: { beginAtZero: true, title: { display: true, text: 'Toplam Ciro (TL)' }, ticks: { callback: function(value){ return formatCurrency(value);}} } }, plugins: { legend: { display: false }, tooltip: {callbacks: {label: function(context){return ' Ciro: ' + formatCurrency(context.raw);}}}} } }); }

    // Drugs per Manufacturer Chart (Pie)
    const drugsPerManCtx = document.getElementById('drugsPerManufacturerChart')?.getContext('2d');
    if (drugsPerManCtx) { new Chart(drugsPerManCtx, { type: 'pie', data: { labels: <?php echo json_encode($chart_data['manufacturer_labels'] ?? []); ?>, datasets: [{ data: <?php echo json_encode($chart_data['manufacturer_drug_counts'] ?? []); ?>, backgroundColor: chartPalette.slice(5, 12), borderWidth:1, borderColor:'#fff' }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels:{padding:10} } } } }); }

    // Doctor Specialization Volume Chart (Bar)
    const docSpecVolumeCtx = document.getElementById('doctorSpecializationVolumeChart')?.getContext('2d');
    if (docSpecVolumeCtx) { new Chart(docSpecVolumeCtx, { type: 'bar', data: { labels: <?php echo json_encode($chart_data['doc_spec_labels'] ?? []); ?>, datasets: [{ label: 'Reçete Sayısı', data: <?php echo json_encode($chart_data['doc_spec_counts'] ?? []); ?>, backgroundColor: chartPalette.map(c => c + 'CC'), borderColor: chartPalette, borderWidth: 1 }] }, options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, title: { display: true, text: 'Reçete Sayısı' }, ticks:{precision:0} } }, plugins: { legend: { display: false } } } }); }

    // Manufacturer Agreement Status Chart (Doughnut)
    const agreementStatusCtx = document.getElementById('agreementStatusChart')?.getContext('2d');
    if (agreementStatusCtx) { new Chart(agreementStatusCtx, { type: 'doughnut', data: { labels: <?php echo json_encode($chart_data['agreement_status_labels'] ?? []); ?>, datasets: [{ data: <?php echo json_encode($chart_data['agreement_status_counts'] ?? []); ?>, backgroundColor: [appColors.success, appColors.orange, appColors.danger, appColors.secondary, appColors.info], borderWidth:1, borderColor:'#fff' }] }, options: { responsive: true, maintainAspectRatio: false, cutout: '60%', plugins: { legend: { position: 'bottom', labels:{padding:10} } } } }); }

    // Top Profitable Drugs Chart (Horizontal Bar)
    const topProfitableDrugsCtx = document.getElementById('topProfitableDrugsChart')?.getContext('2d');
    if (topProfitableDrugsCtx) {
        new Chart(topProfitableDrugsCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_data['top_profit_drug_labels'] ?? []); ?>,
                datasets: [{
                    label: 'Toplam Kâr (TL)',
                    data: <?php echo json_encode($chart_data['top_profit_drug_values'] ?? []); ?>,
                    backgroundColor: appColors.indigo + 'B3',
                    borderColor: appColors.indigo,
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                scales: { x: { beginAtZero: true, title: { display: true, text: 'Toplam Kâr (TL)' }, ticks: { callback: function(value){ return formatCurrency(value);}} } },
                plugins: { legend: { display: false }, tooltip: {callbacks: {label: function(context){return ' Kâr: ' + formatCurrency(context.raw);}}}}
            }
        });
    }

});
</script>
</body>
</html>