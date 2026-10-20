-- Post type (so image-only and video posts can be shown differently from written pieces) and a unique key for idempotent comment imports.
ALTER TABLE stories ADD COLUMN kind ENUM('story','post','picture','video') NOT NULL DEFAULT 'story' AFTER status;
ALTER TABLE stories ADD KEY idx_kind (kind, status, published_at);
ALTER TABLE comments_archive ADD UNIQUE KEY uq_source (source_id);
