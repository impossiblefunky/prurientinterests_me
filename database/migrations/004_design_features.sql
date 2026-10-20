-- Features the approved designs call for: series with chapters, gallery albums with credits, book editions and excerpts,
-- featured items, tags on books/albums, tag aliases.

CREATE TABLE series (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(190) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    description VARCHAR(500) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE stories ADD COLUMN series_id INT UNSIGNED NULL AFTER kind;
ALTER TABLE stories ADD COLUMN chapter_order INT NOT NULL DEFAULT 1 AFTER series_id;
ALTER TABLE stories ADD COLUMN featured TINYINT(1) NOT NULL DEFAULT 0 AFTER chapter_order;
ALTER TABLE stories ADD KEY idx_series (series_id, chapter_order);

CREATE TABLE albums (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(190) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    intro VARCHAR(500) NOT NULL DEFAULT '',
    status ENUM('draft','published') NOT NULL DEFAULT 'draft',
    featured TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE gallery_items ADD COLUMN album_id INT UNSIGNED NULL AFTER id;
ALTER TABLE gallery_items ADD COLUMN source_url VARCHAR(500) NOT NULL DEFAULT '' AFTER caption;
ALTER TABLE gallery_items ADD COLUMN credit VARCHAR(255) NOT NULL DEFAULT '' AFTER source_url;
ALTER TABLE gallery_items ADD COLUMN is_cover TINYINT(1) NOT NULL DEFAULT 0 AFTER credit;
ALTER TABLE gallery_items ADD COLUMN featured TINYINT(1) NOT NULL DEFAULT 0 AFTER is_cover;
ALTER TABLE gallery_items ADD COLUMN story_id INT UNSIGNED NULL AFTER featured;   -- the blog post this image first appeared in
ALTER TABLE gallery_items ADD KEY idx_album (album_id, status, sort_order);

ALTER TABLE books ADD COLUMN excerpt_html MEDIUMTEXT NULL AFTER description_html;
ALTER TABLE books ADD COLUMN featured TINYINT(1) NOT NULL DEFAULT 0 AFTER status;

CREATE TABLE book_editions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    book_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,                       -- Paperback, Kindle ...
    price_display VARCHAR(40) NOT NULL DEFAULT '',    -- shown as typed; empty = not shown
    purchase_url VARCHAR(500) NOT NULL DEFAULT '',
    isbn VARCHAR(20) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    KEY idx_book (book_id, sort_order),
    CONSTRAINT fk_ed_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO book_editions (book_id, name, purchase_url, isbn, sort_order)
    SELECT id, 'Edition', buy_url, isbn, 0 FROM books WHERE buy_url <> '';

ALTER TABLE item_tags MODIFY item_type ENUM('story','gallery','book','album') NOT NULL;

ALTER TABLE tags ADD COLUMN featured TINYINT(1) NOT NULL DEFAULT 0 AFTER description;
ALTER TABLE tags ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER featured;

-- Old or merged tag names keep working: /tag/alias redirects to the real tag.
CREATE TABLE tag_aliases (
    alias_slug VARCHAR(120) NOT NULL PRIMARY KEY,
    tag_id INT UNSIGNED NOT NULL,
    CONSTRAINT fk_alias_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO settings (name, value) VALUES
    ('home_eyebrow', 'A personal archive · since 2006'),
    ('home_headline', 'Twenty years. After dark.'),
    ('home_intro', 'Stories worth returning to. Images that linger. Explore a personal archive growing since 2006.')
ON DUPLICATE KEY UPDATE value = value;
