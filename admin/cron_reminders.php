<?php
/**
 * DigiScan - Automated Due Date Processor
 * Use this script for Cron Jobs or manual system-wide updates
 */
require_once '../config/db.php';
require_once '../includes/notifications_helper.php';

// If running via CLI or authorized request
$result = processDueReminders($conn);

echo "--- DigiScan Automated Task Complete ---\n";
echo date('Y-m-d H:i:s') . "\n";
echo "Reminders Handled: " . $result['reminders'] . "\n";
echo "Overdue Transitions: " . $result['overdue_flagged'] . "\n";
echo "--------------------------------------\n";
?>
