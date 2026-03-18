-- migration/preview_system_update.sql
ALTER TABLE books ADD COLUMN IF NOT EXISTS physical_location VARCHAR(255) DEFAULT 'Main Section, Shelf 1';
ALTER TABLE books ADD COLUMN IF NOT EXISTS preview_pages TEXT; -- Comma separated or JSON list of pages

CREATE TABLE IF NOT EXISTS reading_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (user_id, book_id) -- Prevent multiple history entries for same book in same session (as per user request "Preventing multiple history entries for same book in same session")
);
