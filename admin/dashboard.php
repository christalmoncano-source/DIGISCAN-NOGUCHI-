<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

// Enforce Admin Access
checkAccess('admin');

$page = $_GET['page'] ?? 'overview';
$page_title = "Admin Center - Noguchi Library";
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
    $dewey_decimal = trim($_POST['dewey_decimal'] ?? '');
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

    // Restoration: Scanned resource upload logic
    if (!empty($_FILES['book_file']['name'])) {
        $f_name = $_FILES['book_file']['name'];
        $f_tmp = $_FILES['book_file']['tmp_name'];
        $f_ext = strtolower(pathinfo($f_name, PATHINFO_EXTENSION));
        if ($f_ext == 'pdf') {
            $new_f_filename = 'book_' . uniqid() . ".pdf";
            if (move_uploaded_file($f_tmp, "../uploads/books/" . $new_f_filename)) {
                $file_path = "uploads/books/" . $new_f_filename;
            }
        }
    }

    $cover_path = "";
    $cover_dir = "../assets/img/covers/";
    if (!is_dir($cover_dir)) mkdir($cover_dir, 0777, true);
    $allowed_c_exts = ['jpg', 'jpeg', 'png', 'webp'];

    if (!empty($_FILES['cover_photo']['name'])) {
        $c_name = $_FILES['cover_photo']['name'];
        $c_tmp = $_FILES['cover_photo']['tmp_name'];
        $c_ext = strtolower(pathinfo($c_name, PATHINFO_EXTENSION));
        
        if (in_array($c_ext, $allowed_c_exts)) {
            $new_c_filename = 'cover_' . uniqid() . "." . $c_ext;
            if (move_uploaded_file($c_tmp, $cover_dir . $new_c_filename)) {
                $cover_path = "assets/img/covers/" . $new_c_filename;
            }
        }
    }

    // -- RESTORATION: Preview Pages Processing --
    $preview_pages_json = "[]";
    $final_previews = [];
    $preview_dir = "../assets/img/previews/";
    if (!is_dir($preview_dir)) mkdir($preview_dir, 0777, true);

    if ($book_id) {
        $old_b = $conn->query("SELECT preview_pages FROM books WHERE id = ".(int)$book_id)->fetch_assoc();
        $final_previews = json_decode($old_b['preview_pages'] ?? '[]', true) ?: [];
        
        // Handle deletions
        if (!empty($_POST['delete_previews'])) {
            $final_previews = array_values(array_diff($final_previews, $_POST['delete_previews']));
        }
    }

    // Process new uploads (Up to 10 slots)
    for ($i = 1; $i <= 10; $i++) {
        if (!empty($_FILES["preview_photo_$i"]['name'])) {
            $p_name = $_FILES["preview_photo_$i"]['name'];
            $p_tmp  = $_FILES["preview_photo_$i"]['tmp_name'];
            $p_ext  = strtolower(pathinfo($p_name, PATHINFO_EXTENSION));
            if (in_array($p_ext, $allowed_c_exts)) {
                $p_filename = 'prev_' . uniqid() . "_$i." . $p_ext;
                if (move_uploaded_file($p_tmp, $preview_dir . $p_filename)) {
                    $final_previews[] = "assets/img/previews/" . $p_filename;
                }
            }
        }
    }
    $preview_pages_json = json_encode(array_values($final_previews));

    if ($messageType != "error") {
        if ($book_id) {
            // Update Existing
            $sql = "UPDATE books SET title=?, author=?, edition=?, publication_place=?, publisher=?, publication_date=?, description=?, category=?, isbn=?, content_type=?, media_type=?, carrier_type=?, extent=?, chapter_info=?, total_copies=?, dewey_decimal=?, preview_pages=?, heyzine_url=?" 
                 . ($file_path ? ", file_path=?" : "") 
                 . ($cover_path ? ", cover_image=?" : "") 
                 . " WHERE id=?";
            
            $heyzine_url = trim($_POST['heyzine_url'] ?? '');
            
            $types = "ssssssssssssssisss" . ($file_path ? "s" : "") . ($cover_path ? "s" : "") . "i";
            $params = [$title, $author, $edition, $publication_place, $publisher, $publication_date, $description, $category, $isbn, $content_type, $media_type, $carrier_type, $extent, $chapter_info, $total_copies, $dewey_decimal, $preview_pages_json, $heyzine_url];
            if ($file_path) $params[] = $file_path;
            if ($cover_path) $params[] = $cover_path;
            $params[] = $book_id;
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
        } else {
            // New Insert
            $heyzine_url = trim($_POST['heyzine_url'] ?? '');
            $stmt = $conn->prepare("INSERT INTO books (title, author, edition, publication_place, publisher, publication_date, description, category, isbn, content_type, media_type, carrier_type, extent, chapter_info, total_copies, available_copies, preview_pages, file_path, cover_image, dewey_decimal, heyzine_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssssssssiisssss", $title, $author, $edition, $publication_place, $publisher, $publication_date, $description, $category, $isbn, $content_type, $media_type, $carrier_type, $extent, $chapter_info, $total_copies, $total_copies, $preview_pages_json, $file_path, $cover_path, $dewey_decimal, $heyzine_url);
        }

        if ($stmt->execute()) {
            $message = "Record updated. All attachments digitized successfully.";
            $messageType = "success";
            
            // Log Action
            $log_stmt = $conn->prepare("INSERT INTO system_logs (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $admin_id = $_SESSION['user_id'];
            $action = $book_id ? "UPDATE_BOOK_PREVIEW" : "ADD_BOOK_PREVIEW";
            $details = "Asset: $title (JSON: $preview_pages_json)";
            $ip = $_SERVER['REMOTE_ADDR'];
            $log_stmt->bind_param("isss", $admin_id, $action, $details, $ip);
            $log_stmt->execute();
        } else {
            $message = "Database Error: " . $conn->error;
            $messageType = "error";
        }
    }
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
        $log_stmt = $conn->prepare("INSERT INTO system_logs (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $admin_id = $_SESSION['user_id'];
        $action = "ARCHIVE_BOOK";
        $details = "Asset Archived: $title";
        $ip = $_SERVER['REMOTE_ADDR'];
        $log_stmt->bind_param("isss", $admin_id, $action, $details, $ip);
        $log_stmt->execute();
    }
}

