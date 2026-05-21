<?php
/**
 * DigiScan - Automated Due Date Processor
 * Use this script for Cron Jobs or manual system-wide updates
 */
require_once '../config/db.php';
require_once '../includes/notifications_helper.php';
global $conn;


// If running via CLI or authorized request
try {
    $result = processDueReminders($conn);
    
    echo "--- DigiScan Automated Task Complete ---\n";
    echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
    echo "Reminders Handled: " . (isset($result['reminders']) ? $result['reminders'] : 0) . "\n";
    echo "Overdue Transitions: " . (isset($result['overdue_flagged']) ? $result['overdue_flagged'] : 0) . "\n";
} catch (Exception $e) {
    echo "--- DigiScan Automated Task FAILED ---\n";
    echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
    echo "Error: " . $e->getMessage() . "\n";
}
echo "--------------------------------------\n";
?>
