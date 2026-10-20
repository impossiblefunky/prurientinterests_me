-- Remembers which Takeout image became which local file, so the image import can be re-run without duplicating anything.
CREATE TABLE image_sources (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    takeout_name VARCHAR(255) NOT NULL UNIQUE,     -- lower-case file name inside the Takeout album
    file VARCHAR(255) NOT NULL,
    thumb VARCHAR(255) NOT NULL,
    width INT UNSIGNED NOT NULL DEFAULT 0,
    height INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE gallery_items ADD KEY idx_story (story_id);
