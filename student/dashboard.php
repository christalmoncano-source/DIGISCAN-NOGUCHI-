<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/notifications_helper.php';
global $conn;


checkAccess(['student', 'admin']);

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'User';
$first_name = explode(' ', $full_name)[0];

renderHeaderNoNav("Library Overview - Noguchi Library");


// Fetch Student Stats
$stats_read = 0;
$stats_res  = 0;
$stats_notif = 0;

if ($conn) {
    $res_read = $conn->query("SELECT COUNT(*) FROM reading_history WHERE user_id = $user_id");
    if ($res_read) $stats_read = $res_read->fetch_row()[0];

    $res_res = $conn->query("SELECT COUNT(*) FROM reservations WHERE user_id = $user_id AND status IN ('pending', 'approved')");
    if ($res_res) $stats_res = $res_res->fetch_row()[0];

    $res_notif = $conn->query("SELECT COUNT(*) FROM notifications WHERE user_id = $user_id AND is_read = 0");
    if ($res_notif) $stats_notif = $res_notif->fetch_row()[0];
}

?>

<link rel="stylesheet" href="../assets/css/dashboard.css">

<div class="dash-wrap">
    <?php 
    $active_page = 'dashboard';
    include '../includes/sidebar_student.php'; 
    ?>

    <main class="sb-main">
        <div class="sb-container">
        <div class="welcome-banner">
            <h1>Welcome back, <?php echo htmlspecialchars($first_name); ?>.</h1>
            <p>Your institutional library. Search, discover, and access digital academic resources.</p>
        </div>

        <!-- Dynamic Summary Grid -->
        <div class="summary-grid">
            <div class="summary-card">
                <div class="s-icon s-blue"><i class="fas fa-book-open"></i></div>
                <div class="s-info">
                    <span class="s-label">Books Read</span>
                    <h2 class="s-value"><?php echo number_format($stats_read); ?></h2>
                </div>
            </div>
            <div class="summary-card">
                <div class="s-icon s-orange"><i class="fas fa-bookmark"></i></div>
                <div class="s-info">
                    <span class="s-label">Active Reservations</span>
                    <h2 class="s-value"><?php echo number_format($stats_res); ?></h2>
                </div>
            </div>
            <div class="summary-card">
                <div class="s-icon s-indigo"><i class="fas fa-bell"></i></div>
                <div class="s-info">
                    <span class="s-label">New Notifications</span>
                    <h2 class="s-value"><?php echo number_format($stats_notif); ?></h2>
                </div>
            </div>
        </div>

        <div class="vm-grid">
            <div class="card-small">
                <div class="icon-box icon-purple"><i class="fas fa-eye"></i></div>
                <h3>Our Vision</h3>
                <p>To preserve, conserve, promote and strengthen Filipiniana collections in the region and in the Philippines.</p>
            </div>

            <div class="card-small">
                <div class="icon-box icon-green"><i class="fas fa-rocket"></i></div>
                <h3>Our Mission</h3>
                <p>Father Saturnino Urios University (FSUU) Noguchi Library exists to preserve and promote local knowledge in the region through instruction and research.</p>
            </div>
        </div>

        <div class="history-section">
            <div class="history-title-wrap">
                <h2 class="history-title">History of Noguchi Library</h2>
                <div class="history-accent">
                    <div class="accent-line"></div>
                    <div class="accent-dot"></div>
                </div>
            </div>

            <div style="display: flex; gap: 4rem; align-items: stretch; flex-wrap: wrap;">
                <div style="flex: 1.2; min-width: 300px;">
                    <p class="history-text">The Library was named after Prof. Takashi Noguchi, a Japanese national, who was a researcher of the Saitama Association and a retired faculty of Teiko University. He was born on June 19, 1931 at Urawa City, Saitama Prefecture. Prof. Noguchi has been writing scholarly books and Journals throughout his academic career, and he has done studies about the Philippines. One of them is entitled “A study on the changes of the local administration and finances in ‘a turning point’ of the Philippines”. With the vast amount of information he has collected, he has made it his mission to share it with the younger generation. He donated his Filipiniana books and other library materials to FSUU where he has done his research. He believed that FSUU would be able to preserve the collection well and promote local information by marketing it to the students. Further, FSUU that is well equipped to encourage researchers to learn the history of the Philippines.</p>
                    
                    <p class="history-text">The Noguchi Library was officially opened on June 6, 2013. Its collections range from locally published books, political and historical topics of the Philippines, to handbooks and manuals about Japanese culture and language. It also included rare books such as Documentary Sources of Philippine History by Gregorio F. Zaide and the Philippine Islands by Emma Blair and James Alexander Robertson.</p>
                    
                    <p class="history-text">At present, the Noguchi has a total of 2379 titles and 3766 volumes and continues to grow with yearly donations from Prof. Noguchi, to provide information to clients within and outside the university.</p>
                </div>
                
                <div style="flex: 1; min-width: 350px;">
                    <div class="history-img-card" onclick="openImageModal('../assets/img/noguchi_award.jpg')">
                        <div class="img-wrap">
                            <div class="img-badge"><i class="fas fa-image"></i> Historical Photo</div>
                            <img src="../assets/img/noguchi_award.jpg" alt="Prof. Takashi Noguchi receiving Medal of Merit">
                        </div>
                        <div class="quote-wrap">
                            <div class="quote-icon"><i class="fas fa-quote-left"></i></div>
                            <p class="quote-text">Presented the Medal of Merit to Prof. Takashi Noguchi whose collection of Filipiniana forms the core of the Noguchi Library at FSUU.</p>
                        </div>
                    </div>
                    
                    <div class="image-stats">
                        <div class="stat-item">
                            <div class="stat-icon"><i class="fas fa-book"></i></div>
                            <div class="stat-number">2,379</div>
                            <div class="stat-label">Titles</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
                            <div class="stat-number">3,766</div>
                            <div class="stat-label">Volumes</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon"><i class="fas fa-hand-holding-heart"></i></div>
                            <div class="stat-number">250+</div>
                            <div class="stat-label">Donations</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon"><i class="fas fa-users"></i></div>
                            <div class="stat-number">12k</div>
                            <div class="stat-label">Visitors</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity Section -->
        <div style="margin-top: 4rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: #1e293b; margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fas fa-history" style="color: #6366f1;"></i> Recently Viewed
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem;">
                <?php
                $recent_q = $conn->query("SELECT rh.*, b.title, b.author, b.cover_image FROM reading_history rh JOIN books b ON rh.book_id = b.id WHERE rh.user_id = $user_id ORDER BY rh.viewed_at DESC LIMIT 4");
                if ($recent_q && $recent_q->num_rows > 0):

                    while($r = $recent_q->fetch_assoc()):
                ?>
                    <a href="details.php?id=<?php echo $r['book_id']; ?>" style="text-decoration: none; color: inherit;">
                        <div style="background: white; border-radius: 16px; padding: 1.25rem; border: 1px solid #f1f5f9; display: flex; gap: 1rem; align-items: center; transition: transform 0.2s; cursor: pointer;" onmouseover="this.style.transform='translateX(5px)'" onmouseout="this.style.transform='translateX(0)'">
                            <div style="width: 50px; height: 70px; border-radius: 4px; overflow: hidden; flex-shrink: 0;">
                                <img src="../<?php echo $r['cover_image'] ?: 'assets/img/book-placeholder.jpg'; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            </div>
                            <div style="overflow: hidden;">
                                <h4 style="margin: 0; font-size: 0.95rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($r['title']); ?></h4>
                                <p style="margin: 0.25rem 0 0; font-size: 0.8rem; color: #64748b;"><?php echo htmlspecialchars($r['author']); ?></p>
                                <p style="margin: 0.5rem 0 0; font-size: 0.7rem; color: #94a3b8;"><?php echo date('M d, H:i', strtotime($r['viewed_at'])); ?></p>
                            </div>
                        </div>
                    </a>
                <?php endwhile; else: ?>
                    <p style="grid-column: 1/-1; color: #94a3b8; font-style: italic;">No recent activities found.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Image Zoom Modal -->
<div id="imageModal" class="img-modal" onclick="closeImageModal()">
    <span class="close-modal" onclick="closeImageModal()">&times;</span>
    <img class="img-modal-content" id="modalImage">
</div>

<script>
function openImageModal(src) {
    document.getElementById('imageModal').style.display = "flex";
    document.getElementById('modalImage').src = src;
}
function closeImageModal() {
    document.getElementById('imageModal').style.display = "none";
}
</script>

</body>
</html>
