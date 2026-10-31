<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser($pdo);

// Handle form submissions
if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'update_bobot':
            try {
                $stmt = $pdo->prepare("UPDATE kriteria SET bobot = ? WHERE id = ?");
                foreach ($_POST['bobot'] as $id => $bobot) {
                    $stmt->execute([$bobot, $id]);
                }
                $success = "Bobot kriteria berhasil diupdate!";
            } catch(PDOException $e) {
                $error = "Gagal update bobot: " . $e->getMessage();
            }
            break;
    }
}

// Ambil data kriteria
$stmt = $pdo->query("SELECT * FROM kriteria ORDER BY id");
$kriteria = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung total bobot
$totalBobot = array_sum(array_column($kriteria, 'bobot'));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kriteria - Sistem Penempatan Rumah Asrama</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08); }
        .criteria-card { transition: all 0.3s ease; }
        .criteria-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1); }
        .weight-input { font-size: 1.2em; font-weight: bold; text-align: center; }
        .progress-custom { height: 15px; border-radius: 10px; }
        .btn-custom { border-radius: 8px; font-weight: 600; }
        .criteria-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; color: white; }
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
                    <li class="nav-item"><a class="nav-link active" href="kriteria.php"><i class="fas fa-cogs me-1"></i>Kriteria</a></li>
                    <li class="nav-item"><a class="nav-link" href="fuzzy_ahp.php"><i class="fas fa-calculator me-1"></i>Perhitungan Fuzzy AHP</a></li>
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
        <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-gradient-info text-white">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4 class="mb-1"><i class="fas fa-cogs me-2"></i>Manajemen Kriteria</h4>
                                <p class="mb-0">Kelola bobot kriteria untuk perhitungan Fuzzy AHP</p>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="text-end">
                                    <div class="h5 mb-0">Total Bobot: <?php echo number_format($totalBobot, 2); ?></div>
                                    <small class="opacity-75">
                                        <?php if (abs($totalBobot - 1.0) < 0.01): ?>
                                            <i class="fas fa-check-circle me-1"></i>Bobot Valid
                                        <?php else: ?>
                                            <i class="fas fa-exclamation-triangle me-1"></i>Bobot harus = 1.00
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Update Bobot -->
        <form method="POST">
            <input type="hidden" name="action" value="update_bobot">
            
            <div class="row mb-4">
                <?php 
                $icons = ['fa-walking', 'fa-star', 'fa-users', 'fa-heartbeat', 'fa-map-marker-alt'];
                $colors = ['bg-gradient-primary', 'bg-gradient-success', 'bg-gradient-warning', 'bg-gradient-danger', 'bg-gradient-info'];
                foreach ($kriteria as $index => $k): 
                    $persentase = ($totalBobot > 0) ? ($k['bobot'] / $totalBobot) * 100 : 0;
                ?>
                <div class="col-md-4 mb-4">
                    <div class="card criteria-card h-100">
                        <div class="card-body text-center">
                            <div class="criteria-icon <?php echo $colors[$index % 5]; ?> mx-auto mb-3">
                                <i class="fas <?php echo $icons[$index % 5]; ?>"></i>
                            </div>
                            
                            <h5 class="mb-2"><?php echo htmlspecialchars($k['nama_kriteria']); ?></h5>
                            <small class="text-muted mb-3 d-block">
                                <?php echo htmlspecialchars($k['kode_kriteria']); ?> - 
                                <?php echo htmlspecialchars($k['keterangan']); ?>
                            </small>
                            
                            <div class="mb-3">
                                <label class="form-label">Bobot Kriteria</label>
                                <input type="number" 
                                       class="form-control weight-input" 
                                       name="bobot[<?php echo $k['id']; ?>]" 
                                       value="<?php echo $k['bobot']; ?>" 
                                       step="0.01" 
                                       min="0" 
                                       max="1" 
                                       required>
                            </div>
                            
                            <div class="mb-3">
                                <div class="progress progress-custom">
                                    <div class="progress-bar <?php echo str_replace('bg-gradient-', 'bg-', $colors[$index % 5]); ?>" 
                                         style="width: <?php echo $persentase; ?>%"></div>
                                </div>
                                <small class="text-muted"><?php echo number_format($persentase, 1); ?>% dari total</small>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="row">
                <div class="col-12 text-center">
                    <button type="submit" class="btn btn-primary btn-custom btn-lg">
                        <i class="fas fa-save me-2"></i>Update Bobot Kriteria
                    </button>
                </div>
            </div>
        </form>

        <!-- Penjelasan AHP -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Tentang Analytical Hierarchy Process (AHP)</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Prinsip AHP:</h6>
                                <ul class="list-unstyled">
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        <strong>Dekomposisi:</strong> Memecah masalah kompleks menjadi hierarki
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        <strong>Comparative Judgment:</strong> Perbandingan berpasangan antar kriteria
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        <strong>Synthesis:</strong> Penggabungan hasil untuk mendapat prioritas
                                    </li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Panduan Bobot:</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Prioritas</th>
                                                <th>Range Bobot</th>
                                                <th>Keterangan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><span class="badge bg-danger">Sangat Tinggi</span></td>
                                                <td>0.30 - 0.50</td>
                                                <td>Kriteria paling penting</td>
                                            </tr>
                                            <tr>
                                                <td><span class="badge bg-warning">Tinggi</span></td>
                                                <td>0.20 - 0.30</td>
                                                <td>Kriteria penting</td>
                                            </tr>
                                            <tr>
                                                <td><span class="badge bg-info">Sedang</span></td>
                                                <td>0.10 - 0.20</td>
                                                <td>Kriteria cukup penting</td>
                                            </tr>
                                            <tr>
                                                <td><span class="badge bg-success">Rendah</span></td>
                                                <td>0.05 - 0.10</td>
                                                <td>Kriteria kurang penting</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Matrix Perbandingan (Optional) -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-table me-2"></i>Matrix Perbandingan Kriteria</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-lightbulb me-2"></i>
                            <strong>Tips:</strong> Pastikan total bobot = 1.00 untuk hasil perhitungan yang akurat. 
                            Bobot yang lebih tinggi menunjukkan kriteria yang lebih penting dalam pengambilan keputusan.
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Kriteria</th>
                                        <th>Kode</th>
                                        <th>Bobot</th>
                                        <th>Persentase</th>
                                        <th>Prioritas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Sort kriteria berdasarkan bobot (descending)
                                    usort($kriteria, function($a, $b) {
                                        return $b['bobot'] <=> $a['bobot'];
                                    });
                                    
                                    foreach ($kriteria as $index => $k): 
                                        $persentase = ($totalBobot > 0) ? ($k['bobot'] / $totalBobot) * 100 : 0;
                                        $priorityBadge = '';
                                        if ($k['bobot'] >= 0.30) $priorityBadge = 'bg-danger';
                                        elseif ($k['bobot'] >= 0.20) $priorityBadge = 'bg-warning';
                                        elseif ($k['bobot'] >= 0.10) $priorityBadge = 'bg-info';
                                        else $priorityBadge = 'bg-success';
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($k['nama_kriteria']); ?></td>
                                        <td><code><?php echo htmlspecialchars($k['kode_kriteria']); ?></code></td>
                                        <td><strong><?php echo number_format($k['bobot'], 3); ?></strong></td>
                                        <td>
                                            <div class="progress progress-custom">
                                                <div class="progress-bar <?php echo str_replace('bg-', 'bg-', $priorityBadge); ?>" 
                                                     style="width: <?php echo $persentase; ?>%"></div>
                                            </div>
                                            <?php echo number_format($persentase, 1); ?>%
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $priorityBadge; ?>">
                                                Rank #<?php echo $index + 1; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th colspan="2">Total</th>
                                        <th><?php echo number_format($totalBobot, 3); ?></th>
                                        <th>100%</th>
                                        <th>
                                            <?php if (abs($totalBobot - 1.0) < 0.01): ?>
                                                <span class="badge bg-success">Valid</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Invalid</span>
                                            <?php endif; ?>
                                        </th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-calculate total weight
        document.addEventListener('DOMContentLoaded', function() {
            const weightInputs = document.querySelectorAll('.weight-input');
            
            function updateTotal() {
                let total = 0;
                weightInputs.forEach(input => {
                    total += parseFloat(input.value) || 0;
                });
                
                // Update display (if you want real-time update)
                console.log('Total weight:', total.toFixed(3));
                
                // Validate total
                if (Math.abs(total - 1.0) > 0.01) {
                    weightInputs.forEach(input => {
                        input.style.borderColor = '#dc3545';
                    });
                } else {
                    weightInputs.forEach(input => {
                        input.style.borderColor = '#198754';
                    });
                }
            }
            
            weightInputs.forEach(input => {
                input.addEventListener('input', updateTotal);
            });
            
            // Initial calculation
            updateTotal();
        });
    </script>
</body>
</html>