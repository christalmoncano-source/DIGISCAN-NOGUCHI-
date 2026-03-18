-- migration/update_settings.sql
-- Remove old borrowing settings
DELETE FROM system_settings WHERE setting_key IN ('borrow_duration', 'borrow_limit', 'loan_period', 'max_loans');

-- Ensure new settings exist
INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('download_prevention', 'enabled');
INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('max_preview_pages', '10');