// E. Reservation Management
if (isset($_GET['res_action']) && isset($_GET['rid'])) {
    $rid = (int)$_GET['rid'];
    $action = $_GET['res_action'];
    
    // Fetch reservation details
    $res_q = $conn->prepare("SELECT r.*, b.title, b.available_copies FROM reservations r JOIN books b ON r.book_id = b.id WHERE r.id = ?");
    $res_q->bind_param("i", $rid);
    $res_q->execute();
    $reservation = $res_q->get_result()->fetch_assoc();
    
    if ($reservation) {
        if ($action == 'approve') {
            if ($reservation['available_copies'] > 0) {
                // Set expiry to 48 hours from now
                $pickup_by = date('Y-m-d H:i:s', strtotime('+48 hours'));
                
                $conn->begin_transaction();
                try {
                    // Update reservation
                    $upd = $conn->prepare("UPDATE reservations SET status = 'approved', pickup_by = ? WHERE id = ?");
                    $upd->bind_param("si", $pickup_by, $rid);
                    $upd->execute();
                    
                    // Decrement copies
                    $conn->query("UPDATE books SET available_copies = available_copies - 1 WHERE id = " . (int)$reservation['book_id']);
                    
                    $conn->commit();
                    
                    // Notify User
                    require_once '../includes/notifications_helper.php';
                    $notif_msg = "Your reservation for '{$reservation['title']}' has been approved! Please pick it up by " . date('M d, H:i', strtotime($pickup_by)) . ".";
                    sendNotification($conn, $reservation['user_id'], "Reservation Approved", $notif_msg, 'success');
                    
                    $message = "Reservation approved and stock reserved.";
                    $messageType = "success";
                } catch (Exception $e) {
                    $conn->rollback();
                    $message = "Error processing approval.";
                    $messageType = "error";
                }
            } else {
                $message = "Cannot approve: Book is out of stock.";
                $messageType = "error";
            }
        } elseif ($action == 'decline') {
            $conn->query("UPDATE reservations SET status = 'cancelled' WHERE id = $rid");
            
            // Notify User
            require_once '../includes/notifications_helper.php';
            sendNotification($conn, $reservation['user_id'], "Reservation Declined", "We're sorry, your reservation for '{$reservation['title']}' has been declined.", 'alert');
            
            $message = "Reservation request declined.";
            $messageType = "success";
        } elseif ($action == 'pickup') {
            $conn->query("UPDATE reservations SET status = 'in_use' WHERE id = $rid");
            $message = "Book marked as in use.";
            $messageType = "success";
        } elseif ($action == 'return') {
            $conn->begin_transaction();
            try {
                $conn->query("UPDATE reservations SET status = 'returned' WHERE id = $rid");
                $conn->query("UPDATE books SET available_copies = available_copies + 1 WHERE id = " . (int)$reservation['book_id']);
                $conn->commit();
                
                $message = "Book marked as returned. Inventory updated.";
                $messageType = "success";
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Error processing return.";
                $messageType = "error";
            }
        }
    }
}

// F. Reservation Remarks Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_remarks'])) {
    $rid = (int)$_POST['res_id'];
    $remarks = trim($_POST['remarks']);
    
    $stmt = $conn->prepare("UPDATE reservations SET remarks = ? WHERE id = ?");
    $stmt->bind_param("si", $remarks, $rid);
    if ($stmt->execute()) {
        $message = "Reservation remarks updated.";
        $messageType = "success";
    }
}

// G. Mark Notification as Read
if (isset($_GET['read_notif'])) {
    $nid = (int)$_GET['read_notif'];
    $uid = $_SESSION['user_id'];
    $conn->query("UPDATE notifications SET is_read = 1 WHERE id = $nid AND user_id = $uid");
}

renderHeaderNoNav($page_title);
?>
<link rel="stylesheet" href="../assets/css/admin.css">
<link rel="stylesheet" href="../assets/css/flipbook.css">
<link rel="stylesheet" href="../assets/css/heyzine-viewer.css">


