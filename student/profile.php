<?php
/**
 * Student Profile Page - Restored & Consistent UI
 */
require_once '../includes/auth.php';
require_once '../config/db.php';
global $conn;
checkAccess(['student', 'admin']);

$user_id = $_SESSION['user_id'];
$u_res = $conn->query("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = $user_id");
$u = $u_res->fetch_assoc();

// Statistics
$total_history = $conn->query("SELECT COUNT(*) FROM borrowings WHERE user_id = $user_id")->fetch_row()[0];
$active_loans = $conn->query("SELECT COUNT(*) FROM borrowings WHERE user_id = $user_id AND status IN ('borrowed', 'overdue')")->fetch_row()[0];

// Handle Name Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_name'])) {
    $new_name = trim($_POST['full_name']);
    if (!empty($new_name)) {
        $stmt = $conn->prepare("UPDATE users SET full_name = ? WHERE id = ?");
        $stmt->bind_param("si", $new_name, $user_id);
        if ($stmt->execute()) {
            $_SESSION['full_name'] = $new_name; // Update session
            $_SESSION['message'] = "Preferred name updated successfully!";
            header("Location: profile.php");
            exit();
        } else {
            $_SESSION['error'] = "Failed to update name. Please try again.";
        }
    } else {
        $_SESSION['error'] = "Name cannot be empty.";
    }
}


renderHeaderNoNav("My Profile - Noguchi Library");
?>
<link rel="stylesheet" href="../assets/css/student.css">
<style>
    .profile-grid {
        display: grid;
        grid-template-columns: 320px 1fr;
        gap: 3rem;
        max-width: 1200px;
        margin: 2rem auto;
    }
    .profile-card {
        background: white;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        padding: 2.5rem;
        text-align: center;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    }
    .avatar-large {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: #6366f1;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3.5rem;
        font-weight: 800;
        margin: 0 auto 1.5rem;
    }
    .info-group {
        text-align: left;
        margin-bottom: 2rem;
    }
    .info-group label {
        display: block;
        font-size: 0.75rem;
        font-weight: 800;
        color: #94a3b8;
        text-transform: uppercase;
        margin-bottom: 0.5rem;
    }
    .info-group p {
        font-size: 1.1rem;
        font-weight: 600;
        color: #1e293b;
        margin: 0;
    }
    .table-section {
        background: white;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        margin-bottom: 3rem;
    }
    .table-section h3 {
        padding: 1.5rem 2rem;
        margin: 0;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        font-size: 1.1rem;
        font-weight: 800;
        color: #1e293b;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    th {
        text-align: left;
        padding: 1rem 2rem;
        background: #f8fafc;
        font-size: 0.75rem;
        text-transform: uppercase;
        color: #64748b;
        letter-spacing: 0.05em;
    }
    td {
        padding: 1.25rem 2rem;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.95rem;
        color: #475569;
    }
    .status-pill {
        padding: 4px 12px;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
    }
</style>

