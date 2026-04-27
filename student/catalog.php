<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
checkAccess(['student', 'admin']);

// Filtering Logic
$category = $_GET['category'] ?? 'All';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

$query = "SELECT * FROM books WHERE 1=1";
if ($category !== 'All') {
    $query .= " AND category = '" . $conn->real_escape_string($category) . "'";
}
if (!empty($search)) {
    $query .= " AND (title LIKE '%" . $conn->real_escape_string($search) . "%' OR author LIKE '%" . $conn->real_escape_string($search) . "%')";
}

switch($sort) {
    case 'az': $query .= " ORDER BY title ASC"; break;
    case 'za': $query .= " ORDER BY title DESC"; break;
    default: $query .= " ORDER BY id DESC";
}

$results = $conn->query($query);
$categories = $conn->query("SELECT DISTINCT category FROM books ORDER BY category ASC");

renderHeaderNoNav("Library Catalog - Noguchi Library");
?>
<link rel="stylesheet" href="../assets/css/student.css">

<div class="dash-wrap">
    <?php $active_page = 'catalog'; include '../includes/sidebar_student.php'; ?>

    <main class="cat-content sb-main">
        <div class="sb-container">
            <header class="results-bar" style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 3rem;">
                <div>
                    <h1 style="font-size: 2.5rem; font-weight: 850; color: #0f172a; margin: 0;">Catalog</h1>
                </div>
                <div class="results-badge">
                    <?php echo $results->num_rows; ?> Materials Found
                </div>
            </header>

            <section class="search-panel">
                <form method="GET" style="display: grid; grid-template-columns: 1fr auto; gap: 1rem; align-items: stretch;">
                    <div style="position: relative; flex: 1;">
                        <i class="fas fa-search" style="position: absolute; left: 1.5rem; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none; z-index: 5;"></i>
                        <input type="text" id="catalog-search" name="search" value="<?php echo htmlspecialchars($search); ?>" autocomplete="off" placeholder="Search by title, author, or keywords..." style="width: 100%; padding: 1.25rem 1.5rem 1.25rem 4rem; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 1.1rem; outline: none; transition: 0.3s; background: #fbfcfe;" onfocus="this.style.borderColor='#6366f1'; this.style.boxShadow='0 0 0 4px rgba(99,102,241,0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                        
                        <!-- Autocomplete Dropdown -->
                        <div id="ac-results" class="ac-dropdown" style="display: none;"></div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="padding: 0 2.5rem; border-radius: 14px; font-weight: 700; font-size: 1.1rem; display: flex; align-items: center; justify-content: center; height: 100%;">Search</button>
                </form>

                <div class="filter-grid-row2">
                    <span style="font-size: 0.8rem; font-weight: 800; color: #94a3b8; text-transform: uppercase;">Categories:</span>
                    <div class="category-browse" style="margin: 0; flex: 1;">
                        <a href="?category=All&search=<?php echo urlencode($search); ?>" class="category-btn <?php echo $category == 'All' ? 'active' : ''; ?>">All Collections</a>
                        <?php while($cat = $categories->fetch_assoc()): ?>
                            <a href="?category=<?php echo urlencode($cat['category']); ?>&search=<?php echo urlencode($search); ?>" class="category-btn <?php echo $category == $cat['category'] ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($cat['category']); ?>
                            </a>
                        <?php endwhile; ?>
                    </div>
                </div>
            </section>

            <div class="book-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 2.5rem;">
                <?php if ($results->num_rows > 0): ?>
                    <?php while($b = $results->fetch_assoc()): 
                        $ci_raw = $b['cover_image'] ?? '';
                        $ci_decoded = json_decode($ci_raw, true);
                        $ci_first = is_array($ci_decoded) && !empty($ci_decoded) ? $ci_decoded[0] : ((!empty($ci_raw) && $ci_raw[0] !== '[') ? $ci_raw : '');
                        $ci_src = !empty($ci_first) ? '../'.$ci_first : '../assets/img/library-logo.png';
                    ?>
                        <a href="details.php?id=<?php echo $b['id']; ?>" class="book-card" style="display: block; background: #fff; border-radius: 20px; border: 1px solid #f1f5f9; overflow: hidden; text-decoration: none; color: inherit; transition: all 0.3s ease; height: 100%; display: flex; flex-direction: column;">
                            <div class="book-cover" style="position: relative; height: 350px; overflow: hidden; background: #f8fafc;">
                                <img src="<?php echo htmlspecialchars($ci_src); ?>" style="width: 100%; height: 100%; object-fit: cover; transition: 0.5s;" onerror="this.src='../assets/img/library-logo.png';">
                                
                                <!-- Dewey Decimal Badge -->
                                <div style="position: absolute; top: 1rem; left: 1rem; background: #6366f1; color: white; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 800; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                                    <?php echo htmlspecialchars($b['dewey_decimal'] ?: 'N/A'); ?>
                                </div>

                            </div>
                            <div style="padding: 1.5rem; flex: 1; display: flex; flex-direction: column;">
                                <span style="font-size: 0.7rem; font-weight: 800; color: #6366f1; text-transform: uppercase; margin-bottom: 0.5rem; display: block;"><?php echo htmlspecialchars($b['category']); ?></span>
                                <h3 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: #1e293b; line-height: 1.3; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($b['title']); ?></h3>
                                <p style="margin: 0; font-size: 0.85rem; color: #64748b; margin-top: auto;">by <?php echo htmlspecialchars($b['author']); ?></p>
                            </div>
                        </a>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="grid-column: 1/-1; padding: 5rem; text-align: center; background: #fff; border-radius: 20px; border: 2px dashed #e2e8f0;">
                        <i class="fas fa-search" style="font-size: 3rem; color: #e2e8f0; margin-bottom: 1.5rem;"></i>
                        <h2 style="color: #64748b;">No results found</h2>
                        <p style="color: #94a3b8;">Try adjusting your search criteria or filters.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
    // Live Autocomplete Script
    const searchInput = document.getElementById('catalog-search');
    const acResults = document.getElementById('ac-results');

    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        if (query.length < 1) {
            acResults.style.display = 'none';
            return;
        }

        fetch(`autocomplete.php?q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(data => {
                if (data.length > 0) {
                    let html = '';
                    data.forEach(book => {
                        const cover = book.cover || '../assets/img/library-logo.png';
                        html += `
                            <a href="details.php?id=${book.id}" class="ac-item">
                                <img src="${cover}" alt="cover" onerror="this.src='../assets/img/library-logo.png'">
                                <div class="ac-info">
                                    <div class="ac-title">${book.title}</div>
                                    <div class="ac-sub">${book.author} — ${book.category} (${book.year})</div>
                                </div>
                            </a>
                        `;
                    });
                    acResults.innerHTML = html;
                    acResults.style.display = 'block';
                } else {
                    acResults.style.display = 'none';
                }
            })
            .catch(err => console.error('AC Error:', err));
    });

    // Close dropdown on click outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !acResults.contains(e.target)) {
            acResults.style.display = 'none';
        }
    });
</script>

<?php renderFooter(); ?>
