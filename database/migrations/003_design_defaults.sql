-- Values taken from the approved page designs: pen name, tagline, navigation labels.
INSERT INTO settings (name, value) VALUES ('author_name', 'Louis Friend') ON DUPLICATE KEY UPDATE value = VALUES(value);
UPDATE settings SET value = 'My needs, my fantasies, my desires' WHERE name = 'tagline';
UPDATE settings SET value = 'Adults only. Characters in sexual situations are 18 or older.' WHERE name = 'footer_note';
UPDATE nav_items SET label = 'Interests' WHERE url = '/tags' AND location = 'header';
UPDATE nav_items SET sort_order = sort_order + 10 WHERE location = 'header';
INSERT INTO nav_items (location, label, url, sort_order, visible) VALUES ('header', 'Home', '/', 5, 1);
