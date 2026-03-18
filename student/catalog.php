<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
checkAccess(['student', 'admin']);

// ─────────────────────────────────────────
// 1. INPUT SANITIZATION & VALIDATION
// ─────────────────────────────────────────
$search          = trim($_GET['search'] ?? '');
$author_filter   = trim($_GET['author'] ?? '');
$pub_year        = trim($_GET['pub_year'] ?? '');
$category        = trim($_GET['category'] ?? '');
$availability    = trim($_GET['availability'] ?? '');
$sort_by         = trim($_GET['sort_by'] ?? 'newest');
$page_num        = max(1, (int)($_GET['page'] ?? 1));
$per_page        = 12;
$offset          = ($page_num - 1) * $per_page;

// Whitelist sort options to prevent injection
$allowed_sorts = ['newest', 'oldest', 'alpha', 'most_borrowed'];
if (!in_array($sort_by, $allowed_sorts)) $sort_by = 'newest';

// Whitelist availability options
$allowed_avail = ['', 'available', 'borrowed'];
if (!in_array($availability, $allowed_avail)) $availability = '';

// Validate publication year (4 digits only)
if ($pub_year && !preg_match('/^\d{4}$/', $pub_year)) $pub_year = '';

// ─────────────────────────────────────────
// 2. BUILD DYNAMIC PREPARED STATEMENT
// ─────────────────────────────────────────
$where_clauses = [];
$bind_types    = '';
$bind_values   = [];

if ($search !== '') {
    $where_clauses[] = "(b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ? OR b.category LIKE ?)";
    $like = "%{$search}%";
    $bind_types  .= 'ssss';
    $bind_values  = array_merge($bind_values, [$like, $like, $like, $like]);
}

if ($author_filter !== '') {
    $where_clauses[] = "b.author LIKE ?";
    $bind_types  .= 's';
    $bind_values[] = "%{$author_filter}%";
}

if ($pub_year !== '') {
    $where_clauses[] = "b.publication_date LIKE ?";
    $bind_types  .= 's';
    $bind_values[] = "%{$pub_year}%";
}

if ($category !== '') {
    $where_clauses[] = "b.category = ?";
    $bind_types  .= 's';
    $bind_values[] = $category;
}

// Availability filters removed

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Sort clause
$sort_sql = match($sort_by) {
    'oldest'       => 'ORDER BY b.created_at ASC',
    'alpha'        => 'ORDER BY b.title ASC',
    'most_borrowed'=> 'ORDER BY borrow_count DESC, b.title ASC',
    default        => 'ORDER BY b.created_at DESC',
};

// ─────────────────────────────────────────
// 3. COUNT TOTAL RESULTS (for pagination)
// ─────────────────────────────────────────
$count_sql = "SELECT COUNT(DISTINCT b.id) AS total
              FROM books b
              LEFT JOIN (SELECT book_id, COUNT(*) AS borrow_count FROM borrowings GROUP BY book_id) bc ON bc.book_id = b.id
              $where_sql";
$count_stmt = $conn->prepare($count_sql);
if ($bind_types) {
    $count_stmt->bind_param($bind_types, ...$bind_values);
}
$count_stmt->execute();
$total_results = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages   = max(1, ceil($total_results / $per_page));
$page_num      = min($page_num, $total_pages);

// ─────────────────────────────────────────
// 4. FETCH PAGINATED RESULTS
// ─────────────────────────────────────────
$data_sql = "SELECT b.*, COALESCE(bc.borrow_count, 0) AS borrow_count
             FROM books b
             LEFT JOIN (SELECT book_id, COUNT(*) AS borrow_count FROM borrowings GROUP BY book_id) bc ON bc.book_id = b.id
             $where_sql
             $sort_sql
             LIMIT ? OFFSET ?";
$data_types  = $bind_types . 'ii';
$data_values = array_merge($bind_values, [$per_page, $offset]);
$data_stmt   = $conn->prepare($data_sql);
$data_stmt->bind_param($data_types, ...$data_values);
$data_stmt->execute();
$results = $data_stmt->get_result();

// ─────────────────────────────────────────
// 5. FETCH CATEGORIES FOR BROWSE CATALOG
// ─────────────────────────────────────────
$cat_res = $conn->query("SELECT category_name as category FROM categories ORDER BY category_name ASC");
$categories_list = [];
if ($cat_res) {
    while ($c = $cat_res->fetch_assoc()) {
        $categories_list[] = $c['category'];
    }
}

