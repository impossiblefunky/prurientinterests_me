-- Each book now has its own page, /books/<slug>; old redirects pointed at an anchor on /books.
UPDATE redirects SET to_url = REPLACE(to_url, '/books#', '/books/') WHERE to_url LIKE '/books#%';
