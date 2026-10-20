-- Contact form, new comments (moderated), spam protection log, and tags that can be hidden from the public Interests list.
ALTER TABLE tags ADD COLUMN hidden TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE comments_archive ADD COLUMN author_email VARCHAR(190) NOT NULL DEFAULT '';
ALTER TABLE comments_archive ADD COLUMN ip_hash CHAR(24) NOT NULL DEFAULT '';
ALTER TABLE comments_archive ADD COLUMN created_at DATETIME NULL;

CREATE TABLE contact_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    body TEXT NOT NULL,
    ip_hash CHAR(24) NOT NULL DEFAULT '',
    status ENUM('new','read') NOT NULL DEFAULT 'new',
    created_at DATETIME NOT NULL,
    KEY idx_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per form submission, so each visitor can be limited to a few an hour. Only a one-way hash of the address is kept.
CREATE TABLE form_hits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kind VARCHAR(20) NOT NULL,
    ip_hash CHAR(24) NOT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_hits (kind, ip_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO settings (name, value) VALUES ('contact_email', 'pornoperson@gmail.com') ON DUPLICATE KEY UPDATE value = value;
