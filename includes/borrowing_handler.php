<?php
// includes/borrowing_handler.php
require_once 'auth.php';
require_once '../config/db.php';
require_once 'notifications_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Handle a borrowing request (DISABLED)
 */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'borrow') {
    die(json_encode(['success' => false, 'message' => 'The institutional borrowing system has been decommissioned. Please access resources digitally.']));
}

/**
 * Validate asset access (Bypassed)
 */
function validateAssetAccess($conn, $user_id, $book_id) {
    return true; // Institutional policy: Full access enabled
}
?>
