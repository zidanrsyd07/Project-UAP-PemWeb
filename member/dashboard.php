<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireMember();

// Handle success/error messages from borrow.php
$success_message = '';
$error_message = '';

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Ambil data statistik anggota
$stmt = $pdo->prepare("SELECT COUNT(*) as total_loans FROM loans WHERE id_user = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_loans = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as active_loans FROM loans WHERE id_user = ? AND status = 'borrowed'");
$stmt->execute([$_SESSION['user_id']]);
$active_loans = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as overdue_loans FROM loans WHERE id_user = ? AND status = 'borrowed' AND due_date < CURDATE()");
$stmt->execute([$_SESSION['user_id']]);
$overdue_loans = $stmt->fetchColumn();

// Ambil buku yang tersedia
$stmt = $pdo->prepare("SELECT b.*, c.name as category_name FROM books b LEFT JOIN categories c ON b.id_category = c.id_category ORDER BY b.created_at DESC LIMIT 6");
$stmt->execute();
$available_books = $stmt->fetchAll();

// Ambil riwayat peminjaman terbaru
$stmt = $pdo->prepare("SELECT l.*, b.title, b.author FROM loans l JOIN books b ON l.id_book = b.id_book WHERE l.id_user = ? ORDER BY l.loan_date DESC LIMIT 5");
$stmt->execute([$_SESSION['user_id']]);
$loan_history = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Anggota - Pinjamin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .dashboard-book-container {
            width: 100%;
            max-width: 350px;
            margin: 0 auto;
            position: relative;
            overflow: hidden;
            border-radius: 0.375rem 0.375rem 0 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .dashboard-book-container::before {
            content: "";
            display: block;
            padding-top: 140%;
        }
        
        .dashboard-book-cover-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            transition: transform 0.3s ease;
            border-radius: 0.375rem 0.375rem 0 0;
        }
        
        .dashboard-book-placeholder {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 0.375rem 0.375rem 0 0;
        }
        
        .dashboard-book-placeholder i {
            font-size: 2.5rem;
            color: #6c757d;
        }
        
        /* Hover effects untuk dashboard - perbaikan selector agar hanya mempengaruhi kartu yang di-hover */
        .card:hover .dashboard-book-cover-image {
            transform: scale(1.05);
        }
        
        /* Isolasi efek hover ke kartu yang sedang di-hover saja */
        .col-lg-4 .card {
            transition: transform 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        
        .col-lg-4:hover .card {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
        }
        
        /* Pastikan hanya gambar di kartu yang di-hover yang berubah */
        .col-lg-4 .card .dashboard-book-cover-image {
            transition: transform 0.3s ease;
        }
        
        .col-lg-4:hover .card .dashboard-book-cover-image {
            transform: scale(1.05);
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="#">
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
                            <a class="nav-link active" href="dashboard.php">
                                <i class="bi bi-speedometer2"></i>Dashboard
                            </a>
                            <a class="nav-link" href="books.php">
                                <i class="bi bi-book"></i>Daftar Buku
                            </a>
                            <a class="nav-link" href="loans.php">
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
                            <h1 class="h3 mb-1 text-dark fw-bold">Selamat Datang, <?= $_SESSION['full_name'] ?>! 👋</h1>
                            <p class="text-muted mb-0">
                                <i class="bi bi-credit-card me-1"></i>
                                Nomor Anggota: <span class="badge bg-primary"><?= $_SESSION['user_number'] ?? 'Tidak tersedia' ?></span>
                            </p>
                        </div>
                        <div class="text-end">
                            <small class="text-muted">
                                <i class="bi bi-calendar3 me-1"></i>
                                <?= date('d F Y') ?>
                            </small>
                        </div>
                    </div>

                    <?php if ($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle me-2"></i><?= $success_message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle me-2"></i><?= $error_message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Statistics Cards -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm card-hover stats-card h-100">
                                <div class="card-body text-center p-4" style="min-height: 180px;">
                                    <div class="d-flex align-items-center justify-content-center mb-3">
                                        <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 70px; height: 70px;">
                                            <i class="bi bi-book-half text-primary" style="font-size: 1.8rem;"></i>
                                        </div>
                                    </div>
                                    <h2 class="fw-bold text-dark mb-2 display-4"><?= $total_loans ?></h2>
                                    <p class="text-muted mb-0 fw-medium fs-6">Total Peminjaman</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm card-hover stats-card h-100">
                                <div class="card-body text-center p-4" style="min-height: 180px;">
                                    <div class="d-flex align-items-center justify-content-center mb-3">
                                        <div class="bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 70px; height: 70px;">
                                            <i class="bi bi-clock-history text-warning" style="font-size: 1.8rem;"></i>
                                        </div>
                                    </div>
                                    <h2 class="fw-bold text-dark mb-2 display-4"><?= $active_loans ?></h2>
                                    <p class="text-muted mb-0 fw-medium fs-6">Sedang Dipinjam</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm card-hover stats-card h-100">
                                <div class="card-body text-center p-4" style="min-height: 180px;">
                                    <div class="d-flex align-items-center justify-content-center mb-3">
                                        <div class="bg-danger bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 70px; height: 70px;">
                                            <i class="bi bi-exclamation-triangle text-danger" style="font-size: 1.8rem;"></i>
                                        </div>
                                    </div>
                                    <h2 class="fw-bold text-dark mb-2 display-4"><?= $overdue_loans ?></h2>
                                    <p class="text-muted mb-0 fw-medium fs-6">Terlambat</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Available Books -->
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-primary text-white border-0">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0 fw-bold">
                                            <i class="bi bi-book me-2"></i>Buku Tersedia
                                        </h5>
                                        <a href="books.php" class="btn btn-light btn-sm border-primary text-primary">
                                            <i class="bi bi-eye me-2"></i>Lihat Semua
                                        </a>
                                    </div>
                                </div>
                                <div class="card-body p-4">
                                    <?php if (count($available_books) > 0): ?>
                                    <div class="row g-4">
                                        <?php foreach ($available_books as $book): ?>
                                        <div class="col-lg-4 col-md-6 book-card-wrapper <?= $book['available_copies'] == 0 ? 'opacity-75' : '' ?>">
                                            <div class="card border shadow-sm h-100">
                                                <!-- Book Image Container (Ukuran lebih kecil untuk dashboard) -->
                                                <div class="position-relative dashboard-book-container">
                                                    <?php if ($book['image']): ?>
                                                        <img src="../assets/images/books/<?= sanitize($book['image']) ?>" 
                                                             class="dashboard-book-cover-image" 
                                                             alt="<?= sanitize($book['title']) ?>">
                                                    <?php else: ?>
                                                        <div class="dashboard-book-placeholder">
                                                            <i class="bi bi-book text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="position-absolute top-0 end-0 m-2">
                                                        <?php if ($book['available_copies'] > 0): ?>
                                                            <span class="badge bg-success">
                                                                <i class="bi bi-check-circle me-1"></i><?= $book['available_copies'] ?> tersedia
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">
                                                                <i class="bi bi-x-circle me-1"></i>Habis
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="card-body p-3">
                                                    <h6 class="card-title fw-bold mb-2" title="<?= sanitize($book['title']) ?>">
                                                        <?= sanitize($book['title']) ?>
                                                    </h6>
                                                    
                                                    <div class="mb-3">
                                                        <small class="text-muted d-block text-truncate" title="<?= sanitize($book['author']) ?>">
                                                            <i class="bi bi-person me-1"></i><?= sanitize($book['author']) ?>
                                                        </small>
                                                        <?php if ($book['publisher']): ?>
                                                            <small class="text-muted d-block text-truncate" title="<?= sanitize($book['publisher']) ?>">
                                                                <i class="bi bi-building me-1"></i><?= sanitize($book['publisher']) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                        <?php if ($book['publication_year']): ?>
                                                            <small class="text-muted d-block">
                                                                <i class="bi bi-calendar me-1"></i><?= $book['publication_year'] ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="mb-3">
                                                        <span class="badge bg-light text-dark border">
                                                            <i class="bi bi-tag me-1"></i><?= sanitize($book['category_name']) ?>
                                                        </span>
                                                    </div>

                                                    <div class="mt-auto">
                                                        <?php if ($book['available_copies'] > 0): ?>
                                                            <button type="button" class="btn btn-primary btn-sm w-100" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#borrowModal"
                                                                    onclick="showBorrowModal(<?= $book['id_book'] ?>, '<?= addslashes(sanitize($book['title'])) ?>', '<?= addslashes(sanitize($book['author'])) ?>', '<?= addslashes(sanitize($book['publisher'])) ?>', '<?= $book['publication_year'] ?>', '<?= addslashes(sanitize($book['category_name'])) ?>', '<?= $book['image'] ?>', <?= $book['available_copies'] ?>, '<?= addslashes(sanitize($book['description'])) ?>')">
                                                                <i class="bi bi-plus-circle me-2"></i>Pinjam Buku
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="button" class="btn btn-secondary btn-sm w-100" disabled>
                                                                <i class="bi bi-x-circle me-2"></i>Tidak Tersedia
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-center py-5">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-body p-5">
                                                <i class="bi bi-search display-1 text-muted"></i>
                                                <h4 class="text-muted mt-3">Tidak ada buku tersedia</h4>
                                                <p class="text-muted">Silakan cek kembali nanti</p>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Loans -->
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-success text-white border-0">
                                    <h5 class="mb-0 fw-bold">
                                        <i class="bi bi-clock-history me-2"></i>Riwayat Peminjaman Terbaru
                                    </h5>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (count($loan_history) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="border-0 fw-bold">Buku</th>
                                                    <th class="border-0 fw-bold">Tanggal Pinjam</th>
                                                    <th class="border-0 fw-bold">Jatuh Tempo</th>
                                                    <th class="border-0 fw-bold">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($loan_history as $loan): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="bg-primary bg-opacity-10 rounded p-2 me-3">
                                                                <i class="bi bi-book text-primary"></i>
                                                            </div>
                                                            <div>
                                                                <div class="fw-bold"><?= sanitize($loan['title']) ?></div>
                                                                <small class="text-muted"><?= sanitize($loan['author']) ?></small>
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
                                        <a href="loans.php" class="btn btn-outline-success">
                                            <i class="bi bi-eye me-2"></i>Lihat Semua Riwayat
                                        </a>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="bi bi-journal-x display-1 text-muted"></i>
                                        <h5 class="text-muted mt-3">Belum ada riwayat peminjaman</h5>
                                        <p class="text-muted">Mulai pinjam buku untuk melihat riwayat di sini</p>
                                        <a href="books.php" class="btn btn-primary">
                                            <i class="bi bi-search me-2"></i>Cari Buku
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    

<!-- Modal Konfirmasi Peminjaman -->
<div class="modal fade" id="borrowModal" tabindex="-1" aria-labelledby="borrowModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="borrowModalLabel">
                    <i class="bi bi-book-half me-2"></i>Konfirmasi Peminjaman Buku
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-4">
                    <!-- Book Image -->
                    <div class="col-md-4">
                        <div class="position-relative dashboard-book-container mx-auto" style="max-width: 200px;">
                            <img id="modalBookImage" src="/placeholder.svg" class="dashboard-book-cover-image" alt="">
                            <div id="modalBookPlaceholder" class="dashboard-book-placeholder" style="display: none;">
                                <i class="bi bi-book text-muted"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Book Details -->
                    <div class="col-md-8">
                        <div class="mb-3">
                            <h4 id="modalBookTitle" class="fw-bold text-primary mb-2"></h4>
                            <div class="mb-2">
                                <i class="bi bi-person text-muted me-2"></i>
                                <span id="modalBookAuthor" class="text-muted"></span>
                            </div>
                            <div class="mb-2">
                                <i class="bi bi-building text-muted me-2"></i>
                                <span id="modalBookPublisher" class="text-muted"></span>
                            </div>
                            <div class="mb-2">
                                <i class="bi bi-calendar text-muted me-2"></i>
                                <span id="modalBookYear" class="text-muted"></span>
                            </div>
                            <div class="mb-3">
                                <i class="bi bi-tag text-muted me-2"></i>
                                <span id="modalBookCategory" class="badge bg-light text-dark border"></span>
                            </div>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i>
                                <span id="modalBookStock"></span> eksemplar tersedia
                            </div>
                            <div id="modalBookDescriptionContainer" style="display: none;">
                                <div class="mb-3">
                                    <h6 class="fw-bold text-secondary">
                                        <i class="bi bi-file-text me-2"></i>Deskripsi
                                    </h6>
                                    <p id="modalBookDescription" class="text-muted small"></p>
                                </div>
                            </div>
                        
                            <div class="alert alert-info">
                                <h6 class="fw-bold mb-2">
                                    <i class="bi bi-info-circle me-2"></i>Informasi Peminjaman
                                </h6>
                                <ul class="mb-0 small">
                                    <li>Masa peminjaman: <strong>7 hari</strong></li>
                                    <li>Denda keterlambatan: <strong>Rp 1.000/hari</strong></li>
                                    <li>Buku harus dikembalikan dalam kondisi baik</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-2"></i>Batal
                </button>
                <form id="borrowForm" method="POST" action="borrow.php" style="display: inline;">
                    <input type="hidden" id="bookIdInput" name="book_id" value="">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-2"></i>Konfirmasi Peminjaman
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Script untuk memastikan efek hover hanya berlaku pada kartu yang di-hover
    document.addEventListener('DOMContentLoaded', function() {
        const bookCards = document.querySelectorAll('.book-card-wrapper');
        
        bookCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                // Hanya aktifkan efek pada kartu ini
                const coverImage = this.querySelector('.dashboard-book-cover-image');
                if (coverImage) {
                    coverImage.style.transform = 'scale(1.05)';
                }
            });
            
            card.addEventListener('mouseleave', function() {
                // Reset efek saat mouse meninggalkan kartu
                const coverImage = this.querySelector('.dashboard-book-cover-image');
                if (coverImage) {
                    coverImage.style.transform = 'scale(1)';
                }
            });
        });
    });

