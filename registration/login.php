<?php
session_start();
require_once '../config/db.php';

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $message = "Please enter both email and password.";
        $messageType = "danger";
    } else {
        $sql = "SELECT u.id, u.full_name, u.password, u.is_active, r.role_name 
                FROM users u 
                JOIN roles r ON u.role_id = r.id 
                WHERE u.email = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                if ($user['is_active'] == 1) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role_name'];

                    if ($user['role_name'] === 'admin') {
                        header("Location: ../admin/dashboard.php");
                    } else {
                        header("Location: ../student/dashboard.php");
                    }
                    exit();
                } else {
                    $message = "Your account is not active. Please contact admin.";
                    $messageType = "warning";
                }
            } else {
                $message = "Invalid password.";
                $messageType = "danger";
            }
        } else {
            $message = "No account found with that email.";
            $messageType = "danger";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - DigiScan</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="flex-center-wrapper">
    <div class="container">
        <h2>Login</h2>
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit">Login</button>
        </form>
        <p style="text-align: center; margin-top: 1.5rem;">
            Don't have an account? <a href="register.php" style="color: var(--primary-color);">Register</a>
        </p>
    </div>
</body>
</html>
