<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/notifications_helper.php';

checkAccess(['student', 'admin']);

$user_id = $_SESSION['user_id'];

// ─────────────────────────────────────────────────────────────
// RUN DUE DATE REMINDER PROCESSING ON EVERY DASHBOARD LOAD
// ─────────────────────────────────────────────────────────────
processDueReminders($conn);

// Mark notification as read
if (isset($_GET['read'])) {
    $nid = (int)$_GET['read'];
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $nid, $user_id);
    $stmt->execute();
}

// ─────────────────────────────────────────────────────────────
// ADVANCED SEARCH MODULE — INPUT SANITIZATION & VALIDATION
// ─────────────────────────────────────────────────────────────
$s_title      = trim($_GET['s_title']    ?? '');
$s_author     = trim($_GET['s_author']   ?? '');
$s_subject    = trim($_GET['s_subject']  ?? '');
$s_year       = trim($_GET['s_year']     ?? '');
$s_category   = trim($_GET['s_category'] ?? '');
$s_avail      = trim($_GET['s_avail']    ?? '');
$s_sort       = trim($_GET['s_sort']     ?? 'newest');
$s_page       = max(1, (int)($_GET['s_page'] ?? 1));
$per_page     = 9;
$search_submitted = isset($_GET['s_title']) || isset($_GET['s_author']) || isset($_GET['s_subject']) || isset($_GET['s_year']) || isset($_GET['s_category']) || isset($_GET['s_avail']);

// Whitelist sort values
if (!in_array($s_sort, ['newest', 'oldest', 'alpha', 'most_borrowed'])) {
    $s_sort = 'newest';
}

// Whitelist availability
if (!in_array($s_avail, ['', 'available', 'borrowed'])) {
    $s_avail = '';
}

// Validate year — must be exactly 4 digits
if ($s_year !== '' && !preg_match('/^\d{4}$/', $s_year)) {
    $s_year = '';
    $year_error = "Publication year must be a 4-digit number.";
}

// ─────────────────────────────────────────────────────────────
// BUILD DYNAMIC QUERY WITH PREPARED STATEMENTS
// ─────────────────────────────────────────────────────────────
$where_parts = [];
$bind_types  = '';
$bind_vals   = [];

if ($s_title !== '') {
    $where_parts[] = "b.title LIKE ?";
    $bind_types   .= 's';
    $bind_vals[]   = "%{$s_title}%";
}
if ($s_author !== '') {
    $where_parts[] = "b.author LIKE ?";
    $bind_types   .= 's';
    $bind_vals[]   = "%{$s_author}%";
}
if ($s_subject !== '') {
    $where_parts[] = "(b.category LIKE ? OR b.description LIKE ?)";
    $bind_types   .= 'ss';
    $bind_vals[]   = "%{$s_subject}%";
    $bind_vals[]   = "%{$s_subject}%";
}
if ($s_year !== '') {
    $where_parts[] = "b.publication_date LIKE ?";
    $bind_types   .= 's';
    $bind_vals[]   = "%{$s_year}%";
}
if ($s_category !== '') {
    $where_parts[] = "b.category = ?";
    $bind_types   .= 's';
    $bind_vals[]   = $s_category;
}
if ($s_avail === 'available') {
    $where_parts[] = "b.available_copies > 0";
} elseif ($s_avail === 'borrowed') {
    $where_parts[] = "b.available_copies = 0";
}

$where_sql = !empty($where_parts) ? 'WHERE ' . implode(' AND ', $where_parts) : '';

$sort_sql = match($s_sort) {
    'oldest'        => 'ORDER BY b.created_at ASC',
    'alpha'         => 'ORDER BY b.title ASC',
    'most_borrowed' => 'ORDER BY borrow_count DESC, b.title ASC',
    default         => 'ORDER BY b.created_at DESC',
};

