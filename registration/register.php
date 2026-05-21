<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
global $conn;



$message = "";
$messageType = "";
$registration_success = false;
$user_data = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST['full_name']);
    $course = trim($_POST['course']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role_id = 2; // Hardcoded to Student (ID: 2) for all new registrations

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/@urios\.edu\.ph$/i', $email)) {
        $message = "Invalid institutional email. Must end with @urios.edu.ph";
        $messageType = "danger";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $message = "This email is already registered.";
            $messageType = "danger";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $token = bin2hex(random_bytes(16)); // Verification token
            
            $stmt = $conn->prepare("INSERT INTO users (full_name, course, email, password, role_id, is_active, verification_token) VALUES (?, ?, ?, ?, ?, 0, ?)");
            $stmt->bind_param("ssssis", $full_name, $course, $email, $hashed, $role_id, $token);
            
            if ($stmt->execute()) {
                $registration_success = true;
                $user_data = [
                    'full_name' => $full_name,
                    'email' => $email,
                    'token' => $token
                ];
                $message = "Registration successful! Check your email for verification.";
                $messageType = "success";
            } else {
                $message = "Critical Error:" . $conn->error;
                $messageType = "danger";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - DigiScan</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- EmailJS SDK -->
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/@emailjs/browser@3/dist/email.min.js"></script>
</head>
<body class="flex-center-wrapper">
    <div class="container">
        <h2>Create Account</h2>
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if (!$registration_success): ?>
        <form method="POST" id="regForm">
            <div class="form-group"><label>Full Name</label><input type="text" name="full_name" placeholder="John Doe" required></div>
            <div class="form-group"><label>Course/Dept</label><input type="text" name="course" placeholder="BSIT" required></div>
            <div class="form-group"><label>Email (@urios.edu.ph)</label><input type="email" name="email" id="userEmail" required></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" placeholder="••••••••" required></div>
            <button type="submit" id="submitBtn">Register</button>
        </form>
        <?php else: ?>
            <div style="text-align: center; margin-top: 2rem;">
                <a href="login.php" class="btn btn-primary" style="display: inline-block; padding: 0.8rem 2rem; background: var(--primary-color); color: white; text-decoration: none; border-radius: 8px; font-weight: 700;">Proceed to Login</a>
            </div>
        <?php endif; ?>
        
        <p style="text-align: center; margin-top: 1.5rem;">
            Already have an account? <a href="login.php" style="color: var(--primary-color);">Login</a>
        </p>
    </div>

    <script>
        // Initialize EmailJS
        (function() {
            emailjs.init("q7eEuAmQHbGa3K69A"); 
        })();

        <?php if ($registration_success): ?>
            // Send Verification Email
            const templateParams = {
                to_name: "<?php echo addslashes($user_data['full_name']); ?>",
                to_email: "<?php echo $user_data['email']; ?>",
                verification_link: window.location.origin + window.location.pathname.replace('register.php', 'verify.php') + "?token=<?php echo $user_data['token']; ?>"
            };

            emailjs.send('service_c5th9vf', 'template_f8s3rka', templateParams)
                .then(function(response) {
                    console.log('SUCCESS!', response.status, response.text);
                    alert("Verification email sent to <?php echo $user_data['email']; ?>");
                }, function(error) {
                    console.log('FAILED...', error);
                    alert("EmailJS Error: " + error.text + " (Status: " + error.status + ")");
                });
        <?php endif; ?>
    </script>
</body>
</html>