// Function untuk menampilkan modal konfirmasi peminjaman
function showBorrowModal(bookId, title, author, publisher, year, category, image, stock, description) {
    // Set book ID untuk form
    document.getElementById('bookIdInput').value = bookId;
    
    // Set book details
    document.getElementById('modalBookTitle').textContent = title;
    document.getElementById('modalBookAuthor').textContent = author;
    document.getElementById('modalBookPublisher').textContent = publisher || 'Tidak tersedia';
    document.getElementById('modalBookYear').textContent = year || 'Tidak tersedia';
    document.getElementById('modalBookCategory').textContent = category || 'Tidak ada kategori';
    document.getElementById('modalBookStock').textContent = stock;
    
    // Set book description
    const descriptionContainer = document.getElementById('modalBookDescriptionContainer');
    const descriptionElement = document.getElementById('modalBookDescription');
    
    if (description && description.trim() !== '' && description !== 'null') {
        descriptionElement.textContent = description;
        descriptionContainer.style.display = 'block';
    } else {
        descriptionContainer.style.display = 'none';
    }
    
    // Set book image
    const modalImage = document.getElementById('modalBookImage');
    const modalPlaceholder = document.getElementById('modalBookPlaceholder');
    
    if (image && image.trim() !== '') {
        modalImage.src = '../assets/images/books/' + image;
        modalImage.style.display = 'block';
        modalPlaceholder.style.display = 'none';
    } else {
        modalImage.style.display = 'none';
        modalPlaceholder.style.display = 'flex';
    }
}
    </script>
</body>
</html>
