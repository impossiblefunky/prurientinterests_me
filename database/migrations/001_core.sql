-- Core schema for prurientinterests.me. All timestamps are UTC.

CREATE TABLE admin_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL DEFAULT '',
    password_hash VARCHAR(255) NOT NULL,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE admin_login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_hash VARCHAR(40) NOT NULL,
    email VARCHAR(190) NOT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE settings (
    name VARCHAR(80) NOT NULL PRIMARY KEY,
    value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE stories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(190) NOT NULL UNIQUE,
    legacy_path VARCHAR(255) NULL UNIQUE,          -- exact old Blogger path, e.g. /2012/12/forbidden-fruit.html
    title VARCHAR(255) NOT NULL,
    summary VARCHAR(500) NOT NULL DEFAULT '',      -- short teaser for cards, search results and meta description
    body_html MEDIUMTEXT NOT NULL,
    status ENUM('draft','scheduled','published') NOT NULL DEFAULT 'draft',
    published_at DATETIME NULL,
    author VARCHAR(120) NOT NULL DEFAULT '',
    word_count INT UNSIGNED NOT NULL DEFAULT 0,
    cover_image VARCHAR(255) NULL,
    source ENUM('cms','blogger','docx') NOT NULL DEFAULT 'cms',
    source_id VARCHAR(255) NULL,                   -- Blogger atom id or docx file name
    source_html MEDIUMTEXT NULL,                   -- raw import, private evidence, never shown
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_listing (status, published_at),
    KEY idx_source (source, source_id(120)),
    FULLTEXT KEY ft_search (title, summary, body_html)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE story_revisions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    story_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    body_html MEDIUMTEXT NOT NULL,
    saved_at DATETIME NOT NULL,
    KEY idx_story (story_id, saved_at),
    CONSTRAINT fk_rev_story FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(120) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(500) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tags attach to stories and gallery items.
CREATE TABLE item_tags (
    tag_id INT UNSIGNED NOT NULL,
    item_type ENUM('story','gallery') NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (tag_id, item_type, item_id),
    KEY idx_item (item_type, item_id),
    CONSTRAINT fk_it_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(190) NOT NULL UNIQUE,
    legacy_path VARCHAR(255) NULL UNIQUE,          -- e.g. /p/about.html
    title VARCHAR(255) NOT NULL,
    summary VARCHAR(500) NOT NULL DEFAULT '',
    body_html MEDIUMTEXT NOT NULL,
    status ENUM('draft','published') NOT NULL DEFAULT 'draft',
    source_html MEDIUMTEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE gallery_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    caption VARCHAR(500) NOT NULL DEFAULT '',
    alt_text VARCHAR(255) NOT NULL DEFAULT '',
    file VARCHAR(255) NOT NULL,                    -- path under public/uploads, e.g. gallery/abc.jpg
    thumb VARCHAR(255) NOT NULL DEFAULT '',
    width INT UNSIGNED NOT NULL DEFAULT 0,
    height INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('draft','published') NOT NULL DEFAULT 'draft',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    KEY idx_listing (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE books (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(190) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    subtitle VARCHAR(255) NOT NULL DEFAULT '',
    description_html MEDIUMTEXT NOT NULL,
    cover_image VARCHAR(255) NULL,
    buy_url VARCHAR(500) NOT NULL DEFAULT '',
    buy_label VARCHAR(80) NOT NULL DEFAULT '',
    isbn VARCHAR(20) NOT NULL DEFAULT '',
    published_on DATE NULL,
    status ENUM('draft','published') NOT NULL DEFAULT 'draft',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE nav_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    location ENUM('header','footer') NOT NULL DEFAULT 'header',
    label VARCHAR(80) NOT NULL,
    url VARCHAR(500) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    visible TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE redirects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_path VARCHAR(255) NOT NULL UNIQUE,
    to_url VARCHAR(500) NOT NULL,
    code SMALLINT UNSIGNED NOT NULL DEFAULT 301,
    hits INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Blogger comments are kept as private archive; nothing displays them yet.
CREATE TABLE comments_archive (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    story_id INT UNSIGNED NULL,
    author VARCHAR(190) NOT NULL DEFAULT '',
    body_html MEDIUMTEXT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT '',
    commented_at DATETIME NULL,
    source_id VARCHAR(255) NOT NULL DEFAULT '',
    KEY idx_story (story_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO settings (name, value) VALUES
    ('site_title', 'Prurient Interests'),
    ('tagline', 'Confessions and stories for adults.'),
    ('footer_note', 'Adults only. All characters depicted in sexual situations are over 18.');

INSERT INTO nav_items (location, label, url, sort_order) VALUES
    ('header', 'Stories', '/stories', 10),
    ('header', 'Tags', '/tags', 20),
    ('header', 'Gallery', '/gallery', 30),
    ('header', 'Books', '/books', 40),
    ('header', 'About', '/about', 50);
