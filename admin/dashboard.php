<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

// Enforce Admin Access
checkAccess('admin');

$page = $_GET['page'] ?? 'overview';
$page_title = "Admin Intelligence - DigiScan";
$message = "";
$messageType = "";

// ---------------------------------------------------------
// 1. LOGIC HANDLERS (POST ACTIONS)
// ---------------------------------------------------------

// A. Book Management (Add/Edit)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_book'])) {
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $edition = trim($_POST['edition']);
    $publication_place = trim($_POST['publication_place']);
    $publisher = trim($_POST['publisher']);
    $publication_date = trim($_POST['publication_date']);
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $isbn_type = in_array($_POST['isbn_type'] ?? '', ['ISBN', 'ISSN']) ? $_POST['isbn_type'] : 'ISBN';
    $isbn_num  = trim($_POST['isbn'] ?? '');
    $isbn      = $isbn_num !== '' ? $isbn_type . ': ' . $isbn_num : '';

    $content_type = trim($_POST['content_type'] ?? 'text');
    $media_type = trim($_POST['media_type'] ?? 'unmediated');
    $carrier_type = trim($_POST['carrier_type'] ?? 'volume');
    $extent = trim($_POST['extent'] ?? '');
    $chapter_info = trim($_POST['chapter_info'] ?? '');
    $total_copies = (int)($_POST['total_copies'] ?? 1);
    $book_id = $_POST['book_id'] ?? null;
    $file_path = "";

    // File Validation & Upload
    // ... (logic remains same for file handling)
    if (!empty($_FILES['book_file']['name'])) {
        $target_dir = "../uploads/books/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        
        $file_name = $_FILES['book_file']['name'];
        $file_size = $_FILES['book_file']['size'];
        $file_tmp = $_FILES['book_file']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png'];
        $max_size = 100 * 1024 * 1024; // 100MB limit

        if (!in_array($file_ext, $allowed_exts)) {
            $message = "Invalid file type. Only PDF and Images (JPG, PNG) are allowed.";
            $messageType = "error";
        } elseif ($file_size > $max_size) {
            $message = "File is too large. Maximum size is 100MB.";
            $messageType = "error";
        } else {
            $new_filename = uniqid('book_') . "." . $file_ext;
            if (move_uploaded_file($file_tmp, $target_dir . $new_filename)) {
                $file_path = "uploads/books/" . $new_filename;
            } else {
                $message = "Failed to upload file.";
                $messageType = "error";
            }
        }
    }

    if ($messageType != "error") {
        if ($book_id) {
            // Update Existing - Updated for RDA
            $sql = "UPDATE books SET title=?, author=?, edition=?, publication_place=?, publisher=?, publication_date=?, description=?, category=?, isbn=?, content_type=?, media_type=?, carrier_type=?, extent=?, chapter_info=?, total_copies=? " . ($file_path ? ", file_path=?" : "") . " WHERE id=?";
            $stmt = $conn->prepare($sql);
            if ($file_path) {
                $stmt->bind_param("ssssssssssssssisi", $title, $author, $edition, $publication_place, $publisher, $publication_date, $description, $category, $isbn, $content_type, $media_type, $carrier_type, $extent, $chapter_info, $total_copies, $file_path, $book_id);
            } else {
                $stmt->bind_param("ssssssssssssssii", $title, $author, $edition, $publication_place, $publisher, $publication_date, $description, $category, $isbn, $content_type, $media_type, $carrier_type, $extent, $chapter_info, $total_copies, $book_id);
            }
        } else {
            // New Insert - Updated for RDA
            $stmt = $conn->prepare("INSERT INTO books (title, author, edition, publication_place, publisher, publication_date, description, category, isbn, content_type, media_type, carrier_type, extent, chapter_info, total_copies, available_copies, file_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssssssssiis", $title, $author, $edition, $publication_place, $publisher, $publication_date, $description, $category, $isbn, $content_type, $media_type, $carrier_type, $extent, $chapter_info, $total_copies, $total_copies, $file_path);
        }

        if ($stmt->execute()) {
            $message = "RDA compliant literature asset digitized successfully.";
            $messageType = "success";
            
            // Log Action
            $log_stmt = $conn->prepare("INSERT INTO system_logs (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $admin_id = $_SESSION['user_id'];
            $action = $book_id ? "UPDATE_BOOK_RDA" : "ADD_BOOK_RDA";
            $details = "RDA Asset: $title";
            $ip = $_SERVER['REMOTE_ADDR'];
            $log_stmt->bind_param("isss", $admin_id, $action, $details, $ip);
            $log_stmt->execute();
        } else {
            $message = "Database Error: " . $conn->error;
            $messageType = "error";
        }
    }
}

