<?php
// includes/notifications_helper.php

/**
 * Send a notification to a user
 */
function sendNotification($conn, $user_id, $title, $message, $type = 'info') {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $title, $message, $type);
    return $stmt->execute();
}

/**
 * Sophisticated check for due dates and overdue assets.
 * Optimized for both automated cron jobs and manual triggers.
 */
function processDueReminders($conn) {
    $reminders_count = 0;
    $overdue_count = 0;

    // 1. IDENTIFY UPCOMING DUE DATES (1-2 days before deadline)
    // We only select borrowings that haven't had a reminder sent yet
    $sql_upcoming = "SELECT b.id, b.user_id, b.due_date, books.title, 
                    DATEDIFF(b.due_date, CURDATE()) as days_remaining
                    FROM borrowings b 
                    JOIN books ON b.book_id = books.id 
                    WHERE b.status = 'borrowed' 
                    AND b.reminder_sent = 0
                    AND b.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 2 DAY)";
    
    $res_up = $conn->query($sql_upcoming);
    while ($row = $res_up->fetch_assoc()) {
        $bid = $row['id'];
        $uid = $row['user_id'];
        $title = $row['title'];
        $due = $row['due_date'];
        $days = $row['days_remaining'];
        
        $notif_title = "Upcoming Return Deadline: $title";
        $notif_msg = "Institutional Reminder: The literature asset '$title' is due on $due. You have $days day(s) remaining to return this asset to the library to maintain your good standing.";
        
        if (sendNotification($conn, $uid, $notif_title, $notif_msg, 'reminder')) {
            // Mark as reminded to prevent duplicates
            $conn->query("UPDATE borrowings SET reminder_sent = 1 WHERE id = $bid");
            $reminders_count++;
        }
    }

    // 2. IDENTIFY AND TRANSITION OVERDUE ASSETS
    $sql_overdue = "SELECT b.id, b.user_id, books.title, b.due_date
                    FROM borrowings b 
                    JOIN books ON b.book_id = books.id 
                    WHERE b.status = 'borrowed' 
                    AND b.due_date < CURDATE()";
    
    $res_ov = $conn->query($sql_overdue);
    while ($row = $res_ov->fetch_assoc()) {
        $bid = $row['id'];
        $uid = $row['user_id'];
        $asset = $row['title'];
        $deadline = $row['due_date'];
        
        // Update borrowing status to OVERDUE
        $conn->query("UPDATE borrowings SET status = 'overdue' WHERE id = $bid");
        
        $alert_title = "CRITICAL ALERT: Overdue Asset Detected";
        $alert_msg = "Our monitoring system has flagged '$asset' as OVERDUE. The deadline was $deadline. Your digital access privileges may be restricted until this asset is returned.";
        
        // We only send one overdue alert per asset to keep the dashboard clean
        $check = $conn->query("SELECT id FROM notifications WHERE user_id = $uid AND title = '$alert_title' AND message LIKE '%$asset%'");
        if ($check->num_rows == 0) {
            sendNotification($conn, $uid, $alert_title, $alert_msg, 'alert');
            $overdue_count++;
        }
    }

    return [
        'reminders' => $reminders_count,
        'overdue_flagged' => $overdue_count
    ];
}
?>
