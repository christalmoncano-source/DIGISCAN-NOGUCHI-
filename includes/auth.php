<?php
// includes/auth.php - Role-Based Access Control and UI Rendering
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Enforce role-based access control
 */
function checkAccess($allowed_roles) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: /DIGISCAN-NOGUCHI-/registration/login.php");
        exit();
    }

    $user_role     = $_SESSION['role'] ?? '';
    $allowed_roles = (array)$allowed_roles;

    if (!in_array($user_role, $allowed_roles)) {
        header("Location: /DIGISCAN-NOGUCHI-/access_denied.php");
        exit();
    }
}

/**
 * Render Modern Header
 */
function renderHeader($page_title = "DigiScan") {
    $current_dir = dirname($_SERVER['PHP_SELF']);
    $base_path = (strpos($current_dir, 'admin') !== false || strpos($current_dir, 'registration') !== false || strpos($current_dir, 'student') !== false) ? '../' : './';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="<?php echo $base_path; ?>index.php" class="logo">
                <i class="fas fa-book-open" style="margin-right: 10px;"></i> DigiScan
            </a>
            <div class="nav-toggle" id="mobile-menu">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </div>
            <ul class="nav-menu">
                <li><a href="<?php echo $base_path; ?>index.php#home" class="nav-link">Home</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li>
                        <a href="<?php echo ($_SESSION['role'] === 'admin') ? $base_path.'admin/dashboard.php' : $base_path.'student/dashboard.php'; ?>" class="nav-link">
                            Dashboard
                        </a>
                    </li>
                    <li style="margin-left: 1rem;">
                        <a href="<?php echo $base_path; ?>student/profile.php" style="text-decoration: none;">
                            <span class="user-pill">
                                <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                            </span>
                        </a>
                    </li>
                    <li><a href="<?php echo $base_path; ?>registration/logout.php" class="nav-link nav-btn logout-btn">Logout</a></li>
                <?php else: ?>
                    <li><a href="<?php echo $base_path; ?>index.php#about" class="nav-link">About</a></li>
                    <li><a href="<?php echo $base_path; ?>registration/login.php" class="nav-link">Login</a></li>
                    <li><a href="<?php echo $base_path; ?>registration/register.php" class="nav-link nav-btn">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
    <main>
    <?php
}

/**
 * Retrieve system settings
 */
function getSystemSettings($conn) {
    $res = $conn->query("SELECT * FROM system_settings");
    $settings = [];
    while($row = $res->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

/**
 * Render Compact Footer
 */
function renderFooter() {
    $year = date('Y');
    
    // We need a DB connection to check security settings
    // Since this is a global function, we'll try to use the global $conn if it exists
    global $conn;
    $download_prevention = 'enabled'; // Default
    if (isset($conn)) {
        $res = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'download_prevention'");
        if ($res && $row = $res->fetch_assoc()) {
            $download_prevention = $row['setting_value'];
        }
    }
    ?>
    </main>
    <footer class="footer">
        <div class="footer-inner">
            <span class="footer-brand">
                <i class="fas fa-book-open"></i>
                <strong>DigiScan</strong>
            </span>
            <span class="footer-sep">|</span>
            <span class="footer-sys">Digital Scanned Book Management System</span>
            <span class="footer-sep footer-hide-sm">|</span>
            <span class="footer-copy footer-hide-sm">NOGUCHI LIBRARY &copy; <?php echo $year; ?></span>
        </div>
    </footer>
    <script>
        <?php if ($download_prevention === 'enabled'): ?>
        // Download Prevention Logic
        document.addEventListener('contextmenu', e => e.preventDefault());
        document.addEventListener('keydown', e => {
            // Block Ctrl+S, Ctrl+P, Ctrl+U
            if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'p' || e.key === 'u' || e.key === 'i')) {
                e.preventDefault();
                alert('Downloading and printing is restricted by institutional policy.');
            }
        });
        <?php endif; ?>

        const mobileMenu = document.querySelector('#mobile-menu');
        const navMenu    = document.querySelector('.nav-menu');
        if (mobileMenu) {
            mobileMenu.addEventListener('click', () => {
                mobileMenu.classList.toggle('is-active');
                navMenu.classList.toggle('active');
            });
        }
    </script>
</body>
</html>
    <?php
}
?>