// B. System Settings Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_settings'])) {
    foreach ($_POST['settings'] as $key => $value) {
        $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->bind_param("ss", $value, $key);
        $stmt->execute();
    }
    $message = "System policies updated.";
    $messageType = "success";
}

// C. User Status Toggle
if (isset($_GET['action']) && isset($_GET['uid'])) {
    $uid = (int)$_GET['uid'];
    $act = $_GET['action'];
    $status = ($act == 'activate') ? 1 : 0;
    
    $conn->query("UPDATE users SET is_active = $status WHERE id = $uid");
    $message = "User permissions modified.";
    $messageType = "success";
}

// D. Book Deletion (Archive)
if (isset($_GET['delete']) && $page == 'catalog') {
    $del_id = (int)$_GET['delete'];
    
    // Check if book exists
    $check = $conn->query("SELECT title FROM books WHERE id = $del_id")->fetch_assoc();
    if ($check) {
        $title = $check['title'];
        $conn->query("DELETE FROM books WHERE id = $del_id");
        $message = "Book asset archived successfully.";
        $messageType = "success";
        
        // Log Action
        $log_stmt = $conn->prepare("INSERT INTO system_logs (admin_id, action, details, ip_address) VALUES (?, 'DELETE_BOOK', ?, ?)");
        $admin_id = $_SESSION['user_id'];
        $details = "Deleted Asset: $title";
        $ip = $_SERVER['REMOTE_ADDR'];
        $log_stmt->bind_param("iss", $admin_id, $details, $ip);
        $log_stmt->execute();
    }
}

renderHeader($page_title);
?>

