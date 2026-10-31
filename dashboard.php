<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser($pdo);

// Ambil statistik
$stmt = $pdo->query("SELECT COUNT(*) as total FROM penghuni");
$totalPenghuni = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM penghuni WHERE status_penempatan = 'sudah_ditempatkan'");
$sudahDitempatkan = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT SUM(kuota) as total FROM alternatif");
$totalKuota = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT SUM(terisi) as total FROM alternatif");
$totalTerisi = $stmt->fetch()['total'] ?: 0;

// Ambil data alternatif dengan penghuninya
$stmt = $pdo->query("
    SELECT a.*, 
           (SELECT COUNT(*) FROM penghuni p WHERE p.lokasi_terpilih = a.id) as jumlah_penghuni 
    FROM alternatif a 
    ORDER BY a.id
");
$alternatif = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistem Penempatan Rumah Asrama</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar-brand {
            font-weight: 700;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        .card-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        .bg-gradient-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .bg-gradient-success {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
        }
        .bg-gradient-info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .bg-gradient-warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .progress {
            height: 10px;
            border-radius: 10px;
        }
        .btn-custom {
            border-radius: 10px;
            font-weight: 600;
            padding: 10px 20px;
            transition: all 0.3s ease;
        }
        .btn-custom:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-home me-2"></i>
                Sistem Penempatan Rumah Asrama
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="penghuni.php">
                            <i class="fas fa-users me-1"></i>Data Penghuni
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="kriteria.php">
                            <i class="fas fa-cogs me-1"></i>Kriteria
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="fuzzy_ahp.php">
                            <i class="fas fa-calculator me-1"></i>Perhitungan Fuzzy AHP
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="hasil.php">
                            <i class="fas fa-chart-bar me-1"></i>Hasil Penempatan
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($user['nama_lengkap']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <!-- Welcome Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-gradient-primary text-white">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4 class="mb-1">Selamat Datang, <?php echo htmlspecialchars($user['nama_lengkap']); ?>!</h4>
                                <p class="mb-0">Sistem Penempatan Rumah Asrama menggunakan Metode Fuzzy AHP</p>
                            </div>
                            <div class="col-md-4 text-end">
                                <i class="fas fa-home fa-4x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="card-icon bg-gradient-primary">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                            <div class="col-auto">
                                <div class="text-end">
                                    <div class="h2 mb-0"><?php echo $totalPenghuni; ?></div>
                                    <div class="text-muted">Total Penghuni</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="card-icon bg-gradient-success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            </div>
                            <div class="col-auto">
                                <div class="text-end">
                                    <div class="h2 mb-0"><?php echo $sudahDitempatkan; ?></div>
                                    <div class="text-muted">Sudah Ditempatkan</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="card-icon bg-gradient-info">
                                    <i class="fas fa-home"></i>
                                </div>
                            </div>
                            <div class="col-auto">
                                <div class="text-end">
                                    <div class="h2 mb-0"><?php echo $totalKuota; ?></div>
                                    <div class="text-muted">Total Kuota</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="card-icon bg-gradient-warning">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                            <div class="col-auto">
                                <div class="text-end">
                                    <div class="h2 mb-0"><?php echo ($totalPenghuni - $sudahDitempatkan); ?></div>
                                    <div class="text-muted">Belum Ditempatkan</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>



        <!-- Alternatif Status -->
        <div class="row">
            <?php foreach ($alternatif as $alt): 
                $persentase = ($alt['kuota'] > 0) ? ($alt['jumlah_penghuni'] / $alt['kuota']) * 100 : 0;
                $progressClass = ($persentase >= 90) ? 'bg-danger' : (($persentase >= 70) ? 'bg-warning' : 'bg-success');
            ?>
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-map-marker-alt me-2"></i>
                            <?php echo htmlspecialchars($alt['nama_lokasi']); ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3"><?php echo htmlspecialchars($alt['deskripsi']); ?></p>
                        
                        <div class="row mb-3">
                            <div class="col-6">
                                <div class="text-center">
                                    <div class="h4 mb-0 text-primary"><?php echo $alt['jumlah_penghuni']; ?></div>
                                    <small class="text-muted">Terisi</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center">
                                    <div class="h4 mb-0 text-success"><?php echo $alt['kuota']; ?></div>
                                    <small class="text-muted">Kuota</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-2">
                            <div class="d-flex justify-content-between">
                                <span class="small">Okupansi</span>
                                <span class="small"><?php echo number_format($persentase, 1); ?>%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar <?php echo $progressClass; ?>" 
                                     style="width: <?php echo $persentase; ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="text-center">
                            <span class="badge <?php echo ($alt['kuota'] - $alt['jumlah_penghuni'] > 0) ? 'bg-success' : 'bg-danger'; ?>">
                                Sisa: <?php echo ($alt['kuota'] - $alt['jumlah_penghuni']); ?> unit
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>