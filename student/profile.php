<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
checkAccess(['student', 'admin']);

$user_id = $_SESSION['user_id'];
$u_res = $conn->query("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = $user_id");
$u = $u_res->fetch_assoc();

// Statistics
$total_history = $conn->query("SELECT COUNT(*) FROM borrowings WHERE user_id = $user_id")->fetch_row()[0];
$active_loans = $conn->query("SELECT COUNT(*) FROM borrowings WHERE user_id = $user_id AND status IN ('borrowed', 'overdue')")->fetch_row()[0];

renderHeader("Personal Account - DigiScan");
?>

<div class="container-wide section" style="max-width: 1200px; margin: 0 auto; padding: 0 24px;">
    
    <div class="dashboard-header" style="text-align: left; margin-bottom: 3rem;">
        <h1 style="font-size: 3rem; margin-bottom: 0.5rem;">User Intelligence Profile</h1>
        <p style="color: var(--text-light); font-size: 1.1rem;">Manage your digital identity and review institutional activity.</p>
    </div>

    <div style="display: grid; grid-template-columns: 320px 1fr; gap: 3rem; align-items: start;">
        
        <!-- Left Sidebar: Identity Card -->
        <div>
            <div class="sidebar-nav card" style="text-align: center; padding: 3rem 2rem; margin-bottom: 1.5rem;">
                <div style="width: 120px; height: 120px; border-radius: 50%; background: var(--primary-gradient); margin: 0 auto 1.5rem; display: flex; align-items: center; justify-content: center; color: white; font-size: 3.5rem; font-weight: 800; box-shadow: var(--shadow);">
                    <?php echo substr($u['full_name'], 0, 1); ?>
                </div>
                <h3 style="margin: 0; font-size: 1.5rem;"><?php echo htmlspecialchars($u['full_name']); ?></h3>
                <p style="color: var(--text-light); margin: 0.5rem 0 1.5rem;"><?php echo htmlspecialchars($u['course']); ?></p>
                <span class="status-badge status-active"><?php echo strtoupper($u['role_name']); ?></span>
            </div>

            <div class="card" style="padding: 1.5rem; text-align: left;">
                <h4 style="margin: 0 0 1rem; font-size: 0.75rem; color: var(--text-light); text-transform: uppercase;">Credential Stats</h4>
                <div style="display: grid; gap: 1rem;">
                    <div style="display: flex; justify-content: space-between;">
                        <span>Total Records</span>
                        <strong style="color: var(--primary-color);"><?php echo $total_history; ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Active Loans</span>
                        <strong style="color: var(--accent-color);"><?php echo $active_loans; ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Profile & History Info -->
        <div style="text-align: left;">
            
            <div class="card" style="padding: 2.5rem; margin-bottom: 3rem;">
                <h3 style="margin-top: 0; margin-bottom: 2rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 1rem;">Credential Details</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div>
                        <label style="display: block; font-size: 0.75rem; color: var(--text-light); text-transform: uppercase; font-weight: 700; margin-bottom: 0.5rem;">Institutional Email</label>
                        <p style="font-size: 1.1rem; font-weight: 600; margin: 0;"><?php echo htmlspecialchars($u['email']); ?></p>
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.75rem; color: var(--text-light); text-transform: uppercase; font-weight: 700; margin-bottom: 0.5rem;">Unique ID</label>
                        <p style="font-size: 1.1rem; font-weight: 600; margin: 0;">#DS-<?php echo str_pad($u['id'], 5, '0', STR_PAD_LEFT); ?></p>
                    </div>
                </div>
            </div>

            <!-- Reading and Borrowing History -->
            <h3 style="margin-bottom: 1.5rem;"><i class="fas fa-history" style="color: var(--primary-color); margin-right: 0.5rem;"></i> Historical Activity Log</h3>
            <div class="card" style="padding: 0; overflow: hidden;">
                <table>
                    <thead>
                        <tr>
                            <th>Literature Asset</th>
                            <th>Borrowed</th>
                            <th>Returned</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $history = $conn->query("SELECT br.*, b.title FROM borrowings br JOIN books b ON br.book_id = b.id WHERE br.user_id = $user_id ORDER BY br.borrow_date DESC");
                        if ($history->num_rows > 0):
                            while($h = $history->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($h['title']); ?></strong></td>
                                    <td><?php echo date('M d, Y', strtotime($h['borrow_date'])); ?></td>
                                    <td><?php echo $h['return_date'] ? date('M d, Y', strtotime($h['return_date'])) : '—'; ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $h['status']=='returned'?'status-active':($h['status']=='overdue'?'status-error':'status-pending'); ?>">
                                            <?php echo strtoupper($h['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile;
                        else: ?>
                            <tr><td colspan="4" style="padding: 3rem; text-align: center; color: var(--text-light);">No historical activity recorded in the institutional ledger.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Reading (Chapter Access) Logs -->
            <h3 style="margin: 3rem 0 1.5rem;"><i class="fas fa-glasses" style="color: var(--success-color); margin-right: 0.5rem;"></i> Asset Utilization Log (Recently Read)</h3>
            <div class="card" style="padding: 1.5rem;">
                <?php
                $r_logs = $conn->query("SELECT rl.*, b.title FROM reading_logs rl JOIN books b ON rl.book_id = b.id WHERE rl.user_id = $user_id ORDER BY rl.accessed_at DESC LIMIT 5");
                if ($r_logs->num_rows > 0):
                    while($rl = $r_logs->fetch_assoc()): ?>
                        <div style="padding: 1rem; border-bottom: 1px solid #f8fafc; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong style="color: var(--primary-color);"><?php echo htmlspecialchars($rl['title']); ?></strong>
                                <span style="margin-left: 0.5rem; color: var(--text-light); font-size: 0.85rem;">(Chapter: <?php echo htmlspecialchars($rl['chapter_accessed'] ?: 'Cover'); ?>)</span>
                            </div>
                            <small style="color: var(--text-light);"><?php echo date('M d, H:i', strtotime($rl['accessed_at'])); ?></small>
                        </div>
                    <?php endwhile;
                else: ?>
                    <p style="text-align: center; color: var(--text-light); padding: 1rem;">No digital utilization records found.</p>
                <?php endif; ?>
            </div>

        </div>

    </div>
</div>

<?php renderFooter(); ?>
