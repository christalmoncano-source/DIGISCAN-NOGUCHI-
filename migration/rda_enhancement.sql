-- DigiScan RDA Enhancement Migration
-- Upgrading schema to support Resource Description and Access (RDA) standards

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

-- Add RDA Specific Columns to books table
ALTER TABLE `books` 
ADD COLUMN `edition` varchar(100) DEFAULT NULL AFTER `author`,
ADD COLUMN `publication_place` varchar(255) DEFAULT NULL AFTER `edition`,
ADD COLUMN `publisher` varchar(255) DEFAULT NULL AFTER `publication_place`,
ADD COLUMN `publication_date` varchar(50) DEFAULT NULL AFTER `publisher`,
ADD COLUMN `content_type` varchar(100) DEFAULT 'text' AFTER `isbn`,
ADD COLUMN `media_type` varchar(100) DEFAULT 'computer' AFTER `content_type`,
ADD COLUMN `carrier_type` varchar(100) DEFAULT 'online resource' AFTER `media_type`,
ADD COLUMN `extent` varchar(255) DEFAULT NULL AFTER `carrier_type`;

COMMIT;
