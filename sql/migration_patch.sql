-- SQL Migration Patch for Heyzine Integration
-- Run this on your remote database (e.g., via phpMyAdmin) to fix the dashboard error.

ALTER TABLE books ADD COLUMN IF NOT EXISTS heyzine_url VARCHAR(512) DEFAULT NULL AFTER preview_pages;
