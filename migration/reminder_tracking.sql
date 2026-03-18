-- DigiScan Due Date Reminder Module Enhancements
-- Added to prevent duplicate notifications and support better automated tracking

START TRANSACTION;

-- Add a column to track if the user has already been reminded about an upcoming due date
ALTER TABLE `borrowings` 
ADD COLUMN `reminder_sent` tinyint(1) DEFAULT 0 AFTER `status`;

COMMIT;
