<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total_books FROM books");
$total_books = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total_members FROM users WHERE role = 'member' AND status = 'active'");
$total_members = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total_categories FROM categories");
$total_categories = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as active_loans FROM loans WHERE status = 'borrowed'");
$active_loans = $stmt->fetchColumn();

// Recent loans
$stmt = $pdo->query("
    SELECT l.*, u.full_name, b.title 
    FROM loans l 
    JOIN users u ON l.id_user = u.id_user 
    JOIN books b ON l.id_book = b.id_book 
    ORDER BY l.created_at DESC 
    LIMIT 5
");
$recent_loans = $stmt->fetchAll();

// Low stock books
$stmt = $pdo->query("SELECT * FROM books WHERE available_copies <= 2 ORDER BY available_copies ASC LIMIT 5");
$low_stock_books = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Pinjamin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
    .stats-card {
        border: none;
        background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
        transition: all 0.3s ease;
        min-height: 200px;
    }

    .stats-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 0.75rem 1.5rem rgba(0, 0, 0, 0.15) !important;
    }

    .stats-card .card-body {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: 2rem 1rem;
    }

    .stats-card .display-4 {
        font-size: 2.5rem;
        font-weight: 700;
    }
    </style>
</head>
<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="#">
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
                            <a class="nav-link active" href="dashboard.php">
                                <i class="bi bi-speedometer2"></i>Dashboard
                            </a>
                            <a class="nav-link" href="books.php">
                                <i class="bi bi-book"></i>Kelola Buku
                            </a>
                            <a class="nav-link" href="categories.php">
                                <i class="bi bi-tags"></i>Kategori
                            </a>
                            <a class="nav-link" href="members.php">
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
                            <h1 class="h3 mb-1 text-dark fw-bold">Dashboard Admin 🛡️</h1>
                            <p class="text-muted mb-0">Selamat datang, <?= $_SESSION['full_name'] ?>!</p>
                        </div>
                        <div class="text-end">
                            <small class="text-muted">
                                <i class="bi bi-calendar3 me-1"></i>
                                <?= date('d F Y') ?>
                            </small>
                        </div>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row g-3 mb-4">
                        <div class="col-lg-3 col-md-6">
                            <div class="card border-0 shadow-sm card-hover stats-card">
                                <div class="card-body text-center p-4">
                                    <div class="d-flex align-items-center justify-content-center mb-3">
                                        <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                            <i class="bi bi-book text-primary" style="font-size: 2.5rem;"></i>
                                        </div>
                                    </div>
                                    <h2 class="fw-bold text-dark mb-1 display-4"><?= $total_books ?></h2>
                                    <p class="text-muted mb-0 fw-medium">Total Buku</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="card border-0 shadow-sm card-hover stats-card">
                                <div class="card-body text-center p-4">
                                    <div class="d-flex align-items-center justify-content-center mb-3">
                                        <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                            <i class="bi bi-people text-success" style="font-size: 2.5rem;"></i>
                                        </div>
                                    </div>
                                    <h2 class="fw-bold text-dark mb-1 display-4"><?= $total_members ?></h2>
                                    <p class="text-muted mb-0 fw-medium">Anggota Aktif</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="card border-0 shadow-sm card-hover stats-card">
                                <div class="card-body text-center p-4">
                                    <div class="d-flex align-items-center justify-content-center mb-3">
                                        <div class="bg-info bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                            <i class="bi bi-tags text-info" style="font-size: 2.5rem;"></i>
                                        </div>
                                    </div>
                                    <h2 class="fw-bold text-dark mb-1 display-4"><?= $total_categories ?></h2>
                                    <p class="text-muted mb-0 fw-medium">Kategori</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="card border-0 shadow-sm card-hover stats-card">
                                <div class="card-body text-center p-4">
                                    <div class="d-flex align-items-center justify-content-center mb-3">
                                        <div class="bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                            <i class="bi bi-clock-history text-warning" style="font-size: 2.5rem;"></i>
                                        </div>
                                    </div>
                                    <h2 class="fw-bold text-dark mb-1 display-4"><?= $active_loans ?></h2>
                                    <p class="text-muted mb-0 fw-medium">Sedang Dipinjam</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <!-- Recent Loans -->
                        <div class="col-lg-8">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-primary text-white border-0">
                                    <h5 class="mb-0 fw-bold">
                                        <i class="bi bi-clock-history me-2"></i>Peminjaman Terbaru
                                    </h5>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (count($recent_loans) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="border-0 fw-bold">Anggota</th>
                                                    <th class="border-0 fw-bold">Buku</th>
                                                    <th class="border-0 fw-bold">Tanggal</th>
                                                    <th class="border-0 fw-bold">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_loans as $loan): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="bg-success bg-opacity-10 rounded-circle p-2 me-3">
                                                                <i class="bi bi-person text-success"></i>
                                                            </div>
                                                            <span class="fw-medium"><?= sanitize($loan['full_name']) ?></span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="bg-primary bg-opacity-10 rounded p-2 me-3">
                                                                <i class="bi bi-book text-primary"></i>
                                                            </div>
                                                            <span><?= sanitize($loan['title']) ?></span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <i class="bi bi-calendar3 text-muted me-1"></i>
                                                        <?= formatDate($loan['loan_date']) ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($loan['status'] == 'borrowed'): ?>
                                                            <span class="badge bg-warning">
                                                                <i class="bi bi-clock me-1"></i>Dipinjam
                                                            </span>
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
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="card-footer bg-transparent border-0 text-center">
                                        <a href="loans.php" class="btn btn-outline-primary">
                                            <i class="bi bi-eye me-2"></i>Lihat Semua Peminjaman
                                        </a>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="bi bi-inbox display-1 text-muted"></i>
                                        <h5 class="text-muted mt-3">Belum ada peminjaman</h5>
                                        <p class="text-muted">Data peminjaman akan muncul di sini</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Low Stock Alert -->
                        <div class="col-lg-4">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-warning text-dark border-0">
                                    <h5 class="mb-0 fw-bold">
                                        <i class="bi bi-exclamation-triangle me-2"></i>Stok Menipis
                                    </h5>
                                </div>
                                <div class="card-body p-3">
                                    <?php if (count($low_stock_books) > 0): ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($low_stock_books as $book): ?>
                                        <div class="list-group-item border-0 px-0 py-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="me-2">
                                                    <div class="fw-bold text-truncate" style="max-width: 150px;">
                                                        <?= sanitize($book['title']) ?>
                                                    </div>
                                                    <small class="text-muted"><?= sanitize($book['author']) ?></small>
                                                </div>
                                                <span class="badge bg-warning rounded-pill">
                                                    <?= $book['available_copies'] ?>
                                                </span>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="text-center mt-3">
                                        <a href="books.php" class="btn btn-outline-warning btn-sm">
                                            <i class="bi bi-eye me-2"></i>Kelola Stok
                                        </a>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-check-circle display-4 text-success"></i>
                                        <h6 class="text-success mt-2">Stok Aman</h6>
                                        <small class="text-muted">Semua buku memiliki stok yang cukup</small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
