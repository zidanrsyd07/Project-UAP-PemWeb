<?php
// Helper Functions

/**
 * Format date to Indonesian format
 */
function formatDate($date) {
    if (!$date) return '-';
    
    $months = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    $split = explode('-', $date);
    if (count($split) !== 3) return $date;
    
    return $split[2] . ' ' . $months[(int)$split[1]] . ' ' . $split[0];
}

/**
 * Generate member number
 */
function generateMemberNumber() {
    global $pdo;
    
    // Get current year
    $year = date('Y');
    
    // Get last member number for current year
    $stmt = $pdo->prepare("SELECT user_number FROM users WHERE user_number LIKE ? ORDER BY user_number DESC LIMIT 1");
    $stmt->execute([$year . '%']);
    $last_number = $stmt->fetchColumn();
    
    if ($last_number) {
        // Extract sequence number and increment
        $sequence = (int)substr($last_number, 4) + 1;
    } else {
        // First member of the year
        $sequence = 1;
    }
    
    // Format: YYYY0001, YYYY0002, etc.
    return $year . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}

/**
 * Generate book code
 */
function generateBookCode() {
    return 'BK' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
}

/**
 * Sanitize input
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect function
 */
function redirect($url) {
    // Pastikan tidak ada output sebelum redirect
    if (!headers_sent()) {
        header("Location: $url");
        exit;
    } else {
        // Jika header sudah dikirim, gunakan JavaScript redirect
        echo "<script>window.location.href = '$url';</script>";
        exit;
    }
}

/**
 * Show alert message
 */
function showAlert($message, $type = 'info') {
    $iconMap = [
        'success' => 'bi-check-circle',
        'danger' => 'bi-exclamation-triangle',
        'warning' => 'bi-exclamation-triangle',
        'info' => 'bi-info-circle'
    ];
    
    $icon = $iconMap[$type] ?? 'bi-info-circle';
    
    return "
    <div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
        <i class='bi {$icon}'></i> {$message}
        <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
    </div>";
}

/**
 * Calculate overdue days
 */
function calculateOverdueDays($dueDate) {
    $due = new DateTime($dueDate);
    $today = new DateTime();
    
    if ($today > $due) {
        return $today->diff($due)->days;
    }
    
    return 0;
}

/**
 * Calculate fine amount
 */
function calculateFine($overdueDays, $finePerDay = 1000) {
    return $overdueDays * $finePerDay;
}



?>
