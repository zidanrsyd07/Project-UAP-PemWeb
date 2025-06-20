<?php
session_start();

// Cek apakah file config ada
$config_path = '../config/database.php';
if (!file_exists($config_path)) {
    die("File config/database.php tidak ditemukan. Pastikan file tersebut ada di folder config/");
}

require_once $config_path;
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        header("Location: ../admin/dashboard.php");
        exit;
    } else {
        header("Location: ../member/dashboard.php");
        exit;
    }
}

$error = '';

if ($_POST) {
    $login_identifier = sanitize($_POST['login_identifier']); // Email atau username
    $password = md5($_POST['password']);
    
    try {
        // Cek apakah login dengan email (member) atau username (admin)
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (email = ? OR username = ?) AND password = ? AND status = 'active'");
        $stmt->execute([$login_identifier, $login_identifier, $password]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Set session manually
            $_SESSION['user_id'] = $user['id_user'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            
            if ($user['role'] === 'member') {
                $_SESSION['user_number'] = $user['user_number'];
            } else {
                $_SESSION['username'] = $user['username'];
            }
            
            // Redirect berdasarkan role
            if ($user['role'] === 'admin') {
                header("Location: ../admin/dashboard.php");
                exit;
            } else {
                header("Location: ../member/dashboard.php");
                exit;
            }
        } else {
            $stmt2 = $pdo->prepare("SELECT * FROM users WHERE (email = ? OR username = ?) AND status = 'active'");
            $stmt2->execute([$login_identifier, $login_identifier]);
            $user_check = $stmt2->fetch();
            
            if ($user_check) {
                $error = 'Password salah!';
            } else {
                $error = 'Email/Username tidak ditemukan atau akun tidak aktif!';
            }
        }
    } catch (Exception $e) {
        $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Pinjamin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center min-vh-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center py-4">
                        <h4 class="mb-0">
                            <i class="bi bi-person-circle"></i> Login
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle"></i> <?= $error ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="login_identifier" class="form-label">Email / Username</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-person"></i>
                                    </span>
                                    <input type="text" class="form-control" id="login_identifier" name="login_identifier" 
                                           value="<?= isset($_POST['login_identifier']) ? htmlspecialchars($_POST['login_identifier']) : '' ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="bi bi-box-arrow-in-right"></i> Masuk
                            </button>
                        </form>
                        
                        <div class="text-center">
                            <p class="mb-2">Belum punya akun?</p>
                            <a href="register.php" class="btn btn-outline-primary">
                                <i class="bi bi-person-plus"></i> Daftar Sekarang
                            </a>
                        </div>
                    </div>
                    <div class="card-footer text-center">
                        <a href="../index.php" class="text-decoration-none">
                            <i class="bi bi-arrow-left"></i> Kembali ke Beranda
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
