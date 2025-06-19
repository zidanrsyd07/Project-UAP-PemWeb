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
    
    if ($action === 'add_category') {
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
            $stmt->execute([$name, $description]);
            $success = 'Kategori berhasil ditambahkan!';
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    } elseif ($action === 'edit_category') {
        $id = (int)$_POST['id_category'];
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        
        try {
            $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id_category = ?");
            $stmt->execute([$name, $description, $id]);
            $success = 'Kategori berhasil diperbarui!';
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    } elseif ($action === 'delete_category') {
        $id = (int)$_POST['id_category'];
        
        try {
            // Check if category is used by any books
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM books WHERE id_category = ?");
            $stmt->execute([$id]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                throw new Exception("Kategori tidak dapat dihapus karena masih digunakan oleh $count buku!");
            }
            
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id_category = ?");
            $stmt->execute([$id]);
            $success = 'Kategori berhasil dihapus!';
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Pagination settings
$categories_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $categories_per_page;

// Search parameter
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query conditions
$where_conditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(" AND ", $where_conditions);

// Get total categories count for pagination
$count_sql = "SELECT COUNT(*) FROM categories WHERE $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_categories = $count_stmt->fetchColumn();
$total_pages = ceil($total_categories / $categories_per_page);

// Get categories with pagination
$sql = "
    SELECT c.*, 
           (SELECT COUNT(*) FROM books b WHERE b.id_category = c.id_category) as book_count
    FROM categories c 
    WHERE $where_clause 
    ORDER BY c.name ASC 
    LIMIT $categories_per_page OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kategori - Pinjamin</title>
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
                            <a class="nav-link active" href="categories.php">
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
                                <i class="bi bi-tags me-2"></i>Kelola Kategori
                            </h1>
                            <p class="text-muted mb-0">Kelola kategori buku perpustakaan</p>
                        </div>
                        <div>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryFormModal">
                                <i class="bi bi-plus-circle me-2"></i>Tambah Kategori
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

                    <!-- Search -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <form method="GET" action="categories.php">
                                <div class="row g-3">
                                    <div class="col-md-9">
                                        <label for="search" class="form-label fw-medium">Cari Kategori</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-primary text-white">
                                                <i class="bi bi-search"></i>
                                            </span>
                                            <input type="text" class="form-control" id="search" name="search" 
                                                   placeholder="Nama kategori atau deskripsi..." 
                                                   value="<?= htmlspecialchars($search) ?>">
                                        </div>
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
                                <?php if (!empty($search)): ?>
                                <div class="mt-3">
                                    <a href="categories.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-x-circle me-2"></i>Reset Pencarian
                                    </a>
                                </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <!-- Categories Table -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-primary text-white border-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 fw-bold">
                                    <i class="bi bi-list-ul me-2"></i>Daftar Kategori
                                </h5>
                                <span class="badge bg-light text-primary">
                                    Total: <?= $total_categories ?> kategori
                                </span>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($categories) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="border-0 fw-bold">ID</th>
                                            <th class="border-0 fw-bold">Nama Kategori</th>
                                            <th class="border-0 fw-bold">Deskripsi</th>
                                            <th class="border-0 fw-bold">Jumlah Buku</th>
                                            <th class="border-0 fw-bold text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($categories as $category): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-light text-dark border fw-normal">
                                                    #<?= $category['id_category'] ?>
                                                </span>
                                            </td>
                                            <td class="fw-bold"><?= sanitize($category['name']) ?></td>
                                            <td><?= sanitize($category['description']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $category['book_count'] > 0 ? 'info' : 'secondary' ?>">
                                                    <?= $category['book_count'] ?> buku
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-primary" 
                                                            data-bs-toggle="modal" data-bs-target="#editCategoryModal"
                                                            onclick="setEditCategory(<?= $category['id_category'] ?>, '<?= addslashes(sanitize($category['name'])) ?>', '<?= addslashes(sanitize($category['description'])) ?>')">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                            data-bs-toggle="modal" data-bs-target="#deleteCategoryModal"
                                                            onclick="setDeleteCategory(<?= $category['id_category'] ?>, '<?= addslashes(sanitize($category['name'])) ?>', <?= $category['book_count'] ?>)"
                                                            <?= $category['book_count'] > 0 ? 'disabled' : '' ?>>
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
                                <h4 class="text-muted mt-3">Tidak ada kategori ditemukan</h4>
                                <?php if (!empty($search)): ?>
                                    <p class="text-muted">Coba ubah kata kunci pencarian</p>
                                    <a href="categories.php" class="btn btn-primary">
                                        <i class="bi bi-arrow-clockwise me-2"></i>Reset Pencarian
                                    </a>
                                <?php else: ?>
                                    <p class="text-muted">Belum ada kategori yang tercatat saat ini</p>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryFormModal">
                                        <i class="bi bi-plus-circle me-2"></i>Tambah Kategori Baru
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
                                        <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">
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
                                        <a class="page-link" href="?page=1&search=<?= urlencode($search) ?>">1</a>
                                    </li>
                                    <?php if ($start_page > 2): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($end_page < $total_pages): ?>
                                    <?php if ($end_page < $total_pages - 1): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $total_pages ?>&search=<?= urlencode($search) ?>"><?= $total_pages ?></a>
                                    </li>
                                <?php endif; ?>

                                <!-- Next Page -->
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">
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

    <!-- Add Category Modal -->
    <div class="modal fade" id="categoryFormModal" tabindex="-1" aria-labelledby="categoryFormModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="categoryFormModalLabel">
                        <i class="bi bi-plus-circle me-2"></i>Tambah Kategori Baru
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="categoryForm" method="POST" action="categories.php">
                        <input type="hidden" name="action" value="add_category">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label fw-medium">Nama Kategori <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label fw-medium">Deskripsi</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Batal
                    </button>
                    <button type="submit" form="categoryForm" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>Simpan Kategori
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editCategoryModalLabel">
                        <i class="bi bi-pencil me-2"></i>Edit Kategori
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editCategoryForm" method="POST" action="categories.php">
                        <input type="hidden" name="action" value="edit_category">
                        <input type="hidden" id="edit_id_category" name="id_category" value="">
                        
                        <div class="mb-3">
                            <label for="edit_name" class="form-label fw-medium">Nama Kategori <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label fw-medium">Deskripsi</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Batal
                    </button>
                    <button type="submit" form="editCategoryForm" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>Simpan Perubahan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Category Modal -->
    <div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-labelledby="deleteCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteCategoryModalLabel">
                        <i class="bi bi-exclamation-triangle me-2"></i>Konfirmasi Hapus
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Anda yakin ingin menghapus kategori ini?</p>
                    <div class="alert alert-warning">
                        <strong>Kategori:</strong> <span id="deleteCategoryName"></span>
                    </div>
                    <div id="deleteCategoryWarning" class="alert alert-danger d-none">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Kategori ini tidak dapat dihapus karena masih digunakan oleh <span id="deleteCategoryCount"></span> buku.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Batal
                    </button>
                    <form id="deleteCategoryForm" method="POST" action="categories.php">
                        <input type="hidden" name="action" value="delete_category">
                        <input type="hidden" id="delete_id_category" name="id_category" value="">
                        <button type="submit" class="btn btn-danger" id="deleteCategoryButton">
                            <i class="bi bi-trash me-2"></i>Hapus Kategori
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to set edit category data
        function setEditCategory(id, name, description) {
            document.getElementById('edit_id_category').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_description').value = description;
        }
        
        // Function to set delete category data
        function setDeleteCategory(id, name, bookCount) {
            document.getElementById('delete_id_category').value = id;
            document.getElementById('deleteCategoryName').textContent = name;
            document.getElementById('deleteCategoryCount').textContent = bookCount;
            
            const warningDiv = document.getElementById('deleteCategoryWarning');
            const deleteButton = document.getElementById('deleteCategoryButton');
            
            if (bookCount > 0) {
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
