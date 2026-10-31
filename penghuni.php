<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser($pdo);

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    $stmt = $pdo->prepare("INSERT INTO penghuni (nip, nama_lengkap, pangkat, mobilitas_tugas, jumlah_keluarga, kondisi_kesehatan, lokasi_rumah_sekarang) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['nip'], $_POST['nama_lengkap'], $_POST['pangkat'],
                        $_POST['mobilitas_tugas'], $_POST['jumlah_keluarga'],
                        $_POST['kondisi_kesehatan'], $_POST['lokasi_rumah_sekarang']
                    ]);
                    $success = "Data penghuni berhasil ditambahkan!";
                } catch(PDOException $e) {
                    $error = "Gagal menambah data: " . $e->getMessage();
                }
                break;
                
            case 'edit':
                try {
                    $stmt = $pdo->prepare("UPDATE penghuni SET nama_lengkap=?, pangkat=?, mobilitas_tugas=?, jumlah_keluarga=?, kondisi_kesehatan=?, lokasi_rumah_sekarang=? WHERE id=?");
                    $stmt->execute([
                        $_POST['nama_lengkap'], $_POST['pangkat'], $_POST['mobilitas_tugas'],
                        $_POST['jumlah_keluarga'], $_POST['kondisi_kesehatan'],
                        $_POST['lokasi_rumah_sekarang'], $_POST['id']
                    ]);
                    $success = "Data penghuni berhasil diupdate!";
                } catch(PDOException $e) {
                    $error = "Gagal update data: " . $e->getMessage();
                }
                break;
                
            case 'delete':
                try {
                    $stmt = $pdo->prepare("DELETE FROM penghuni WHERE id=?");
                    $stmt->execute([$_POST['id']]);
                    $success = "Data penghuni berhasil dihapus!";
                } catch(PDOException $e) {
                    $error = "Gagal hapus data: " . $e->getMessage();
                }
                break;
        }
    }
}

