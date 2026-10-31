<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser($pdo);

// Ambil statistik penempatan
$stmt = $pdo->query("
    SELECT 
        a.id, a.nama_lokasi, a.kuota, a.terisi,
        COUNT(p.id) as jumlah_penghuni,
        ROUND(AVG(p.skor_akhir), 4) as rata_rata_skor
    FROM alternatif a
    LEFT JOIN penghuni p ON a.id = p.lokasi_terpilih
    GROUP BY a.id, a.nama_lokasi, a.kuota, a.terisi
    ORDER BY a.id
");
$statistikLokasi = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil data penghuni yang sudah ditempatkan
$stmt = $pdo->query("
    SELECT 
        p.*, a.nama_lokasi, a.kode_alternatif
    FROM penghuni p
    JOIN alternatif a ON p.lokasi_terpilih = a.id
    WHERE p.status_penempatan = 'sudah_ditempatkan'
    ORDER BY p.skor_akhir DESC
");
$penghuniDitempatkan = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil penghuni belum ditempatkan
$stmt = $pdo->query("
    SELECT COUNT(*) as total FROM penghuni 
    WHERE status_penempatan = 'belum_ditempatkan'
");
$belumDitempatkan = $stmt->fetch()['total'];

// Data untuk chart
$dataChart = [];
foreach ($statistikLokasi as $lokasi) {
    $dataChart['labels'][] = $lokasi['nama_lokasi'];
    $dataChart['terisi'][] = $lokasi['jumlah_penghuni'];
    $dataChart['kuota'][] = $lokasi['kuota'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Penempatan - Sistem Penempatan Rumah Asrama</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #f8f9fa; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08); }
        .card:hover { transform: translateY(-2px); transition: all 0.3s ease; }
        .stat-card { text-align: center; padding: 30px 20px; }
        .stat-icon { width: 60px; height: 60px; border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: white; margin: 0 auto 15px; }
        .progress-custom { height: 12px; border-radius: 10px; }
        .btn-export { border-radius: 8px; font-weight: 600; }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#"><i class="fas fa-home me-2"></i>Sistem Penempatan Rumah Asrama</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-1"></i>Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="penghuni.php"><i class="fas fa-users me-1"></i>Data Penghuni</a></li>
                    <li class="nav-item"><a class="nav-link" href="kriteria.php"><i class="fas fa-cogs me-1"></i>Kriteria</a></li>
                    <li class="nav-item"><a class="nav-link" href="fuzzy_ahp.php"><i class="fas fa-calculator me-1"></i>Perhitungan Fuzzy AHP</a></li>
                    <li class="nav-item"><a class="nav-link active" href="hasil.php"><i class="fas fa-chart-bar me-1"></i>Hasil Penempatan</a></li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($user['nama_lengkap']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-gradient-success text-white">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4 class="mb-1"><i class="fas fa-chart-bar me-2"></i>Hasil Penempatan Rumah Asrama</h4>
                                <p class="mb-0">Laporan lengkap hasil perhitungan Fuzzy AHP dan penempatan penghuni</p>
                            </div>
                            <div class="col-md-4 text-end">
                                <button class="btn btn-light btn-export me-2" onclick="printReport()">
                                    <i class="fas fa-print me-2"></i>Print
                                </button>
                                <button class="btn btn-light btn-export" onclick="exportToExcel()">
                                    <i class="fas fa-file-excel me-2"></i>Export
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistik Ringkas -->
        <div class="row mb-4">
            <?php foreach ($statistikLokasi as $index => $lokasi): 
                $persentase = ($lokasi['kuota'] > 0) ? ($lokasi['jumlah_penghuni'] / $lokasi['kuota']) * 100 : 0;
                $colors = ['bg-gradient-primary', 'bg-gradient-info', 'bg-gradient-warning'];
                $icons = ['fa-road', 'fa-building', 'fa-tree'];
            ?>
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-body stat-card">
                        <div class="stat-icon <?php echo $colors[$index % 3]; ?>">
                            <i class="fas <?php echo $icons[$index % 3]; ?>"></i>
                        </div>
                        <h5 class="mb-1"><?php echo htmlspecialchars($lokasi['nama_lokasi']); ?></h5>
                        <div class="row text-center mb-3">
                            <div class="col-6">
                                <div class="h3 mb-0 text-primary"><?php echo $lokasi['jumlah_penghuni']; ?></div>
                                <small class="text-muted">Terisi</small>
                            </div>
                            <div class="col-6">
                                <div class="h3 mb-0 text-success"><?php echo $lokasi['kuota']; ?></div>
                                <small class="text-muted">Kuota</small>
                            </div>
                        </div>
                        <div class="progress progress-custom mb-2">
                            <div class="progress-bar <?php echo ($persentase >= 90) ? 'bg-danger' : (($persentase >= 70) ? 'bg-warning' : 'bg-success'); ?>" 
                                 style="width: <?php echo $persentase; ?>%"></div>
                        </div>
                        <small class="text-muted">Okupansi: <?php echo number_format($persentase, 1); ?>%</small>
                        <?php if ($lokasi['rata_rata_skor'] > 0): ?>
                        <br><small class="text-info">Rata-rata Skor: <?php echo $lokasi['rata_rata_skor']; ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Chart Section -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-column me-2"></i>Grafik Okupansi Lokasi</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="okupansiChart" height="100"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-pie-chart me-2"></i>Distribusi Penempatan</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="distribusiChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabel Hasil Penempatan -->
        <div class="card">
            <div class="card-header">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Daftar Penghuni Ditempatkan</h5>
                    </div>
                    <div class="col-md-6 text-end">
                        <span class="badge bg-success fs-6">
                            <?php echo count($penghuniDitempatkan); ?> dari <?php echo (count($penghuniDitempatkan) + $belumDitempatkan); ?> penghuni
                        </span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($penghuniDitempatkan)): ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="resultTable">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>NIP</th>
                                <th>Nama Lengkap</th>
                                <th>Pangkat</th>
                                <th>Lokasi Ditempatkan</th>
                                <th>Skor Akhir</th>
                                <th>Kriteria Utama</th>
                                <th>Tanggal Penempatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($penghuniDitempatkan as $index => $penghuni): ?>
                            <tr>
                                <td>
                                    <span class="badge <?php 
                                        echo ($index < 10) ? 'bg-success' : (($index < 20) ? 'bg-info' : 'bg-secondary'); 
                                    ?>">
                                        #<?php echo $index + 1; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($penghuni['nip']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($penghuni['nama_lengkap']); ?></strong>
                                    <br><small class="text-muted">
                                        Keluarga: <?php echo $penghuni['jumlah_keluarga']; ?> orang
                                    </small>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($penghuni['pangkat']); ?></span>
                                </td>
                                <td>
                                    <span class="badge <?php 
                                        echo ($penghuni['kode_alternatif'] == 'A1') ? 'bg-success' : 
                                             (($penghuni['kode_alternatif'] == 'A2') ? 'bg-info' : 'bg-warning'); 
                                    ?>">
                                        <?php echo htmlspecialchars($penghuni['nama_lokasi']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold text-primary"><?php echo number_format($penghuni['skor_akhir'], 4); ?></span>
                                </td>
                                <td>
                                    <small>
                                        <i class="fas fa-walking me-1"></i><?php echo ucfirst($penghuni['mobilitas_tugas']); ?><br>
                                        <i class="fas fa-heart me-1"></i><?php echo str_replace('_', ' ', ucfirst($penghuni['kondisi_kesehatan'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y H:i', strtotime($penghuni['created_at'])); ?>
                                    </small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-home fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">Belum Ada Penempatan</h5>
                    <p class="text-muted">Jalankan perhitungan Fuzzy AHP terlebih dahulu</p>
                    <a href="fuzzy_ahp.php" class="btn btn-primary">
                        <i class="fas fa-calculator me-2"></i>Mulai Perhitungan
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($belumDitempatkan > 0): ?>
        <!-- Alert Penghuni Belum Ditempatkan -->
        <div class="card mt-4">
            <div class="card-body">
                <div class="alert alert-warning mb-0">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h6 class="mb-1">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Masih ada <?php echo $belumDitempatkan; ?> penghuni yang belum ditempatkan
                            </h6>
                            <p class="mb-0">Kuota mungkin sudah penuh atau perlu menjalankan ulang perhitungan</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <a href="fuzzy_ahp.php" class="btn btn-warning">
                                <i class="fas fa-redo me-2"></i>Hitung Ulang
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Chart Okupansi
        const okupansiCtx = document.getElementById('okupansiChart').getContext('2d');
        new Chart(okupansiCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($dataChart['labels']); ?>,
                datasets: [
                    {
                        label: 'Terisi',
                        data: <?php echo json_encode($dataChart['terisi']); ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.8)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 2
                    },
                    {
                        label: 'Kuota',
                        data: <?php echo json_encode($dataChart['kuota']); ?>,
                        backgroundColor: 'rgba(255, 206, 86, 0.8)',
                        borderColor: 'rgba(255, 206, 86, 1)',
                        borderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Perbandingan Okupansi vs Kuota'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Chart Distribusi
        const distribusiCtx = document.getElementById('distribusiChart').getContext('2d');
        new Chart(distribusiCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($dataChart['labels']); ?>,
                datasets: [{
                    data: <?php echo json_encode($dataChart['terisi']); ?>,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 206, 86, 0.8)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Functions
        function printReport() {
            window.print();
        }

        function exportToExcel() {
            // Simple CSV export
            let csv = 'Rank,NIP,Nama,Pangkat,Lokasi,Skor,Tanggal\n';
            const rows = document.querySelectorAll('#resultTable tbody tr');
            
            rows.forEach(row => {
                const cols = row.querySelectorAll('td');
                const data = [
                    cols[0].textContent.trim(),
                    cols[1].textContent.trim(),
                    cols[2].textContent.trim(),
                    cols[3].textContent.trim(),
                    cols[4].textContent.trim(),
                    cols[5].textContent.trim(),
                    cols[7].textContent.trim()
                ];
                csv += data.join(',') + '\n';
            });
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'hasil_penempatan_' + new Date().toISOString().slice(0,10) + '.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>