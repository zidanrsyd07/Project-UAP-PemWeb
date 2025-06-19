<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

// Inisialisasi variabel
$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_member') {
        $full_name = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $address = sanitize($_POST['address']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        try {
            // Generate user number
            $year = date('Y');
            $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(user_number, 5) AS UNSIGNED)) as max_num FROM users WHERE user_number LIKE ?");
            $stmt->execute([$year . '%']);
            $result = $stmt->fetch();
            $next_num = ($result['max_num'] ?? 0) + 1;
            $user_number = $year . str_pad($next_num, 4, '0', STR_PAD_LEFT);
            
            $stmt = $pdo->prepare("
                INSERT INTO users (user_number, full_name, email, phone, address, password, role, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'member', 'active')
            ");
            $stmt->execute([$user_number, $full_name, $email, $phone, $address, $password]);
            $success = "Anggota berhasil ditambahkan dengan nomor anggota: $user_number";
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    } elseif ($action === 'edit_member') {
        $id = (int)$_POST['id_user'];
        $full_name = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $address = sanitize($_POST['address']);
        $status = sanitize($_POST['status']);
        
        try {
            $sql = "UPDATE users SET full_name = ?, email = ?, phone = ?, address = ?, status = ?";
            $params = [$full_name, $email, $phone, $address, $status];
            
            // Update password if provided
            if (!empty($_POST['password'])) {
                $sql .= ", password = ?";
                $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            }
            
            $sql .= " WHERE id_user = ? AND role = 'member'";
            $params[] = $id;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $success = 'Data anggota berhasil diperbarui!';
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    } elseif ($action === 'delete_member') {
        $id = (int)$_POST['id_user'];
        
        try {
            // Check if member has active loans
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE id_user = ? AND status = 'borrowed'");
            $stmt->execute([$id]);
            $active_loans = $stmt->fetchColumn();
            
            if ($active_loans > 0) {
                throw new Exception("Anggota tidak dapat dihapus karena masih memiliki $active_loans peminjaman aktif!");
            }
            
            $stmt = $pdo->prepare("DELETE FROM users WHERE id_user = ? AND role = 'member'");
            $stmt->execute([$id]);
            $success = 'Anggota berhasil dihapus!';
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Pagination settings
$members_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $members_per_page;

// Search and filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Build query conditions
$where_conditions = ["role = 'member'"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(full_name LIKE ? OR email LIKE ? OR user_number LIKE ? OR phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Get total members count for pagination
$count_sql = "SELECT COUNT(*) FROM users WHERE $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_members = $count_stmt->fetchColumn();
$total_pages = ceil($total_members / $members_per_page);

// Get members with pagination
$sql = "
    SELECT u.*, 
           (SELECT COUNT(*) FROM loans l WHERE l.id_user = u.id_user) as total_loans,
           (SELECT COUNT(*) FROM loans l WHERE l.id_user = u.id_user AND l.status = 'borrowed') as active_loans
    FROM users u 
    WHERE $where_clause 
    ORDER BY u.created_at DESC 
    LIMIT $members_per_page OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Anggota - Pinjamin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="dashboard.php">
                <i class="bi bi-shield-check-fill me-2"></i>Pinjamin Admin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-2"></i>
                            <?= $_SESSION['full_name'] ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="../index.php"><i class="bi bi-house me-2"></i>Beranda</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-2 col-md-3 px-0">
                <div class="sidebar">
                    <div class="p-3">
                        <h6 class="text-uppercase text-muted fw-bold mb-3" style="font-size: 0.75rem; letter-spacing: 1px;">Menu Admin</h6>
                        <nav class="nav flex-column">
                            <a class="nav-link" href="dashboard.php">
                                <i class="bi bi-speedometer2"></i>Dashboard
                            </a>
                            <a class="nav-link" href="books.php">
                                <i class="bi bi-book"></i>Kelola Buku
                            </a>
                            <a class="nav-link" href="categories.php">
                                <i class="bi bi-tags"></i>Kategori
                            </a>
                            <a class="nav-link active" href="members.php">
                                <i class="bi bi-people"></i>Anggota
                            </a>
                            <a class="nav-link" href="loans.php">
                                <i class="bi bi-calendar-check"></i>Peminjaman
                            </a>
                        </nav>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-10 col-md-9">
                <div class="p-4">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="h3 mb-1 text-dark fw-bold">
                                <i class="bi bi-people me-2"></i>Kelola Anggota
                            </h1>
                            <p class="text-muted mb-0">Kelola anggota perpustakaan</p>
                        </div>
                        <div>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#memberFormModal">
                                <i class="bi bi-plus-circle me-2"></i>Tambah Anggota
                            </button>
                        </div>
                    </div>

                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle me-2"></i><?= $success ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle me-2"></i><?= $error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Search and Filter -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <form method="GET" action="members.php">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="search" class="form-label fw-medium">Cari Anggota</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-primary text-white">
                                                <i class="bi bi-search"></i>
                                            </span>
                                            <input type="text" class="form-control" id="search" name="search" 
                                                   placeholder="Nama, email, nomor anggota, atau telepon..." 
                                                   value="<?= htmlspecialchars($search) ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="status" class="form-label fw-medium">Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="">Semua Status</option>
                                            <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Aktif</option>
                                            <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Tidak Aktif</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">&nbsp;</label>
                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-search me-2"></i>Cari
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php if (!empty($search) || !empty($status_filter)): ?>
                                <div class="mt-3">
                                    <a href="members.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-x-circle me-2"></i>Reset Filter
                                    </a>
                                </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <!-- Members Table -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-primary text-white border-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 fw-bold">
                                    <i class="bi bi-list-ul me-2"></i>Daftar Anggota
                                </h5>
                                <span class="badge bg-light text-primary">
                                    Total: <?= $total_members ?> anggota
                                </span>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($members) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="border-0 fw-bold">No. Anggota</th>
                                            <th class="border-0 fw-bold">Nama Lengkap</th>
                                            <th class="border-0 fw-bold">Email</th>
                                            <th class="border-0 fw-bold">Telepon</th>
                                            <th class="border-0 fw-bold">Status</th>
                                            <th class="border-0 fw-bold">Peminjaman</th>
                                            <th class="border-0 fw-bold text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($members as $member): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-light text-dark border fw-normal">
                                                    <?= sanitize($member['user_number']) ?>
                                                </span>
                                            </td>
                                            <td class="fw-bold"><?= sanitize($member['full_name']) ?></td>
                                            <td><?= sanitize($member['email']) ?></td>
                                            <td><?= sanitize($member['phone']) ?></td>
                                            <td>
                                                <?php if ($member['status'] === 'active'): ?>
                                                    <span class="badge bg-success">Aktif</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Tidak Aktif</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    Total: <?= $member['total_loans'] ?>
                                                </span>
                                                <?php if ($member['active_loans'] > 0): ?>
                                                    <br><span class="badge bg-warning text-dark">
                                                        Aktif: <?= $member['active_loans'] ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-info" 
                                                            data-bs-toggle="modal" data-bs-target="#memberDetailModal"
                                                            onclick="showMemberDetail(<?= htmlspecialchars(json_encode($member)) ?>)">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-primary" 
                                                            data-bs-toggle="modal" data-bs-target="#editMemberModal"
                                                            onclick="setEditMember(<?= htmlspecialchars(json_encode($member)) ?>)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                            data-bs-toggle="modal" data-bs-target="#deleteMemberModal"
                                                            onclick="setDeleteMember(<?= $member['id_user'] ?>, '<?= addslashes(sanitize($member['full_name'])) ?>', <?= $member['active_loans'] ?>)"
                                                            <?= $member['active_loans'] > 0 ? 'disabled' : '' ?>>
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-search display-1 text-muted"></i>
                                <h4 class="text-muted mt-3">Tidak ada anggota ditemukan</h4>
                                <?php if (!empty($search) || !empty($status_filter)): ?>
                                    <p class="text-muted">Coba ubah kata kunci pencarian atau filter status</p>
                                    <a href="members.php" class="btn btn-primary">
                                        <i class="bi bi-arrow-clockwise me-2"></i>Reset Pencarian
                                    </a>
                                <?php else: ?>
                                    <p class="text-muted">Belum ada anggota yang terdaftar saat ini</p>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#memberFormModal">
                                        <i class="bi bi-plus-circle me-2"></i>Tambah Anggota Baru
                                    </button>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-center">
                        <nav aria-label="Navigasi halaman">
                            <ul class="pagination">
                                <!-- Previous Page -->
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link"><i class="bi bi-chevron-left"></i></span>
                                    </li>
                                <?php endif; ?>

                                <!-- Page Numbers -->
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                if ($start_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=1&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>">1</a>
                                    </li>
                                    <?php if ($start_page > 2): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($end_page < $total_pages): ?>
                                    <?php if ($end_page < $total_pages - 1): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $total_pages ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>"><?= $total_pages ?></a>
                                    </li>
                                <?php endif; ?>

                                <!-- Next Page -->
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link"><i class="bi bi-chevron-right"></i></span>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Member Modal -->
    <div class="modal fade" id="memberFormModal" tabindex="-1" aria-labelledby="memberFormModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="memberFormModalLabel">
                        <i class="bi bi-plus-circle me-2"></i>Tambah Anggota Baru
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="memberForm" method="POST" action="members.php">
                        <input type="hidden" name="action" value="add_member">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="full_name" class="form-label fw-medium">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label fw-medium">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label fw-medium">Telepon <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="phone" name="phone" required>
                            </div>
                            <div class="col-md-6">
                                <label for="password" class="form-label fw-medium">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="col-12">
                                <label for="address" class="form-label fw-medium">Alamat</label>
                                <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="alert alert-info mt-3">
                            <i class="bi bi-info-circle me-2"></i>
                            Nomor anggota akan dibuat otomatis setelah data disimpan.
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Batal
                    </button>
                    <button type="submit" form="memberForm" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>Simpan Anggota
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Member Modal -->
    <div class="modal fade" id="editMemberModal" tabindex="-1" aria-labelledby="editMemberModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editMemberModalLabel">
                        <i class="bi bi-pencil me-2"></i>Edit Anggota
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editMemberForm" method="POST" action="members.php">
                        <input type="hidden" name="action" value="edit_member">
                        <input type="hidden" id="edit_id_user" name="id_user" value="">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="edit_full_name" class="form-label fw-medium">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_full_name" name="full_name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_email" class="form-label fw-medium">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="edit_email" name="email" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_phone" class="form-label fw-medium">Telepon <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="edit_phone" name="phone" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_status" class="form-label fw-medium">Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_status" name="status" required>
                                    <option value="active">Aktif</option>
                                    <option value="inactive">Tidak Aktif</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="edit_address" class="form-label fw-medium">Alamat</label>
                                <textarea class="form-control" id="edit_address" name="address" rows="3"></textarea>
                            </div>
                            <div class="col-12">
                                <label for="edit_password" class="form-label fw-medium">Password Baru</label>
                                <input type="password" class="form-control" id="edit_password" name="password">
                                <div class="form-text">Kosongkan jika tidak ingin mengubah password</div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Batal
                    </button>
                    <button type="submit" form="editMemberForm" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>Simpan Perubahan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Member Detail Modal -->
    <div class="modal fade" id="memberDetailModal" tabindex="-1" aria-labelledby="memberDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="memberDetailModalLabel">
                        <i class="bi bi-person-circle me-2"></i>Detail Anggota
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nomor Anggota</label>
                            <p id="detail_user_number" class="form-control-plaintext border rounded p-2 bg-light"></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Status</label>
                            <p id="detail_status" class="form-control-plaintext"></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nama Lengkap</label>
                            <p id="detail_full_name" class="form-control-plaintext border rounded p-2 bg-light"></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Email</label>
                            <p id="detail_email" class="form-control-plaintext border rounded p-2 bg-light"></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Telepon</label>
                            <p id="detail_phone" class="form-control-plaintext border rounded p-2 bg-light"></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tanggal Daftar</label>
                            <p id="detail_created_at" class="form-control-plaintext border rounded p-2 bg-light"></p>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Alamat</label>
                            <p id="detail_address" class="form-control-plaintext border rounded p-2 bg-light"></p>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Statistik Peminjaman</label>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="card bg-info text-white">
                                        <div class="card-body text-center">
                                            <h4 id="detail_total_loans" class="mb-1">0</h4>
                                            <small>Total Peminjaman</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-warning text-dark">
                                        <div class="card-body text-center">
                                            <h4 id="detail_active_loans" class="mb-1">0</h4>
                                            <small>Peminjaman Aktif</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Member Modal -->
    <div class="modal fade" id="deleteMemberModal" tabindex="-1" aria-labelledby="deleteMemberModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteMemberModalLabel">
                        <i class="bi bi-exclamation-triangle me-2"></i>Konfirmasi Hapus
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Anda yakin ingin menghapus anggota ini?</p>
                    <div class="alert alert-warning">
                        <strong>Anggota:</strong> <span id="deleteMemberName"></span>
                    </div>
                    <div id="deleteMemberWarning" class="alert alert-danger d-none">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Anggota ini tidak dapat dihapus karena masih memiliki <span id="deleteMemberLoans"></span> peminjaman aktif.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Batal
                    </button>
                    <form id="deleteMemberForm" method="POST" action="members.php">
                        <input type="hidden" name="action" value="delete_member">
                        <input type="hidden" id="delete_id_user" name="id_user" value="">
                        <button type="submit" class="btn btn-danger" id="deleteMemberButton">
                            <i class="bi bi-trash me-2"></i>Hapus Anggota
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to set edit member data
        function setEditMember(member) {
            document.getElementById('edit_id_user').value = member.id_user;
            document.getElementById('edit_full_name').value = member.full_name;
            document.getElementById('edit_email').value = member.email;
            document.getElementById('edit_phone').value = member.phone;
            document.getElementById('edit_address').value = member.address || '';
            document.getElementById('edit_status').value = member.status;
        }
        
        // Function to show member detail
        function showMemberDetail(member) {
            document.getElementById('detail_user_number').textContent = member.user_number;
            document.getElementById('detail_full_name').textContent = member.full_name;
            document.getElementById('detail_email').textContent = member.email;
            document.getElementById('detail_phone').textContent = member.phone;
            document.getElementById('detail_address').textContent = member.address || 'Tidak ada alamat';
            document.getElementById('detail_created_at').textContent = new Date(member.created_at).toLocaleDateString('id-ID');
            document.getElementById('detail_total_loans').textContent = member.total_loans;
            document.getElementById('detail_active_loans').textContent = member.active_loans;
            
            const statusElement = document.getElementById('detail_status');
            if (member.status === 'active') {
                statusElement.innerHTML = '<span class="badge bg-success">Aktif</span>';
            } else {
                statusElement.innerHTML = '<span class="badge bg-secondary">Tidak Aktif</span>';
            }
        }
        
        // Function to set delete member data
        function setDeleteMember(id, name, activeLoans) {
            document.getElementById('delete_id_user').value = id;
            document.getElementById('deleteMemberName').textContent = name;
            document.getElementById('deleteMemberLoans').textContent = activeLoans;
            
            const warningDiv = document.getElementById('deleteMemberWarning');
            const deleteButton = document.getElementById('deleteMemberButton');
            
            if (activeLoans > 0) {
                warningDiv.classList.remove('d-none');
                deleteButton.disabled = true;
            } else {
                warningDiv.classList.add('d-none');
                deleteButton.disabled = false;
            }
        }
    </script>
</body>
</html>
