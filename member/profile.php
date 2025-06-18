<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireMember();

$success = '';
$error = '';

// Get current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id_user = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    $error = 'Data pengguna tidak ditemukan!';
}

// Handle form submission
if ($_POST && $user) {
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    try {
        // Validate email uniqueness (exclude current user)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id_user != ?");
        $stmt->execute([$email, $_SESSION['user_id']]);
        $email_exists = $stmt->fetchColumn();
        
        if ($email_exists > 0) {
            $error = 'Email sudah digunakan oleh pengguna lain!';
        } else {
            // Prepare update query
            $update_fields = [
                'full_name = ?',
                'email = ?', 
                'phone = ?',
                'address = ?'
            ];
            $params = [$full_name, $email, $phone, $address];
            
            // Check if password change is requested
            if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
                // Validate current password
                if (md5($current_password) !== $user['password']) {
                    $error = 'Password saat ini tidak benar!';
                } elseif (empty($new_password)) {
                    $error = 'Password baru tidak boleh kosong!';
                } elseif (strlen($new_password) < 6) {
                    $error = 'Password baru minimal 6 karakter!';
                } elseif ($new_password !== $confirm_password) {
                    $error = 'Konfirmasi password tidak cocok!';
                } else {
                    // Add password to update
                    $update_fields[] = 'password = ?';
                    $params[] = md5($new_password);
                }
            }
            
            if (empty($error)) {
                // Update user data
                $params[] = $_SESSION['user_id'];
                $sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id_user = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                // Update session data
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id_user = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
                
                $success = 'Profil berhasil diperbarui!';
            }
        }
    } catch (Exception $e) {
        $error = 'Terjadi kesalahan saat memperbarui profil: ' . $e->getMessage();
    }
}