<div class="container-wide section" style="display: grid; grid-template-columns: 260px 1fr; gap: 2rem; align-items: start; max-width: 1400px; margin: 0 auto; padding: 0 24px;">
    
    <!-- SIDEBAR NAVIGATION -->
    <aside class="sidebar-nav card" style="text-align: left; padding: 1.5rem; position: sticky; top: 100px;">
        <h4 style="margin: 0 0 1.5rem; font-size: 0.75rem; color: var(--text-light); text-transform: uppercase; letter-spacing: 1px;">Admin Modules</h4>
        <nav style="display: flex; flex-direction: column; gap: 0.5rem;">
            <a href="?page=overview" class="admin-sidebar-link <?php echo $page=='overview'?'active-nav':''; ?>"><i class="fas fa-home"></i> Home</a>
            <a href="?page=catalog" class="admin-sidebar-link <?php echo ($page=='catalog'||$page=='edit_book')?'active-nav':''; ?>"><i class="fas fa-book"></i> Catalog</a>
            <a href="?page=users" class="admin-sidebar-link <?php echo $page=='users'?'active-nav':''; ?>"><i class="fas fa-users"></i> Users</a>
            <a href="?page=reports" class="admin-sidebar-link <?php echo $page=='reports'?'active-nav':''; ?>"><i class="fas fa-file-alt"></i> Reports</a>
            <a href="?page=settings" class="admin-sidebar-link <?php echo $page=='settings'?'active-nav':''; ?>"><i class="fas fa-cog"></i> Settings</a>
        </nav>
    </aside>

    <!-- MAIN CONTENT AREA -->
    <main class="admin-main">
        
        <?php
        $page_names = [
            'overview' => 'Intelligence Overview',
            'catalog'  => 'Asset Management',
            'edit_book'=> 'Metadata Editor',
            'preview'  => 'Asset Preview Simulator',
            'users'    => 'User Command Center',
            'reports'  => 'Platform Analytics',
            'settings' => 'System Policies'
        ];
        $current_page_name = $page_names[$page] ?? ucfirst($page);
        ?>
        <nav class="breadcrumb-nav" aria-label="breadcrumb" style="margin-bottom: 2rem; display: block; text-align: left;">
            <ol style="list-style: none; padding: 0; margin: 0; display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; color: #64748b;">
                <li><a href="dashboard.php" style="color: var(--primary-color); text-decoration: none;"><i class="fas fa-shield-alt"></i> Command Hub</a></li>
                <?php if ($page !== 'overview'): ?>
                    <li><i class="fas fa-chevron-right" style="font-size: 0.6rem; margin: 0 0.2rem; opacity: 0.5;"></i></li>
                    <li aria-current="page" style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($current_page_name); ?></li>
                <?php endif; ?>
            </ol>
        </nav>

        <!-- Feedback Messages -->
        <?php if ($message): ?>
            <div class="alert <?php echo $messageType == 'success' ? 'alert-success' : 'alert-danger'; ?>" style="margin-bottom: 2rem;">
                <i class="fas <?php echo $messageType == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($page == 'overview'): ?>
            <div class="dashboard-header" style="text-align: left; margin-bottom: 2rem;">
                <h2>Administrative Intelligence</h2>
                <p>Global system metrics and institutional status overview.</p>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
                <?php
                $u_count = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
                $b_count = $conn->query("SELECT COUNT(*) FROM books")->fetch_row()[0];
                ?>
                <div class="card stat-card"><i class="fas fa-users"></i><div class="stat-info"><h3><?php echo $u_count; ?></h3><p>Total Patrons</p></div></div>
                <div class="card stat-card" style="border-color: var(--success-color);"><i class="fas fa-book" style="color: var(--success-color);"></i><div class="stat-info"><h3><?php echo $b_count; ?></h3><p>Library Inventory</p></div></div>
            </div>

            <!-- Quick Logs -->
            <div class="card" style="margin-top: 2rem; text-align: left;">
                <h3 style="margin-top: 0; margin-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem;">Recent Administrative Activity</h3>
                <div style="max-height: 400px; overflow-y: auto;">
                    <?php
                    $logs = $conn->query("SELECT sl.*, u.full_name FROM system_logs sl JOIN users u ON sl.admin_id = u.id ORDER BY sl.created_at DESC LIMIT 10");
                    while($l = $logs->fetch_assoc()): ?>
                        <div style="padding: 0.75rem; border-bottom: 1px solid #f8fafc; font-size: 0.9rem;">
                            <span style="color: var(--text-light);"><?php echo date('M d, H:i', strtotime($l['created_at'])); ?>:</span> 
                            <strong><?php echo $l['full_name']; ?></strong> performed 
                            <code style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px;"><?php echo $l['action']; ?></code> 
                            (<?php echo htmlspecialchars($l['details']); ?>)
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>

        <?php elseif ($page == 'catalog'): ?>
            <div class="dashboard-header" style="text-align: left; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2>Asset Management</h2>
                    <p>Interface for maintaining and expanding the digital and physical collection.</p>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="?page=edit_book" class="btn btn-primary"><i class="fas fa-plus"></i> Add Book</a>
                </div>
            </div>

            <div class="card" style="padding: 0; overflow: hidden; text-align: left;">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Book Details</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $res = $conn->query("SELECT * FROM books ORDER BY id DESC");
                        while($row = $res->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo str_pad($row['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['title']); ?></strong><br>
                                    <small style="color: var(--text-light);">by <?php echo htmlspecialchars($row['author']); ?></small>
                                </td>
                                <td><span class="badge" style="background: #f1f5f9; color: var(--primary-color);"><?php echo htmlspecialchars($row['category'] ?: 'Uncategorized'); ?></span></td>
                                <td>
                                    <span class="status-badge <?php echo $row['available_copies']>0?'status-active':'status-pending'; ?>">
                                        <?php echo $row['available_copies']>0?'IN STOCK':'DEPLETED'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?page=preview&id=<?php echo $row['id']; ?>" class="btn btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;" title="Preview System"><i class="fas fa-eye"></i></a>
                                    <a href="?page=edit_book&id=<?php echo $row['id']; ?>" class="btn btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;"><i class="fas fa-edit"></i></a>
                                    <a href="?page=catalog&delete=<?php echo $row['id']; ?>" class="btn btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.8rem; color: var(--error-color);" onclick="return confirm('Archive this asset?')"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($page == 'edit_book'): 
            $edit_id = $_GET['id'] ?? null;
            $b = $edit_id ? $conn->query("SELECT * FROM books WHERE id = ".(int)$edit_id)->fetch_assoc() : null;
            ?>
            <div class="dashboard-header" style="text-align: left; margin-bottom: 2rem;">
                <h2><?php echo $b ? 'Edit Book Record' : 'Add New Book'; ?></h2>
                <p>Fill in the book details below to register it in the library catalog.</p>
            </div>

            <div class="card" style="text-align: left; padding: 2.5rem;">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="book_id" value="<?php echo $b['id'] ?? ''; ?>">

                    <!-- Section 1: Basic Information -->
                    <h4 style="color: var(--primary-color); border-bottom: 1px solid #eef2ff; padding-bottom: 0.5rem; margin-bottom: 1.5rem;">1. Basic Information</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                        <div class="form-group"><label>Title</label><input type="text" name="title" value="<?php echo htmlspecialchars($b['title'] ?? ''); ?>" required></div>
                        <div class="form-group"><label>Author</label><input type="text" name="author" value="<?php echo htmlspecialchars($b['author'] ?? ''); ?>" required></div>
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category" required style="width: 100%; padding: 0.75rem; border: 1.5px solid #e2e8f0; border-radius: 8px; font-family: inherit;">
                                <option value="">Select Category...</option>
                                <?php
                                $cat_q = $conn->query("SELECT category_name FROM categories ORDER BY category_name ASC");
                                while($cat = $cat_q->fetch_assoc()):
                                ?>
                                <option value="<?php echo htmlspecialchars($cat['category_name']); ?>" <?php echo ($b['category'] ?? '') === $cat['category_name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Section 2: Publication & Stock -->
                    <h4 style="color: var(--primary-color); border-bottom: 1px solid #eef2ff; padding-bottom: 0.5rem; margin: 2rem 0 1.5rem;">2. Publication & Stock</h4>
                    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                        <div class="form-group"><label>Edition</label><input type="text" name="edition" value="<?php echo htmlspecialchars($b['edition'] ?? ''); ?>" placeholder="e.g., 1st Edition"></div>
                        <div class="form-group">
                            <label>Publication — Place, Publisher, Year</label>
                            <div style="display: flex; gap: 10px;">
                                <input type="text" name="publication_place" value="<?php echo htmlspecialchars($b['publication_place'] ?? ''); ?>" placeholder="Place"     style="flex: 1;">
                                <input type="text" name="publisher"         value="<?php echo htmlspecialchars($b['publisher']          ?? ''); ?>" placeholder="Publisher" style="flex: 2;">
                                <input type="text" name="publication_date"  value="<?php echo htmlspecialchars($b['publication_date']   ?? ''); ?>" placeholder="Year"      style="flex: 1;">
                            </div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                        <div class="form-group">
                            <label>Identifier Type &amp; Number (ISBN/ISSN)</label>
                            <?php
                                // Parse stored value like "ISBN: 9780..." or "ISSN: 0028-..."
                                $stored_isbn = $b['isbn'] ?? '';
                                if (str_starts_with($stored_isbn, 'ISSN: ')) {
                                    $saved_type = 'ISSN';
                                    $saved_num  = substr($stored_isbn, 6);
                                } elseif (str_starts_with($stored_isbn, 'ISBN: ')) {
                                    $saved_type = 'ISBN';
                                    $saved_num  = substr($stored_isbn, 6);
                                } else {
                                    $saved_type = 'ISBN';
                                    $saved_num  = $stored_isbn;
                                }
                            ?>
                            <div style="display: flex; gap: 1rem; margin-bottom: 0.5rem; align-items: center;">
                                <label style="display: flex; align-items: center; gap: 0.35rem; font-weight: 500; font-size: 0.9rem; cursor: pointer; margin: 0;">
                                    <input type="radio" name="isbn_type" value="ISBN"
                                        <?php echo $saved_type === 'ISBN' ? 'checked' : ''; ?>>
                                    ISBN
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.35rem; font-weight: 500; font-size: 0.9rem; cursor: pointer; margin: 0;">
                                    <input type="radio" name="isbn_type" value="ISSN"
                                        <?php echo $saved_type === 'ISSN' ? 'checked' : ''; ?>>
                                    ISSN
                                </label>
                            </div>
                            <input type="text" name="isbn" value="<?php echo htmlspecialchars($saved_num); ?>" placeholder="Enter number...">
                        </div>
                        <div class="form-group" style="display: flex; flex-direction: column; justify-content: flex-end;">
                            <label>Stock (Copies)</label>
                            <input type="number" name="total_copies" value="<?php echo htmlspecialchars($b['total_copies'] ?? '1'); ?>" required>
                        </div>
                    </div>

                    <!-- Section 3: Scanned Resource -->
                    <h4 style="color: var(--primary-color); border-bottom: 1px solid #eef2ff; padding-bottom: 0.5rem; margin: 2rem 0 1.5rem;">3. Scanned Resource</h4>
                    <div class="form-group" style="margin-bottom: 2.5rem; padding: 1.5rem; background: #f8fafc; border-radius: 12px; border: 1px dashed #e2e8f0;">
                        <label style="margin-bottom: 1rem;"><i class="fas fa-file-pdf"></i> Scanned Resource (Digital File)</label>
                        <input type="file" name="book_file" style="display: block; margin-top: 0.5rem;">
                        <?php if (!empty($b['file_path'])): ?>
                            <div style="margin-top: 1rem; font-size: 0.85rem; color: var(--success-color);">
                                <i class="fas fa-check-double"></i> File Verified: <a href="../<?php echo $b['file_path']; ?>" target="_blank" style="font-weight: 700;">View File</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" name="save_book" class="btn btn-primary" style="padding: 1.25rem 3rem;">Save Record</button>
                    <a href="?page=catalog" class="btn btn-secondary" style="margin-left: 1rem;">Cancel</a>
                </form>
            </div>



        <?php elseif ($page == 'preview'): 
            $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
            $preview_key = $_GET['preview'] ?? null;
            if (!$id) {
                echo "<script>window.location='?page=catalog';</script>"; exit;
            }
            $stmt = $conn->prepare("SELECT * FROM books WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $b = $stmt->get_result()->fetch_assoc();
            
            if (!$b) {
                echo "<script>window.location='?page=catalog';</script>"; exit;
            }
            
            $preview_pages = json_decode($b['preview_pages'] ?? '{}', true) ?: ['Cover' => 'p1', 'Title Page' => 'p2', 'Chapter 1' => 'p3', 'Sample 1' => 'p4', 'Sample 2' => 'p5'];
        ?>
            <div class="dashboard-header" style="text-align: left; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2>Asset Preview Mode</h2>
                    <p>Reviewing how patrons interact with the digital asset constraints.</p>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="?page=edit_book&id=<?php echo $id; ?>" class="btn btn-primary"><i class="fas fa-edit"></i> Edit Metadata</a>
                    <a href="?page=catalog" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Catalog</a>
                </div>
            </div>

            <style>
                .preview-item { display: flex; align-items: center; padding: 1rem; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 0.75rem; text-decoration: none; color: inherit; transition: 0.2s; }
                .preview-item:hover { background: #f8fafc; border-color: var(--primary-color); transform: translateX(5px); }
                .preview-active { background: #eff6ff; border-color: var(--primary-color); }
            </style>

            <div style="display: grid; grid-template-columns: 350px 1fr; gap: 3rem; align-items: start; text-align: left;">
                
                <!-- Left Column -->
                <div style="position: sticky; top: 100px;">
                    <div style="width: 100%; height: 500px; background: #f1f5f9; border-radius: 12px; display: flex; align-items: center; justify-content: center; overflow: hidden; box-shadow: var(--shadow-xl); margin-bottom: 2rem; border: 1px solid #e2e8f0;">
                        <img src="<?php echo htmlspecialchars($b['cover_image'] ?? '../assets/img/book-placeholder.jpg'); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    </div>
                    
                    <div class="card" style="padding: 1.5rem;">
                        <h3 style="margin-top: 0; font-size: 1.1rem; display: flex; align-items: center; gap: 0.5rem;"><i class="fas fa-eye" style="color: var(--primary-color);"></i> Preview Simulator</h3>
                        
                        <?php if (!empty($preview_pages)): ?>
                            <div style="margin-top: 1rem;">
                                <?php foreach ($preview_pages as $label => $file): ?>
                                    <a href="?page=preview&id=<?php echo $id; ?>&preview=<?php echo urlencode($label); ?>" 
                                       class="preview-item <?php echo $preview_key === $label ? 'preview-active' : ''; ?>">
                                        <i class="fas fa-file-image" style="margin-right: 1rem; color: #94a3b8;"></i>
                                        <span style="font-weight: 500;"><?php echo htmlspecialchars($label); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p style="color: #94a3b8; font-size: 0.9rem;">No preview pages configured.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Column -->
                <div>
                    <?php if ($preview_key && isset($preview_pages[$preview_key])): ?>
                        <!-- Admin Simulator Viewer -->
                        <div class="card" style="margin-bottom: 3rem; padding: 1rem; background: #334155; position: relative;">
                            <div style="position: absolute; top: 1.5rem; left: 1.5rem; color: white; background: rgba(0,0,0,0.5); padding: 5px 12px; border-radius: 20px; font-size: 0.75rem;">
                                Admin Render: <?php echo htmlspecialchars($preview_key); ?>
                            </div>
                            <div style="width: 100%; min-height: 600px; display: flex; align-items: center; justify-content: center; background: #525659; border-radius: 4px;">
                                 <div style="text-align: center; color: rgba(255,255,255,0.8);">
                                    <i class="fas fa-file-image" style="font-size: 5rem; margin-bottom: 1.5rem; opacity: 0.3;"></i>
                                    <p>[ Asset Preview: <?php echo htmlspecialchars($preview_key); ?> ]</p>
                                    <small>Admin Verification View</small>
                                 </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <span class="badge" style="background: var(--primary-gradient); color: white; margin-bottom: 1rem;">
                        <?php echo htmlspecialchars($b['category'] ?: 'Institutional Resource'); ?>
                    </span>
                    <h1 style="font-size: 2.5rem; font-weight: 800; margin: 0.5rem 0 0.5rem; line-height: 1.1; color: #0f172a;"><?php echo htmlspecialchars($b['title']); ?></h1>
                    <p style="font-size: 1.25rem; color: var(--text-light); margin-bottom: 2rem;">by <span style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($b['author']); ?></span></p>

                    <div class="card" style="background: #f8fafc; border: 1px dashed #cbd5e1; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h4 style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; margin: 0 0 0.5rem 0;">Physical Location Verification</h4>
                            <p style="font-size: 1rem; font-weight: 600; color: var(--error-color); margin: 0;">
                                <i class="fas fa-map-marker-alt" style="margin-right: 5px;"></i>
                                <?php echo htmlspecialchars($b['physical_location'] ?? 'Section A, Shelf 1'); ?>
                            </p>
                        </div>
                        <i class="fas fa-archive" style="font-size: 2rem; color: #cbd5e1; opacity: 0.5;"></i>
                    </div>
                    
                    <div style="margin-top: 2rem;">
                        <h4 style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.5rem;">Summary (MARC 520)</h4>
                        <p style="font-size: 1.05rem; color: #475569; line-height: 1.6; white-space: pre-wrap;"><?php echo htmlspecialchars($b['description'] ?: 'No summary configured.'); ?></p>
                    </div>
                </div>
            </div>

        <?php elseif ($page == 'users'): ?>
            <div class="dashboard-header" style="text-align: left; margin-bottom: 2rem;">
                <h2>User Command Center</h2>
                <p>Administrative interface for managing digital identities and access tiers.</p>
            </div>

            <div class="card" style="padding: 0; overflow: hidden; text-align: left;">
                <table>
                    <thead>
                        <tr>
                            <th>Identity</th>
                            <th>Classification</th>
                            <th>Audit Status</th>
                            <th>Created At</th>
                            <th>Control Access</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $users = $conn->query("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.id ORDER BY u.id DESC");
                        while($u = $users->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($u['full_name']); ?></strong><br>
                                    <small><?php echo $u['email']; ?></small>
                                </td>
                                <td><span class="badge" style="background: #eef2ff; color: #4f46e5;"><?php echo strtoupper($u['role_name']); ?></span></td>
                                <td>
                                    <span class="status-badge <?php echo $u['is_active']?'status-active':'status-pending'; ?>">
                                        <?php echo $u['is_active']?'ACTIVE':'PENDING'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                                <td>
                                    <?php if($u['is_active']): ?>
                                        <a href="?page=users&action=deactivate&uid=<?php echo $u['id']; ?>" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.8rem; background: #fee2e2; color: #991b1b; border: none;">Deactivate</a>
                                    <?php else: ?>
                                        <a href="?page=users&action=activate&uid=<?php echo $u['id']; ?>" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.8rem;">Activate</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($page == 'reports'): ?>
            <div class="dashboard-header" style="text-align: left; margin-bottom: 2rem;">
                <h2>Platform Analytics & Reports</h2>
                <p>Generated summaries for accountability and institutional performance tracking.</p>
            </div>

            <div style="display: grid; grid-template-columns: 1fr; gap: 2rem;">
                <div class="card" style="text-align: left;">
                    <h3>Inventory Health & Asset Status</h3>
                    <div style="padding: 1.5rem 0;">
                        <?php
                        $low_stock = $conn->query("SELECT title, available_copies FROM books WHERE available_copies < 2 LIMIT 6");
                        if ($low_stock->num_rows > 0):
                            while($ls = $low_stock->fetch_assoc()): ?>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 1.25rem; border-bottom: 1px solid #f8fafc; padding-bottom: 0.75rem;">
                                    <span style="font-weight: 500;"><?php echo htmlspecialchars($ls['title']); ?></span>
                                    <span style="color: var(--error-color); font-weight: 800; background: #fee2e2; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem;">
                                        <?php echo $ls['available_copies']; ?> PHYSICAL UNITS
                                    </span>
                                </div>
                            <?php endwhile;
                        else: ?>
                            <div style="text-align: center; color: var(--text-light); padding: 2rem;">
                                <i class="fas fa-check-circle" style="font-size: 2.5rem; color: var(--success-color); opacity: 0.3; margin-bottom: 1rem; display: block;"></i>
                                <p>All physical inventory levels are within institutional thresholds.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-top: 2rem; text-align: left; padding: 0; overflow: hidden;">
                <div style="padding: 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
                    <h3>Full Administrative Audit Log</h3>
                    <button class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Administrator</th>
                            <th>Action Type</th>
                            <th>Technical Details</th>
                            <th>Client IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $full_logs = $conn->query("SELECT sl.*, u.full_name FROM system_logs sl JOIN users u ON sl.admin_id = u.id ORDER BY sl.created_at DESC");
                        while($fl = $full_logs->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $fl['created_at']; ?></td>
                                <td><strong><?php echo $fl['full_name']; ?></strong></td>
                                <td><code style="color: var(--primary-color);"><?php echo $fl['action']; ?></code></td>
                                <td><small><?php echo htmlspecialchars($fl['details']); ?></small></td>
                                <td><?php echo $fl['ip_address']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($page == 'settings'): ?>
            <div class="dashboard-header" style="text-align: left; margin-bottom: 2rem;">
                <h2>Institutional Policy Configuration</h2>
                <p>Digital rights management and security parameters for intellectual property protection.</p>
            </div>

            <div class="card" style="text-align: left; max-width: 800px;">
                <form method="POST">
                    <?php
                    // Fetch settings into an associative array for easier access
                    $settings_res = $conn->query("SELECT * FROM system_settings");
                    $settings = [];
                    while($row = $settings_res->fetch_assoc()) {
                        $settings[$row['setting_key']] = $row['setting_value'];
                    }
                    ?>
                    
                    <!-- Download Prevention Setting -->
                    <div class="form-group" style="margin-bottom: 2.5rem;">
                        <label style="font-weight: 800; text-transform: uppercase; font-size: 0.8rem; color: var(--text-light); display: block; margin-bottom: 0.5rem;">
                            Download Prevention
                        </label>
                        <select name="settings[download_prevention]" class="form-control" style="width: 100%; padding: 0.8rem;">
                            <option value="enabled" <?php echo ($settings['download_prevention'] ?? '') == 'enabled' ? 'selected' : ''; ?>>Enabled (Block right-click & Save-as)</option>
                            <option value="disabled" <?php echo ($settings['download_prevention'] ?? '') == 'disabled' ? 'selected' : ''; ?>>Disabled (Standard access)</option>
                        </select>
                        <small style="color: var(--text-light); display: block; margin-top: 0.5rem;">
                            When enabled, the system injects security scripts to prevent users from saving or downloading digital assets.
                        </small>
                    </div>

                    <!-- Max Preview Pages Setting -->
                    <div class="form-group" style="margin-bottom: 2.5rem;">
                        <label style="font-weight: 800; text-transform: uppercase; font-size: 0.8rem; color: var(--text-light); display: block; margin-bottom: 0.5rem;">
                            Max Preview Pages
                        </label>
                        <select name="settings[max_preview_pages]" class="form-control" style="width: 100%; padding: 0.8rem;">
                            <option value="10" <?php echo ($settings['max_preview_pages'] ?? '') == '10' ? 'selected' : ''; ?>>10 Pages Maximum</option>
                            <option value="20" <?php echo ($settings['max_preview_pages'] ?? '') == '20' ? 'selected' : ''; ?>>20 Pages Maximum</option>
                        </select>
                        <small style="color: var(--text-light); display: block; margin-top: 0.5rem;">
                            Sets the hard limitation for the number of pages a student can view within the digital reader.
                        </small>
                    </div>

                    <?php if (isset($settings['security_policy'])): ?>
                    <div class="form-group" style="margin-bottom: 2.5rem;">
                        <label style="font-weight: 800; text-transform: uppercase; font-size: 0.8rem; color: var(--text-light); display: block; margin-bottom: 0.5rem;">
                            Security Policy
                        </label>
                        <input type="text" name="settings[security_policy]" value="<?php echo htmlspecialchars($settings['security_policy']); ?>" style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <?php endif; ?>
                    
                    <button type="submit" name="update_settings" class="btn btn-primary" style="padding: 1.25rem 3.5rem; font-weight: 700;">Update Institutional Policies</button>
                </form>
            </div>

        <?php endif; ?>

    </main>
</div>

<?php renderFooter(); ?>
