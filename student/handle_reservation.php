<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/notifications_helper.php';

// Enforce Student Access
checkAccess('student');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$book_id = isset($_POST['book_id']) ? (int)$_POST['book_id'] : 0;

if (!$book_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid book specified.']);
    exit;
}

// 1. Check if book exists and has available copies
$stmt = $conn->prepare("SELECT title, available_copies FROM books WHERE id = ?");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();

if (!$book) {
    echo json_encode(['success' => false, 'message' => 'Book not found.']);
    exit;
}

if ($book['available_copies'] <= 0) {
    echo json_encode(['success' => false, 'message' => 'This book is currently out of stock.']);
    exit;
}

// 2. Check if user already has an active or pending reservation for this book
$check = $conn->prepare("SELECT id FROM reservations WHERE user_id = ? AND book_id = ? AND status IN ('pending', 'approved', 'in_use')");
$check->bind_param("ii", $user_id, $book_id);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'You already have an active reservation for this book.']);
    exit;
}

// 3. Create reservation
$ins = $conn->prepare("INSERT INTO reservations (user_id, book_id, status) VALUES (?, ?, 'pending')");
$ins->bind_param("ii", $user_id, $book_id);

if ($ins->execute()) {
    $title = $book['title'];
    $student_name = $_SESSION['full_name'];
    
    // Notify Student
    sendNotification($conn, $user_id, "Reservation Requested", "Your request to reserve '$title' has been submitted and is awaiting admin approval.", "info");
    
    // Notify Admins
    notifyAdmins($conn, "New Reservation Request", "Student '$student_name' has requested to reserve the book '$title'. Please review the request in the Reservations panel.", "info");
    
    echo json_encode(['success' => true, 'message' => 'Reservation requested successfully!']);
} else {
    echo json_encode(['success' => false, 'message' => 'System error. Please try again later.']);
}
?>
