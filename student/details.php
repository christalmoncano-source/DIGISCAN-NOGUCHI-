<?php
/**
 * Noguchi Library - Book Details & Access Page
 */
require_once '../includes/auth.php';
require_once '../config/db.php';
checkAccess(['student', 'admin']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

function getBookMetadata($conn, $id) {
    if (!$id) return null;
    $st = $conn->prepare("SELECT * FROM books WHERE id = ?");
    $st->bind_param("i", $id);
    $st->execute();
    return $st->get_result()->fetch_assoc();
}

$b = getBookMetadata($conn, $id);
if (!$b) {
    header("Location: catalog.php?error=notfound");
    exit();
}

// Suggested Books Logic (Same Category)
$suggested = [];
$cat = $b['category'];
$s_res = $conn->query("SELECT * FROM books WHERE category = '$cat' AND id != $id LIMIT 4");
while($s = $s_res->fetch_assoc()) {
    $suggested[] = $s;
}

// Image Resolution
$ci_raw = $b['cover_image'] ?? '';
$ci_decoded = json_decode($ci_raw, true);
$ci_first = is_array($ci_decoded) && !empty($ci_decoded) ? $ci_decoded[0] : ((!empty($ci_raw) && $ci_raw[0] !== '[') ? $ci_raw : '');
$ci_src = !empty($ci_first) ? '../'.$ci_first : '../assets/img/library-logo.png';

// Preview Pages
$pp_raw = $b['preview_pages'] ?? '';
$pp_dec = json_decode($pp_raw, true) ?: [];

// Combine Cover with Preview Pages for the Flipbook
$all_pages = [$ci_src];
foreach($pp_dec as $p) {
    $all_pages[] = '../' . $p;
}

// Reservation check
$uid = $_SESSION['user_id'];
$current_res = null;
$res_st = $conn->prepare("SELECT status, pickup_by FROM reservations WHERE user_id = ? AND book_id = ? AND status IN ('pending', 'approved', 'in_use')");
$res_st->bind_param("ii", $uid, $b['id']);
$res_st->execute();
$current_res = $res_st->get_result()->fetch_assoc();

renderHeaderNoNav(htmlspecialchars($b['title']) . " - Noguchi Library");
?>
<link rel="stylesheet" href="../assets/css/student.css">
<link rel="stylesheet" href="../assets/css/flipbook.css">
<link rel="stylesheet" href="../assets/css/heyzine-viewer.css">

<div class="dash-wrap">
    <?php $active_page = 'catalog'; include '../includes/sidebar_student.php'; ?>

    <main class="sb-main">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <a href="catalog.php" class="back-link" style="display: inline-flex; align-items: center; gap: 0.5rem; color: #64748b; text-decoration: none; font-weight: 600; transition: 0.3s;" onmouseover="this.style.color='#6366f1'" onmouseout="this.style.color='#64748b'">
                <i class="fas fa-arrow-left"></i> Back to Catalog
            </a>
            
            <div style="display: flex; gap: 0.75rem;">
                <?php if (!$current_res): ?>
                    <button id="reserve-btn" class="btn btn-primary" <?php echo $b['available_copies'] <= 0 ? 'disabled' : ''; ?> style="padding: 0.6rem 1.5rem; font-size: 0.85rem;">
                        <i class="fas fa-bookmark"></i> <?php echo $b['available_copies'] > 0 ? 'Reserve Physical Copy' : 'Out of Stock'; ?>
                    </button>
                <?php else: ?>
                    <span style="padding: 0.6rem 1.5rem; background: #dcfce7; color: #166534; border-radius: 10px; font-size: 0.85rem; font-weight: 700; border: 1px solid #bbf7d0;">
                        <i class="fas fa-calendar-check"></i> Reservation <?php echo ucfirst($current_res['status']); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="sb-container">
            <!-- Header: Title & Author ABOVE Flipbook -->
            <header style="text-align: center; margin-bottom: 3rem;">
                <div style="display: flex; justify-content: center; gap: 0.75rem; align-items: center; margin-bottom: 1rem;">
                    <span class="meta-tag"><?php echo htmlspecialchars($b['category']); ?></span>
                    <span style="background: #6366f1; color: white; padding: 4px 12px; border-radius: 8px; font-size: 0.75rem; font-weight: 800;">
                        DEWEY: <?php echo htmlspecialchars($b['dewey_decimal'] ?: 'N/A'); ?>
                    </span>
                </div>
                <h1 style="font-size: 3rem; font-weight: 850; color: #0f172a; margin: 0; line-height: 1.1;"><?php echo htmlspecialchars($b['title']); ?></h1>
                <p style="font-size: 1.25rem; color: #64748b; margin-top: 1rem; font-weight: 500;">By <?php echo htmlspecialchars($b['author']); ?></p>
            </header>

            <!-- Flipbook Section: Focused & Centered -->
            <div class="flipbook-wrapper">
                <?php if (!empty($b['heyzine_url'])): ?>
                    <div class="flipbook-container">
                        <div class="flipbook-loading"><i class="fas fa-spinner fa-spin"></i> Loading Flipbook...</div>
                        <iframe src="<?php echo htmlspecialchars($b['heyzine_url']); ?>" class="heyzine-iframe" allowfullscreen allow="clipboard-write"></iframe>
                    </div>
                <?php else: ?>
                    <div class="fb-wrap">
                        <div id="flipbook" class="fb-book is-cover">
                            <div class="fb-spine"></div>
                            <div class="fb-panel fb-panel-left"><div id="fb-left" class="fb-page-img"></div></div>
                            <div class="fb-panel fb-panel-right">
                                <div id="fb-right" class="fb-page-img"></div>
                                <div id="fb-end" class="fb-end-card">
                                    <div class="fb-end-inner">
                                        <i class="fas fa-lock" style="font-size: 3rem; color: #f59e0b; margin-bottom: 1.5rem; display: block;"></i>
                                        <h2 style="font-size: 2rem; font-weight: 850; color: white; font-style: italic; margin-bottom: 0.5rem;">Limited Preview</h2>
                                        <p style="color: #94a3b8; font-size: 0.9rem; margin-bottom: 2rem;">Institutional Access Policy Active</p>
                                        
                                        <div style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); padding: 1.5rem; border-radius: 12px; font-size: 0.95rem; line-height: 1.6; color: #e2e8f0; max-width: 320px; margin: 0 auto;">
                                            Please visit the <strong>Noguchi Library</strong> to access the full physical copy of this material.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="fb-flip-wrap" id="fb-flip-wrap">
                                <div class="fb-flip-page" id="fb-flip-page">
                                    <div class="fb-flip-front" id="fb-flip-front"></div>
                                    <div class="fb-flip-back" id="fb-flip-back"></div>
                                    <div class="fb-flip-shadow" id="fb-flip-shadow"></div>
                                </div>
                            </div>
                            <button id="fb-prev" class="fb-nav"><i class="fas fa-chevron-left"></i></button>
                            <button id="fb-next" class="fb-nav"><i class="fas fa-chevron-right"></i></button>
                        </div>
                        <div class="fb-info">
                            <div class="fb-pill"><span id="fb-page-num">1</span> / <span id="fb-total-pages">1</span></div>
                            <div class="fb-badge-pill"><i class="fas fa-shield-halved"></i> Institutional Preview</div>
                            <div class="fb-hint-pill"><i class="fas fa-keyboard"></i> Use arrows to flip</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Details Section: BELOW Flipbook - Centered for Readability -->
            <div style="max-width: 850px; margin: 4rem auto 0; text-align: center;">
                <div class="book-main-info" style="margin-bottom: 4rem;">
                    <div class="info-section">
                        <h3 style="font-size: 1.25rem; font-weight: 850; color: #0f172a; margin-bottom: 1.5rem; text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-align-left" style="color: #6366f1; margin-right: 12px;"></i> Material Abstract
                        </h3>
                        <p style="line-height: 1.8; color: #475569; font-size: 1.15rem; text-align: justify; max-width: 800px; margin: 0 auto;">
                            <?php echo nl2br(htmlspecialchars($b['description'])); ?>
                        </p>
                    </div>
                </div>

                <div class="info-section" style="background: #f8fafc; padding: 2.5rem; border-radius: 24px; border: 1px solid #e2e8f0; text-align: left; max-width: 700px; margin: 0 auto;">
                    <h3 style="font-size: 0.9rem; font-weight: 800; color: #1e293b; margin-bottom: 2rem; text-transform: uppercase; letter-spacing: 0.05em; text-align: center;">Bibliographic Analysis</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                        <div class="info-item">
                            <label style="display: block; font-size: 0.7rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px;">ISBN ID</label>
                            <span style="font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($b['isbn'] ?: 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <label style="display: block; font-size: 0.7rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px;">Press / Publisher</label>
                            <span style="font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($b['publisher'] ?: 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <label style="display: block; font-size: 0.7rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px;">Publication Date</label>
                            <span style="font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($b['publication_date'] ?: 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <label style="display: block; font-size: 0.7rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px;">Physical Condition</label>
                            <span class="badge" style="background: #eef2ff; color: #6366f1;">
                                <?php echo $b['available_copies']; ?> Units Tracked
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Suggested Books Section -->
            <?php if (!empty($suggested)): ?>
                <div style="margin-top: 6rem;">
                    <h3 style="font-size: 1.5rem; font-weight: 850; color: #0f172a; margin-bottom: 2.5rem; text-align: left;">Suggested for You</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 2rem;">
                        <?php foreach($suggested as $sb): 
                             $s_ci_raw = $sb['cover_image'] ?? '';
                             $s_ci_dec = json_decode($s_ci_raw, true);
                             $s_ci_first = is_array($s_ci_dec) && !empty($s_ci_dec) ? $s_ci_dec[0] : ((!empty($s_ci_raw) && $s_ci_raw[0] !== '[') ? $s_ci_raw : '');
                             $s_src = !empty($s_ci_first) ? '../'.$s_ci_first : '../assets/img/library-logo.png';
                        ?>
                            <a href="details.php?id=<?php echo $sb['id']; ?>" style="text-decoration: none; color: inherit; display: block; group">
                                <div style="height: 280px; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; margin-bottom: 1rem; transition: 0.3s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 25px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                                    <img src="<?php echo htmlspecialchars($s_src); ?>" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='../assets/img/library-logo.png';">
                                </div>
                                <h4 style="margin: 0; font-size: 0.95rem; font-weight: 800; color: #1e293b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($sb['title']); ?></h4>
                                <p style="margin: 0.25rem 0 0; font-size: 0.8rem; color: #64748b;"><?php echo htmlspecialchars($sb['author']); ?></p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
    // ── FLIPBOOK ENGINE ──
    <?php if (empty($b['heyzine_url'])): ?>
    const pages = <?php echo json_encode($all_pages); ?>;
    let currentPage = 1;
    const totalPages = pages.length;

    const book = document.getElementById('flipbook');
    const leftPage = document.getElementById('fb-left');
    const rightPage = document.getElementById('fb-right');
    const endCard = document.getElementById('fb-end');
    const pageNumDisplay = document.getElementById('fb-page-num');
    const totalDisplay = document.getElementById('fb-total-pages');
    
    const prevBtn = document.getElementById('fb-prev');
    const nextBtn = document.getElementById('fb-next');
    
    const flipWrap = document.getElementById('fb-flip-wrap');
    const flipPage = document.getElementById('fb-flip-page');
    const flipFront = document.getElementById('fb-flip-front');
    const flipBack = document.getElementById('fb-flip-back');

    function updateView() {
        totalDisplay.innerText = totalPages;
        pageNumDisplay.innerText = currentPage;

        if (currentPage === 1) {
            book.classList.add('is-cover');
            leftPage.style.backgroundImage = 'none';
            rightPage.style.backgroundImage = `url('${pages[0]}')`;
            endCard.style.display = 'none';
            prevBtn.style.display = 'none';
            nextBtn.style.display = 'flex';
        } else {
            book.classList.remove('is-cover');
            prevBtn.style.display = 'flex';
            const leftIdx = (currentPage - 1) * 2 - 1;
            const rightIdx = (currentPage - 1) * 2;
            leftPage.style.backgroundImage = `url('${pages[leftIdx]}')`;
            const maxPage = Math.floor(totalPages / 2) + 2;

            if (rightIdx < totalPages) {
                rightPage.style.backgroundImage = `url('${pages[rightIdx]}')`;
                endCard.style.display = 'none';
                nextBtn.style.display = (currentPage < maxPage) ? 'flex' : 'none';
            } else if (currentPage === maxPage) {
                rightPage.style.backgroundImage = 'none';
                endCard.style.display = 'flex';
                nextBtn.style.display = 'none';
            } else {
                rightPage.style.backgroundImage = 'none';
                endCard.style.display = 'none';
                nextBtn.style.display = (currentPage < maxPage) ? 'flex' : 'none';
            }
        }
    }

    function flip(direction) {
        const maxPage = Math.floor(totalPages / 2) + 2;
        if (direction === 'next' && currentPage < maxPage) {
            const nextP = currentPage + 1;
            flipWrap.style.display = 'block';
            if (currentPage === 1) {
                flipFront.style.backgroundImage = `url('${pages[0]}')`;
                const leftIdx = 1;
                flipBack.style.backgroundImage = (leftIdx < totalPages) ? `url('${pages[leftIdx]}')` : 'none';
            } else {
                const rightIdx = (currentPage - 1) * 2;
                flipFront.style.backgroundImage = (rightIdx < totalPages) ? `url('${pages[rightIdx]}')` : 'none';
                const leftIdx = (nextP - 1) * 2 - 1;
                flipBack.style.backgroundImage = (leftIdx < totalPages) ? `url('${pages[leftIdx]}')` : 'none';
            }
            flipPage.style.transition = 'transform 0.6s ease-in-out';
            flipPage.style.transform = 'rotateY(-180deg)';
            setTimeout(() => {
                currentPage = nextP; updateView();
                flipPage.style.transition = 'none';
                flipPage.style.transform = 'rotateY(0deg)';
                flipWrap.style.display = 'none';
            }, 600);
        } else if (direction === 'prev' && currentPage > 1) {
            const prevP = currentPage - 1;
            flipWrap.style.display = 'block';
            flipPage.style.transition = 'none';
            flipPage.style.transform = 'rotateY(-180deg)';
            const leftIdx = (currentPage - 1) * 2 - 1;
            flipFront.style.backgroundImage = (leftIdx < totalPages) ? `url('${pages[leftIdx]}')` : 'none';
            const rightIdxPrev = (prevP - 1) * 2;
            flipBack.style.backgroundImage = (prevP === 1) ? `url('${pages[0]}')` : `url('${pages[rightIdxPrev]}')`;
            setTimeout(() => {
                flipPage.style.transition = 'transform 0.6s ease-in-out';
                flipPage.style.transform = 'rotateY(0deg)';
            }, 10);
            setTimeout(() => {
                currentPage = prevP; updateView();
                flipWrap.style.display = 'none';
            }, 610);
        }
    }

    nextBtn.onclick = () => flip('next');
    prevBtn.onclick = () => flip('prev');
    document.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowRight') flip('next');
        if (e.key === 'ArrowLeft') flip('prev');
    });

    updateView();
    <?php endif; ?>

    // Reservation AJAX
    const rb = document.getElementById('reserve-btn');
    if(rb){
        rb.onclick = function(){
            const policyMessage = "Reservation Policy Notice:\n\nPlease be informed that once you reserve a book, it will be held for 3 days. If you do not physically visit the Noguchi Library within this period, your reservation will be automatically removed.\n\nDo you want to proceed with your reservation?";
            if (!confirm(policyMessage)) {
                return;
            }
            
            const btn = this; 
            btn.disabled = true; 
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            const fd = new FormData(); 
            fd.append('book_id', <?php echo $b['id']; ?>);
            fetch('handle_reservation.php', { method:'POST', body:fd })
            .then(r => r.json())
            .then(d => { 
                if(d.success){ 
                    alert(d.message); 
                    window.location.reload(); 
                } else { 
                    alert(d.message); 
                    btn.disabled = false; 
                    btn.innerHTML = '<i class="fas fa-bookmark"></i> Reserve Physical Copy'; 
                } 
            });
        };
    }
</script>

<?php renderFooter(); ?>
