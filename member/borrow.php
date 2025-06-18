<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireMember();

$success = '';
$error = '';

// Handle form submission
if ($_POST && isset($_POST['book_id'])) {
    $book_id = (int)$_POST['book_id'];
    $user_id = $_SESSION['user_id'];
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Check if book exists and available
        $stmt = $pdo->prepare("SELECT * FROM books WHERE id_book = ? AND available_copies > 0");
        $stmt->execute([$book_id]);
        $book = $stmt->fetch();
        
        if (!$book) {
            throw new Exception('Buku tidak tersedia atau tidak ditemukan!');
        }
        
        // Check if user already borrowed this book and hasn't returned it
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE id_user = ? AND id_book = ? AND status = 'borrowed'");
        $stmt->execute([$user_id, $book_id]);
        $already_borrowed = $stmt->fetchColumn();
        
        if ($already_borrowed > 0) {
            throw new Exception('Anda sudah meminjam buku ini dan belum mengembalikannya!');
        }
        
        // Check user's active loans limit (max 3 books)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE id_user = ? AND status = 'borrowed'");
        $stmt->execute([$user_id]);
        $active_loans = $stmt->fetchColumn();
        
        if ($active_loans >= 3) {
            throw new Exception('Anda sudah mencapai batas maksimal peminjaman (3 buku). Kembalikan buku terlebih dahulu!');
        }
        
        // Calculate due date (7 days from now)
        $loan_date = date('Y-m-d');
        $due_date = date('Y-m-d', strtotime('+7 days'));
        
        // Insert loan record
        $stmt = $pdo->prepare("INSERT INTO loans (id_user, id_book, loan_date, due_date, status) VALUES (?, ?, ?, ?, 'borrowed')");
        $stmt->execute([$user_id, $book_id, $loan_date, $due_date]);
        
        // Update book available copies
        $stmt = $pdo->prepare("UPDATE books SET available_copies = available_copies - 1 WHERE id_book = ?");
        $stmt->execute([$book_id]);
        
        // Commit transaction
        $pdo->commit();
        
        $success = 'Buku berhasil dipinjam! Jatuh tempo pengembalian: ' . formatDate($due_date);
        
    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollback();
        $error = $e->getMessage();
    }
}

// Redirect back to dashboard with message
if ($success) {
    $_SESSION['success_message'] = $success;
} elseif ($error) {
    $_SESSION['error_message'] = $error;
}

header("Location: dashboard.php");
exit;
?>
