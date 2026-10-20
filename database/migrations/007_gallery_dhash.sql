-- Fingerprint of each gallery image (a 64-bit "difference hash"), used to find duplicates.
ALTER TABLE gallery_items ADD COLUMN dhash CHAR(16) NULL;
ALTER TABLE gallery_items ADD KEY idx_dhash (dhash);