// ─────────────────────────────────────────
// 6. HELPER: BUILD URL FOR PAGINATION LINKS
// ─────────────────────────────────────────
function buildUrl($page) {
    $params = $_GET;
    $params['page'] = $page;
    return 'catalog.php?' . http_build_query($params);
}

// Check if any filters are active
$has_filters = ($search || $author_filter || $pub_year || $category || $availability || $sort_by !== 'newest');

renderHeader("Library Search - DigiScan");
?>

<style>
.search-panel { background: white; border-radius: 16px; box-shadow: var(--shadow); padding: 2rem; margin-bottom: 2rem; border: 1px solid #f1f5f9; }
.filter-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr; gap: 1rem; align-items: end; }
.filter-grid-row2 { display: flex; gap: 1rem; align-items: center; margin-top: 1rem; flex-wrap: wrap; }
.filter-field label { font-size: 0.75rem; font-weight: 600; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 0.4rem; }
.filter-field input, .filter-field select { width: 100%; padding: 0.75rem 1rem; border-radius: 10px; border: 1.5px solid #e2e8f0; font-size: 0.9rem; transition: border-color 0.2s; background: #fafafa; }
.filter-field input:focus, .filter-field select:focus { border-color: var(--primary-color); outline: none; background: white; }
.search-input-wrap { position: relative; }
.search-input-wrap i { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; }
.search-input-wrap input { padding-left: 2.8rem !important; }
.results-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
.results-badge { background: var(--primary-gradient); color: white; padding: 0.4rem 1rem; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }
.active-tag { display: inline-flex; align-items: center; gap: 0.4rem; background: #eef2ff; color: var(--primary-color); padding: 0.3rem 0.75rem; border-radius: 20px; font-size: 0.78rem; font-weight: 600; }
.pagination { display: flex; gap: 0.5rem; justify-content: center; margin-top: 3rem; flex-wrap: wrap; }
.pagination a, .pagination span { padding: 0.6rem 1rem; border-radius: 8px; text-decoration: none; font-size: 0.9rem; font-weight: 500; border: 1.5px solid #e2e8f0; color: var(--text-color); transition: all 0.2s; }
.pagination a:hover { border-color: var(--primary-color); color: var(--primary-color); }
.pagination .current-page { background: var(--primary-gradient); color: white; border-color: transparent; }
.sort-btn { padding: 0.5rem 1rem; border-radius: 8px; border: 1.5px solid #e2e8f0; background: white; cursor: pointer; font-size: 0.85rem; color: var(--text-color); transition: all 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; }
.sort-btn.active-sort { border-color: var(--primary-color); background: #eef2ff; color: var(--primary-color); font-weight: 600; }
.category-browse { display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; }
.category-btn { padding: 0.75rem 1.5rem; background: white; border: 1.5px solid #e2e8f0; border-radius: 30px; font-weight: 600; color: #64748b; text-decoration: none; transition: 0.2s; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
.category-btn:hover { background: #f8fafc; border-color: var(--primary-color); transform: translateY(-2px); }
.category-btn.active { background: var(--primary-color); color: white; border-color: var(--primary-color); }
@media (max-width: 900px) { .filter-grid { grid-template-columns: 1fr 1fr; } }
@media (max-width: 600px) { .filter-grid { grid-template-columns: 1fr; } }
</style>

<div class="container-wide section" style="display: grid; grid-template-columns: 260px 1fr; gap: 2rem; align-items: start; max-width: 1400px; margin: 0 auto; padding: 0 24px;">

    <!-- SIDEBAR NAVIGATION -->
    <aside class="sidebar-nav card" style="text-align: left; padding: 1.5rem; position: sticky; top: 100px;">
        <h4 style="margin: 0 0 1.5rem; font-size: 0.75rem; color: var(--text-light); text-transform: uppercase; letter-spacing: 1px;">Library Services</h4>
        <nav style="display: flex; flex-direction: column; gap: 0.5rem;">
            <a href="catalog.php" class="admin-sidebar-link active-nav" style="padding: 0.75rem 1rem; border-radius: 8px; color: var(--text-color); text-decoration: none; display: flex; align-items: center; gap: 0.75rem; font-weight: 500; transition: all 0.2s;"><i class="fas fa-search"></i> Library Search</a>
            <a href="profile.php" class="admin-sidebar-link" style="padding: 0.75rem 1rem; border-radius: 8px; color: var(--text-color); text-decoration: none; display: flex; align-items: center; gap: 0.75rem; font-weight: 500; transition: all 0.2s;"><i class="fas fa-user-circle"></i> User Profile</a>
        </nav>
    </aside>

    <main style="min-width: 0;">

    <!-- Page Header -->
    <div style="text-align: left; margin-bottom: 2rem;">
        <h1 style="font-size: 2.5rem; margin-bottom: 0.5rem;">Library Search</h1>
        <p style="color: var(--text-light); font-size: 1rem;">Browse the catalog by pre-defined collections, or use the advanced search tools.</p>
    </div>

    <!-- ─── BROWSE CATEGORIES (VISUAL NAVIGATION) ─── -->
    <div class="category-browse">
        <?php 
            $all_params = $_GET;
            $all_params['category'] = '';
            $all_params['page'] = 1;
            $all_url = 'catalog.php?' . http_build_query($all_params);
        ?>
        <a href="<?php echo htmlspecialchars($all_url); ?>" class="category-btn <?php echo empty($category) ? 'active' : ''; ?>">
            <i class="fas fa-layer-group"></i> All Collections
        </a>
        
        <?php foreach ($categories_list as $c_name): ?>
            <?php 
                $cat_params = $_GET;
                $cat_params['category'] = $c_name;
                $cat_params['page'] = 1;
                $cat_url = 'catalog.php?' . http_build_query($cat_params);
            ?>
            <a href="<?php echo htmlspecialchars($cat_url); ?>" class="category-btn <?php echo $category === $c_name ? 'active' : ''; ?>">
                <i class="fas fa-book"></i> <?php echo htmlspecialchars($c_name); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- ─── SEARCH & FILTER PANEL ─── -->
    <div class="search-panel">
        <form method="GET" action="catalog.php" id="search-form">

            <!-- Row 1: Main search fields -->
            <div class="filter-grid">
                <div class="filter-field">
                    <label>Keyword Search</label>
                    <div class="search-input-wrap">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" id="search" placeholder="Title, author, ISBN, subject..."
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="filter-field">
                    <label>Author (MARC 100)</label>
                    <input type="text" name="author" placeholder="Search by author..."
                           value="<?php echo htmlspecialchars($author_filter); ?>">
                </div>
                <div class="filter-field">
                    <label>Publication Year</label>
                    <input type="text" name="pub_year" placeholder="e.g. 2022" maxlength="4"
                           value="<?php echo htmlspecialchars($pub_year); ?>">
                </div>
                <div class="filter-field">
                    <label>Subject / Category</label>
                    <select name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories_list as $c_name): ?>
                            <option value="<?php echo htmlspecialchars($c_name); ?>"
                                <?php echo $category === $c_name ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>

            <!-- Row 2: Sort + Actions -->
            <div class="filter-grid-row2">
                <span style="font-size: 0.78rem; color: var(--text-light); font-weight: 600; text-transform: uppercase;">Sort by:</span>
                <?php
                $sort_options = [
                    'newest'       => ['icon' => 'fa-clock',          'label' => 'Newest'],
                    'oldest'       => ['icon' => 'fa-history',        'label' => 'Oldest'],
                    'alpha'        => ['icon' => 'fa-sort-alpha-down', 'label' => 'A–Z'],
                ];
                foreach ($sort_options as $val => $opt): ?>
                    <button type="submit" name="sort_by" value="<?php echo $val; ?>"
                            class="sort-btn <?php echo $sort_by === $val ? 'active-sort' : ''; ?>">
                        <i class="fas <?php echo $opt['icon']; ?>"></i> <?php echo $opt['label']; ?>
                    </button>
                <?php endforeach; ?>

                <div style="margin-left: auto; display: flex; gap: 0.75rem;">
                    <button type="submit" class="btn btn-primary" style="padding: 0.7rem 2rem;">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if ($has_filters): ?>
                        <a href="catalog.php" class="btn btn-secondary" style="padding: 0.7rem 1.5rem;">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- ─── RESULTS BAR ─── -->
    <div class="results-bar">
        <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
            <span class="results-badge"><i class="fas fa-book-open"></i> <?php echo number_format($total_results); ?> result<?php echo $total_results !== 1 ? 's' : ''; ?> found</span>
            <?php if ($search): ?><span class="active-tag"><i class="fas fa-search"></i> "<?php echo htmlspecialchars($search); ?>"</span><?php endif; ?>
            <?php if ($author_filter): ?><span class="active-tag"><i class="fas fa-user-pen"></i> <?php echo htmlspecialchars($author_filter); ?></span><?php endif; ?>
            <?php if ($category): ?><span class="active-tag"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($category); ?></span><?php endif; ?>
            <?php if ($pub_year): ?><span class="active-tag"><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($pub_year); ?></span><?php endif; ?>

        </div>
        <span style="font-size: 0.85rem; color: var(--text-light);">
            <?php if ($total_pages > 1): ?>
                Page <?php echo $page_num; ?> of <?php echo $total_pages; ?>
            <?php endif; ?>
        </span>
    </div>

    <!-- ─── RESULTS GRID ─── -->
    <?php if ($total_results > 0): ?>
        <div class="grid-catalog">
            <?php while ($b = $results->fetch_assoc()):
                $bid = $b['id'];
                $is_borrowed = false; // Borrowing disabled
            ?>
                <div class="book-card" style="animation: fadeInUp 0.3s ease forwards;">
                    <div class="book-cover">
                        <i class="fas fa-book"></i>
                        <?php if ($b['borrow_count'] >= 5): ?>
                            <span style="position: absolute; top: 10px; left: 10px; background: #f59e0b; color: white; font-size: 0.65rem; font-weight: 700; padding: 3px 8px; border-radius: 20px; text-transform: uppercase;">
                                <i class="fas fa-fire"></i> Popular
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="book-info">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem; gap: 0.5rem;">
                            <span class="badge" style="background: #f1f5f9; color: var(--primary-color); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 60%;">
                                <?php echo htmlspecialchars($b['category'] ?: 'General'); ?>
                            </span>
                        </div>
                        <h3 style="font-size: 1rem; line-height: 1.3; margin-bottom: 0.3rem;"><?php echo htmlspecialchars($b['title']); ?></h3>
                        <p class="author" style="font-size: 0.85rem; margin-bottom: 0.25rem;">by <?php echo htmlspecialchars($b['author']); ?></p>
                        <?php if ($b['publication_date']): ?>
                            <p style="font-size: 0.75rem; color: var(--text-light); margin: 0 0 0.5rem;">
                                <i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($b['publication_date']); ?>
                            </p>
                        <?php endif; ?>


                        <div style="border-top: 1px solid #f1f5f9; padding-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 0.75rem; color: var(--text-light);">ISBN: <?php echo htmlspecialchars($b['isbn'] ?: 'N/A'); ?></span>
                                <a href="details.php?id=<?php echo $b['id']; ?>" class="btn btn-primary" style="padding: 0.45rem 1rem; font-size: 0.8rem;">
                                    View Details
                                </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <!-- ─── PAGINATION ─── -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page_num > 1): ?>
                    <a href="<?php echo buildUrl(1); ?>"><i class="fas fa-angle-double-left"></i></a>
                    <a href="<?php echo buildUrl($page_num - 1); ?>"><i class="fas fa-angle-left"></i> Prev</a>
                <?php endif; ?>

                <?php
                $start = max(1, $page_num - 2);
                $end   = min($total_pages, $page_num + 2);
                for ($p = $start; $p <= $end; $p++): ?>
                    <?php if ($p === $page_num): ?>
                        <span class="current-page"><?php echo $p; ?></span>
                    <?php else: ?>
                        <a href="<?php echo buildUrl($p); ?>"><?php echo $p; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page_num < $total_pages): ?>
                    <a href="<?php echo buildUrl($page_num + 1); ?>">Next <i class="fas fa-angle-right"></i></a>
                    <a href="<?php echo buildUrl($total_pages); ?>"><i class="fas fa-angle-double-right"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- No Results State -->
        <div style="padding: 6rem 2rem; text-align: center;">
            <i class="fas fa-search" style="font-size: 4rem; color: #e2e8f0; margin-bottom: 1.5rem; display: block;"></i>
            <h2 style="color: var(--text-light); margin-bottom: 1rem;">No books found matching your criteria.</h2>
            <p style="color: #94a3b8; margin-bottom: 2rem;">Try adjusting your search terms, removing filters, or browsing all available titles.</p>
            <a href="catalog.php" class="btn btn-primary">
                <i class="fas fa-undo"></i> Clear All Filters
            </a>
        </div>
    <?php endif; ?>
    </main>
</div>
<style>
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>

<?php renderFooter(); ?>