// Get user statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total_loans FROM loans WHERE id_user = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_loans = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as active_loans FROM loans WHERE id_user = ? AND status = 'borrowed'");
$stmt->execute([$_SESSION['user_id']]);
$active_loans = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as returned_loans FROM loans WHERE id_user = ? AND status = 'returned'");
$stmt->execute([$_SESSION['user_id']]);
$returned_loans = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - Pinjamin</title>
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
                            <a class="nav-link" href="loans.php">
                                <i class="bi bi-calendar-check"></i>Peminjaman Saya
                            </a>
                            <a class="nav-link active" href="profile.php">
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
                                <i class="bi bi-person-gear me-2"></i>Profil Saya
                            </h1>
                            <p class="text-muted mb-0">Kelola informasi akun dan preferensi Anda</p>
                        </div>
                        <div class="text-end">
                            <small class="text-muted">
                                <i class="bi bi-calendar3 me-1"></i>
                                <?= date('d F Y') ?>
                            </small>
                        </div>
                    </div>

                    <?php if ($success): ?>
                        <?= showAlert($success, 'success') ?>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <?= showAlert($error, 'danger') ?>
                    <?php endif; ?>

                    <div class="row g-4">
                        <!-- Profile Statistics -->
                        <div class="col-lg-4">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-primary text-white border-0">
                                    <h5 class="mb-0 fw-bold">
                                        <i class="bi bi-graph-up me-2"></i>Statistik Saya
                                    </h5>
                                </div>
                                <div class="card-body p-4">
                                    <!-- Profile Avatar -->
                                    <div class="text-center mb-4">
                                        <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center" 
                                             style="width: 80px; height: 80px;">
                                            <i class="bi bi-person-fill text-primary" style="font-size: 2.5rem;"></i>
                                        </div>
                                        <h5 class="mt-3 mb-1 fw-bold"><?= sanitize($user['full_name']) ?></h5>
                                        <p class="text-muted mb-0">
                                            <i class="bi bi-credit-card me-1"></i>
                                            <?= $user['user_number'] ?>
                                        </p>
                                    </div>

                                    <!-- Statistics -->
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                                <div>
                                                    <i class="bi bi-book-half text-primary me-2"></i>
                                                    <span class="fw-medium">Total Peminjaman</span>
                                                </div>
                                                <span class="badge bg-primary rounded-pill"><?= $total_loans ?></span>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                                <div>
                                                    <i class="bi bi-clock-history text-warning me-2"></i>
                                                    <span class="fw-medium">Sedang Dipinjam</span>
                                                </div>
                                                <span class="badge bg-warning rounded-pill"><?= $active_loans ?></span>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                                <div>
                                                    <i class="bi bi-check-circle text-success me-2"></i>
                                                    <span class="fw-medium">Telah Dikembalikan</span>
                                                </div>
                                                <span class="badge bg-success rounded-pill"><?= $returned_loans ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Member Since -->
                                    <div class="mt-4 pt-3 border-top">
                                        <small class="text-muted">
                                            <i class="bi bi-calendar-plus me-1"></i>
                                            Anggota sejak <?= formatDate(date('Y-m-d', strtotime($user['created_at']))) ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Profile Form -->
                        <div class="col-lg-8">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-success text-white border-0">
                                    <h5 class="mb-0 fw-bold">
                                        <i class="bi bi-pencil-square me-2"></i>Edit Profil
                                    </h5>
                                </div>
                                <div class="card-body p-4">
                                    <form method="POST">
                                        <div class="row g-3">
                                            <!-- Personal Information -->
                                            <div class="col-12">
                                                <h6 class="fw-bold text-primary mb-3">
                                                    <i class="bi bi-person me-2"></i>Informasi Pribadi
                                                </h6>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label for="full_name" class="form-label fw-medium">Nama Lengkap</label>
                                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                                       value="<?= sanitize($user['full_name']) ?>" required>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label for="email" class="form-label fw-medium">Email</label>
                                                <input type="email" class="form-control" id="email" name="email" 
                                                       value="<?= sanitize($user['email']) ?>" required>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label for="phone" class="form-label fw-medium">Nomor Telepon</label>
                                                <input type="tel" class="form-control" id="phone" name="phone" 
                                                       value="<?= sanitize($user['phone']) ?>">
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label for="user_number" class="form-label fw-medium">Nomor Anggota</label>
                                                <input type="text" class="form-control" value="<?= $user['user_number'] ?>" 
                                                       readonly disabled>
                                                <div class="form-text">Nomor anggota tidak dapat diubah</div>
                                            </div>
                                            
                                            <div class="col-12">
                                                <label for="address" class="form-label fw-medium">Alamat</label>
                                                <textarea class="form-control" id="address" name="address" rows="3"><?= sanitize($user['address']) ?></textarea>
                                            </div>

                                            <!-- Password Change Section -->
                                            <div class="col-12 mt-4">
                                                <hr>
                                                <h6 class="fw-bold text-primary mb-3">
                                                    <i class="bi bi-shield-lock me-2"></i>Ubah Password
                                                </h6>
                                                <div class="alert alert-info">
                                                    <i class="bi bi-info-circle me-2"></i>
                                                    Kosongkan jika tidak ingin mengubah password
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-4">
                                                <label for="current_password" class="form-label fw-medium">Password Saat Ini</label>
                                                <input type="password" class="form-control" id="current_password" name="current_password">
                                            </div>
                                            
                                            <div class="col-md-4">
                                                <label for="new_password" class="form-label fw-medium">Password Baru</label>
                                                <input type="password" class="form-control" id="new_password" name="new_password">
                                                <div class="form-text">Minimal 6 karakter</div>
                                            </div>
                                            
                                            <div class="col-md-4">
                                                <label for="confirm_password" class="form-label fw-medium">Konfirmasi Password</label>
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                            </div>

                                            <!-- Submit Button -->
                                            <div class="col-12 mt-4">
                                                <div class="d-flex gap-2">
                                                    <button type="submit" class="btn btn-success">
                                                        <i class="bi bi-check-circle me-2"></i>Simpan Perubahan
                                                    </button>
                                                    <a href="dashboard.php" class="btn btn-outline-secondary">
                                                        <i class="bi bi-arrow-left me-2"></i>Kembali
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Password Validation Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const currentPassword = document.getElementById('current_password');
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            function validatePasswords() {
                if (newPassword.value && newPassword.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Password tidak cocok');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }
            
            newPassword.addEventListener('input', validatePasswords);
            confirmPassword.addEventListener('input', validatePasswords);
            
            // If any password field is filled, require current password
            [newPassword, confirmPassword].forEach(field => {
                field.addEventListener('input', function() {
                    if (this.value && !currentPassword.value) {
                        currentPassword.required = true;
                    } else if (!newPassword.value && !confirmPassword.value) {
                        currentPassword.required = false;
                    }
                });
            });
        });
    </script>
</body>
</html>
