<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireMember();

// Pagination settings
$loans_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $loans_per_page;

// Filter parameters
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query conditions
$where_conditions = ["l.id_user = ?"];
$params = [$_SESSION['user_id']];

if (!empty($status_filter)) {
    $where_conditions[] = "l.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(b.title LIKE ? OR b.author LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(" AND ", $where_conditions);

// Get total loans count for pagination
$count_sql = "SELECT COUNT(*) FROM loans l JOIN books b ON l.id_book = b.id_book WHERE $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_loans = $count_stmt->fetchColumn();
$total_pages = ceil($total_loans / $loans_per_page);

// Get loans with pagination
$sql = "SELECT l.*, b.title, b.author, b.publisher, b.publication_year, c.name as category_name
        FROM loans l 
        JOIN books b ON l.id_book = b.id_book 
        LEFT JOIN categories c ON b.id_category = c.id_category
        WHERE $where_clause 
        ORDER BY l.loan_date DESC 
        LIMIT $loans_per_page OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$loans = $stmt->fetchAll();

// Get statistics
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'borrowed' THEN 1 ELSE 0 END) as borrowed,
        SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as returned,
        SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue
    FROM loans 
    WHERE id_user = ?
");
$stats_stmt->execute([$_SESSION['user_id']]);
$stats = $stats_stmt->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peminjaman Saya - Pinjamin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="dashboard.php">
                <i class="bi bi-book-fill me-2"></i>Pinjamin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-2" style="font-size: 1.5rem;"></i>
                            <?= $_SESSION['full_name'] ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Profil</a></li>
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
                        <h6 class="text-uppercase text-muted fw-bold mb-3" style="font-size: 0.75rem; letter-spacing: 1px;">Menu Utama</h6>
                        <nav class="nav flex-column">
                            <a class="nav-link" href="dashboard.php">
                                <i class="bi bi-speedometer2"></i>Dashboard
                            </a>
                            <a class="nav-link" href="books.php">
                                <i class="bi bi-book"></i>Daftar Buku
                            </a>
                            <a class="nav-link active" href="loans.php">
                                <i class="bi bi-calendar-check"></i>Peminjaman Saya
                            </a>
                            <a class="nav-link" href="profile.php">
                                <i class="bi bi-person"></i>Profil Saya
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
                                <i class="bi bi-calendar-check me-2"></i>Peminjaman Saya
                            </h1>
                            <p class="text-muted mb-0">Riwayat dan status peminjaman buku Anda</p>
                        </div>
                        <div class="text-end">
                            <small class="text-muted">
                                Total: <span class="fw-bold"><?= $total_loans ?></span> peminjaman
                            </small>
                        </div>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row g-3 mb-4">
                        <div class="col-lg-3 col-md-6">
                            <div class="card border-0 shadow-sm card-hover stats-card">
                                <div class="card-body text-center p-3">
                                    <div class="d-flex align-items-center justify-content-center mb-2">
                                        <div class="bg-primary bg-opacity-10 rounded-circle p-2">
                                            <i class="bi bi-book text-primary" style="font-size: 1.5rem;"></i>
                                        </div>
                                    </div>
                                    <h4 class="fw-bold text-dark mb-1"><?= $stats['total'] ?></h4>
                                    <p class="text-muted mb-0 small fw-medium">Total Peminjaman</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="card border-0 shadow-sm card-hover stats-card">
                                <div class="card-body text-center p-3">
                                    <div class="d-flex align-items-center justify-content-center mb-2">
                                        <div class="bg-warning bg-opacity-10 rounded-circle p-2">
                                            <i class="bi bi-clock-history text-warning" style="font-size: 1.5rem;"></i>
                                        </div>
                                    </div>
                                    <h4 class="fw-bold text-dark mb-1"><?= $stats['borrowed'] ?></h4>
                                    <p class="text-muted mb-0 small fw-medium">Sedang Dipinjam</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="card border-0 shadow-sm card-hover stats-card">
                                <div class="card-body text-center p-3">
                                    <div class="d-flex align-items-center justify-content-center mb-2">
                                        <div class="bg-success bg-opacity-10 rounded-circle p-2">
                                            <i class="bi bi-check-circle text-success" style="font-size: 1.5rem;"></i>
                                        </div>
                                    </div>
                                    <h4 class="fw-bold text-dark mb-1"><?= $stats['returned'] ?></h4>
                                    <p class="text-muted mb-0 small fw-medium">Dikembalikan</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="card border-0 shadow-sm card-hover stats-card">
                                <div class="card-body text-center p-3">
                                    <div class="d-flex align-items-center justify-content-center mb-2">
                                        <div class="bg-danger bg-opacity-10 rounded-circle p-2">
                                            <i class="bi bi-exclamation-triangle text-danger" style="font-size: 1.5rem;"></i>
                                        </div>
                                    </div>
                                    <h4 class="fw-bold text-dark mb-1"><?= $stats['overdue'] ?></h4>
                                    <p class="text-muted mb-0 small fw-medium">Terlambat</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Search and Filter -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <form method="GET" action="loans.php">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="search" class="form-label fw-medium">Cari Buku</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-primary text-white">
                                                <i class="bi bi-search"></i>
                                            </span>
                                            <input type="text" class="form-control" id="search" name="search" 
                                                   placeholder="Judul atau penulis buku..." 
                                                   value="<?= htmlspecialchars($search) ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="status" class="form-label fw-medium">Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="">Semua Status</option>
                                            <option value="borrowed" <?= $status_filter == 'borrowed' ? 'selected' : '' ?>>Sedang Dipinjam</option>
                                            <option value="returned" <?= $status_filter == 'returned' ? 'selected' : '' ?>>Dikembalikan</option>
                                            <option value="overdue" <?= $status_filter == 'overdue' ? 'selected' : '' ?>>Terlambat</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">&nbsp;</label>
                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-search me-2"></i>Filter
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php if (!empty($search) || !empty($status_filter)): ?>
                                <div class="mt-3">
                                    <a href="loans.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-x-circle me-2"></i>Reset Filter
                                    </a>
                                </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <!-- Loans List -->
                    <?php if (count($loans) > 0): ?>
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-primary text-white border-0">
                            <h5 class="mb-0 fw-bold">
                                <i class="bi bi-list-ul me-2"></i>Riwayat Peminjaman
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="border-0 fw-bold">Buku</th>
                                            <th class="border-0 fw-bold">Tanggal Pinjam</th>
                                            <th class="border-0 fw-bold">Jatuh Tempo</th>
                                            <th class="border-0 fw-bold">Tanggal Kembali</th>
                                            <th class="border-0 fw-bold">Status</th>
                                            <th class="border-0 fw-bold">Denda</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($loans as $loan): ?>
                                        <?php
                                        $overdue_days = 0;
                                        $fine_amount = 0;
                                        
                                        if ($loan['status'] == 'borrowed' && $loan['due_date'] < date('Y-m-d')) {
                                            $overdue_days = calculateOverdueDays($loan['due_date']);
                                            $fine_amount = calculateFine($overdue_days);
                                        } elseif ($loan['fine'] > 0) {
                                            $fine_amount = $loan['fine'];
                                        }
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-start">
                                                    <div class="bg-primary bg-opacity-10 rounded p-2 me-3 flex-shrink-0">
                                                        <i class="bi bi-book text-primary"></i>
                                                    </div>
                                                    <div class="min-w-0">
                                                        <div class="fw-bold"><?= sanitize($loan['title']) ?></div>
                                                        <small class="text-muted d-block">oleh <?= sanitize($loan['author']) ?></small>
                                                        <?php if ($loan['publisher']): ?>
                                                            <small class="text-muted d-block">
                                                                <i class="bi bi-building me-1"></i><?= sanitize($loan['publisher']) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                        <?php if ($loan['category_name']): ?>
                                                            <span class="badge bg-light text-dark border mt-1">
                                                                <i class="bi bi-tag me-1"></i><?= sanitize($loan['category_name']) ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <i class="bi bi-calendar3 text-muted me-1"></i>
                                                <?= formatDate($loan['loan_date']) ?>
                                            </td>
                                            <td>
                                                <i class="bi bi-calendar-x text-muted me-1"></i>
                                                <?= formatDate($loan['due_date']) ?>
                                                <?php if ($loan['status'] == 'borrowed' && $overdue_days > 0): ?>
                                                    <br><small class="text-danger">
                                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                                        Terlambat <?= $overdue_days ?> hari
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($loan['return_date']): ?>
                                                    <i class="bi bi-calendar-check text-success me-1"></i>
                                                    <?= formatDate($loan['return_date']) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($loan['status'] == 'borrowed'): ?>
                                                    <?php if ($overdue_days > 0): ?>
                                                        <span class="badge bg-danger">
                                                            <i class="bi bi-exclamation-triangle me-1"></i>Terlambat
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">
                                                            <i class="bi bi-clock me-1"></i>Dipinjam
                                                        </span>
                                                    <?php endif; ?>
                                                <?php elseif ($loan['status'] == 'returned'): ?>
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-check-circle me-1"></i>Dikembalikan
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">
                                                        <i class="bi bi-exclamation-triangle me-1"></i>Terlambat
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($fine_amount > 0): ?>
                                                    <span class="text-danger fw-bold">
                                                        Rp <?= number_format($fine_amount, 0, ',', '.') ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
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

                    <?php else: ?>
                    <!-- No Loans Found -->
                    <div class="text-center py-5">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-5">
                                <i class="bi bi-journal-x display-1 text-muted"></i>
                                <h4 class="text-muted mt-3">Tidak ada riwayat peminjaman</h4>
                                <?php if (!empty($search) || !empty($status_filter)): ?>
                                    <p class="text-muted">Coba ubah filter pencarian</p>
                                    <a href="loans.php" class="btn btn-primary">
                                        <i class="bi bi-arrow-clockwise me-2"></i>Reset Filter
                                    </a>
                                <?php else: ?>
                                    <p class="text-muted">Mulai pinjam buku untuk melihat riwayat di sini</p>
                                    <a href="books.php" class="btn btn-primary">
                                        <i class="bi bi-search me-2"></i>Cari Buku
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
