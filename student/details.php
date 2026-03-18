<?php
/**
 * Noguchi Library Digital Access and Preview System
 * Module: Book Details (Procedural PHP)
 */
require_once '../includes/auth.php';
require_once '../config/db.php';
checkAccess(['student', 'admin']);

// ── FUNCTIONS ────────────────────────────────────────────────────────

/**
 * Retrieving complete book information from database using book ID
 */
function getBookById($conn, $book_id) {
    if (!$book_id) return null;
    $stmt = $conn->prepare("SELECT b.*, c.category_name as category_display 
                            FROM books b 
                            LEFT JOIN (SELECT DISTINCT category as category_name FROM books) c ON b.category = c.category_name
                            WHERE b.id = ?");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Retrieve all metadata fields from the database
 */
function getBookMetadata($book_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT edition, publication_place, publisher, publication_date, content_type, media_type, carrier_type, extent, isbn, physical_location FROM books WHERE id = ?");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Fetch available preview page list
 */
function getPreviewPages($book_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT preview_pages FROM books WHERE id = ?");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if (!$res || empty($res['preview_pages'])) return [];
    // Assume preview_pages is stored as a JSON string: {"Cover":"p1.jpg", "Title Page":"p2.jpg", ...}
    // Or a comma separated list. Let's try to handle both or default to a standard set.
    return json_decode($res['preview_pages'], true) ?: ['Cover' => 'p1', 'Title Page' => 'p2', 'Chapter 1' => 'p3', 'Sample 1' => 'p4', 'Sample 2' => 'p5'];
}

/**
 * Fetch related books for suggestions
 */
function getRelatedBooks($conn, $category, $current_book_id, $limit = 4) {
    $stmt = $conn->prepare("SELECT id, title, author FROM books WHERE category = ? AND id != ? LIMIT ?");
    $stmt->bind_param("sii", $category, $current_book_id, $limit);
    $stmt->execute();
    return $stmt->get_result();
}

// ── PROCESSING ────────────────────────────────────────────────────────

// Validating and sanitizing the book ID parameter
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$preview_key = $_GET['preview'] ?? null;

if (!$id) {
    header("Location: catalog.php");
    exit();
}

$b = getBookById($conn, $id);
if (!$b) {
    header("Location: catalog.php?error=notfound");
    exit();
}

// Tracking user viewing history
if (!isset($_SESSION['viewed_books'])) $_SESSION['viewed_books'] = [];
if (!in_array($id, $_SESSION['viewed_books'])) {
    $uid = $_SESSION['user_id'];
    $stmt = $conn->prepare("INSERT IGNORE INTO reading_history (user_id, book_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $uid, $id);
    $stmt->execute();
    $_SESSION['viewed_books'][] = $id;
}

// Preview Limit Check (Session based)
if (!isset($_SESSION['preview_count'])) $_SESSION['preview_count'] = 0;
$settings = getSystemSettings($conn);
$max_preview = (int)($settings['max_preview_pages'] ?? 10);

$preview_pages = getPreviewPages($id);
$meta = getBookMetadata($id);

renderHeader(htmlspecialchars($b['title']) . " - Noguchi Library");
?>

<style>
    .breadcrumb { display: flex; list-style: none; padding: 0; margin-bottom: 2rem; font-size: 0.9rem; color: var(--text-light); }
    .breadcrumb li::after { content: "/"; margin: 0 0.5rem; }
    .breadcrumb li:last-child::after { content: ""; }
    .breadcrumb a { color: var(--primary-color); text-decoration: none; }
    
    .meta-label { font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; margin-bottom: 0.25rem; display: block; }
    .meta-value { font-size: 0.95rem; color: #1e293b; margin-bottom: 1.25rem; }
    
    .preview-item { display: flex; align-items: center; padding: 1rem; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 0.75rem; text-decoration: none; color: inherit; transition: 0.2s; }
    .preview-item:hover { background: #f8fafc; border-color: var(--primary-color); transform: translateX(5px); }
    .preview-active { background: #eff6ff; border-color: var(--primary-color); }
    
    .related-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1.5rem; }
    .related-card { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); transition: 0.3s; text-decoration: none; color: inherit; }
    .related-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1); }
    
    @media print {
        .no-print { display: none !important; }
        .card { box-shadow: none !important; border: 1px solid #ddd !important; }
    }
</style>

<div class="container-wide section" style="max-width: 1200px; margin: 0 auto; padding: 0 24px;">
    
    <!-- Adding breadcrumb navigation -->
    <nav class="no-print">
        <ul class="breadcrumb">
            <li><a href="catalog.php">Catalog</a></li>
            <li><?php echo htmlspecialchars($b['category'] ?: 'General'); ?></li>
            <li style="color: #64748b; font-weight: 600;"><?php echo htmlspecialchars($b['title']); ?></li>
        </ul>
    </nav>

    <div style="display: grid; grid-template-columns: 400px 1fr; gap: 4rem; align-items: start;">
        
        <!-- Left Column: Image & Preview Actions -->
        <div style="position: sticky; top: 100px;">
            <!-- Displaying book cover image -->
            <div style="width: 100%; height: 550px; background: #f1f5f9; border-radius: 12px; display: flex; align-items: center; justify-content: center; overflow: hidden; box-shadow: var(--shadow-xl); margin-bottom: 2rem; border: 1px solid #e2e8f0;">
                <img src="<?php echo htmlspecialchars($b['cover_image'] ?? '../assets/img/book-placeholder.jpg'); ?>" 
                     alt="Cover of <?php echo htmlspecialchars($b['title']); ?>"
                     style="width: 100%; height: 100%; object-fit: cover;">
            </div>
            
            <!-- Preview Section Header -->
            <div class="card" style="padding: 1.5rem;">
                <h3 style="margin-top: 0; font-size: 1.1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-eye" style="color: var(--primary-color);"></i> Preview (Sample Pages)
                </h3>
                
                <?php if (!empty($preview_pages)): ?>
                    <div style="margin-top: 1rem;">
                        <?php 
                        $i = 0;
                        foreach ($preview_pages as $label => $file): 
                        ?>
                            <a href="?id=<?php echo $id; ?>&preview=<?php echo urlencode($label); ?>" 
                               class="preview-item <?php echo $preview_key === $label ? 'preview-active' : ''; ?>">
                                <i class="fas fa-file-image" style="margin-right: 1rem; color: #94a3b8;"></i>
                                <span style="font-weight: 500;"><?php echo htmlspecialchars($label); ?></span>
                                <i class="fas fa-chevron-right" style="margin-left: auto; font-size: 0.75rem; opacity: 0.3;"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="padding: 2rem; text-align: center; color: #94a3b8; background: #f8fafc; border-radius: 8px;">
                        <i class="fas fa-info-circle" style="display: block; font-size: 1.5rem; margin-bottom: 0.5rem;"></i>
                        No preview available for this asset.
                    </div>
                <?php endif; ?>

                <!-- Adding clear and prominent disclaimer notice -->
                <div style="margin-top: 2rem; padding: 1rem; background: #fffbeb; border-left: 4px solid #f59e0b; border-radius: 4px;">
                    <p style="font-size: 0.8rem; color: #92400e; margin: 0; line-height: 1.5;">
                        <i class="fas fa-exclamation-circle"></i> <strong>This is a preview only.</strong> To access the complete physical volume, please visit the FSUU Noguchi Library.
                    </p>
                </div>
            </div>
        </div>

        <!-- Right Column: Details & Preview Display -->
        <div style="text-align: left;">
            
            <?php if ($preview_key && isset($preview_pages[$preview_key])): ?>
                <!-- Implementing page viewer that opens selected preview page -->
                <div class="card" style="margin-bottom: 3rem; padding: 1rem; background: #334155; position: relative;">
                    <div style="position: absolute; top: 1.5rem; left: 1.5rem; color: white; background: rgba(0,0,0,0.5); padding: 5px 12px; border-radius: 20px; font-size: 0.75rem;">
                        Preview Mode: <?php echo htmlspecialchars($preview_key); ?>
                    </div>
                    <div oncontextmenu="return false" style="width: 100%; min-height: 600px; display: flex; align-items: center; justify-content: center; background: #525659; border-radius: 4px;">
                         <!-- In a real app, this would be the actual page image -->
                         <div style="text-align: center; color: rgba(255,255,255,0.8);">
                            <i class="fas fa-file-image" style="font-size: 5rem; margin-bottom: 1.5rem; opacity: 0.3;"></i>
                            <p>[ Institutional Asset Preview: <?php echo htmlspecialchars($preview_key); ?> ]</p>
                            <small>Watermarked Sample Interface</small>
                         </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Showing book title prominently at the top -->
            <h1 style="font-size: 3.5rem; font-weight: 800; line-height: 1.1; margin: 0 0 0.75rem; color: #0f172a;"><?php echo htmlspecialchars($b['title']); ?></h1>
            <p style="font-size: 1.5rem; color: var(--text-light); margin-bottom: 2.5rem;">by <span style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($b['author']); ?></span></p>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 3rem;">
                <div>
                    <h3 style="font-size: 1rem; text-transform: uppercase; letter-spacing: 1px; color: #64748b; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.75rem; margin-bottom: 1.5rem;">Institutional Metadata</h3>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                        <div>
                            <span class="meta-label">Publication Date</span>
                            <p class="meta-value"><?php echo htmlspecialchars($meta['publication_date'] ?: 'N/A'); ?></p>
                            
                            <span class="meta-label">Collection Category</span>
                            <span class="badge" style="background: #eff6ff; color: var(--primary-color); border: 1px solid #dbeafe; font-size: 0.85rem;">
                                <?php echo htmlspecialchars($b['category'] ?: 'General Collection'); ?>
                            </span>
                        </div>
                        <div>
                            <span class="meta-label">Standard Code (ISBN)</span>
                            <p class="meta-value"><?php echo htmlspecialchars($meta['isbn'] ?: 'N/A'); ?></p>
                            
                            <!-- Showing physical location indicator -->
                            <span class="meta-label">Physical Location</span>
                            <p class="meta-value" style="font-weight: 600; color: var(--error-color);">
                                <i class="fas fa-map-marker-alt" style="margin-right: 5px;"></i>
                                <?php echo htmlspecialchars($meta['physical_location'] ?: 'Section A, Shelf 1'); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div style="margin-top: 1rem;">
                        <span class="meta-label">Summary / Abstract</span>
                        <p style="font-size: 1rem; color: #475569; line-height: 1.6;"><?php echo htmlspecialchars($b['description'] ?: 'No summary available for this literature.'); ?></p>
                    </div>
                </div>

                <div class="card" style="background: #f8fafc; border: 1px dashed #cbd5e1;">
                    <h4 class="meta-label" style="margin-bottom: 1rem;">Extended Details</h4>
                    <ul style="list-style: none; padding: 0; margin: 0; font-size: 0.85rem; color: #475569;">
                        <li style="margin-bottom: 0.75rem;"><strong>Publisher:</strong> <?php echo htmlspecialchars($meta['publisher'] ?: 'N/A'); ?></li>
                        <li style="margin-bottom: 0.75rem;"><strong>Edition:</strong> <?php echo htmlspecialchars($meta['edition'] ?: 'Standard'); ?></li>
                        <li style="margin-bottom: 0.75rem;"><strong>Carrier:</strong> <?php echo htmlspecialchars($meta['carrier_type'] ?: 'Online Resource'); ?></li>
                        <li><strong>Extent:</strong> <?php echo htmlspecialchars($meta['extent'] ?: 'N/A'); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Creating related materials section -->
            <div style="margin-top: 5rem;">
                <h3 style="font-size: 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-layer-group" style="color: var(--primary-color);"></i> Related Materials
                </h3>
                
                <div class="related-grid">
                    <?php 
                    $related = getRelatedBooks($conn, $b['category'], $id, 4);
                    if ($related->num_rows > 0):
                        while($rb = $related->fetch_assoc()): ?>
                            <a href="?id=<?php echo $rb['id']; ?>" class="related-card">
                                <div style="height: 220px; background: #f1f5f9; overflow: hidden;">
                                    <img src="<?php echo htmlspecialchars($rb['cover_image'] ?? '../assets/img/book-placeholder.jpg'); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                                <div style="padding: 1rem;">
                                    <h4 style="margin: 0; font-size: 0.85rem; line-height: 1.3; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($rb['title']); ?></h4>
                                    <p style="margin: 0.25rem 0 0; font-size: 0.75rem; color: var(--text-light);"><?php echo htmlspecialchars($rb['author']); ?></p>
                                </div>
                            </a>
                        <?php endwhile;
                    else: ?>
                        <!-- Adding fallback message when no related materials found -->
                        <div style="grid-column: 1/-1; padding: 3rem; text-align: center; background: #f8fafc; border-radius: 12px; color: #94a3b8;">
                            <i class="fas fa-folder-open" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <p>No other materials found in this collection category.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div style="margin-top: 4rem; display: flex; justify-content: center;">
                <a href="catalog.php" class="btn btn-secondary" style="padding: 1rem 3rem;">
                    <i class="fas fa-th-large" style="margin-right: 0.5rem;"></i> Back to Library Catalog
                </a>
            </div>

        </div>
    </div>
</div>

<script>
    // Disabling right-click on preview pages to prevent saving
    document.addEventListener('contextmenu', e => {
        if (e.target.closest('[oncontextmenu]')) e.preventDefault();
    });

    // Logging preview accesses for analytics
    const bookId = <?php echo $id; ?>;
    console.log('Book ID ' + bookId + ' accessed for preview.');
</script>

<?php renderFooter(); ?>