// Pagination dan Search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$whereClause = '';
$params = [];
if ($search) {
    $whereClause = "WHERE nama_lengkap LIKE ? OR nip LIKE ? OR pangkat LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}

// Get total records
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM penghuni $whereClause");
$stmt->execute($params);
$totalRecords = $stmt->fetch()['total'];
$totalPages = ceil($totalRecords / $limit);

// Get penghuni data
$stmt = $pdo->prepare("
    SELECT p.*, a.nama_lokasi 
    FROM penghuni p 
    LEFT JOIN alternatif a ON p.lokasi_terpilih = a.id 
    $whereClause 
    ORDER BY p.created_at DESC 
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$penghuni = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Penghuni - Sistem Penempatan Rumah Asrama</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08); }
        .btn-custom { border-radius: 8px; font-weight: 600; }
        .badge-status { font-size: 0.75em; }
        .table th { background-color: #f8f9fa; font-weight: 600; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#"><i class="fas fa-home me-2"></i>Sistem Penempatan Rumah Asrama</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-1"></i>Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active" href="penghuni.php"><i class="fas fa-users me-1"></i>Data Penghuni</a></li>
                    <li class="nav-item"><a class="nav-link" href="kriteria.php"><i class="fas fa-cogs me-1"></i>Kriteria</a></li>
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

        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h4 class="mb-0"><i class="fas fa-users me-2"></i>Data Penghuni Asrama</h4>
                                <p class="text-muted mb-0">Kelola data penghuni untuk sistem penempatan rumah</p>
                            </div>
                            <div class="col-md-6 text-end">
                                <button class="btn btn-primary btn-custom" data-bs-toggle="modal" data-bs-target="#addModal">
                                    <i class="fas fa-plus me-2"></i>Tambah Penghuni
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-8">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" name="search" placeholder="Cari berdasarkan nama, NIP, atau pangkat..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-outline-primary btn-custom me-2">Cari</button>
                                <a href="penghuni.php" class="btn btn-outline-secondary btn-custom">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Daftar Penghuni (<?php echo $totalRecords; ?> data)</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>NIP</th>
                                <th>Nama Lengkap</th>
                                <th>Pangkat</th>
                                <th>Mobilitas</th>
                                <th>Jml Keluarga</th>
                                <th>Kesehatan</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($penghuni as $index => $p): ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($p['nip']); ?></td>
                                <td><?php echo htmlspecialchars($p['nama_lengkap']); ?></td>
                                <td><?php echo htmlspecialchars($p['pangkat']); ?></td>
                                <td>
                                    <span class="badge <?php echo ($p['mobilitas_tugas'] == 'tinggi') ? 'bg-danger' : (($p['mobilitas_tugas'] == 'sedang') ? 'bg-warning' : 'bg-success'); ?>">
                                        <?php echo ucfirst($p['mobilitas_tugas']); ?>
                                    </span>
                                </td>
                                <td><?php echo $p['jumlah_keluarga']; ?> orang</td>
                                <td>
                                    <span class="badge <?php echo ($p['kondisi_kesehatan'] == 'sehat') ? 'bg-success' : (($p['kondisi_kesehatan'] == 'cukup_sehat') ? 'bg-warning' : 'bg-danger'); ?>">
                                        <?php echo str_replace('_', ' ', ucfirst($p['kondisi_kesehatan'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($p['status_penempatan'] == 'sudah_ditempatkan'): ?>
                                        <span class="badge bg-success">Ditempatkan</span><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($p['nama_lokasi']); ?></small>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Belum Ditempatkan</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" onclick="editPenghuni(<?php echo htmlspecialchars(json_encode($p)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" onclick="deletePenghuni(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['nama_lengkap']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($penghuni)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Tidak ada data penghuni</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                        </li>
                        
                        <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Tambah Penghuni Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">NIP</label>
                                <input type="text" class="form-control" name="nip" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama Lengkap</label>
                                <input type="text" class="form-control" name="nama_lengkap" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Pangkat</label>
                                <select class="form-select" name="pangkat" required>
                                    <option value="">Pilih Pangkat</option>
                                    <option value="Letda">Letda</option>
                                    <option value="Lettu">Lettu</option>
                                    <option value="Kapten">Kapten</option>
                                    <option value="Mayor">Mayor</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Mobilitas Tugas</label>
                                <select class="form-select" name="mobilitas_tugas" required>
                                    <option value="">Pilih Mobilitas</option>
                                    <option value="tinggi">Tinggi</option>
                                    <option value="sedang">Sedang</option>
                                    <option value="rendah">Rendah</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jumlah Keluarga</label>
                                <input type="number" class="form-control" name="jumlah_keluarga" min="1" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kondisi Kesehatan</label>
                                <select class="form-select" name="kondisi_kesehatan" required>
                                    <option value="">Pilih Kondisi</option>
                                    <option value="sehat">Sehat</option>
                                    <option value="cukup_sehat">Cukup Sehat</option>
                                    <option value="kurang_sehat">Kurang Sehat</option>
                                </select>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Lokasi Rumah Saat Ini</label>
                                <input type="text" class="form-control" name="lokasi_rumah_sekarang" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Data Penghuni</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">NIP</label>
                                <input type="text" class="form-control" id="edit_nip" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama Lengkap</label>
                                <input type="text" class="form-control" name="nama_lengkap" id="edit_nama_lengkap" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Pangkat</label>
                                <select class="form-select" name="pangkat" id="edit_pangkat" required>
                                    <option value="Letda">Letda</option>
                                    <option value="Lettu">Lettu</option>
                                    <option value="Kapten">Kapten</option>
                                    <option value="Mayor">Mayor</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Mobilitas Tugas</label>
                                <select class="form-select" name="mobilitas_tugas" id="edit_mobilitas_tugas" required>
                                    <option value="tinggi">Tinggi</option>
                                    <option value="sedang">Sedang</option>
                                    <option value="rendah">Rendah</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jumlah Keluarga</label>
                                <input type="number" class="form-control" name="jumlah_keluarga" id="edit_jumlah_keluarga" min="1" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kondisi Kesehatan</label>
                                <select class="form-select" name="kondisi_kesehatan" id="edit_kondisi_kesehatan" required>
                                    <option value="sehat">Sehat</option>
                                    <option value="cukup_sehat">Cukup Sehat</option>
                                    <option value="kurang_sehat">Kurang Sehat</option>
                                </select>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Lokasi Rumah Saat Ini</label>
                                <input type="text" class="form-control" name="lokasi_rumah_sekarang" id="edit_lokasi_rumah_sekarang" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2 text-danger"></i>Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus data penghuni:</p>
                    <div class="alert alert-warning">
                        <strong id="delete_nama"></strong>
                    </div>
                    <p class="text-muted small">Data yang sudah dihapus tidak dapat dikembalikan.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST" id="deleteForm">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete_id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Hapus</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editPenghuni(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_nip').value = data.nip;
            document.getElementById('edit_nama_lengkap').value = data.nama_lengkap;
            document.getElementById('edit_pangkat').value = data.pangkat;
            document.getElementById('edit_mobilitas_tugas').value = data.mobilitas_tugas;
            document.getElementById('edit_jumlah_keluarga').value = data.jumlah_keluarga;
            document.getElementById('edit_kondisi_kesehatan').value = data.kondisi_kesehatan;
            document.getElementById('edit_lokasi_rumah_sekarang').value = data.lokasi_rumah_sekarang;
            
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }

        function deletePenghuni(id, nama) {
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_nama').textContent = nama;
            
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>