// COUNT QUERY
$total_search_results = 0;
$total_search_pages   = 1;
if ($search_submitted) {
    $count_stmt = $conn->prepare("
        SELECT COUNT(DISTINCT b.id) AS total
        FROM books b
        LEFT JOIN (SELECT book_id, COUNT(*) AS borrow_count FROM borrowings GROUP BY book_id) bc ON bc.book_id = b.id
        $where_sql
    ");
    if ($bind_types) {
        $count_stmt->bind_param($bind_types, ...$bind_vals);
    }
    $count_stmt->execute();
    $total_search_results = $count_stmt->get_result()->fetch_assoc()['total'];
    $total_search_pages   = max(1, ceil($total_search_results / $per_page));
    $s_page = min($s_page, $total_search_pages);
}

// DATA QUERY
$search_results = null;
if ($search_submitted) {
    $offset      = ($s_page - 1) * $per_page;
    $data_types  = $bind_types . 'ii';
    $data_vals   = array_merge($bind_vals, [$per_page, $offset]);

    $data_stmt = $conn->prepare("
        SELECT b.*, COALESCE(bc.borrow_count, 0) AS borrow_count
        FROM books b
        LEFT JOIN (SELECT book_id, COUNT(*) AS borrow_count FROM borrowings GROUP BY book_id) bc ON bc.book_id = b.id
        $where_sql
        $sort_sql
        LIMIT ? OFFSET ?
    ");
    $data_stmt->bind_param($data_types, ...$data_vals);
    $data_stmt->execute();
    $search_results = $data_stmt->get_result();
}

// CATEGORIES FOR DROPDOWN
$cat_res = $conn->query("SELECT DISTINCT category FROM books WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");

// HELPER: Build paginated URL preserving all GET params
function dashUrl($page) {
    $p = $_GET;
    $p['s_page'] = $page;
    return 'dashboard.php?' . http_build_query($p);
}

// ─────────────────────────────────────────────────────────────
// ACTIVE LOAN SUMMARY FOR WELCOME BANNER
// ─────────────────────────────────────────────────────────────
$active_loan_count = 0; // Borrowing disabled

$unread_notif_res = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0");
$unread_notif_res->bind_param("i", $user_id);
$unread_notif_res->execute();
$unread_count = $unread_notif_res->get_result()->fetch_assoc()['cnt'];

renderHeader("Institutional Hub - DigiScan");
?>

<style>
/* ── Dashboard Layout ── */
.dash-wrap     { display: grid; grid-template-columns: 260px 1fr; gap: 2rem; max-width: 1400px; margin: 0 auto; padding: 0 24px; }
.dash-main     { text-align: left; }

/* ── Stats Strip ── */
.stat-strip    { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 2rem; }
.stat-card     { background: white; border-radius: 12px; padding: 1.25rem 1.5rem; border: 1px solid #f1f5f9; box-shadow: var(--shadow); display: flex; align-items: center; gap: 1rem; }
.stat-icon     { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: white; flex-shrink: 0; }
.stat-num      { font-size: 1.6rem; font-weight: 800; line-height: 1; }
.stat-label    { font-size: 0.75rem; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.5px; }

/* ── Search Panel ── */
.search-module { background: white; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: var(--shadow); padding: 2rem; margin-bottom: 2rem; }
.search-module h3 { margin: 0 0 1.5rem; font-size: 1.1rem; display: flex; align-items: center; gap: 0.75rem; }
.search-grid   { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
.search-grid-2 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem; }
.sf-label      { font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.35rem; display: block; }
.sf-input, .sf-select {
    width: 100%; padding: 0.65rem 0.9rem; border-radius: 8px;
    border: 1.5px solid #e2e8f0; font-size: 0.88rem;
    transition: border-color 0.2s, box-shadow 0.2s; background: #fafafa;
}
.sf-input:focus, .sf-select:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(79,70,229,0.1); outline: none; background: white; }
.sf-wrap       { position: relative; }
.sf-wrap i     { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.85rem; }
.sf-wrap .sf-input { padding-left: 2.2rem; }

/* ── Sort Buttons ── */
.sort-row      { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
.sort-lbl      { font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; }
.s-btn         { padding: 0.45rem 1rem; border-radius: 8px; border: 1.5px solid #e2e8f0; background: white; cursor: pointer; font-size: 0.82rem; color: #475569; transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.4rem; text-decoration: none; }
.s-btn:hover   { border-color: var(--primary-color); color: var(--primary-color); }
.s-btn.s-on    { border-color: var(--primary-color); background: #eef2ff; color: var(--primary-color); font-weight: 700; }
.s-actions     { display: flex; gap: 0.75rem; justify-content: flex-end; }

/* ── Results ── */
.results-bar   { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem; }
.res-badge     { background: var(--primary-gradient); color: white; padding: 0.35rem 1rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
.filter-chip   { display: inline-flex; align-items: center; gap: 0.3rem; background: #eef2ff; color: var(--primary-color); padding: 0.25rem 0.65rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
.res-grid      { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem; }
.r-card        { background: white; border-radius: 12px; border: 1px solid #f1f5f9; box-shadow: var(--shadow); overflow: hidden; transition: transform 0.2s, box-shadow 0.2s; animation: fadeUp 0.3s ease forwards; }
.r-card:hover  { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
.r-cover       { height: 110px; background: var(--primary-gradient); display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.6); font-size: 2.5rem; position: relative; }
.r-body        { padding: 1rem; }
.r-cat         { font-size: 0.68rem; font-weight: 700; color: var(--primary-color); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.4rem; }
.r-title       { font-size: 0.9rem; font-weight: 700; line-height: 1.3; margin-bottom: 0.25rem; color: var(--text-color); }
.r-author      { font-size: 0.78rem; color: var(--text-light); margin-bottom: 0.5rem; }
.r-meta        { font-size: 0.7rem; color: #94a3b8; margin-bottom: 0.75rem; }
.r-foot        { border-top: 1px solid #f1f5f9; padding-top: 0.75rem; display: flex; justify-content: space-between; align-items: center; }
.avail-dot     { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.7rem; font-weight: 600; }
.avail-dot::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: currentColor; display: inline-block; }
.avail-ok  { color: #10b981; }
.avail-no  { color: #ef4444; }

/* ── Pagination ── */
.pager         { display: flex; gap: 0.4rem; justify-content: center; margin-top: 1.5rem; flex-wrap: wrap; }
.pager a, .pager span { padding: 0.5rem 0.85rem; border-radius: 7px; text-decoration: none; font-size: 0.85rem; font-weight: 500; border: 1.5px solid #e2e8f0; color: #475569; transition: all 0.2s; }
.pager a:hover { border-color: var(--primary-color); color: var(--primary-color); }
.pager .cur    { background: var(--primary-gradient); color: white; border-color: transparent; }

/* ── Empty State ── */
.empty-state   { padding: 4rem 2rem; text-align: center; color: var(--text-light); }
.empty-state i { font-size: 3.5rem; opacity: 0.2; display: block; margin-bottom: 1.25rem; }

@keyframes fadeUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
@media (max-width: 900px) { .dash-wrap { grid-template-columns: 1fr; } .search-grid { grid-template-columns: 1fr 1fr; } }
@media (max-width: 600px) { .search-grid, .search-grid-2 { grid-template-columns: 1fr; } .stat-strip { grid-template-columns: 1fr; } }
</style>

<div class="section">
<div class="dash-wrap">

    <!-- ═══ SIDEBAR ═══ -->
    <aside class="sidebar-nav card" style="text-align: left; padding: 1.5rem; align-self: start; position: sticky; top: 100px;">
        <div style="text-align: center; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9;">
            <div style="width: 72px; height: 72px; border-radius: 50%; background: var(--primary-gradient); margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; font-weight: 800;">
                <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
            </div>
            <h4 style="margin: 0; font-weight: 800; font-size: 0.95rem;"><?php echo htmlspecialchars($_SESSION['full_name']); ?></h4>
            <p style="font-size: 0.75rem; color: var(--text-light); margin-top: 4px;"><?php echo strtoupper($_SESSION['role']); ?></p>
        </div>
        <nav style="display: flex; flex-direction: column; gap: 0.25rem;">
            <a href="dashboard.php" class="admin-sidebar-link active-nav"><i class="fas fa-home"></i> Dashboard</a>
            <a href="catalog.php"   class="admin-sidebar-link"><i class="fas fa-book"></i> Browse Catalog</a>
            <a href="profile.php"   class="admin-sidebar-link"><i class="fas fa-user-circle"></i> My Profile</a>
            <a href="../registration/logout.php" class="admin-sidebar-link" style="margin-top: 2rem; color: var(--error-color);">
                <i class="fas fa-sign-out-alt"></i> Sign Out
            </a>
        </nav>
    </aside>

    <!-- ═══ MAIN CONTENT ═══ -->
    <section class="dash-main">

        <!-- Welcome Banner -->
        <div class="card" style="margin-bottom: 2rem; background: var(--primary-gradient); color: white; border: none; padding: 2.5rem; position: relative; overflow: hidden;">
            <div style="position: absolute; right: -30px; bottom: -30px; width: 180px; height: 180px; border-radius: 50%; background: rgba(255,255,255,0.07);"></div>
            <div style="position: absolute; right: 60px; top: -40px; width: 120px; height: 120px; border-radius: 50%; background: rgba(255,255,255,0.05);"></div>
            <h1 style="font-size: 2.2rem; margin-bottom: 0.4rem; position: relative;">
                Welcome back, <?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]); ?>.
            </h1>
            <p style="opacity: 0.85; font-size: 1rem; position: relative;">
                Your personal library hub. Search, discover, and read institutional digital resources.
            </p>
        </div>

        <!-- ── Stat Strip ── -->
        <div class="stat-strip" style="grid-template-columns: repeat(2, 1fr);">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #ef4444);">
                    <i class="fas fa-bell"></i>
                </div>
                <div>
                    <div class="stat-num"><?php echo $unread_count; ?></div>
                    <div class="stat-label">Unread Alerts</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #0e9f6e);">
                    <i class="fas fa-database"></i>
                </div>
                <div>
                    <?php $total_bks = $conn->query("SELECT COUNT(*) AS c FROM books")->fetch_assoc()['c']; ?>
                    <div class="stat-num"><?php echo number_format($total_bks); ?></div>
                    <div class="stat-label">Available Titles</div>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr; margin-bottom: 2.5rem;">
            <!-- Notifications Panel -->
            <div>
                <h3 style="margin-bottom: 1rem; font-size: 1rem;">
                    <i class="fas fa-bell" style="color: #f59e0b;"></i> Alerts
                    <?php if ($unread_count > 0): ?>
                        <span style="background: #ef4444; color: white; font-size: 0.65rem; padding: 2px 7px; border-radius: 20px; margin-left: 0.4rem;"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </h3>
                <div style="display: flex; flex-direction: column; gap: 0.75rem; max-height: 400px; overflow-y: auto;">
                    <?php
                    $notif_stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 6");
                    $notif_stmt->bind_param("i", $user_id);
                    $notif_stmt->execute();
                    $notifs = $notif_stmt->get_result();
                    if ($notifs->num_rows > 0):
                        while ($n = $notifs->fetch_assoc()):
                            $border = match($n['type']) {
                                'alert'    => 'var(--error-color)',
                                'reminder' => '#f59e0b',
                                'success'  => '#10b981',
                                default    => 'var(--primary-color)',
                            };
                    ?>
                        <div class="card" style="padding: 1.25rem; position: relative; border-left: 4px solid <?php echo $border; ?>; opacity: <?php echo $n['is_read'] ? 0.65 : 1; ?>; margin: 0;">
                            <h4 style="margin: 0 0 0.35rem; font-size: 0.9rem; padding-right: 1.5rem;"><?php echo htmlspecialchars($n['title']); ?></h4>
                            <p style="font-size: 0.85rem; color: var(--text-light); margin: 0; line-height: 1.4;"><?php echo htmlspecialchars($n['message']); ?></p>
                            <small style="display: block; margin-top: 0.5rem; color: #94a3b8; font-size: 0.7rem;">
                                <?php echo date('M d, H:i', strtotime($n['created_at'])); ?>
                            </small>
                            <?php if (!$n['is_read']): ?>
                                <a href="?read=<?php echo $n['id']; ?>" title="Mark as Read"
                                   style="position: absolute; top: 1rem; right: 1rem; color: #94a3b8; font-size: 0.9rem;">
                                    <i class="fas fa-check"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endwhile;
                    else: ?>
                        <div class="card" style="padding: 2.5rem; text-align: center; color: var(--text-light); margin: 0;">
                            <i class="fas fa-bell-slash" style="font-size: 2rem; opacity: 0.2; display: block; margin-bottom: 0.75rem;"></i>
                            <p style="margin: 0; font-size: 0.95rem;">All quiet. No new alerts.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════ -->
        <!--   ADVANCED SEARCH & FILTERING MODULE           -->
        <!-- ══════════════════════════════════════════════ -->
        <div class="search-module">
            <h3>
                <span style="width: 36px; height: 36px; background: var(--primary-gradient); border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; color: white;">
                    <i class="fas fa-search"></i>
                </span>
                Advanced Book Search
            </h3>

            <?php if (isset($year_error)): ?>
                <div style="background: #fef2f2; color: #ef4444; padding: 0.75rem 1rem; border-radius: 8px; border-left: 4px solid #ef4444; margin-bottom: 1rem; font-size: 0.88rem;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $year_error; ?>
                </div>
            <?php endif; ?>

            <form method="GET" action="dashboard.php" id="adv-search-form">

                <!-- Row 1: Four main fields -->
                <div class="search-grid">
                    <div>
                        <label class="sf-label" for="s_title">Title (MARC 245)</label>
                        <div class="sf-wrap">
                            <i class="fas fa-book"></i>
                            <input class="sf-input" type="text" name="s_title" id="s_title"
                                   placeholder="Search by book title..." maxlength="200"
                                   value="<?php echo htmlspecialchars($s_title); ?>">
                        </div>
                    </div>
                    <div>
                        <label class="sf-label" for="s_author">Author (MARC 100)</label>
                        <div class="sf-wrap">
                            <i class="fas fa-user-pen"></i>
                            <input class="sf-input" type="text" name="s_author" id="s_author"
                                   placeholder="Search by author name..." maxlength="200"
                                   value="<?php echo htmlspecialchars($s_author); ?>">
                        </div>
                    </div>
                    <div>
                        <label class="sf-label" for="s_subject">Subject / Topic (MARC 650)</label>
                        <div class="sf-wrap">
                            <i class="fas fa-tag"></i>
                            <input class="sf-input" type="text" name="s_subject" id="s_subject"
                                   placeholder="Topic or subject keyword..." maxlength="200"
                                   value="<?php echo htmlspecialchars($s_subject); ?>">
                        </div>
                    </div>
                    <div>
                        <label class="sf-label" for="s_year">Publication Year (MARC 264)</label>
                        <div class="sf-wrap">
                            <i class="fas fa-calendar"></i>
                            <input class="sf-input" type="text" name="s_year" id="s_year"
                                   placeholder="e.g. 2022" maxlength="4" pattern="\d{4}"
                                   value="<?php echo htmlspecialchars($s_year); ?>">
                        </div>
                    </div>
                </div>

                <!-- Row 2: Filter dropdowns -->
                <div class="search-grid-2">
                    <div>
                        <label class="sf-label" for="s_category">Category / Subject</label>
                        <select class="sf-select" name="s_category" id="s_category">
                            <option value="">All Categories</option>
                            <?php
                            // reset cat_res to re-iterate
                            $cat_res->data_seek(0);
                            while ($c = $cat_res->fetch_assoc()):
                            ?>
                                <option value="<?php echo htmlspecialchars($c['category']); ?>"
                                    <?php echo $s_category === $c['category'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['category']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="sf-label" for="s_avail">Availability Status</label>
                        <select class="sf-select" name="s_avail" id="s_avail">
                            <option value="">All Books</option>
                            <option value="available" <?php echo $s_avail === 'available' ? 'selected' : ''; ?>>Available Now</option>
                            <option value="borrowed"  <?php echo $s_avail === 'borrowed'  ? 'selected' : ''; ?>>Out of Stock</option>
                        </select>
                    </div>
                    <div style="display: flex; align-items: flex-end; gap: 0.75rem;">
                        <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.75rem; flex: 1;">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <?php if ($search_submitted): ?>
                            <a href="dashboard.php" class="btn btn-secondary" style="padding: 0.65rem 1.25rem;">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Row 3: Sort buttons -->
                <div class="sort-row">
                    <span class="sort-lbl"><i class="fas fa-sort"></i> Sort by:</span>
                    <?php
                    $sorts = [
                        'newest'        => ['fa-clock',           'Newest First'],
                        'oldest'        => ['fa-history',         'Oldest First'],
                        'alpha'         => ['fa-sort-alpha-down',  'A – Z'],
                        'most_borrowed' => ['fa-fire',            'Most Borrowed'],
                    ];
                    foreach ($sorts as $val => [$icon, $label]):
                    ?>
                        <button type="submit" name="s_sort" value="<?php echo $val; ?>"
                                class="s-btn <?php echo $s_sort === $val ? 's-on' : ''; ?>">
                            <i class="fas <?php echo $icon; ?>"></i> <?php echo $label; ?>
                        </button>
                    <?php endforeach; ?>
                    <input type="hidden" name="s_sort" id="s_sort_hidden" value="<?php echo htmlspecialchars($s_sort); ?>">
                </div>

            </form>
        </div><!-- /.search-module -->

        <!-- ── RESULTS ── -->
        <?php if ($search_submitted): ?>
            <div class="results-bar">
                <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;">
                    <span class="res-badge"><i class="fas fa-list-ul"></i> <?php echo number_format($total_search_results); ?> result<?php echo $total_search_results !== 1 ? 's' : ''; ?></span>
                    <?php if ($s_title):   ?><span class="filter-chip"><i class="fas fa-book"></i> <?php echo htmlspecialchars($s_title); ?></span><?php endif; ?>
                    <?php if ($s_author):  ?><span class="filter-chip"><i class="fas fa-user-pen"></i> <?php echo htmlspecialchars($s_author); ?></span><?php endif; ?>
                    <?php if ($s_subject): ?><span class="filter-chip"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($s_subject); ?></span><?php endif; ?>
                    <?php if ($s_year):    ?><span class="filter-chip"><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($s_year); ?></span><?php endif; ?>
                    <?php if ($s_category):?><span class="filter-chip"><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($s_category); ?></span><?php endif; ?>
                    <?php if ($s_avail):   ?><span class="filter-chip"><i class="fas fa-circle"></i> <?php echo ucfirst($s_avail); ?></span><?php endif; ?>
                </div>
                <?php if ($total_search_pages > 1): ?>
                    <span style="font-size: 0.8rem; color: var(--text-light);">
                        Page <?php echo $s_page; ?> of <?php echo $total_search_pages; ?>
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($total_search_results > 0 && $search_results): ?>
                <div class="res-grid">
                    <?php while ($b = $search_results->fetch_assoc()):
                        $lc = $conn->prepare("SELECT id FROM borrowings WHERE user_id = ? AND book_id = ? AND status = 'borrowed'");
                        $lc->bind_param("ii", $user_id, $b['id']);
                        $lc->execute();
                        $has_loan = $lc->get_result()->num_rows > 0;
                    ?>
                        <div class="r-card">
                            <div class="r-cover">
                                <i class="fas fa-book"></i>
                                <?php if ($b['borrow_count'] >= 5): ?>
                                    <span style="position: absolute; top: 8px; left: 8px; background: #f59e0b; color: white; font-size: 0.6rem; font-weight: 700; padding: 2px 7px; border-radius: 20px;">
                                        <i class="fas fa-fire"></i> Hot
                                    </span>
                                <?php endif; ?>
                                <?php if ($b['available_copies'] > 0): ?>
                                    <span style="position: absolute; top: 8px; right: 8px; background: rgba(16,185,129,0.9); color: white; font-size: 0.6rem; font-weight: 700; padding: 2px 7px; border-radius: 20px;">Free</span>
                                <?php else: ?>
                                    <span style="position: absolute; top: 8px; right: 8px; background: rgba(239,68,68,0.9); color: white; font-size: 0.6rem; font-weight: 700; padding: 2px 7px; border-radius: 20px;">Taken</span>
                                <?php endif; ?>
                            </div>
                            <div class="r-body">
                                <div class="r-cat"><?php echo htmlspecialchars($b['category'] ?: 'General'); ?></div>
                                <div class="r-title"><?php echo htmlspecialchars($b['title']); ?></div>
                                <div class="r-author">by <?php echo htmlspecialchars($b['author']); ?></div>
                                <div class="r-meta">
                                    <?php if ($b['publication_date']): ?>
                                        <i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($b['publication_date']); ?> &nbsp;
                                    <?php endif; ?>
                                    <i class="fas fa-bookmark"></i> <?php echo $b['borrow_count']; ?> borrow<?php echo $b['borrow_count'] != 1 ? 's' : ''; ?>
                                </div>
                                <div class="r-foot">
                                    <span class="avail-dot <?php echo $b['available_copies'] > 0 ? 'avail-ok' : 'avail-no'; ?>">
                                        <?php echo $b['available_copies'] > 0 ? 'Available' : 'Out of Stock'; ?>
                                    </span>
                                    <?php if ($has_loan): ?>
                                        <a href="read.php?id=<?php echo $b['id']; ?>" class="btn btn-primary" style="padding: 0.35rem 0.8rem; font-size: 0.75rem; background: var(--success-gradient);">
                                            <i class="fas fa-book-open"></i> Read
                                        </a>
                                    <?php else: ?>
                                        <a href="details.php?id=<?php echo $b['id']; ?>" class="btn btn-primary" style="padding: 0.35rem 0.8rem; font-size: 0.75rem;">
                                            View
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_search_pages > 1): ?>
                    <div class="pager">
                        <?php if ($s_page > 1): ?>
                            <a href="<?php echo dashUrl(1); ?>"><i class="fas fa-angle-double-left"></i></a>
                            <a href="<?php echo dashUrl($s_page - 1); ?>"><i class="fas fa-angle-left"></i></a>
                        <?php endif; ?>
                        <?php
                        for ($p = max(1, $s_page - 2); $p <= min($total_search_pages, $s_page + 2); $p++):
                        ?>
                            <?php if ($p === $s_page): ?>
                                <span class="cur"><?php echo $p; ?></span>
                            <?php else: ?>
                                <a href="<?php echo dashUrl($p); ?>"><?php echo $p; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($s_page < $total_search_pages): ?>
                            <a href="<?php echo dashUrl($s_page + 1); ?>"><i class="fas fa-angle-right"></i></a>
                            <a href="<?php echo dashUrl($total_search_pages); ?>"><i class="fas fa-angle-double-right"></i></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-search-minus"></i>
                    <h3 style="color: var(--text-light); margin-bottom: 0.75rem;">No books match your search.</h3>
                    <p style="color: #94a3b8; font-size: 0.9rem;">Try different keywords, remove some filters, or <a href="catalog.php" style="color: var(--primary-color);">browse all titles</a>.</p>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Prompt when no search yet -->
            <div class="empty-state" style="background: white; border-radius: 16px; border: 1px dashed #e2e8f0;">
                <i class="fas fa-book-open" style="opacity: 0.15;"></i>
                <p style="color: #94a3b8; font-size: 0.95rem; margin: 0;">
                    Use the search form above to find books by title, author, subject, or year.<br>
                    Or <a href="catalog.php" style="color: var(--primary-color); font-weight: 600;">browse the full catalog</a>.
                </p>
            </div>
        <?php endif; ?>

    </section>
</div><!-- /.dash-wrap -->
</div><!-- /.section -->

<script>
// Remove duplicate sort_hidden input conflict — ensure sort buttons properly set the value
document.querySelectorAll('.s-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        document.getElementById('s_sort_hidden').remove();
    });
});
</script>

<?php renderFooter(); ?>
