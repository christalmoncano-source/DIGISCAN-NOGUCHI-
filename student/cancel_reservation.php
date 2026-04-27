<?php
session_start();
require_once '../includes/auth.php';
require_once '../config/db.php';
checkAccess(['student', 'admin']);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reservation_id'])) {
    $reservation_id = (int)$_POST['reservation_id'];
    $user_id = $_SESSION['user_id'];
    
    // Verify ownership and status
    $stmt = $conn->prepare("SELECT status, book_id FROM reservations WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $reservation_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $status = $row['status'];
        $book_id = $row['book_id'];
        
        if (in_array($status, ['pending', 'approved'])) {
            $conn->begin_transaction();
            try {
                // Update to cancelled
                $upd = $conn->prepare("UPDATE reservations SET status = 'cancelled' WHERE id = ?");
                $upd->bind_param("i", $reservation_id);
                $upd->execute();
                
                // If it was approved, we need to return the book to stock
                if ($status == 'approved') {
                    $conn->query("UPDATE books SET available_copies = available_copies + 1 WHERE id = " . (int)$book_id);
                }
                
                $conn->commit();
                $_SESSION['message'] = "Reservation cancelled successfully.";
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['error'] = "Failed to cancel the reservation. Please try again.";
            }
        } else {
            $_SESSION['error'] = "This reservation cannot be cancelled at this stage.";
        }
    } else {
        $_SESSION['error'] = "Unauthorized access or reservation not found.";
    }
} else {
    $_SESSION['error'] = "Invalid request method.";
}

header("Location: profile.php");
exit;