<div class="admin-layout">

    <!-- ══ SIDEBAR ══ -->
    <aside class="sb-shell">

        <!-- Brand -->
        <a href="?page=overview" class="sb-brand">
            <img src="../assets/img/library-logo.png" alt="Library Logo" class="sb-brand-icon">
            <div class="sb-brand-text">
                <strong>Noguchi Library</strong>
                <span>Admin</span>
            </div>
        </a>

        <!-- User card -->
        <div class="sb-user">
            <div class="sb-avatar"><?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?></div>
            <div class="sb-uinfo">
                <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>
                <span><?php echo strtoupper($_SESSION['role']); ?></span>
            </div>
        </div>

        <!-- Nav -->
        <nav class="sb-nav">
            <span class="sb-nav-label">Main</span>
            <a href="?page=overview" class="sb-link <?php echo $page=='overview'?'sb-active':''; ?>">
                <span class="sb-icon"><i class="fas fa-gauge-high"></i></span> Overview
            </a>
            <a href="?page=catalog" class="sb-link <?php echo ($page=='catalog'||$page=='edit_book'||$page=='preview')?'sb-active':''; ?>">
                <span class="sb-icon"><i class="fas fa-book-open"></i></span> Catalogs
            </a>
            <a href="?page=users" class="sb-link <?php echo $page=='users'?'sb-active':''; ?>">
                <span class="sb-icon"><i class="fas fa-users"></i></span> Users
            </a>
            <a href="?page=reservations" class="sb-link <?php echo $page=='reservations'?'sb-active':''; ?>">
                <span class="sb-icon"><i class="fas fa-calendar-check"></i></span> Reservations
            </a>



        </nav>

        <!-- Sign out -->
        <div class="sb-bottom">
            <a href="../registration/logout.php" class="sb-link sb-logout">
                <span class="sb-icon"><i class="fas fa-right-from-bracket"></i></span> Sign Out
            </a>
        </div>

    </aside>

    <!-- MAIN CONTENT -->
    <main class="sb-main admin-main-wrapper">
        <div class="sb-container">

        <!-- Feedback Messages -->
        <?php if ($message): ?>
            <div class="alert <?php echo $messageType == 'success' ? 'alert-success' : 'alert-danger'; ?>" style="margin-bottom: 2rem;">
                <i class="fas <?php echo $messageType == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($page == 'overview'): ?>
            <div class="welcome-banner" style="background: linear-gradient(135deg, rgba(30, 41, 59, 0.9) 0%, rgba(15, 23, 42, 0.9) 100%), url('../assets/img/noguchi_main.png'); background-size: cover; background-position: center; border-radius: 20px; padding: 4rem; color: white; margin-bottom: 2.5rem; text-align: left; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                <h1 style="font-size: 2.5rem; font-weight: 850; margin: 0 0 0.5rem 0; letter-spacing: -0.5px;">Admin Dashboard</h1>
                <p style="font-size: 1.1rem; opacity: 0.8; margin: 0;">Welcome, <?php echo explode(' ', $_SESSION['full_name'])[0]; ?>. You are managing the Noguchi Library Digital Assets.</p>
            </div>
             
            <!-- Stats Row -->
            <div style="display: grid; grid-template-columns: 1fr; gap: 2rem;">
                <!-- Quick Logs -->
                <div class="card" style="text-align: left;">
                    <h3 style="margin-top: 0; margin-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem;">Recent Activity</h3>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php
                        $logs = $conn->query("SELECT sl.*, u.full_name FROM system_logs sl JOIN users u ON sl.admin_id = u.id ORDER BY sl.created_at DESC LIMIT 10");
                        while($l = $logs->fetch_assoc()): ?>
                            <div style="padding: 0.75rem; border-bottom: 1px solid #f8fafc; font-size: 0.9rem;">
                                <span style="color: var(--text-light);"><?php echo date('M d, H:i', strtotime($l['created_at'])); ?>:</span> 
                                <strong><?php echo $l['full_name']; ?></strong> performed 
                                <code style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px;"><?php echo $l['action']; ?></code> 
                                <?php 
                                    // Make JSON output more readable if it's a long array
                                    $detailsText = htmlspecialchars($l['details']);
                                    if (strlen($detailsText) > 100) {
                                        $detailsText = substr($detailsText, 0, 100) . '...';
                                    }
                                ?>
                                (<?php echo $detailsText; ?>)
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

        <?php elseif ($page == 'catalog'): ?>
            <div class="dashboard-header" style="text-align: left; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2>Catalogs</h2>
                </div>
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <form method="GET" action="" style="display: flex; gap: 0.5rem; margin: 0; position: relative;">
                        <input type="hidden" name="page" value="catalog">
                        <input type="text" id="catalogSearch" name="search" autocomplete="off" placeholder="Search title or author..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" style="padding: 0.6rem 1rem; border-radius: 6px; border: 1px solid #cbd5e1; outline: none; width: 250px; font-family: inherit;">
                        <button type="submit" class="btn btn-secondary" style="padding: 0.6rem 1rem;"><i class="fas fa-search"></i></button>
                        
                        <!-- Suggestion Box -->
                        <div id="searchSuggestions" style="display: none; position: absolute; top: 100%; left: 0; width: 250px; background: white; border: 1px solid #e2e8f0; border-radius: 6px; margin-top: 4px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); z-index: 50; max-height: 250px; overflow-y: auto;">
                        </div>
                    </form>
                    <a href="?page=edit_book" class="btn btn-primary"><i class="fas fa-plus"></i> Add Book</a>
                </div>
                
                <?php
                // Fetch basic book details for JS autocomplete
                $all_books_q = $conn->query("SELECT id, title, author FROM books");
                $books_array = [];
                while($bk = $all_books_q->fetch_assoc()) {
                    $books_array[] = $bk;
                }
                ?>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const searchInput = document.getElementById('catalogSearch');
                    const suggestionsBox = document.getElementById('searchSuggestions');
                    const books = <?php echo json_encode($books_array); ?>;
                    
                    searchInput.addEventListener('input', function() {
                        const val = this.value.toLowerCase().trim();
                        suggestionsBox.innerHTML = '';
                        
                        if (val.length > 0) {
                            const matches = books.filter(b => b.title.toLowerCase().includes(val) || b.author.toLowerCase().includes(val));
                            if (matches.length > 0) {
                                matches.slice(0, 6).forEach(match => {
                                    const div = document.createElement('div');
                                    div.style.padding = '10px 15px';
                                    div.style.cursor = 'pointer';
                                    div.style.borderBottom = '1px solid #f1f5f9';
                                    div.style.fontSize = '0.9rem';
                                    div.style.transition = 'background 0.2s';
                                    
                                    // Highlight match safely
                                    // Escape string for regex
                                    const escapeRegExp = (string) => string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                                    const regex = new RegExp(`(${escapeRegExp(val)})`, "gi");
                                    const highlightedTitle = match.title.replace(regex, "<span style='color: var(--primary-color); font-weight: bold;'>$1</span>");
                                    
                                    div.innerHTML = `<div style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><strong>${highlightedTitle}</strong></div>
                                                     <div style="color: #94a3b8; font-size: 0.8rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">by ${match.author}</div>`;
                                    
                                    div.addEventListener('mouseover', () => div.style.background = '#f8fafc');
                                    div.addEventListener('mouseout', () => div.style.background = 'transparent');
                                    
                                    // Click to view the asset directly
                                    div.addEventListener('click', () => {
                                        window.location.href = `?page=preview&id=${match.id}`;
                                    });
                                    
                                    suggestionsBox.appendChild(div);
                                });
                                suggestionsBox.style.display = 'block';
                            } else {
                                suggestionsBox.innerHTML = '<div style="padding: 10px 15px; color: #94a3b8; font-size: 0.9rem;">No titles found</div>';
                                suggestionsBox.style.display = 'block';
                            }
                        } else {
                            suggestionsBox.style.display = 'none';
                        }
                    });
                    
                    document.addEventListener('click', function(e) {
                        if (!searchInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
                            suggestionsBox.style.display = 'none';
                        }
                    });
                });
                </script>
            </div>

            <div class="card" style="padding: 0; overflow: hidden; text-align: left;">
                <div style="overflow-x: auto;">
                    <table>
                    <thead>
                        <tr>
                            <th>Dewey Decimal No.</th>
                            <th>Book Details</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $search = $_GET['search'] ?? '';
                        if ($search !== '') {
                            $stmt = $conn->prepare("SELECT * FROM books WHERE title LIKE CONCAT('%', ?, '%') OR author LIKE CONCAT('%', ?, '%') OR dewey_decimal LIKE CONCAT('%', ?, '%') ORDER BY id DESC");
                            $stmt->bind_param("sss", $search, $search, $search);
                            $stmt->execute();
                            $res = $stmt->get_result();
                        } else {
                            $res = $conn->query("SELECT * FROM books ORDER BY id DESC");
                        }
                        while($row = $res->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['dewey_decimal'] ?: 'N/A'); ?></td>
                                <td style="display: flex; align-items: center; gap: 1rem;">
                                    <div style="width: 40px; height: 55px; border-radius: 4px; overflow: hidden; background: #f1f5f9; flex-shrink: 0; border: 1px solid #e2e8f0;">
                                        <?php
                                        $ci_raw = $row['cover_image'] ?? '';
                                        $ci_decoded = json_decode($ci_raw, true);
                                        $ci_first = is_array($ci_decoded) && !empty($ci_decoded) ? $ci_decoded[0] : ((!empty($ci_raw) && $ci_raw[0] !== '[') ? $ci_raw : '');
                                        $ci_src = !empty($ci_first) ? '../'.$ci_first : '../assets/img/book-placeholder.jpg';
                                        ?>
                                        <img src="<?php echo htmlspecialchars($ci_src); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($row['title']); ?></strong><br>
                                        <small style="color: var(--text-light);">by <?php echo htmlspecialchars($row['author']); ?></small>
                                    </div>
                                </td>
                                <td><span class="badge" style="background: #f1f5f9; color: var(--primary-color);"><?php echo htmlspecialchars($row['category'] ?: 'Uncategorized'); ?></span></td>
                                <td>
                                    <span class="status-badge <?php echo $row['available_copies']>0?'status-active':'status-pending'; ?>">
                                        <?php echo $row['available_copies']>0?'IN STOCK':'IN USE'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?page=preview&id=<?php echo $row['id']; ?>" class="btn btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;" title="View Asset"><i class="fas fa-eye"></i></a>
                                    <a href="?page=edit_book&id=<?php echo $row['id']; ?>" class="btn btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;" title="Edit Asset"><i class="fas fa-edit"></i></a>
                                    <a href="?page=catalog&delete=<?php echo $row['id']; ?>" class="btn btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.8rem; color: var(--error-color);" onclick="return confirm('Archive this asset?')" title="Delete Asset"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                </div>
            </div>

        <?php elseif ($page == 'edit_book'): 
            $edit_id = $_GET['id'] ?? null;
            $b = $edit_id ? $conn->query("SELECT * FROM books WHERE id = ".(int)$edit_id)->fetch_assoc() : null;
            ?>
            <div class="dashboard-header" style="text-align: left; margin-bottom: 2rem;">
                <h2><?php echo $b ? 'Edit Book Record' : 'Add New Book'; ?></h2>
            </div>

            <div class="card" style="text-align: left; padding: 2.5rem;">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="book_id" value="<?php echo $b['id'] ?? ''; ?>">

                    <!-- Section 1: Basic Information -->
                    <h4 style="color: var(--primary-color); border-bottom: 1px solid #eef2ff; padding-bottom: 0.5rem; margin-bottom: 1.5rem;">MARC View</h4>
                    <div class="grid-responsive" style="display: grid; gap: 1.5rem; margin-bottom: 1.5rem;">
                        <div class="form-group"><label>Title</label><input type="text" name="title" value="<?php echo htmlspecialchars($b['title'] ?? ''); ?>" required></div>
                        <div class="form-group"><label>Author</label><input type="text" name="author" value="<?php echo htmlspecialchars($b['author'] ?? ''); ?>" required></div>
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category" required style="width: 100%;">
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
                    
                    <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 1.5rem; margin-bottom: 1.5rem;">
                        <div class="form-group"><label>Edition</label><input type="text" name="edition" value="<?php echo htmlspecialchars($b['edition'] ?? ''); ?>" placeholder="e.g., 1st Edition"></div>
                        <div class="form-group"><label>Publication Place</label><input type="text" name="publication_place" value="<?php echo htmlspecialchars($b['publication_place'] ?? ''); ?>" placeholder="Place"></div>
                        <div class="form-group"><label>Publisher</label><input type="text" name="publisher" value="<?php echo htmlspecialchars($b['publisher'] ?? ''); ?>" placeholder="Publisher"></div>
                        <div class="form-group"><label>Publication Year</label><input type="text" name="publication_date"  value="<?php echo htmlspecialchars($b['publication_date'] ?? ''); ?>" placeholder="Year"></div>
                        <div class="form-group"><label>Dewey Decimal No.</label><input type="text" name="dewey_decimal" value="<?php echo htmlspecialchars($b['dewey_decimal'] ?? ''); ?>" placeholder="e.g., 005.133"></div>
                    </div>

                    <div style="display: grid; grid-template-columns: 15% 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                        <div class="form-group">
                            <label>Physical Copies</label>
                            <input type="number" name="total_copies" value="<?php echo htmlspecialchars($b['total_copies'] ?? '1'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Heyzine Flipbook URL</label>
                            <input type="url" name="heyzine_url" value="<?php echo htmlspecialchars($b['heyzine_url'] ?? ''); ?>" placeholder="https://heyzine.com/flip-book/...">
                        </div>
                    </div>

                    <!-- Section 3: Cover & Previews -->
                    <h4 style="color: var(--primary-color); border-bottom: 1px solid #eef2ff; padding-bottom: 0.5rem; margin: 2rem 0 1.5rem;">Upload Cover Photo & Previews</h4> 
                    <div style="margin-bottom: 2.5rem;">
                        <div class="form-group" style="padding: 1.5rem; background: #f8fafc; border-radius: 12px; border: 1px dashed #e2e8f0;">
                            <label style="margin-bottom: 1rem;"><i class="fas fa-image"></i> Cover Photo</label>
                            <input type="file" name="cover_photo" accept="image/*" style="display: block; margin-top: 0.5rem; margin-bottom: 1rem;">
                            <?php 
                            $c_raw = $b['cover_image'] ?? '';
                            $c_dec = json_decode($c_raw, true);
                            $c_disp = is_array($c_dec) && !empty($c_dec) ? $c_dec[0] : ((!empty($c_raw) && $c_raw[0] !== '[') ? $c_raw : '');
                            if (!empty($c_disp)): ?>
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 2rem;">
                                    <img src="../<?php echo htmlspecialchars($c_disp); ?>" style="width: 40px; height: 55px; object-fit: cover; border-radius: 4px;">
                                    <span style="font-size: 0.85rem; color: var(--success-color); font-weight: 700;">Current Cover</span>
                                </div>
                            <?php endif; ?>

                            <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 1.5rem 0;">

                            <label style="margin-bottom: 1rem; font-weight: 700;"><i class="fas fa-images"></i> Preview Attachments <span style="font-weight: 400; color: var(--text-light); font-size: 0.82rem;">(up to 10 images for previewing — JPG, PNG, WebP)</span></label>

                            <?php
                            $existing_display = [];
                            if (!empty($b['preview_pages'])) {
                                $dec = json_decode($b['preview_pages'], true);
                                $existing_display = is_array($dec) ? $dec : [];
                            }
                            ?>

                            <?php if (!empty($existing_display)): ?>
                            <div style="margin-bottom: 1.25rem;">
                                <p style="font-size: 0.8rem; color: var(--text-light); margin-bottom: 0.6rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Current Previews — tick to delete</p>
                                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                                    <?php foreach ($existing_display as $idx => $cp): ?>
                                    <label style="position: relative; cursor: pointer;" title="Check to remove">
                                        <img src="../<?php echo htmlspecialchars($cp); ?>" style="width: 64px; height: 90px; object-fit: cover; border-radius: 6px; border: 2px solid #e2e8f0; display: block; transition: opacity 0.2s;" class="existing-cover-thumb">
                                        <input type="checkbox" name="delete_previews[]" value="<?php echo htmlspecialchars($cp); ?>"
                                               style="position: absolute; top: 4px; right: 4px; width: 16px; height: 16px; accent-color: #ef4444; cursor: pointer;"
                                               onchange="this.closest('label').querySelector('img').style.opacity = this.checked ? '0.35' : '1'">
                                        <span style="display: block; text-align: center; font-size: 0.7rem; color: #94a3b8; margin-top: 3px;">#<?php echo $idx + 1; ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- 10 upload slots -->
                            <p style="font-size: 0.8rem; color: var(--text-light); margin-bottom: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Upload New Previews</p>
                            <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px;">
                                <?php for ($ci = 1; $ci <= 10; $ci++): ?>
                                <div style="display: flex; flex-direction: column; align-items: center; gap: 6px;">
                                    <label for="preview_slot_<?php echo $ci; ?>" style="
                                        width: 70px; height: 95px; border: 2px dashed #cbd5e1; border-radius: 8px;
                                        display: flex; flex-direction: column; align-items: center; justify-content: center;
                                        cursor: pointer; background: #fff; overflow: hidden; position: relative;
                                        transition: border-color 0.2s, background 0.2s;
                                    " onmouseover="this.style.borderColor='var(--primary-color)'" onmouseout="this.style.borderColor='#cbd5e1'">
                                        <img id="preview_img_<?php echo $ci; ?>" src="" alt=""
                                             style="display: none; width: 100%; height: 100%; object-fit: cover; position: absolute; top:0; left:0; border-radius: 6px;">
                                        <span class="slot-placeholder-<?php echo $ci; ?>" style="text-align:center; color:#94a3b8; font-size: 0.7rem; line-height:1.4; padding: 4px;">
                                            <i class="fas fa-plus" style="font-size: 1.2rem; display:block; margin-bottom:4px;"></i>Slot <?php echo $ci; ?>
                                        </span>
                                    </label>
                                    <input type="file" id="preview_slot_<?php echo $ci; ?>" name="preview_photo_<?php echo $ci; ?>" accept="image/*"
                                           style="display: none;"
                                           onchange="previewAttachment(this, <?php echo $ci; ?>)">
                                    <span style="font-size: 0.68rem; color: #94a3b8; font-weight: 600;">Image <?php echo $ci; ?></span>
                                </div>
                                <?php endfor; ?>
                            </div>

                            <script>
                            function previewAttachment(input, slot) {
                                const preview = document.getElementById('preview_img_' + slot);
                                const placeholder = document.querySelector('.slot-placeholder-' + slot);
                                if (input.files && input.files[0]) {
                                    const reader = new FileReader();
                                    reader.onload = function(e) {
                                        preview.src = e.target.result;
                                        preview.style.display = 'block';
                                        if (placeholder) placeholder.style.display = 'none';
                                    };
                                    reader.readAsDataURL(input.files[0]);
                                } else {
                                    preview.style.display = 'none';
                                    preview.src = '';
                                    if (placeholder) placeholder.style.display = 'block';
                                }
                            }
                            </script>
                        </div>
                    </div>

                    <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                        <button type="submit" name="save_book" class="btn btn-primary" style="padding: 1rem 3rem; width: auto; min-width: 200px;">Save Record</button>
                        <a href="?page=catalog" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        <?php elseif ($page == 'preview'): 
            $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
            if (!$id) { echo "<script>window.location='?page=catalog';</script>"; exit; }
            
            $stmt = $conn->prepare("SELECT * FROM books WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $b = $stmt->get_result()->fetch_assoc();
            if (!$b) { echo "<script>window.location='?page=catalog';</script>"; exit; }
            $ci_raw = $b['cover_image'] ?? '';
            $ci_dec = json_decode($ci_raw, true);
            $ci_first = is_array($ci_dec) && !empty($ci_dec) ? $ci_dec[0] : ((!empty($ci_raw) && $ci_raw[0] !== '[') ? $ci_raw : '');
            $ci_src = !empty($ci_first) ? '../'.$ci_first : '../assets/img/book-placeholder.jpg';
            
            // Prepare flipbook pages for admin
            $adm_pp_raw = $b['preview_pages'] ?? '';
            $adm_pp_dec = json_decode($adm_pp_raw, true) ?: [];
            $adm_all_pages = [$ci_src];
            foreach($adm_pp_dec as $p) { $adm_all_pages[] = '../' . $p; }
        ?>
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem;">
                    <a href="?page=catalog" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Catalog</a>
                    <a href="?page=edit_book&id=<?php echo $id; ?>" class="btn btn-primary"><i class="fas fa-edit"></i> Edit Record</a>
                </div>

                <!-- Header: Title & Author ABOVE Flipbook -->
                <header style="text-align: center; margin-bottom: 3rem;">
                    <div style="display: flex; justify-content: center; gap: 0.75rem; align-items: center; margin-bottom: 1rem;">
                        <span class="badge" style="background: #eef2ff; color: #6366f1;"><?php echo htmlspecialchars($b['category']); ?></span>
                        <span style="background: #6366f1; color: white; padding: 4px 12px; border-radius: 8px; font-size: 0.75rem; font-weight: 800;">
                            DEWEY: <?php echo htmlspecialchars($b['dewey_decimal'] ?: 'N/A'); ?>
                        </span>
                    </div>
                    <h1 style="font-size: 2.8rem; font-weight: 850; color: #0f172a; margin: 0;"><?php echo htmlspecialchars($b['title']); ?></h1>
                    <p style="font-size: 1.25rem; color: #64748b; margin-top: 1rem;">by <?php echo htmlspecialchars($b['author']); ?></p>
                </header>

                <div class="card" style="padding: 3rem; margin-bottom: 3rem;">
                    <div style="max-width: 900px; margin: 0 auto;">
                        <?php if (!empty($b['heyzine_url'])): ?>
                            <!-- Heyzine Flipbook -->
                            <div class="flipbook-wrapper">
                                <div class="flipbook-container">
                                    <div class="flipbook-loading"><i class="fas fa-spinner fa-spin"></i> Loading Flipbook...</div>
                                    <iframe src="<?php echo htmlspecialchars($b['heyzine_url']); ?>" class="heyzine-iframe" allowfullscreen allow="clipboard-write"></iframe>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Admin Flipbook Fallback -->
                            <div class="fb-wrap">
                                <div id="adm-flipbook" class="fb-book is-cover">
                                    <div class="fb-spine"></div>
                                    <div class="fb-panel fb-panel-left"><div id="adm-fb-left" class="fb-page-img"></div></div>
                                    <div class="fb-panel fb-panel-right">
                                        <div id="adm-fb-right" class="fb-page-img"></div>
                                        <div id="adm-fb-end" class="fb-end-card">
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
                                    <div class="fb-flip-wrap" id="adm-fb-flip-wrap">
                                        <div class="fb-flip-page" id="adm-fb-flip-page">
                                            <div class="fb-flip-front" id="adm-fb-flip-front"></div>
                                            <div class="fb-flip-back" id="adm-fb-flip-back"></div>
                                            <div class="fb-flip-shadow" id="adm-fb-flip-shadow"></div>
                                        </div>
                                    </div>
                                    <button id="adm-fb-prev" class="fb-nav"><i class="fas fa-chevron-left"></i></button>
                                    <button id="adm-fb-next" class="fb-nav"><i class="fas fa-chevron-right"></i></button>
                                </div>
                                <div class="fb-info">
                                    <div class="fb-pill"><span id="adm-fb-page-num">1</span> / <span id="adm-fb-total-pages">1</span></div>
                                    <div class="fb-hint-pill">Click corners or use arrows</div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="max-width: 850px; margin: 4rem auto 0; text-align: center;">
                    <div style="margin-bottom: 4rem;">
                        <h4 style="border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem; margin-bottom: 1.5rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 800; font-size: 0.85rem; color: #94a3b8; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-align-left" style="color: #6366f1; margin-right: 12px;"></i> Material Abstract
                        </h4>
                        <p style="line-height: 1.8; color: #475569; text-align: justify; font-size: 1.05rem; max-width: 800px; margin: 0 auto;"><?php echo nl2br(htmlspecialchars($b['description'])); ?></p>
                    </div>

                    <div style="background: #f8fafc; padding: 2.5rem; border-radius: 20px; border: 1px solid #e2e8f0; text-align: left; max-width: 650px; margin: 0 auto;">
                        <label style="display:block; font-size: 0.7rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 1rem; text-align: center;">Reference Information</label>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                            <div>
                                <label style="display: block; font-size: 0.65rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px;">Catalog No</label>
                                <span style="font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($b['dewey_decimal'] ?: 'N/A'); ?></span>
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.65rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px;">Stock Level</label>
                                <span style="font-weight: 700; color: #1e293b;"><?php echo $b['available_copies']; ?> / <?php echo $b['total_copies']; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Suggested Books (Admin View) -->
                <?php
                $adm_s_res = $conn->query("SELECT * FROM books WHERE category = '".addslashes($b['category'])."' AND id != $id LIMIT 4");
                if ($adm_s_res->num_rows > 0): ?>
                    <div style="margin-top: 5rem;">
                        <h3 style="font-size: 1.3rem; font-weight: 850; color: #1e293b; margin-bottom: 2rem;">Thematically Related Books</h3>
                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 2rem;">
                            <?php while($sb = $adm_s_res->fetch_assoc()): 
                                 $s_ci_raw = $sb['cover_image'] ?? '';
                                 $s_ci_dec = json_decode($s_ci_raw, true);
                                 $s_ci_first = is_array($s_ci_dec) && !empty($s_ci_dec) ? $s_ci_dec[0] : ((!empty($s_ci_raw) && $s_ci_raw[0] !== '[') ? $s_ci_raw : '');
                                 $s_src = !empty($s_ci_first) ? '../'.$s_ci_first : '../assets/img/library-logo.png';
                            ?>
                                <a href="?page=preview&id=<?php echo $sb['id']; ?>" style="text-decoration: none; color: inherit;">
                                    <div style="height: 200px; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; margin-bottom: 0.75rem;">
                                        <img src="<?php echo htmlspecialchars($s_src); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                    <h5 style="margin:0; font-size: 0.85rem; font-weight: 800;"><?php echo htmlspecialchars($sb['title']); ?></h5>
                                </a>
                            <?php endwhile; ?>
                        </div>
                    </div>
                <?php endif; ?>
                </div>

                <script>
                (function() {
                    const pages = <?php echo json_encode($adm_all_pages); ?>;
                    let currentPage = 1;
                    const totalPages = pages.length;

                    const book = document.getElementById('adm-flipbook');
                    const leftPage = document.getElementById('adm-fb-left');
                    const rightPage = document.getElementById('adm-fb-right');
                    const endCard = document.getElementById('adm-fb-end');
                    const pageNumDisplay = document.getElementById('adm-fb-page-num');
                    const totalDisplay = document.getElementById('adm-fb-total-pages');
                    
                    const prevBtn = document.getElementById('adm-fb-prev');
                    const nextBtn = document.getElementById('adm-fb-next');
                    
                    const flipWrap = document.getElementById('adm-fb-flip-wrap');
                    const flipPage = document.getElementById('adm-fb-flip-page');
                    const flipFront = document.getElementById('adm-fb-flip-front');
                    const flipBack = document.getElementById('adm-fb-flip-back');

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
                    updateView();
                })();
                </script>
<?php elseif ($page == 'users'): ?>
            <div class="dashboard-header" style="text-align: left; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2>User Center</h2>
                </div>
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <form method="GET" action="" style="display: flex; gap: 0.5rem; margin: 0;">
                        <input type="hidden" name="page" value="users">
                        <input type="text" name="user_search" placeholder="Search name or email..." value="<?php echo htmlspecialchars($_GET['user_search'] ?? ''); ?>" style="padding: 0.6rem 1rem; border-radius: 6px; border: 1px solid #cbd5e1; outline: none; width: 250px; font-family: inherit;">
                        <button type="submit" class="btn btn-secondary" style="padding: 0.6rem 1rem;"><i class="fas fa-search"></i></button>
                    </form>
                </div>
            </div>

            <div class="card" style="padding: 0; overflow: hidden; text-align: left;">
                <div style="overflow-x: auto;">
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
                        $user_search = $_GET['user_search'] ?? '';
                        if ($user_search !== '') {
                            $stmt = $conn->prepare("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.full_name LIKE CONCAT('%', ?, '%') OR u.email LIKE CONCAT('%', ?, '%') ORDER BY u.id DESC");
                            $stmt->bind_param("ss", $user_search, $user_search);
                            $stmt->execute();
                            $users = $stmt->get_result();
                        } else {
                            $users = $conn->query("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.id ORDER BY u.id DESC");
                        }
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
            </div>


        <?php elseif ($page == 'reservations'): ?>
            <div class="dashboard-header">
                <div>
                    <h2>Pending Reservations</h2>
                    <p>Manage and review physical book reservation requests.</p>
                </div>
            </div>

            <div class="card">
                <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Book Asset</th>
                            <th>Student Name</th>
                            <th>Request Date</th>
                            <th>Current Stock</th>
                            <th>Remarks</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $res_list = $conn->query("SELECT r.*, u.full_name, b.title, b.available_copies 
                                                 FROM reservations r 
                                                 JOIN users u ON r.user_id = u.id 
                                                 JOIN books b ON r.book_id = b.id 
                                                 WHERE r.status = 'pending' 
                                                 ORDER BY r.created_at ASC");
                        if ($res_list->num_rows > 0):
                            while($row = $res_list->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['title']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($row['created_at'])); ?></td>
                                    <td>
                                        <span class="badge" style="background: <?php echo $row['available_copies'] > 0 ? '#f0fdf4' : '#fef2f2'; ?>; color: <?php echo $row['available_copies'] > 0 ? '#166534' : '#991b1b'; ?>;">
                                            <?php echo $row['available_copies']; ?> Available
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: flex; gap: 5px;">
                                            <input type="hidden" name="res_id" value="<?php echo $row['id']; ?>">
                                            <input type="text" name="remarks" value="<?php echo htmlspecialchars($row['remarks'] ?? ''); ?>" placeholder="Add remarks..." style="font-size: 0.8rem; padding: 4px 8px; border: 1px solid #e2e8f0; border-radius: 4px; width: 120px;">
                                            <button type="submit" name="save_remarks" class="btn btn-secondary" style="padding: 4px 8px; font-size: 0.7rem;"><i class="fas fa-save"></i></button>
                                        </form>
                                    </td>
                                    <td style="display: flex; gap: 0.5rem;">
                                        <?php if ($row['available_copies'] > 0): ?>
                                            <a href="?page=reservations&res_action=approve&rid=<?php echo $row['id']; ?>" class="btn btn-primary" style="padding: 0.4rem 1rem; font-size: 0.8rem;">
                                                <i class="fas fa-check"></i> Approve
                                            </a>
                                        <?php endif; ?>
                                        <a href="?page=reservations&res_action=decline&rid=<?php echo $row['id']; ?>" class="btn btn-secondary" style="padding: 0.4rem 1rem; font-size: 0.8rem; color: var(--error-color);">
                                            <i class="fas fa-times"></i> Decline
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; 
                        else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 3rem; color: var(--text-light);">
                                    <i class="fas fa-inbox" style="font-size: 2rem; display: block; margin-bottom: 1rem; opacity: 0.2;"></i>
                                    No pending reservation requests found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>

            <!-- Recently Approved (History) -->
            <div class="dashboard-header" style="margin-top: 4rem;">
                <h3>Active Reservations</h3>
            </div>
            <div class="card">
                <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Book Asset</th>
                            <th>Student Name</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Remarks</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $hist_list = $conn->query("SELECT r.*, u.full_name, b.title 
                                                  FROM reservations r 
                                                  JOIN users u ON r.user_id = u.id 
                                                  JOIN books b ON r.book_id = b.id 
                                                  WHERE r.status IN ('approved', 'in_use') 
                                                  ORDER BY r.pickup_by ASC");
                        if ($hist_list->num_rows > 0):
                            while($row = $hist_list->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                    <td>
                                        <span style="<?php echo strtotime($row['pickup_by']) < time() ? 'color: var(--error-color); font-weight: bold;' : ''; ?>">
                                            <?php echo date('M d, Y H:i', strtotime($row['pickup_by'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $row['status'] == 'approved' ? 'status-active' : 'status-pending'; ?>" style="<?php echo $row['status'] == 'in_use' ? 'background: #dcfce7; color: #166534;' : ''; ?>">
                                            <?php echo strtoupper(str_replace('_', ' ', $row['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: flex; gap: 5px;">
                                            <input type="hidden" name="res_id" value="<?php echo $row['id']; ?>">
                                            <input type="text" name="remarks" value="<?php echo htmlspecialchars($row['remarks'] ?? ''); ?>" placeholder="Add remarks..." style="font-size: 0.8rem; padding: 4px 8px; border: 1px solid #e2e8f0; border-radius: 4px; width: 120px;">
                                            <button type="submit" name="save_remarks" class="btn btn-secondary" style="padding: 4px 8px; font-size: 0.7rem;"><i class="fas fa-save"></i></button>
                                        </form>
                                    </td>
                                    <td style="display: flex; gap: 0.5rem;">
                                        <?php if ($row['status'] == 'approved'): ?>
                                            <a href="?page=reservations&res_action=pickup&rid=<?php echo $row['id']; ?>" class="btn btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.75rem;">
                                            In Use (Library Only)
                                            </a>
                                        <?php elseif ($row['status'] == 'in_use'): ?>
                                            <a href="?page=reservations&res_action=return&rid=<?php echo $row['id']; ?>" class="btn btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.75rem; background: var(--success-color);">
                                            Mark Returned
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; 
                        else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-light);">No active reservations.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>

            <!-- Fulfillment History -->
            <div class="dashboard-header" style="margin-top: 4rem;">
                <h3>Fulfillment & Return History</h3>
                <p>Closed reservation records and returned assets.</p>
            </div>
            <div class="card">
                <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Book Asset</th>
                            <th>Student Name</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $closed_list = $conn->query("SELECT r.*, u.full_name, b.title 
                                                   FROM reservations r 
                                                   JOIN users u ON r.user_id = u.id 
                                                   JOIN books b ON r.book_id = b.id 
                                                   WHERE r.status IN ('returned', 'cancelled', 'expired') 
                                                   ORDER BY r.updated_at DESC LIMIT 20");
                        if ($closed_list->num_rows > 0):
                            while($row = $closed_list->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($row['updated_at'])); ?></td>
                                    <td>
                                        <span class="status-badge" style="background: <?php 
                                            echo $row['status'] == 'returned' ? '#f0fdf4; color: #166534;' : '#fef2f2; color: #991b1b;'; ?>">
                                            <?php echo strtoupper($row['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: flex; gap: 5px;">
                                            <input type="hidden" name="res_id" value="<?php echo $row['id']; ?>">
                                            <input type="text" name="remarks" value="<?php echo htmlspecialchars($row['remarks'] ?? ''); ?>" placeholder="Edit remarks..." style="font-size: 0.8rem; padding: 4px 8px; border: 1px solid #e2e8f0; border-radius: 4px; width: 120px;">
                                            <button type="submit" name="save_remarks" class="btn btn-secondary" style="padding: 4px 8px; font-size: 0.7rem;"><i class="fas fa-save"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; 
                        else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-light);">No history records yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>



        <?php endif; ?>

        </div><!-- /.sb-container -->
    </main>
</div>

<?php renderFooter(); ?>
