<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/notifications_helper.php';

checkAccess(['student', 'admin']);

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'User';
$first_name = explode(' ', $full_name)[0];

renderHeaderNoNav("Library Dashboard - Noguchi Library");
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

        <div class="vm-grid">
            <div class="card-small">
                <div class="icon-box icon-purple"><i class="fas fa-eye"></i></div>
                <h3>Our Vision</h3>
                <p>To preserve, conserve, promote and strengthen Filipiniana collections in the region and in the Philippines.</p>
            </div>

            <div class="card-small">
                <div class="icon-box icon-green"><i class="fas fa-rocket"></i></div>
                <h3>Our Mission</h3>
                <p>To preserve, conserve, promote and strengthen Filipiniana collections in the region and in the Philippines.</p>
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
