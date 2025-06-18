<?php
session_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pinjamin - Perpustakaan Mini</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top shadow" style="background-color: #6366f1 !important;">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="bi bi-book"></i> Pinjamin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <a class="nav-link" href="admin/dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard Admin
                            </a>
                        <?php else: ?>
                            <a class="nav-link" href="member/dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        <?php endif; ?>
                        <a class="nav-link" href="auth/logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    <?php else: ?>
                        <a class="nav-link" href="auth/login.php">
                            <i class="bi bi-box-arrow-in-right"></i> Login / Daftar
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8">
                    <h1 class="hero-title">Selamat Datang di Pinjamin</h1>
                    <p class="hero-subtitle">Sistem Perpustakaan Mini yang memudahkan Anda dalam meminjam dan mengelola buku-buku favorit</p>
                    <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <a href="auth/register.php" class="btn btn-custom-light btn-lg">
                                <i class="bi bi-person-plus"></i> Daftar Sekarang
                            </a>
                            <a href="auth/login.php" class="btn btn-custom-outline btn-lg">
                                <i class="bi bi-box-arrow-in-right"></i> Login
                            </a>
                        <?php else: ?>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <a href="admin/dashboard.php" class="btn btn-custom-light btn-lg">
                                    <i class="bi bi-speedometer2"></i> Dashboard Admin
                                </a>
                            <?php else: ?>
                                <a href="member/dashboard.php" class="btn btn-custom-light btn-lg">
                                    <i class="bi bi-speedometer2"></i> Dashboard Saya
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center mb-5">
                    <h2 class="fw-bold text-primary display-5">Fitur Unggulan</h2>
                    <p class="text-muted fs-5">Nikmati kemudahan dalam mengelola perpustakaan</p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card text-center p-4">
                        <div class="card-body">
                            <div class="feature-icon d-flex align-items-center justify-content-center mx-auto mb-3" 
                                 style="width: 80px; height: 80px; background-color: rgba(99, 102, 241, 0.1) !important; color: #6366f1 !important; border-radius: 50%;">
                                <i class="bi bi-search"></i>
                            </div>
                            <h3 class="card-title h5 fw-bold">Pencarian Buku</h3>
                            <p class="card-text text-muted">Temukan buku yang Anda cari dengan mudah dan cepat</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card text-center p-4">
                        <div class="card-body">
                            <div class="feature-icon d-flex align-items-center justify-content-center mx-auto mb-3" 
                                 style="width: 80px; height: 80px; background-color: rgba(99, 102, 241, 0.1) !important; color: #6366f1 !important; border-radius: 50%;">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                            <h3 class="card-title h5 fw-bold">Peminjaman Online</h3>
                            <p class="card-text text-muted">Pinjam buku secara online dan pantau status peminjaman</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card text-center p-4">
                        <div class="card-body">
                            <div class="feature-icon d-flex align-items-center justify-content-center mx-auto mb-3" 
                                 style="width: 80px; height: 80px; background-color: rgba(99, 102, 241, 0.1) !important; color: #6366f1 !important; border-radius: 50%;">
                                <i class="bi bi-graph-up"></i>
                            </div>
                            <h3 class="card-title h5 fw-bold">Riwayat Lengkap</h3>
                            <p class="card-text text-muted">Lihat riwayat peminjaman dan pengembalian buku</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="py-5 bg-white">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-3 mb-4">
                    <div class="card border-0 bg-transparent">
                        <div class="card-body">
                            <h3 class="text-primary fw-bold display-6">500+</h3>
                            <p class="text-muted">Koleksi Buku</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card border-0 bg-transparent">
                        <div class="card-body">
                            <h3 class="text-primary fw-bold display-6">200+</h3>
                            <p class="text-muted">Anggota Aktif</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card border-0 bg-transparent">
                        <div class="card-body">
                            <h3 class="text-primary fw-bold display-6">50+</h3>
                            <p class="text-muted">Peminjaman/Bulan</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card border-0 bg-transparent">
                        <div class="card-body">
                            <h3 class="text-primary fw-bold display-6">24/7</h3>
                            <p class="text-muted">Akses Online</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="text-white py-4" style="background-color: #6366f1 !important;">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <p class="mb-0">&copy; 2024 Pinjamin - Perpustakaan Mini. Semua hak dilindungi undang-undang.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
