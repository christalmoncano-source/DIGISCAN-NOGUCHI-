-- DigiScan Migration: Add Dewey Decimal System Number
-- Description: Adds a dewey_decimal column to the books table to store library classification identifiers.

ALTER TABLE `books` ADD COLUMN IF NOT EXISTS `dewey_decimal` VARCHAR(50) DEFAULT NULL;
