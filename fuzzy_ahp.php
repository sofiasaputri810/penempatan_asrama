<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser($pdo);

// Fuzzy AHP Class
class FuzzyAHP {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Matriks perbandingan kriteria (contoh bobot)
    private function getKriteriaWeights() {
        return [
            'mobilitas_tugas' => 0.35,     // Prioritas tinggi
            'pangkat' => 0.25,             // Prioritas sedang-tinggi  
            'jumlah_keluarga' => 0.20,     // Prioritas sedang
            'kondisi_kesehatan' => 0.15,   // Prioritas sedang-rendah
            'lokasi_rumah_sekarang' => 0.05 // Prioritas rendah
        ];
    }
    
    // Nilai fuzzy untuk setiap alternatif berdasarkan kriteria
    private function getAlternatifValues() {
        return [
            1 => [ // Lokasi A (Dekat Jalan)
                'mobilitas_tugas' => 0.9,      // Sangat cocok untuk mobilitas tinggi
                'pangkat' => 0.8,              // Cocok untuk pangkat tinggi
                'jumlah_keluarga' => 0.6,      // Cukup untuk keluarga besar
                'kondisi_kesehatan' => 0.7,    // Aksesibilitas kesehatan baik
                'lokasi_rumah_sekarang' => 0.5 // Netral untuk jarak
            ],
            2 => [ // Lokasi B (Tengah)
                'mobilitas_tugas' => 0.7,      // Cukup untuk mobilitas
                'pangkat' => 0.7,              // Seimbang untuk semua pangkat
                'jumlah_keluarga' => 0.8,      // Baik untuk keluarga sedang
                'kondisi_kesehatan' => 0.8,    // Lokasi strategis untuk kesehatan
                'lokasi_rumah_sekarang' => 0.6 // Cukup strategis
            ],
            3 => [ // Lokasi C (Belakang)
                'mobilitas_tugas' => 0.4,      // Kurang cocok untuk mobilitas tinggi
                'pangkat' => 0.5,              // Cocok untuk pangkat rendah-sedang
                'jumlah_keluarga' => 0.9,      // Sangat baik untuk keluarga besar
                'kondisi_kesehatan' => 0.6,    // Tenang, baik untuk pemulihan
                'lokasi_rumah_sekarang' => 0.7 // Cocok jika rumah sekarang jauh
            ]
        ];
    }
    
    public function calculateFuzzyScore($penghuni) {
        $kriteriaWeights = $this->getKriteriaWeights();
        $alternatifValues = $this->getAlternatifValues();
        $scores = [];
        
        foreach ($alternatifValues as $altId => $altValues) {
            $totalScore = 0;
            
            // Hitung skor untuk setiap kriteria
            foreach ($kriteriaWeights as $kriteria => $weight) {
                $penghuniValue = $this->getPenghuniValue($penghuni, $kriteria);
                $altValue = $altValues[$kriteria];
                
                // Fuzzy multiplication dan normalisasi
                $kriteriaScore = ($penghuniValue * $altValue * $weight);
                $totalScore += $kriteriaScore;
            }
            
            $scores[$altId] = $totalScore;
        }
        
        return $scores;
    }
    
    private function getPenghuniValue($penghuni, $kriteria) {
        switch($kriteria) {
            case 'mobilitas_tugas':
                return ($penghuni['mobilitas_tugas'] == 'tinggi') ? 0.9 : 
                       (($penghuni['mobilitas_tugas'] == 'sedang') ? 0.6 : 0.3);
            
            case 'pangkat':
                $pangkatValues = [
                    'Mayor' => 0.9, 'Kapten' => 0.7, 
                    'Lettu' => 0.5, 'Letda' => 0.3
                ];
                return $pangkatValues[$penghuni['pangkat']] ?? 0.1;
            
            case 'jumlah_keluarga':
                return ($penghuni['jumlah_keluarga'] >= 5) ? 0.9 : 
                       (($penghuni['jumlah_keluarga'] >= 3) ? 0.6 : 0.3);
            
            case 'kondisi_kesehatan':
                return ($penghuni['kondisi_kesehatan'] == 'kurang_sehat') ? 0.9 :
                       (($penghuni['kondisi_kesehatan'] == 'cukup_sehat') ? 0.6 : 0.3);
            
            case 'lokasi_rumah_sekarang':
                // Simplified: semakin jauh dari pusat, semakin tinggi nilainya
                return rand(30, 90) / 100;
        }
        return 0.5;
    }
    