<div class="dash-wrap">
    <?php $active_page = 'profile'; include '../includes/sidebar_student.php'; ?>

    <main class="sb-main">
        <header style="margin-bottom: 3rem;">
            <h1 style="font-size: 2.5rem; font-weight: 850; color: #0f172a; margin: 0;">Account Settings</h1>
        </header>

        <?php if (isset($_SESSION['message'])): ?>
            <div style="background-color: #dcfce7; border-left: 4px solid #16a34a; padding: 1rem 1.5rem; border-radius: 0 12px 12px 0; margin-bottom: 2rem; color: #166534; font-weight: 600;">
                <i class="fas fa-check-circle" style="margin-right: 0.5rem;"></i> <?php echo htmlspecialchars($_SESSION['message']); ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div style="background-color: #fee2e2; border-left: 4px solid #dc2626; padding: 1rem 1.5rem; border-radius: 0 12px 12px 0; margin-bottom: 2rem; color: #991b1b; font-weight: 600;">
                <i class="fas fa-exclamation-circle" style="margin-right: 0.5rem;"></i> <?php echo htmlspecialchars($_SESSION['error']); ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div style="background-color: #eff6ff; border-left: 4px solid #3b82f6; padding: 1.25rem 1.5rem; border-radius: 0 12px 12px 0; margin-bottom: 2.5rem; display: flex; gap: 1rem; align-items: flex-start; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
            <i class="fas fa-info-circle" style="color: #3b82f6; font-size: 1.25rem; margin-top: 0.125rem;"></i>
            <div>
                <h3 style="margin: 0 0 0.35rem 0; color: #1e3a8a; font-weight: 800;">Reservation Policy Notice</h3>
                <p style="margin: 0; color: #1e40af; font-size: 0.97rem; line-height: 1.5;">Please be informed that once you reserve a book, it will be held for <strong>3 days</strong>. If you do not physically visit the Noguchi Library within this period, your reservation will be automatically removed.</p>
            </div>
        </div>

        <div class="profile-grid">
            <aside>
                <div class="profile-card">
                    <div class="avatar-large"><?php echo strtoupper(substr($u['full_name'], 0, 1)); ?></div>
                    <div id="display-name-wrap">
                        <h2 style="margin: 0; font-size: 1.5rem; color: #1e293b;"><?php echo htmlspecialchars($u['full_name']); ?></h2>
                        <p style="color: #64748b; margin: 0.5rem 0 1rem; font-weight: 500;"><?php echo htmlspecialchars($u['course'] ?: 'Not Specified'); ?></p>
                        <button onclick="toggleEditName()" style="background: none; border: none; color: #6366f1; font-size: 0.85rem; font-weight: 700; cursor: pointer; padding: 0; margin-bottom: 1.5rem; font-family: 'Outfit', sans-serif;"><i class="fas fa-edit"></i> Edit Name</button>
                    </div>
                    
                    <div id="edit-name-wrap" style="display: none; margin-bottom: 1.5rem;">
                        <form method="POST">
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($u['full_name']); ?>" style="width: 100%; padding: 0.75rem; border: 1.5px solid #e2e8f0; border-radius: 12px; margin-bottom: 0.75rem; text-align: center; font-family: 'Outfit', sans-serif; font-size: 1rem; font-weight: 600; color: #1e293b; outline: none; transition: border-color 0.2s;" onfocus="this.style.borderColor='#6366f1'">
                            <div style="display: flex; gap: 0.5rem;">
                                <button type="submit" name="update_name" class="btn btn-primary" style="flex: 1; padding: 0.6rem; font-size: 0.85rem; font-family: 'Outfit', sans-serif;">Save</button>
                                <button type="button" onclick="toggleEditName()" style="flex: 1; padding: 0.6rem; font-size: 0.85rem; border: 1px solid #e2e8f0; background: white; border-radius: 10px; cursor: pointer; font-family: 'Outfit', sans-serif; font-weight: 600; color: #64748b;">Cancel</button>
                            </div>
                        </form>
                    </div>


                    <span class="status-pill" style="background: #eef2ff; color: #4f46e5;">Institutional <?php echo ucfirst($u['role_name']); ?></span>

                    
                    <div style="margin-top: 2.5rem; border-top: 1px solid #f1f5f9; padding-top: 2rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div style="text-align: center;">
                            <div style="font-size: 1.5rem; font-weight: 800; color: #1e293b;"><?php echo $total_history; ?></div>
                            <div style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; font-weight: 700;">Records</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 1.5rem; font-weight: 800; color: #6366f1;"><?php echo $active_loans; ?></div>
                            <div style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; font-weight: 700;">Active</div>
                        </div>
                    </div>
                </div>
            </aside>

            <div>
                <div class="profile-card" style="text-align: left; margin-bottom: 3rem;">
                    <h3 style="margin-top: 0; margin-bottom: 2rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 1rem;">Profile</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                        <div class="info-group">
                            <label>Email Address</label>
                            <p><?php echo htmlspecialchars($u['email']); ?></p>
                        </div>
                        <div class="info-group">
                            <label>Student ID</label>
                            <p>#NOG-<?php echo str_pad($u['id'], 6, '0', STR_PAD_LEFT); ?></p>
                        </div>
                    </div>
                </div>

                <div class="table-section">
                    <h3><i class="fas fa-bookmark" style="color: #6366f1; margin-right: 0.5rem;"></i> My Physical Reservations</h3>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                            <tr>
                                <th>Book Title</th>
                                <th>Requested</th>
                                <th>State</th>
                                <th>Pickup Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $res_list = $conn->query("SELECT r.*, b.title FROM reservations r JOIN books b ON r.book_id = b.id WHERE r.user_id = $user_id ORDER BY r.created_at DESC");
                            if ($res_list->num_rows > 0):
                                while($r = $res_list->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($r['title']); ?></strong></td>
                                        <td><?php echo date('M d, Y', strtotime($r['created_at'])); ?></td>
                                        <td>
                                            <?php 
                                            $s = $r['status'];
                                            $c = ($s == 'approved' ? '#dcfce7; color: #166534' : ($s == 'pending' ? '#fef3c7; color: #92400e' : '#fee2e2; color: #991b1b'));
                                            ?>
                                            <span class="status-pill" style="background: <?php echo $c; ?>;"><?php echo strtoupper($s); ?></span>
                                        </td>
                                        <td><?php echo $r['pickup_by'] ? date('M d', strtotime($r['pickup_by'])) : '—'; ?></td>
                                        <td>
                                            <?php if ($s == 'pending' || $s == 'approved'): ?>
                                                <form action="cancel_reservation.php" method="POST" onsubmit="return confirm('Are you sure you want to cancel this reservation?');" style="margin: 0;">
                                                    <input type="hidden" name="reservation_id" value="<?php echo $r['id']; ?>">
                                                    <button type="submit" style="padding: 0.4rem 0.8rem; font-size: 0.75rem; background: #fee2e2; color: #991b1b; border: none; cursor: pointer; border-radius: 4px; font-weight: 600;">Cancel</button>
                                                </form>
                                            <?php else: ?>
                                                <span style="color: #94a3b8; font-size: 0.8rem;">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile;
                            else: ?>
                                <tr><td colspan="5" style="text-align: center; padding: 3rem; color: #94a3b8;">No physical reservations found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                </div>

                <div class="table-section">
                    <h3><i class="fas fa-eye" style="color: #6366f1; margin-right: 0.5rem;"></i> Student View History</h3>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                            <tr>
                                <th>Literature Asset</th>
                                <th>Category</th>
                                <th>Last Viewed</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $views = $conn->query("SELECT rh.*, b.title, b.category FROM reading_history rh JOIN books b ON rh.book_id = b.id WHERE rh.user_id = $user_id ORDER BY rh.viewed_at DESC LIMIT 10");
                            if ($views->num_rows > 0):
                                while($v = $views->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($v['title']); ?></strong></td>
                                        <td><span class="status-pill" style="background: #f1f5f9; color: #475569;"><?php echo htmlspecialchars($v['category']); ?></span></td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($v['viewed_at'])); ?></td>
                                        <td><a href="details.php?id=<?php echo $v['book_id']; ?>" style="color: #6366f1; text-decoration: none; font-weight: 700; font-size: 0.85rem;"><i class="fas fa-book-open"></i> View Again</a></td>
                                    </tr>
                                <?php endwhile;
                            else: ?>
                                <tr><td colspan="4" style="text-align: center; padding: 3rem; color: #94a3b8;">No recent view history found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
function toggleEditName() {
    const disp = document.getElementById('display-name-wrap');
    const edit = document.getElementById('edit-name-wrap');
    if (edit.style.display === 'none') {
        edit.style.display = 'block';
        disp.style.display = 'none';
    } else {
        edit.style.display = 'none';
        disp.style.display = 'block';
    }
}
</script>

<?php renderFooter(); ?>

