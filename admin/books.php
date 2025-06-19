<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$success = '';
$error = '';
$book = [
    'id_book' => '',
    'book_code' => '',
    'title' => '',
    'author' => '',
    'publisher' => '',
    'publication_year' => '',
    'id_category' => '',
    'total_copies' => 1,
    'available_copies' => 1,
    'description' => '',
    'image' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $book_id = isset($_POST['id_book']) ? (int)$_POST['id_book'] : 0;
        $book_code = sanitize($_POST['book_code']);
        $title = sanitize($_POST['title']);
        $author = sanitize($_POST['author']);
        $publisher = sanitize($_POST['publisher']);
        $publication_year = sanitize($_POST['publication_year']);
        $id_category = (int)$_POST['id_category'];
        $total_copies = (int)$_POST['total_copies'];
        $available_copies = isset($_POST['available_copies']) ? (int)$_POST['available_copies'] : $total_copies;
        $description = sanitize($_POST['description']);
        
        // Validasi data
        if (empty($title) || empty($author)) {
            $error = 'Judul dan penulis buku wajib diisi!';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Cek apakah kode buku sudah ada (untuk tambah buku)
                if ($action === 'add') {
                    if (empty($book_code)) {
                        $book_code = generateBookCode();
                    } else {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM books WHERE book_code = ?");
                        $stmt->execute([$book_code]);
                        if ($stmt->fetchColumn() > 0) {
                            throw new Exception('Kode buku sudah digunakan!');
                        }
                    }
                }
                
                // Upload gambar jika ada
                $image = $_POST['current_image'] ?? '';
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = __DIR__ . '/../assets/images/books/';
                    
                    // Buat direktori jika belum ada
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_name = $_FILES['image']['name'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (!in_array($file_ext, $allowed_exts)) {
                        throw new Exception('Format file tidak didukung! Gunakan JPG, PNG, atau GIF.');
                    }
                    
                   
                    $new_file_name = 'book_' . time() . '_' . uniqid() . '.' . $file_ext;
                    $upload_path = $upload_dir . $new_file_name;
                    
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                        // Hapus gambar lama jika ada
                        if ($action === 'edit' && !empty($_POST['current_image'])) {
                            $old_image_path = $upload_dir . $_POST['current_image'];
                            if (file_exists($old_image_path)) {
                                @unlink($old_image_path);
                            }
                        }
                        $image = $new_file_name;
                    } else {
                        throw new Exception('Gagal mengupload gambar!');
                    }
                }
                
                // Tambah atau update buku
                if ($action === 'add') {
                    $stmt = $pdo->prepare("
                        INSERT INTO books (book_code, title, author, publisher, publication_year, id_category, 
                                          total_copies, available_copies, description, image)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $book_code, $title, $author, $publisher, $publication_year, 
                        $id_category ?: null, $total_copies, $available_copies, $description, $image
                    ]);
                    $success = 'Buku berhasil ditambahkan!';
                } else {
                    // Jika edit, update data buku
                    $stmt = $pdo->prepare("
                        UPDATE books 
                        SET title = ?, author = ?, publisher = ?, publication_year = ?, 
                            id_category = ?, total_copies = ?, available_copies = ?, 
                            description = ?" . ($image ? ", image = ?" : "") . "
                        WHERE id_book = ?
                    ");
                    
                    $params = [
                        $title, $author, $publisher, $publication_year, 
                        $id_category ?: null, $total_copies, $available_copies, 
                        $description
                    ];
                    
                    if ($image) {
                        $params[] = $image;
                    }
                    
                    $params[] = $book_id;
                    $stmt->execute($params);
                    $success = 'Buku berhasil diperbarui!';
                }
                
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $book_id = (int)$_POST['id_book'];
        
        try {
            $pdo->beginTransaction();
            
            // Cek apakah buku sedang dipinjam
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE id_book = ? AND status = 'borrowed'");
            $stmt->execute([$book_id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Buku tidak dapat dihapus karena sedang dipinjam!');
            }
            
            // Ambil info gambar buku
            $stmt = $pdo->prepare("SELECT image FROM books WHERE id_book = ?");
            $stmt->execute([$book_id]);
            $image = $stmt->fetchColumn();
            
            // Hapus buku dari database
            $stmt = $pdo->prepare("DELETE FROM books WHERE id_book = ?");
            $stmt->execute([$book_id]);
            
            // Hapus file gambar jika ada
            if ($image) {
                $image_path = __DIR__ . '/../assets/images/books/' . $image;
                if (file_exists($image_path)) {
                    @unlink($image_path);
                }
            }
            
            $pdo->commit();
            $success = 'Buku berhasil dihapus!';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Ambil data buku untuk edit
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $book_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM books WHERE id_book = ?");
    $stmt->execute([$book_id]);
    $book = $stmt->fetch();
    
    if (!$book) {
        $error = 'Buku tidak ditemukan!';
    }
}

// Pagination settings
$books_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $books_per_page;

// Search and filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// Build query conditions
$where_conditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(b.title LIKE ? OR b.author LIKE ? OR b.publisher LIKE ? OR b.book_code LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($category_filter > 0) {
    $where_conditions[] = "b.id_category = ?";
    $params[] = $category_filter;
}

$where_clause = implode(" AND ", $where_conditions);

$count_sql = "SELECT COUNT(*) FROM books b WHERE $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_books = $count_stmt->fetchColumn();
$total_pages = ceil($total_books / $books_per_page);

$sql = "SELECT b.*, c.name as category_name 
        FROM books b 
        LEFT JOIN categories c ON b.id_category = c.id_category 
        WHERE $where_clause 
        ORDER BY b.title ASC 
        LIMIT $books_per_page OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll();

$categories_stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $categories_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Buku - Pinjamin</title>
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
                            <a class="nav-link active" href="books.php">
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
                            <h1 class="h3 mb-1 text-dark fw-bold">
                                <i class="bi bi-book me-2"></i>Kelola Buku
                            </h1>
                            <p class="text-muted mb-0">Tambah, edit, dan hapus buku perpustakaan</p>
                        </div>
                        <div>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bookFormModal">
                                <i class="bi bi-plus-circle me-2"></i>Tambah Buku
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
                            <form method="GET" action="books.php">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="search" class="form-label fw-medium">Cari Buku</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-primary text-white">
                                                <i class="bi bi-search"></i>
                                            </span>
                                            <input type="text" class="form-control" id="search" name="search" 
                                                   placeholder="Judul, penulis, penerbit, atau kode buku..." 
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

                    <!-- Books Table -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-primary text-white border-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 fw-bold">
                                    <i class="bi bi-list-ul me-2"></i>Daftar Buku
                                </h5>
                                <span class="badge bg-light text-primary">
                                    Total: <?= $total_books ?> buku
                                </span>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($books) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="border-0 fw-bold">Kode</th>
                                            <th class="border-0 fw-bold">Gambar</th>
                                            <th class="border-0 fw-bold">Judul</th>
                                            <th class="border-0 fw-bold">Penulis</th>
                                            <th class="border-0 fw-bold">Kategori</th>
                                            <th class="border-0 fw-bold">Stok</th>
                                            <th class="border-0 fw-bold text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($books as $book_item): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-light text-dark border fw-normal">
                                                    <?= sanitize($book_item['book_code']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="width: 50px; height: 70px; overflow: hidden; border-radius: 4px;">
                                                    <?php if ($book_item['image']): ?>
                                                        <img src="../assets/images/books/<?= sanitize($book_item['image']) ?>" 
                                                             class="img-fluid" alt="<?= sanitize($book_item['title']) ?>"
                                                             style="width: 100%; height: 100%; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="bg-light d-flex align-items-center justify-content-center h-100">
                                                            <i class="bi bi-book text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?= sanitize($book_item['title']) ?></div>
                                                <small class="text-muted"><?= $book_item['publication_year'] ? 'Tahun ' . $book_item['publication_year'] : '' ?></small>
                                            </td>
                                            <td><?= sanitize($book_item['author']) ?></td>
                                            <td>
                                                <?php if ($book_item['category_name']): ?>
                                                    <span class="badge bg-info text-dark">
                                                        <?= sanitize($book_item['category_name']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="badge bg-<?= $book_item['available_copies'] > 0 ? 'success' : 'danger' ?> me-2">
                                                        <?= $book_item['available_copies'] ?>
                                                    </span>
                                                    <span class="text-muted">/ <?= $book_item['total_copies'] ?></span>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-info" 
                                                            data-bs-toggle="modal" data-bs-target="#bookDetailModal"
                                                            onclick="showBookDetail(<?= $book_item['id_book'] ?>, '<?= addslashes(sanitize($book_item['title'])) ?>', '<?= addslashes(sanitize($book_item['author'])) ?>', '<?= addslashes(sanitize($book_item['publisher'])) ?>', '<?= $book_item['publication_year'] ?>', '<?= addslashes(sanitize($book_item['category_name'])) ?>', '<?= $book_item['image'] ?>', <?= $book_item['total_copies'] ?>, <?= $book_item['available_copies'] ?>, '<?= addslashes(sanitize($book_item['description'])) ?>')">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <a href="books.php?edit=<?= $book_item['id_book'] ?>" class="btn btn-sm btn-warning">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                            data-bs-toggle="modal" data-bs-target="#deleteBookModal"
                                                            onclick="setDeleteBookId(<?= $book_item['id_book'] ?>, '<?= addslashes(sanitize($book_item['title'])) ?>')">
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
                                <h4 class="text-muted mt-3">Tidak ada buku ditemukan</h4>
                                <?php if (!empty($search) || $category_filter > 0): ?>
                                    <p class="text-muted">Coba ubah kata kunci pencarian atau filter kategori</p>
                                    <a href="books.php" class="btn btn-primary">
                                        <i class="bi bi-arrow-clockwise me-2"></i>Reset Pencarian
                                    </a>
                                <?php else: ?>
                                    <p class="text-muted">Belum ada buku yang tersedia saat ini</p>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bookFormModal">
                                        <i class="bi bi-plus-circle me-2"></i>Tambah Buku Baru
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
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="bookFormModal" tabindex="-1" aria-labelledby="bookFormModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="bookFormModalLabel">
                        <i class="bi bi-book me-2"></i><?= isset($_GET['edit']) ? 'Edit Buku' : 'Tambah Buku Baru' ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="bookForm" method="POST" action="books.php" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="<?= isset($_GET['edit']) ? 'edit' : 'add' ?>">
                        <?php if (isset($_GET['edit'])): ?>
                            <input type="hidden" name="id_book" value="<?= $book['id_book'] ?>">
                        <?php endif; ?>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="book_code" class="form-label fw-medium">Kode Buku</label>
                                <input type="text" class="form-control" id="book_code" name="book_code" 
                                       value="<?= isset($book['book_code']) ? sanitize($book['book_code']) : '' ?>"
                                       <?= isset($_GET['edit']) ? 'readonly' : '' ?>>
                                <div class="form-text">
                                    <?= isset($_GET['edit']) ? 'Kode buku tidak dapat diubah' : 'Kosongkan untuk generate otomatis' ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="id_category" class="form-label fw-medium">Kategori</label>
                                <select class="form-select" id="id_category" name="id_category">
                                    <option value="">Pilih Kategori</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id_category'] ?>" 
                                                <?= isset($book['id_category']) && $book['id_category'] == $category['id_category'] ? 'selected' : '' ?>>
                                            <?= sanitize($category['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label for="title" class="form-label fw-medium">Judul Buku <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?= isset($book['title']) ? sanitize($book['title']) : '' ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="author" class="form-label fw-medium">Penulis <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="author" name="author" 
                                       value="<?= isset($book['author']) ? sanitize($book['author']) : '' ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="publisher" class="form-label fw-medium">Penerbit</label>
                                <input type="text" class="form-control" id="publisher" name="publisher" 
                                       value="<?= isset($book['publisher']) ? sanitize($book['publisher']) : '' ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="publication_year" class="form-label fw-medium">Tahun Terbit</label>
                                <input type="number" class="form-control" id="publication_year" name="publication_year" 
                                       min="1900" max="<?= date('Y') ?>" 
                                       value="<?= isset($book['publication_year']) ? $book['publication_year'] : '' ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="total_copies" class="form-label fw-medium">Total Eksemplar</label>
                                <input type="number" class="form-control" id="total_copies" name="total_copies" 
                                       min="1" value="<?= isset($book['total_copies']) ? $book['total_copies'] : 1 ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="available_copies" class="form-label fw-medium">Eksemplar Tersedia</label>
                                <input type="number" class="form-control" id="available_copies" name="available_copies" 
                                       min="0" value="<?= isset($book['available_copies']) ? $book['available_copies'] : 1 ?>">
                            </div>
                            <div class="col-md-12">
                                <label for="description" class="form-label fw-medium">Deskripsi</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?= isset($book['description']) ? sanitize($book['description']) : '' ?></textarea>
                            </div>
                            <div class="col-md-12">
                                <label for="image" class="form-label fw-medium">Gambar Sampul</label>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                <div class="form-text">Format: JPG, PNG, GIF. Maks: 2MB</div>
                                
                                <?php if (isset($book['image']) && $book['image']): ?>
                                    <input type="hidden" name="current_image" value="<?= sanitize($book['image']) ?>">
                                    <div class="mt-2 d-flex align-items-center">
                                        <div style="width: 50px; height: 70px; overflow: hidden; border-radius: 4px; margin-right: 10px;">
                                            <img src="../assets/images/books/<?= sanitize($book['image']) ?>" 
                                                 class="img-fluid" alt="Current Image"
                                                 style="width: 100%; height: 100%; object-fit: cover;">
                                        </div>
                                        <span class="text-muted">Gambar saat ini</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Batal
                    </button>
                    <button type="submit" form="bookForm" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i><?= isset($_GET['edit']) ? 'Simpan Perubahan' : 'Simpan Buku' ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Book Detail  -->
    <div class="modal fade" id="bookDetailModal" tabindex="-1" aria-labelledby="bookDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-dark">
                    <h5 class="modal-title" id="bookDetailModalLabel">
                        <i class="bi bi-book me-2"></i>Detail Buku
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-md-4">
                            <div class="position-relative book-container mx-auto" style="max-width: 200px;">
                                <img id="modalBookImage" src="/placeholder.svg" class="book-cover-image" alt="">
                                <div id="modalBookPlaceholder" class="book-placeholder" style="display: none;">
                                    <i class="bi bi-book text-muted"></i>
                                </div>
                            </div>
                        </div>
                        
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
                                    <span id="modalBookCategory" class="badge bg-info text-dark"></span>
                                </div>
                                <div class="alert alert-success">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="bi bi-check-circle me-2"></i>
                                            <span id="modalBookAvailable"></span> dari <span id="modalBookTotal"></span> eksemplar tersedia
                                        </div>
                                        <div>
                                            <span id="modalBookStatus" class="badge bg-success">Tersedia</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="modalBookDescriptionContainer">
                                <div class="mb-3">
                                    <h6 class="fw-bold text-secondary">
                                        <i class="bi bi-file-text me-2"></i>Deskripsi
                                    </h6>
                                    <p id="modalBookDescription" class="text-muted"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Tutup
                    </button>
                    <a id="editBookLink" href="#" class="btn btn-warning">
                        <i class="bi bi-pencil me-2"></i>Edit Buku
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Book -->
    <div class="modal fade" id="deleteBookModal" tabindex="-1" aria-labelledby="deleteBookModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteBookModalLabel">
                        <i class="bi bi-exclamation-triangle me-2"></i>Konfirmasi Hapus
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Anda yakin ingin menghapus buku <strong id="deleteBookTitle"></strong>?</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Perhatian: Tindakan ini tidak dapat dibatalkan!
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Batal
                    </button>
                    <form id="deleteBookForm" method="POST" action="books.php">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" id="deleteBookId" name="id_book" value="">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-2"></i>Hapus Buku
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_GET['edit'])): ?>
            var bookFormModal = new bootstrap.Modal(document.getElementById('bookFormModal'));
            bookFormModal.show();
            <?php endif; ?>
            
            document.getElementById('total_copies').addEventListener('change', function() {
                var availableCopies = document.getElementById('available_copies');
                if (parseInt(availableCopies.value) > parseInt(this.value)) {
                    availableCopies.value = this.value;
                }
                availableCopies.max = this.value;
            });
        });
        
        function showBookDetail(bookId, title, author, publisher, year, category, image, totalCopies, availableCopies, description) {
            document.getElementById('modalBookTitle').textContent = title;
            document.getElementById('modalBookAuthor').textContent = author || 'Tidak tersedia';
            document.getElementById('modalBookPublisher').textContent = publisher || 'Tidak tersedia';
            document.getElementById('modalBookYear').textContent = year || 'Tidak tersedia';
            document.getElementById('modalBookCategory').textContent = category || 'Tidak ada kategori';
            document.getElementById('modalBookTotal').textContent = totalCopies;
            document.getElementById('modalBookAvailable').textContent = availableCopies;
            
            const statusElement = document.getElementById('modalBookStatus');
            if (availableCopies <= 0) {
                statusElement.textContent = 'Tidak Tersedia';
                statusElement.className = 'badge bg-danger';
            } else if (availableCopies < totalCopies) {
                statusElement.textContent = 'Sebagian Tersedia';
                statusElement.className = 'badge bg-warning text-dark';
            } else {
                statusElement.textContent = 'Tersedia';
                statusElement.className = 'badge bg-success';
            }
            
            const descriptionElement = document.getElementById('modalBookDescription');
            const descriptionContainer = document.getElementById('modalBookDescriptionContainer');
            
            if (description && description.trim() !== '' && description !== 'null') {
                descriptionElement.textContent = description;
                descriptionContainer.style.display = 'block';
            } else {
                descriptionElement.textContent = 'Tidak ada deskripsi';
                descriptionContainer.style.display = 'block';
            }
            
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

            document.getElementById('editBookLink').href = 'books.php?edit=' + bookId;
        }

        function setDeleteBookId(bookId, bookTitle) {
            document.getElementById('deleteBookId').value = bookId;
            document.getElementById('deleteBookTitle').textContent = bookTitle;
        }
    </script>
</body>
</html>
