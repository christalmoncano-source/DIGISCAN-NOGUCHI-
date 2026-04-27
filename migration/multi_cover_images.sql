-- DigiScan Migration: Multi-Cover Image Support
-- Date: 2026-03-24
-- Description: Widens the cover_image column in the books table to TEXT
--              so it can store a JSON array of up to 5 cover image paths.
--              Backward compatible: existing single-path strings still work.

ALTER TABLE `books`
    MODIFY COLUMN `cover_image` TEXT DEFAULT NULL;
