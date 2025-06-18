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

// Pagination settings
$books_per_page = 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $books_per_page;

// Search and filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// Build query conditions
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(b.title LIKE ? OR b.author LIKE ? OR b.publisher LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($category_filter > 0) {
    $where_conditions[] = "b.id_category = ?";
    $params[] = $category_filter;
}

$where_clause = count($where_conditions) > 0 ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total books count for pagination
$count_sql = "SELECT COUNT(*) FROM books b $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_books = $count_stmt->fetchColumn();
$total_pages = ceil($total_books / $books_per_page);

// Get books with pagination
$sql = "SELECT b.*, c.name as category_name 
        FROM books b 
        LEFT JOIN categories c ON b.id_category = c.id_category 
        $where_clause 
        ORDER BY b.title ASC 
        LIMIT $books_per_page OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll();

// Get all categories for filter dropdown
$categories_stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $categories_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Buku - Pinjamin</title>
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
                            <a class="nav-link active" href="books.php">
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
                            <h1 class="h3 mb-1 text-dark fw-bold">
                                <i class="bi bi-book me-2"></i>Daftar Buku
                            </h1>
                            <p class="text-muted mb-0">Temukan dan pinjam buku favorit Anda</p>
                        </div>
                        <div class="text-end">
                            <small class="text-muted">
                                Total: <span class="fw-bold"><?= $total_books ?></span> buku tersedia
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

                    <!-- Search and Filter -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <form method="GET" action="books.php">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="search" class="form-label fw-medium">Cari Buku</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-primary text-white">
                                                <i class="bi bi-search"></i>
                                            </span>
                                            <input type="text" class="form-control" id="search" name="search" 
                                                   placeholder="Judul, penulis, atau penerbit..." 
                                                   value="<?= htmlspecialchars($search) ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="category" class="form-label fw-medium">Kategori</label>
                                        <select class="form-select" id="category" name="category">
                                            <option value="0">Semua Kategori</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?= $category['id_category'] ?>" 
                                                        <?= $category_filter == $category['id_category'] ? 'selected' : '' ?>>
                                                    <?= sanitize($category['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">&nbsp;</label>
                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-search me-2"></i>Cari
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php if (!empty($search) || $category_filter > 0): ?>
                                <div class="mt-3">
                                    <a href="books.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-x-circle me-2"></i>Reset Filter
                                    </a>
                                </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <!-- Books Grid -->
                    <?php if (count($books) > 0): ?>
                    <div class="row g-4 mb-4">
                        <?php foreach ($books as $book): ?>
                        <div class="col-lg-3 col-md-4 col-sm-6">
                            <div class="card border shadow-sm card-hover h-100 <?= $book['available_copies'] == 0 ? 'opacity-75' : '' ?>">
                                <!-- Book Image Container (A4 Ratio) -->
                                <div class="position-relative book-container">
                                    <?php if ($book['image']): ?>
                                        <img src="../assets/images/books/<?= sanitize($book['image']) ?>" 
                                             class="book-cover-image" 
                                             alt="<?= sanitize($book['title']) ?>">
                                    <?php else: ?>
                                        <div class="book-placeholder">
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

                                    <?php if ($book['description']): ?>
                                    <p class="card-text small text-muted mb-3" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                        <?= sanitize($book['description']) ?>
                                    </p>
                                    <?php endif; ?>

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

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-center">
                        <nav aria-label="Navigasi halaman">
                            <ul class="pagination">
                                <!-- Previous Page -->
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&category=<?= $category_filter ?>">
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
                                        <a class="page-link" href="?page=1&search=<?= urlencode($search) ?>&category=<?= $category_filter ?>">1</a>
                                    </li>
                                    <?php if ($start_page > 2): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= $category_filter ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($end_page < $total_pages): ?>
                                    <?php if ($end_page < $total_pages - 1): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $total_pages ?>&search=<?= urlencode($search) ?>&category=<?= $category_filter ?>"><?= $total_pages ?></a>
                                    </li>
                                <?php endif; ?>

                                <!-- Next Page -->
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&category=<?= $category_filter ?>">
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
                    <!-- No Books Found -->
                    <div class="text-center py-5">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-5">
                                <i class="bi bi-search display-1 text-muted"></i>
                                <h4 class="text-muted mt-3">Tidak ada buku ditemukan</h4>
                                <?php if (!empty($search) || $category_filter > 0): ?>
                                    <p class="text-muted">Coba ubah kata kunci pencarian atau filter kategori</p>
                                    <a href="books.php" class="btn btn-primary">
                                        <i class="bi bi-arrow-clockwise me-2"></i>Reset Pencarian
                                    </a>
                                <?php else: ?>
                                    <p class="text-muted">Belum ada buku yang tersedia saat ini</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
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
                            <div class="position-relative book-container mx-auto" style="max-width: 200px;">
                                <img id="modalBookImage" src="/placeholder.svg" class="book-cover-image" alt="">
                                <div id="modalBookPlaceholder" class="book-placeholder" style="display: none;">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
