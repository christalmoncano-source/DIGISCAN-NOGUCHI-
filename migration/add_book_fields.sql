ALTER TABLE books 
ADD COLUMN edition VARCHAR(255) AFTER author,
ADD COLUMN publication_place VARCHAR(255) AFTER edition,
ADD COLUMN publisher VARCHAR(255) AFTER publication_place,
ADD COLUMN publication_date VARCHAR(100) AFTER publisher,
ADD COLUMN content_type VARCHAR(100) DEFAULT 'text',
ADD COLUMN media_type VARCHAR(100) DEFAULT 'unmediated',
ADD COLUMN carrier_type VARCHAR(100) DEFAULT 'volume',
ADD COLUMN extent VARCHAR(255);
