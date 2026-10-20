-- A tag is a subject (what a visitor wants to explore), a format (Story, Pictures, Book) or a source (another site or label from the old blog).
ALTER TABLE tags ADD COLUMN kind ENUM('subject','format','source') NOT NULL DEFAULT 'subject';
UPDATE tags SET kind = 'format' WHERE slug IN ('story', 'pictures', 'book', 'review', 'media');
UPDATE tags SET kind = 'source' WHERE slug IN ('sugasm', 'pleasurists', 'elust', 'belladonna', 'literotica', 'sybatra', 'fetlife', 'youtube', 'cinekink', 'fictionmania');
