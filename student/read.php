<?php
// student/read.php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/borrowing_handler.php';

checkAccess(['student', 'admin']);

$book_id = $_GET['id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$book_id) {
    header("Location: dashboard.php");
    exit();
}

// DEPRECATED: Borrowing check removed for full digital access
/*
if (!validateAssetAccess($conn, $user_id, (int)$book_id)) {
    header("Location: details.php?id=$book_id&error=expired");
    exit();
}
*/

// Fetch book details for display
$res = $conn->query("SELECT * FROM books WHERE id = " . (int)$book_id);
$b = $res->fetch_assoc();

// Log reading activity
$conn->query("INSERT INTO reading_logs (user_id, book_id, chapter_accessed) VALUES ($user_id, $book_id, 'Cover to Finish')");

// Fetch system settings for enforcement
$settings = getSystemSettings($conn);
$download_prevention = $settings['download_prevention'] ?? 'enabled';
$max_pages = $settings['max_preview_pages'] ?? '10';

renderHeader("Reading: " . htmlspecialchars($b['title']));
?>

<div class="container-wide section" style="max-width: 1200px; margin: 0 auto; padding: 0 24px;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1 style="margin: 0;"><?php echo htmlspecialchars($b['title']); ?></h1>
            <p style="color: var(--text-light); margin: 0;">Institutional Digital Resource — <?php echo $max_pages; ?> Page Preview Policy Active</p>
        </div>
        <a href="dashboard.php" class="btn btn-secondary">Exit Reader</a>
    </div>

    <!-- Security Overlay if Download Prevention is enabled -->
    <div class="card" style="padding: 0; background: #525659; overflow: hidden; height: 85vh; border-radius: 12px; box-shadow: var(--shadow-lg); position: relative;">
        <?php if ($b['file_path']): ?>
            <?php if ($download_prevention === 'enabled'): ?>
                <div style="position: absolute; top: 0; left: 0; width: 100%; height: 50px; background: rgba(0,0,0,0.5); color: white; display: flex; align-items: center; justify-content: center; z-index: 10; font-size: 0.8rem; pointer-events: none;">
                    <i class="fas fa-lock" style="margin-right: 10px;"></i> SECURE PREVIEW MODE ACTIVE — Unauthorized duplication is prohibited.
                </div>
            <?php endif; ?>
            
            <iframe id="pdf-reader" src="../<?php echo $b['file_path']; ?>#toolbar=0&navpanes=0&scrollbar=0" style="width: 100%; height: 100%; border: none;"></iframe>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: white; padding: 3rem; text-align: center;">
                <i class="fas fa-file-excel" style="font-size: 5rem; margin-bottom: 2rem; opacity: 0.5;"></i>
                <h2>Digital Asset Missing</h2>
                <p>The scanned source for this literature has not been provisioned by the administrator.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
<?php if ($download_prevention === 'enabled'): ?>
// Additional layer of security: Disable copy/paste and shortcuts inside reader
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && (e.key === 'c' || e.key === 'u' || e.key === 's' || e.key === 'p')) {
        e.preventDefault();
        alert('Action restricted for digital asset protection.');
    }
});
document.addEventListener('contextmenu', e => e.preventDefault());
<?php endif; ?>

// Page Limitation Logic (Soft Enforcement)
const maxPages = <?php echo (int)$max_pages; ?>;
console.log('Institutional Policy: Limited to ' + maxPages + ' pages.');
</script>

<?php renderFooter(); ?>
