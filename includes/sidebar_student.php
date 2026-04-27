<?php
/**
 * Student Sidebar - Restored to match institutional images
 * Usage: include '../includes/sidebar_student.php';
 * Expects: $active_page (e.g., 'dashboard', 'catalog', 'profile')
 */
$active_page = $active_page ?? '';
$full_name = $_SESSION['full_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'Student';
$initial = strtoupper(substr($full_name, 0, 1));
$base_path = (strpos(dirname($_SERVER['PHP_SELF']), 'student') !== false || strpos(dirname($_SERVER['PHP_SELF']), 'admin') !== false) ? '../' : './';
?>

<style>
/* ── Sidebar Style ── */
.sidebar { 
    background-color: #1e293b; 
    color: white; 
    padding: 2rem 1.5rem; 
    display: flex; 
    flex-direction: column; 
    gap: 2rem;
    position: sticky;
    top: 0;
    z-index: 1050;
    height: 100vh;
    width: 280px;
    flex-shrink: 0;
    text-align: left;
}
.sidebar-header { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; }
.sidebar-logo { width: 40px; height: 40px; object-fit: contain; }
.sidebar-brand h4 { margin: 0; font-size: 1.25rem; font-weight: 700; color: #f8fafc; }
.sidebar-brand p { margin: 0; font-size: 0.9rem; color: #94a3b8; }

.user-block { 
    background: rgba(255,255,255,0.05); 
    border: 1px solid rgba(255,255,255,0.1); 
    border-radius: 12px; 
    padding: 1rem; 
    display: flex; 
    align-items: center; 
    gap: 0.75rem; 
}
.avatar-circle { 
    width: 44px; 
    height: 44px; 
    border-radius: 50%; 
    background: #6366f1; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    font-weight: 700; 
    font-size: 1.1rem;
    color: white;
}
.user-info h5 { margin: 0; font-size: 1.05rem; color: white; }
.user-info span { font-size: 0.85rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }

.nav-section { display: flex; flex-direction: column; gap: 0.5rem; }
.nav-label { font-size: 0.85rem; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.5rem; font-weight: 700; }
.nav-item { 
    display: flex; 
    align-items: center; 
    gap: 0.75rem; 
    padding: 0.85rem 1rem; 
    color: #94a3b8; 
    text-decoration: none; 
    border-radius: 10px; 
    font-weight: 500; 
    transition: 0.3s; 
}
.nav-item i { font-size: 1.1rem; width: 20px; text-align: center; }
.nav-item:hover { background: rgba(99, 102, 241, 0.1); color: #818cf8; }
.nav-item.active { background: #312e81; color: white; }

.sidebar-footer { margin-top: auto; }

@media (max-width: 992px) {
    .sidebar {
        position: relative;
        height: auto;
        padding: 1.5rem;
        flex-direction: row;
        flex-wrap: wrap;
        width: 100%;
        justify-content: space-between;
        align-items: center;
    }
    .user-block { display: none; }
    .nav-section { flex-direction: row; flex-wrap: wrap; width: 100%; justify-content: center; }
    .nav-label { display: none; }
    .sidebar-footer { margin-top: 0; }
}
@media (max-width: 600px) {
    .nav-section {
        gap: 0.25rem;
    }
    .nav-item {
        padding: 0.5rem;
        font-size: 0.85rem;
    }
}
</style>

<aside class="sidebar">
    <div class="sidebar-header">
        <img src="<?php echo $base_path; ?>assets/img/library-logo.png" alt="Logo" class="sidebar-logo">
        <div class="sidebar-brand">
            <h4>Noguchi Library</h4>
            <p><?php echo $role === 'admin' ? 'Admin Panel' : 'Student Portal'; ?></p>
        </div>
    </div>

    <div class="user-block">
        <div class="avatar-circle"><?php echo $initial; ?></div>
        <div class="user-info">
            <h5><?php echo htmlspecialchars($full_name); ?></h5>
            <span><?php echo strtoupper($role); ?></span>
        </div>
    </div>

    <nav class="nav-section">
        <span class="nav-label"><?php echo $role === 'admin' ? 'Management' : 'My Library'; ?></span>
        <?php if ($role === 'admin'): ?>
            <a href="dashboard.php" class="nav-item <?php echo $active_page === 'dashboard' ? 'active' : ''; ?>"><i class="fas fa-chart-line"></i> Overview</a>
            <a href="?page=catalog" class="nav-item <?php echo $active_page === 'catalog' ? 'active' : ''; ?>"><i class="fas fa-book"></i> Inventory</a>
            <a href="?page=users" class="nav-item <?php echo $active_page === 'users' ? 'active' : ''; ?>"><i class="fas fa-users"></i> User Center</a>
        <?php else: ?>
            <a href="dashboard.php" class="nav-item <?php echo $active_page === 'dashboard' ? 'active' : ''; ?>"><i class="fas fa-home"></i> Dashboard</a>
            <a href="catalog.php" class="nav-item <?php echo $active_page === 'catalog' ? 'active' : ''; ?>"><i class="fas fa-search"></i> Browse Catalog</a>
            <a href="profile.php" class="nav-item <?php echo $active_page === 'profile' ? 'active' : ''; ?>"><i class="fas fa-user-circle"></i> My Profile</a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="<?php echo $base_path; ?>registration/logout.php" class="nav-item" style="color: #fca5a5;"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
    </div>
</aside>