    public function processAllPenghuni() {
        // Ambil semua penghuni yang belum ditempatkan
        $stmt = $this->pdo->query("
            SELECT * FROM penghuni 
            WHERE status_penempatan = 'belum_ditempatkan' 
            ORDER BY id
        ");
        $penghuniList = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $results = [];
        
        foreach ($penghuniList as $penghuni) {
            $scores = $this->calculateFuzzyScore($penghuni);
            
            // Simpan hasil ke database
            $this->pdo->prepare("DELETE FROM hasil_fuzzy WHERE penghuni_id = ?")->execute([$penghuni['id']]);
            
            foreach ($scores as $altId => $score) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO hasil_fuzzy (penghuni_id, alternatif_id, skor_fuzzy) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$penghuni['id'], $altId, $score]);
            }
            
            $results[$penghuni['id']] = [
                'penghuni' => $penghuni,
                'scores' => $scores,
                'best_alternative' => array_keys($scores, max($scores))[0]
            ];
        }
        
        return $results;
    }
    
    public function assignHousing() {
        // Ambil kuota alternatif
        $stmt = $this->pdo->query("SELECT * FROM alternatif ORDER BY id");
        $alternatif = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $kuotaRemaining = [];
        foreach ($alternatif as $alt) {
            $kuotaRemaining[$alt['id']] = $alt['kuota'];
        }
        
        // Ambil hasil fuzzy terurut berdasarkan skor tertinggi
        $stmt = $this->pdo->query("
            SELECT hf.*, p.nama_lengkap, p.nip, a.nama_lokasi
            FROM hasil_fuzzy hf
            JOIN penghuni p ON hf.penghuni_id = p.id
            JOIN alternatif a ON hf.alternatif_id = a.id
            WHERE p.status_penempatan = 'belum_ditempatkan'
            ORDER BY hf.skor_fuzzy DESC
        ");
        $hasilFuzzy = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $assignments = [];
        $assignedPenghuni = [];
        
        foreach ($hasilFuzzy as $hasil) {
            $penghuniId = $hasil['penghuni_id'];
            $altId = $hasil['alternatif_id'];
            
            // Skip jika penghuni sudah ditempatkan
            if (in_array($penghuniId, $assignedPenghuni)) {
                continue;
            }
            
            // Cek apakah masih ada kuota
            if ($kuotaRemaining[$altId] > 0) {
                // Assign penghuni ke lokasi
                $stmt = $this->pdo->prepare("
                    UPDATE penghuni 
                    SET status_penempatan = 'sudah_ditempatkan', 
                        lokasi_terpilih = ?,
                        skor_akhir = ?
                    WHERE id = ?
                ");
                $stmt->execute([$altId, $hasil['skor_fuzzy'], $penghuniId]);
                
                $kuotaRemaining[$altId]--;
                $assignedPenghuni[] = $penghuniId;
                
                $assignments[] = [
                    'penghuni_id' => $penghuniId,
                    'nama' => $hasil['nama_lengkap'],
                    'nip' => $hasil['nip'],
                    'lokasi' => $hasil['nama_lokasi'],
                    'skor' => $hasil['skor_fuzzy']
                ];
            }
        }
        
        // Update jumlah terisi di tabel alternatif
        foreach ($alternatif as $alt) {
            $terisi = $alt['kuota'] - $kuotaRemaining[$alt['id']];
            $this->pdo->prepare("UPDATE alternatif SET terisi = ? WHERE id = ?")
                     ->execute([$terisi, $alt['id']]);
        }
        
        return $assignments;
    }
}

// Handle form submission
$message = '';
$messageType = '';

if ($_POST && isset($_POST['action'])) {
    $fuzzyAHP = new FuzzyAHP($pdo);
    
    switch ($_POST['action']) {
        case 'calculate':
            try {
                $results = $fuzzyAHP->processAllPenghuni();
                $message = "Perhitungan Fuzzy AHP berhasil untuk " . count($results) . " penghuni!";
                $messageType = 'success';
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = 'danger';
            }
            break;
            
        case 'assign':
            try {
                $assignments = $fuzzyAHP->assignHousing();
                $message = "Penempatan berhasil untuk " . count($assignments) . " penghuni!";
                $messageType = 'success';
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = 'danger';
            }
            break;
            
        case 'reset':
            try {
                $pdo->exec("UPDATE penghuni SET status_penempatan = 'belum_ditempatkan', lokasi_terpilih = NULL, skor_akhir = 0");
                $pdo->exec("DELETE FROM hasil_fuzzy");
                $pdo->exec("UPDATE alternatif SET terisi = 0");
                $message = "Data berhasil direset!";
                $messageType = 'success';
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = 'danger';
            }
            break;
    }
}

// Ambil statistik
$stmt = $pdo->query("SELECT COUNT(*) as total FROM penghuni WHERE status_penempatan = 'belum_ditempatkan'");
$belumDitempatkan = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM hasil_fuzzy");
$sudahDihitung = $stmt->fetch()['total'] > 0;

// Ambil hasil perhitungan terbaru
$stmt = $pdo->query("
    SELECT hf.*, p.nama_lengkap, p.nip, a.nama_lokasi, a.kode_alternatif
    FROM hasil_fuzzy hf
    JOIN penghuni p ON hf.penghuni_id = p.id
    JOIN alternatif a ON hf.alternatif_id = a.id
    ORDER BY hf.skor_fuzzy DESC
    LIMIT 20
");
$hasilTerbaru = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perhitungan Fuzzy AHP - Sistem Penempatan Rumah Asrama</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #f8f9fa; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08); }
        .btn-custom { border-radius: 10px; font-weight: 600; padding: 12px 24px; transition: all 0.3s ease; }
        .btn-custom:hover { transform: translateY(-2px); }
        .process-step { background: #f8f9fa; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
        .step-number { width: 40px; height: 40px; background: #007bff; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .progress-custom { height: 8px; border-radius: 10px; }
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
                    <li class="nav-item"><a class="nav-link active" href="fuzzy_ahp.php"><i class="fas fa-calculator me-1"></i>Perhitungan Fuzzy AHP</a></li>
                    <li class="nav-item"><a class="nav-link" href="hasil.php"><i class="fas fa-chart-bar me-1"></i>Hasil Penempatan</a></li>
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
        <!-- Alert Messages -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
            <i class="fas <?php echo ($messageType == 'success') ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> me-2"></i>
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-gradient-primary text-white">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4 class="mb-1"><i class="fas fa-calculator me-2"></i>Perhitungan Fuzzy AHP</h4>
                                <p class="mb-0">Sistem pengambilan keputusan untuk penempatan rumah asrama</p>
                            </div>
                            <div class="col-md-4 text-end">
                                <i class="fas fa-brain fa-4x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Process Steps -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list-ol me-2"></i>Langkah Perhitungan</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="process-step">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="step-number me-3">1</div>
                                        <h6 class="mb-0">Hitung Skor Fuzzy</h6>
                                    </div>
                                    <p class="text-muted small mb-3">Menghitung skor fuzzy untuk setiap penghuni berdasarkan 5 kriteria dan 3 alternatif lokasi.</p>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="calculate">
                                        <button type="submit" class="btn btn-primary btn-custom" <?php echo ($belumDitempatkan == 0) ? 'disabled' : ''; ?>>
                                            <i class="fas fa-calculator me-2"></i>Hitung Skor
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="process-step">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="step-number me-3">2</div>
                                        <h6 class="mb-0">Penempatan Otomatis</h6>
                                    </div>
                                    <p class="text-muted small mb-3">Menempatkan penghuni ke lokasi terbaik berdasarkan skor tertinggi dan ketersediaan kuota.</p>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="assign">
                                        <button type="submit" class="btn btn-success btn-custom" <?php echo (!$sudahDihitung) ? 'disabled' : ''; ?>>
                                            <i class="fas fa-home me-2"></i>Tempatkan
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="process-step">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="step-number me-3">3</div>
                                        <h6 class="mb-0">Reset Data</h6>
                                    </div>
                                    <p class="text-muted small mb-3">Mengembalikan semua data ke kondisi awal untuk perhitungan ulang.</p>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="reset">
                                        <button type="submit" class="btn btn-warning btn-custom" onclick="return confirm('Yakin ingin reset semua data?')">
                                            <i class="fas fa-redo me-2"></i>Reset
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Info -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-users fa-2x text-warning mb-3"></i>
                        <h4 class="mb-1"><?php echo $belumDitempatkan; ?></h4>
                        <p class="text-muted mb-0">Penghuni Belum Ditempatkan</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas <?php echo $sudahDihitung ? 'fa-check-circle text-success' : 'fa-clock text-muted'; ?> fa-2x mb-3"></i>
                        <h4 class="mb-1"><?php echo $sudahDihitung ? 'Selesai' : 'Menunggu'; ?></h4>
                        <p class="text-muted mb-0">Status Perhitungan</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-chart-line fa-2x text-info mb-3"></i>
                        <h4 class="mb-1"><?php echo count($hasilTerbaru); ?></h4>
                        <p class="text-muted mb-0">Hasil Perhitungan</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metode Fuzzy AHP Info -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Tentang Metode Fuzzy AHP</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Kriteria yang Digunakan:</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-arrow-right text-primary me-2"></i><strong>Mobilitas Tugas (35%)</strong> - Tingkat mobilitas dalam menjalankan tugas</li>
                                    <li><i class="fas fa-arrow-right text-primary me-2"></i><strong>Pangkat (25%)</strong> - Tingkat kepangkatan dalam hierarki</li>
                                    <li><i class="fas fa-arrow-right text-primary me-2"></i><strong>Jumlah Keluarga (20%)</strong> - Jumlah anggota keluarga yang ditanggung</li>
                                    <li><i class="fas fa-arrow-right text-primary me-2"></i><strong>Kondisi Kesehatan (15%)</strong> - Status kesehatan penghuni</li>
                                    <li><i class="fas fa-arrow-right text-primary me-2"></i><strong>Lokasi Rumah Saat Ini (5%)</strong> - Jarak dari lokasi rumah saat ini</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Alternatif Lokasi:</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-home text-success me-2"></i><strong>Lokasi A</strong> - Dekat jalan utama (10 unit)</li>
                                    <li><i class="fas fa-home text-info me-2"></i><strong>Lokasi B</strong> - Tengah area asrama (19 unit)</li>
                                    <li><i class="fas fa-home text-warning me-2"></i><strong>Lokasi C</strong> - Area belakang yang tenang (21 unit)</li>
                                </ul>
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-lightbulb me-1"></i>
                                        Sistem menggunakan logika fuzzy untuk menangani ketidakpastian dalam pengambilan keputusan
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hasil Perhitungan Terbaru -->
        <?php if (!empty($hasilTerbaru)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Hasil Perhitungan Terbaru (Top 20)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>NIP</th>
                                        <th>Nama Penghuni</th>
                                        <th>Lokasi Terbaik</th>
                                        <th>Skor Fuzzy</th>
                                        <th>Persentase</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $maxScore = !empty($hasilTerbaru) ? $hasilTerbaru[0]['skor_fuzzy'] : 1;
                                    foreach ($hasilTerbaru as $index => $hasil): 
                                        $persentase = ($hasil['skor_fuzzy'] / $maxScore) * 100;
                                        
                                        // Cek status penempatan
                                        $stmt = $pdo->prepare("SELECT status_penempatan FROM penghuni WHERE id = ?");
                                        $stmt->execute([$hasil['penghuni_id']]);
                                        $status = $stmt->fetch()['status_penempatan'];
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-primary">#<?php echo $index + 1; ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($hasil['nip']); ?></td>
                                        <td><?php echo htmlspecialchars($hasil['nama_lengkap']); ?></td>
                                        <td>
                                            <span class="badge <?php 
                                                echo ($hasil['kode_alternatif'] == 'A1') ? 'bg-success' : 
                                                     (($hasil['kode_alternatif'] == 'A2') ? 'bg-info' : 'bg-warning'); 
                                            ?>">
                                                <?php echo htmlspecialchars($hasil['nama_lokasi']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($hasil['skor_fuzzy'], 4); ?></td>
                                        <td>
                                            <div class="progress progress-custom">
                                                <div class="progress-bar bg-info" style="width: <?php echo $persentase; ?>%"></div>
                                            </div>
                                            <small class="text-muted"><?php echo number_format($persentase, 1); ?>%</small>
                                        </td>
                                        <td>
                                            <?php if ($status == 'sudah_ditempatkan'): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check me-1"></i>Ditempatkan
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">
                                                    <i class="fas fa-clock me-1"></i>Menunggu
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="hasil.php" class="btn btn-outline-primary btn-custom">
                                <i class="fas fa-eye me-2"></i>Lihat Semua Hasil
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>