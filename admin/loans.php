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
    
    if ($action === 'add_loan') {
        $user_id = (int)$_POST['id_user'];
        $book_id = (int)$_POST['id_book'];
        $loan_date = $_POST['loan_date'];
        $due_date = $_POST['due_date'];
        
        try {
            $pdo->beginTransaction();
            
            // Cek apakah buku tersedia
            $stmt = $pdo->prepare("SELECT available_copies FROM books WHERE id_book = ?");
            $stmt->execute([$book_id]);
            $book = $stmt->fetch();
            
            if (!$book || $book['available_copies'] <= 0) {
                throw new Exception('Buku tidak tersedia atau stok habis!');
            }
            
            // Cek apakah anggota aktif
            $stmt = $pdo->prepare("SELECT status FROM users WHERE id_user = ? AND role = 'member'");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user || $user['status'] !== 'active') {
                throw new Exception('Anggota tidak ditemukan atau tidak aktif!');
            }
            
            // Tambah peminjaman
            $stmt = $pdo->prepare("
                INSERT INTO loans (id_user, id_book, loan_date, due_date, status)
                VALUES (?, ?, ?, ?, 'borrowed')
            ");
            $stmt->execute([$user_id, $book_id, $loan_date, $due_date]);
            
            // Update stok buku
            $stmt = $pdo->prepare("UPDATE books SET available_copies = available_copies - 1 WHERE id_book = ?");
            $stmt->execute([$book_id]);
            
            $pdo->commit();
            $success = 'Peminjaman berhasil ditambahkan!';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error: ' . $e->getMessage();
        }
    } elseif ($action === 'return_book') {
        $loan_id = (int)$_POST['id_loan'];
        $return_date = $_POST['return_date'];
        
        try {
            $pdo->beginTransaction();
            
            // Ambil data peminjaman
            $stmt = $pdo->prepare("SELECT id_book FROM loans WHERE id_loan = ? AND status = 'borrowed'");
            $stmt->execute([$loan_id]);
            $loan = $stmt->fetch();
            
            if (!$loan) {
                throw new Exception('Peminjaman tidak ditemukan atau sudah dikembalikan!');
            }
            
            // Update status peminjaman
            $stmt = $pdo->prepare("
                UPDATE loans SET status = 'returned', return_date = ? 
                WHERE id_loan = ?
            ");
            $stmt->execute([$return_date, $loan_id]);
            
            // Update stok buku
            $stmt = $pdo->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE id_book = ?");
            $stmt->execute([$loan['id_book']]);
            
            $pdo->commit();
            $success = 'Buku berhasil dikembalikan!';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error: ' . $e->getMessage();
        }
    } elseif ($action === 'delete_loan') {
        $loan_id = (int)$_POST['id_loan'];
        
        try {
            $pdo->beginTransaction();
            
            // Ambil data peminjaman
            $stmt = $pdo->prepare("SELECT id_book, status FROM loans WHERE id_loan = ?");
            $stmt->execute([$loan_id]);
            $loan = $stmt->fetch();
            
            if (!$loan) {
                throw new Exception('Peminjaman tidak ditemukan!');
            }
            
            // Jika status borrowed, kembalikan stok
            if ($loan['status'] === 'borrowed') {
                $stmt = $pdo->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE id_book = ?");
                $stmt->execute([$loan['id_book']]);
            }
            
            // Hapus peminjaman
            $stmt = $pdo->prepare("DELETE FROM loans WHERE id_loan = ?");
            $stmt->execute([$loan_id]);
            
            $pdo->commit();
            $success = 'Data peminjaman berhasil dihapus!';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Pagination settings
$loans_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $loans_per_page;

// Search and filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$member_filter = isset($_GET['member']) ? (int)$_GET['member'] : 0;

// Build query conditions
$where_conditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.full_name LIKE ? OR b.title LIKE ? OR u.user_number LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status_filter)) {
    $where_conditions[] = "l.status = ?";
    $params[] = $status_filter;
}

if ($member_filter > 0) {
    $where_conditions[] = "l.id_user = ?";
    $params[] = $member_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Get total loans count for pagination
$count_sql = "
    SELECT COUNT(*) 
    FROM loans l 
    JOIN users u ON l.id_user = u.id_user 
    JOIN books b ON l.id_book = b.id_book 
    WHERE $where_clause
";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_loans = $count_stmt->fetchColumn();
$total_pages = ceil($total_loans / $loans_per_page);

// Get loans with pagination
$sql = "
    SELECT l.*, u.full_name, u.user_number, b.title as book_title, b.author,
           CASE 
               WHEN l.status = 'borrowed' AND l.due_date < CURDATE() THEN 'overdue'
               ELSE l.status
           END as display_status,
           DATEDIFF(CURDATE(), l.due_date) as days_overdue
    FROM loans l 
    JOIN users u ON l.id_user = u.id_user 
    JOIN books b ON l.id_book = b.id_book 
    WHERE $where_clause 
    ORDER BY l.loan_date DESC 
    LIMIT $loans_per_page OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$loans = $stmt->fetchAll();

// Get data for form dropdowns
$users_stmt = $pdo->query("SELECT id_user, full_name, user_number FROM users WHERE role = 'member' AND status = 'active' ORDER BY full_name");
$users = $users_stmt->fetchAll();

$books_stmt = $pdo->query("SELECT id_book, title, author, available_copies FROM books WHERE available_copies > 0 ORDER BY title");
$books = $books_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Peminjaman - Pinjamin</title>
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
                            <a class="nav-link" href="members.php">
                                <i class="bi bi-people"></i>Anggota
                            </a>
                            <a class="nav-link active" href="loans.php">
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
                                <i class="bi bi-calendar-check me-2"></i>Kelola Peminjaman
                            </h1>
                            <p class="text-muted mb-0">Kelola peminjaman dan pengembalian buku</p>
                        </div>
                        <div>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#loanFormModal">
                                <i class="bi bi-plus-circle me-2"></i>Tambah Peminjaman
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
                            <form method="GET" action="loans.php">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="search" class="form-label fw-medium">Cari Peminjaman</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-primary text-white">
                                                <i class="bi bi-search"></i>
                                            </span>
                                            <input type="text" class="form-control" id="search" name="search" 
                                                   placeholder="Nama anggota, judul buku, atau nomor anggota..." 
                                                   value="<?= htmlspecialchars($search) ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="status" class="form-label fw-medium">Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="">Semua Status</option>
                                            <option value="borrowed" <?= $status_filter === 'borrowed' ? 'selected' : '' ?>>Dipinjam</option>
                                            <option value="returned" <?= $status_filter === 'returned' ? 'selected' : '' ?>>Dikembalikan</option>
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
                                    <a href="loans.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-x-circle me-2"></i>Reset Filter
                                    </a>
                                </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <!-- Loans Table -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-primary text-white border-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 fw-bold">
                                    <i class="bi bi-list-ul me-2"></i>Daftar Peminjaman
                                </h5>
                                <span class="badge bg-light text-primary">
                                    Total: <?= $total_loans ?> peminjaman
                                </span>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($loans) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="border-0 fw-bold">ID</th>
                                            <th class="border-0 fw-bold">Anggota</th>
                                            <th class="border-0 fw-bold">Buku</th>
                                            <th class="border-0 fw-bold">Tanggal Pinjam</th>
                                            <th class="border-0 fw-bold">Jatuh Tempo</th>
                                            <th class="border-0 fw-bold">Status</th>
                                            <th class="border-0 fw-bold text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($loans as $loan): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-light text-dark border fw-normal">
                                                    #<?= $loan['id_loan'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?= sanitize($loan['full_name']) ?></div>
                                                <small class="text-muted"><?= sanitize($loan['user_number']) ?></small>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?= sanitize($loan['book_title']) ?></div>
                                                <small class="text-muted">oleh <?= sanitize($loan['author']) ?></small>
                                            </td>
                                            <td><?= date('d/m/Y', strtotime($loan['loan_date'])) ?></td>
                                            <td>
                                                <?= date('d/m/Y', strtotime($loan['due_date'])) ?>
                                                <?php if ($loan['display_status'] === 'overdue'): ?>
                                                    <br><small class="text-danger fw-bold">
                                                        Terlambat <?= $loan['days_overdue'] ?> hari
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($loan['display_status'] === 'borrowed'): ?>
                                                    <span class="badge bg-warning text-dark">Dipinjam</span>
                                                <?php elseif ($loan['display_status'] === 'overdue'): ?>
                                                    <span class="badge bg-danger">Terlambat</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Dikembalikan</span>
                                                    <br><small class="text-muted">
                                                        <?= date('d/m/Y', strtotime($loan['return_date'])) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group">
                                                    <?php if ($loan['status'] === 'borrowed'): ?>
                                                        <button type="button" class="btn btn-sm btn-success" 
                                                                data-bs-toggle="modal" data-bs-target="#returnBookModal"
                                                                onclick="setReturnLoanId(<?= $loan['id_loan'] ?>, '<?= addslashes(sanitize($loan['book_title'])) ?>', '<?= addslashes(sanitize($loan['full_name'])) ?>')">
                                                            <i class="bi bi-arrow-return-left"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                            data-bs-toggle="modal" data-bs-target="#deleteLoanModal"
                                                            onclick="setDeleteLoanId(<?= $loan['id_loan'] ?>, '<?= addslashes(sanitize($loan['book_title'])) ?>', '<?= addslashes(sanitize($loan['full_name'])) ?>')">
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
                                <h4 class="text-muted mt-3">Tidak ada peminjaman ditemukan</h4>
                                <?php if (!empty($search) || !empty($status_filter)): ?>
                                    <p class="text-muted">Coba ubah kata kunci pencarian atau filter status</p>
                                    <a href="loans.php" class="btn btn-primary">
                                        <i class="bi bi-arrow-clockwise me-2"></i>Reset Pencarian
                                    </a>
                                <?php else: ?>
                                    <p class="text-muted">Belum ada peminjaman yang tercatat saat ini</p>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#loanFormModal">
                                        <i class="bi bi-plus-circle me-2"></i>Tambah Peminjaman Baru
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

    <!-- Loan Form Modal -->
    <div class="modal fade" id="loanFormModal" tabindex="-1" aria-labelledby="loanFormModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="loanFormModalLabel">
                        <i class="bi bi-plus-circle me-2"></i>Tambah Peminjaman Baru
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="loanForm" method="POST" action="loans.php">
                        <input type="hidden" name="action" value="add_loan">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="id_user" class="form-label fw-medium">Anggota <span class="text-danger">*</span></label>
                                <select class="form-select" id="id_user" name="id_user" required>
                                    <option value="">Pilih Anggota</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?= $user['id_user'] ?>">
                                            <?= sanitize($user['full_name']) ?> (<?= sanitize($user['user_number']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="id_book" class="form-label fw-medium">Buku <span class="text-danger">*</span></label>
                                <select class="form-select" id="id_book" name="id_book" required>
                                    <option value="">Pilih Buku</option>
                                    <?php foreach ($books as $book): ?>
                                        <option value="<?= $book['id_book'] ?>">
                                            <?= sanitize($book['title']) ?> - <?= sanitize($book['author']) ?> (Stok: <?= $book['available_copies'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="loan_date" class="form-label fw-medium">Tanggal Pinjam <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="loan_date" name="loan_date" 
                                       value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="due_date" class="form-label fw-medium">Tanggal Jatuh Tempo <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="due_date" name="due_date" 
                                       value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Batal
                    </button>
                    <button type="submit" form="loanForm" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>Simpan Peminjaman
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Return Book Modal -->
    <div class="modal fade" id="returnBookModal" tabindex="-1" aria-labelledby="returnBookModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="returnBookModalLabel">
                        <i class="bi bi-arrow-return-left me-2"></i>Kembalikan Buku
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Konfirmasi pengembalian buku:</p>
                    <div class="alert alert-info">
                        <strong>Buku:</strong> <span id="returnBookTitle"></span><br>
                        <strong>Anggota:</strong> <span id="returnMemberName"></span>
                    </div>
                    <form id="returnBookForm" method="POST" action="loans.php">
                        <input type="hidden" name="action" value="return_book">
                        <input type="hidden" id="returnLoanId" name="id_loan" value="">
                        
                        <div class="mb-3">
                            <label for="return_date" class="form-label fw-medium">Tanggal Kembali <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="return_date" name="return_date" 
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Batal
                    </button>
                    <button type="submit" form="returnBookForm" class="btn btn-success">
                        <i class="bi bi-check-circle me-2"></i>Kembalikan Buku
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Loan Modal -->
    <div class="modal fade" id="deleteLoanModal" tabindex="-1" aria-labelledby="deleteLoanModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteLoanModalLabel">
                        <i class="bi bi-exclamation-triangle me-2"></i>Konfirmasi Hapus
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Anda yakin ingin menghapus data peminjaman ini?</p>
                    <div class="alert alert-warning">
                        <strong>Buku:</strong> <span id="deleteLoanBookTitle"></span><br>
                        <strong>Anggota:</strong> <span id="deleteLoanMemberName"></span>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Jika buku belum dikembalikan, stok akan otomatis dikembalikan.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Batal
                    </button>
                    <form id="deleteLoanForm" method="POST" action="loans.php">
                        <input type="hidden" name="action" value="delete_loan">
                        <input type="hidden" id="deleteLoanId" name="id_loan" value="">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-2"></i>Hapus Data
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to set return loan id
        function setReturnLoanId(loanId, bookTitle, memberName) {
            document.getElementById('returnLoanId').value = loanId;
            document.getElementById('returnBookTitle').textContent = bookTitle;
            document.getElementById('returnMemberName').textContent = memberName;
        }
        
        // Function to set delete loan id
        function setDeleteLoanId(loanId, bookTitle, memberName) {
            document.getElementById('deleteLoanId').value = loanId;
            document.getElementById('deleteLoanBookTitle').textContent = bookTitle;
            document.getElementById('deleteLoanMemberName').textContent = memberName;
        }
    </script>
</body>
</html>
