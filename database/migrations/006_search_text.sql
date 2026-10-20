-- Plain text of each story (no HTML tags), used by the site search. Filled by bin/rebuild-search.php and kept up to date on every save.
ALTER TABLE stories ADD COLUMN search_text MEDIUMTEXT NULL AFTER body_html;
