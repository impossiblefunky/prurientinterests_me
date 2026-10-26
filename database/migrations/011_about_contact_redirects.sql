-- About and Contact now live at /about and /contact; their old Blogger addresses redirect there and leave the sitemap.
INSERT IGNORE INTO redirects (from_path, to_url, code, created_at) VALUES
  ('/p/about-prurient-interests.html', '/about', 301, UTC_TIMESTAMP()),
  ('/p/contact.html', '/contact', 301, UTC_TIMESTAMP());
