<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

$message = "";
$messageType = "";

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Check if token exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE verification_token = ? AND is_active = 0");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];
        
        // Update user to active
        $update = $conn->prepare("UPDATE users SET is_active = 1, verification_token = NULL WHERE id = ?");
        $update->bind_param("i", $user_id);
        
        if ($update->execute()) {
            $message = "Account verified successfully! You can now login.";
            $messageType = "success";
        } else {
            $message = "Database error. Please try again later.";
            $messageType = "danger";
        }
    } else {
        $message = "Invalid or expired verification link.";
        $messageType = "danger";
    }
} else {
    header("Location: login.php");
    exit();
}

renderHeader("Account Verification - DigiScan");
?>

<div class="flex-center-wrapper">
    <div class="container" style="text-align: center;">
        <i class="fas <?php echo ($messageType == 'success') ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>" 
           style="font-size: 4rem; color: <?php echo ($messageType == 'success') ? 'var(--success-color)' : 'var(--error-color)'; ?>; margin-bottom: 2rem;"></i>
        
        <h2 style="margin-bottom: 1rem;"><?php echo ($messageType == 'success') ? "Verified!" : "Verification Failed"; ?></h2>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo $message; ?>
        </div>
        
        <div style="margin-top: 2rem;">
            <a href="login.php" class="btn btn-primary">Proceed to Login</a>
        </div>
    </div>
</div>

<?php renderFooter(); ?>
