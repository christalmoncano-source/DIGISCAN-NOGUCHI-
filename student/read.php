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

$res = $conn->query("SELECT * FROM books WHERE id = " . (int)$book_id);
$b = $res->fetch_assoc();

$conn->query("INSERT INTO reading_logs (user_id, book_id, chapter_accessed) VALUES ($user_id, $book_id, 'Cover to Finish')");

$settings = getSystemSettings($conn);
$download_prevention = $settings['download_prevention'] ?? 'enabled';
$max_pages = $settings['max_preview_pages'] ?? '10';

renderHeaderNoNav("Reading: " . htmlspecialchars($b['title']));
?>
<link rel="stylesheet" href="../assets/css/student.css">
<link rel="stylesheet" href="../assets/css/heyzine-viewer.css">

<div class="dash-wrap">
    <?php $active_page = 'catalog'; include '../includes/sidebar_student.php'; ?>

    <main class="sb-main">
        <div class="sb-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 850; color: #0f172a;"><?php echo htmlspecialchars($b['title']); ?></h1>
                    <p style="color: #64748b; margin: 0.5rem 0 0 0;">Institutional Digital Resource — Coverage: Full Content Scan</p>
                </div>
                <a href="details.php?id=<?php echo $book_id; ?>" class="btn btn-secondary">Exit Reader</a>
            </div>

            <!-- Security Overlay if Download Prevention is enabled -->
            <div class="card" style="padding: 0; background: #525659; overflow: hidden; height: 80vh; border-radius: 20px; box-shadow: 0 20px 50px rgba(0,0,0,0.1); position: relative; border: none;">
                <?php if (!empty($b['heyzine_url'])): ?>
                    <div class="flipbook-wrapper" style="max-width: 100%; margin: 0; height: 100%; border-radius: 0; padding: 0;">
                        <style>.flipbook-container { height: 100%; aspect-ratio: auto; }</style>
                        <div class="flipbook-container">
                            <div class="flipbook-loading"><i class="fas fa-spinner fa-spin"></i> Loading Flipbook...</div>
                            <iframe src="<?php echo htmlspecialchars($b['heyzine_url']); ?>" class="heyzine-iframe" allowfullscreen allow="clipboard-write"></iframe>
                        </div>
                    </div>
                <?php elseif ($b['file_path']): ?>
                    <?php if ($download_prevention === 'enabled'): ?>
                        <div style="position: absolute; top: 0; left: 0; width: 100%; height: 50px; background: rgba(15,23,42,0.8); color: white; display: flex; align-items: center; justify-content: center; z-index: 10; font-size: 0.75rem; pointer-events: none; backdrop-filter: blur(4px); font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
                            <i class="fas fa-lock" style="margin-right: 12px; color: #fbbf24;"></i> Secure Preview Mode Active
                        </div>
                    <?php endif; ?>
                    
                    <iframe id="pdf-reader" src="../<?php echo $b['file_path']; ?>#toolbar=0&navpanes=0&scrollbar=0" style="width: 100%; height: 100%; border: none;"></iframe>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: white; padding: 3rem; text-align: center;">
                        <i class="fas fa-file-excel" style="font-size: 5rem; margin-bottom: 2rem; opacity: 0.5;"></i>
                        <h2 style="font-weight: 800;">Digital Asset Missing</h2>
                        <p style="color: #cbd5e1;">The scanned source for this literature has not been provisioned by the administrator.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
<?php if ($download_prevention === 'enabled'): ?>
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && (e.key === 'c' || e.key === 'u' || e.key === 's' || e.key === 'p')) {
        e.preventDefault();
        alert('Action restricted for digital asset protection.');
    }
});
document.addEventListener('contextmenu', e => e.preventDefault());
<?php endif; ?>
</script>

<?php renderFooter(); ?>
