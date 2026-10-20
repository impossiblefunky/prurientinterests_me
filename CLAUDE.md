# prurientinterests.me

Public site + small CMS for Mike White's adult writing site, replacing the Blogger blog. Sole publisher: Mike White. Sister project (same stack, patterns borrowed): `projectionboothpodcast_com`.

## Decisions (owner-confirmed 2026-10-19)
- Domain: **prurientinterests.me**. Hosting: **DreamHost shared**, **PHP 8.3+, Composer, MySQL, PDO**, server-rendered plain PHP templates. No Laravel/Node/React.
- Deploy: GitHub `main` -> GitHub Action rsync over SSH (never FTP). The workflow also runs `php bin/migrate.php`.
- Content source (updated 2026-10-20): **the Blogger Takeout (`feed.atom`) is the master copy of every story.** The two Word files (`confessions.docx`, `story.doc.docx`) are NOT imported over it, because their text may have been changed. They are only compared (`bin/compare-docx.php`, read-only). Spelling is fixed on the blog text through a reviewed workflow: `bin/spellcheck.php` writes a report, Mike approves corrections in `docs/spelling-fixes.tsv` and words in `docs/custom-dictionary.txt`, then `bin/apply-spelling.php --write` applies them (old text kept as a revision). Never auto-correct; the writing is slangy and intentional.
- Keep exact old Blogger paths (`/YYYY/MM/slug.html`, `/p/page.html`).
- Design source: `Prurient_Interests_All_Page_Designs.zip` (15 pages: adults-only entry, home, tag directory, tag results, story library, reading page, gallery, books, about, and 5 CMS screens: story editor, page manager, gallery editor, book editor, tag management, navigation/redirects). **The current CSS is a neutral placeholder; match the designs once they can be read.**
- Adults-only gate on every public page (cookie `pi_adult`, 30 days); gate is `noindex`; RTA label sent as a header and meta. Never show or log anything from stories in error messages.
- One admin user for now. Server-side validation mirrors browser rules (`required="required"`, email min length 5 and x@x.x pattern). Tables are `data-sortable`, never by Actions. WCAG 2.1 AA: 44px targets, focus rings, skip link, live regions, 12px minimum text.
- Outside links: new tab, "(opens in a new tab)" for screen readers, never wrap the icon alone.
- Owner is new to git, Composer and the terminal: explain unfamiliar terms briefly; give commands as single fenced `bash` blocks.

## What the real data and the designs showed (2026-10-20)
- Pen name **Louis Friend**; tagline **"My needs, my fantasies, my desires"**; a personal archive "since 2006". Public nav: Home, Interests (= tags), Stories, Gallery, Books, About. Footer: "Prurient Interests · Louis Friend".
- Blogger feed: 1,163 live posts (2006-2026, ~260k words), 10 drafts, 4 pages (about-prurient-interests, contact, life-on-my-knees, freedom-is-slavery), 215 live comments (all link to posts via `blogger:parent`), 25 spam + 1 ghosted dropped, 47 labels, 1,207 images in post bodies (almost all on Google's servers).
- Post types (`stories.kind`, guessed on import, editable): ~159 story, ~162 post, ~805 picture (image-only), ~47 video. **Decision pending from Mike:** how picture and video posts appear (keep every one at its old URL regardless).
- The Takeout holds ~4,400 original images in `Albums/Prurient Interests` plus `Blogger Pictures`; they are the source for self-hosting.
- Designs add features not built yet: **series with chapters** (Series, Chapter order, "Continue the series / Next chapter"), library filters (format: standalone/series; years), **tag pages mixing stories, images and books** (Everything/Stories/Images/Books), tags on books and images, **book editions** (edition name, price, purchase link, ISBN; "Read an excerpt"), **gallery albums** (album title/intro, cover image, source credit URL, creator credit, display order, feature on homepage), homepage controls (feature on homepage, section order, featured book, featured tags), tag aliases / canonical tag merge with redirects, reading page ("Paper reading mode", "Larger text", drop cap, original publication date), publish date shown in **Eastern time**, CMS sidebar "The writing desk" (Stories & posts, Pages & homepage, Media & galleries, Books).
- Design palette: near-black plum background, cream serif headings (Georgia-style), rose accent text, wine-red primary buttons, thin rose borders, small caps eyebrow labels. Fonts are system serif/sans in the mockups.
- Word-vs-blog comparison (confessions.docx, 36 pieces): 6 identical, 7 same text but different title, 12 spelling/punctuation only, 10 wording differs, 1 not found. All titles in the Word file may differ from the blog's. `story.doc.docx` matches a blog post by opening words (wording differs).
- First spell check: 687 possible misspellings, 744 names to confirm, 234 doubled words. Mike APPROVED (2026-10-20) 17 typo fixes (`docs/spelling-fixes.tsv`) and 39 intentional words (`docs/custom-dictionary.txt`; "newfound" stays one word). They apply on the real database with `bin/apply-spelling.php --write` right after the Blogger import. Further spell-check rounds still need Mike's review.

## Layout
`public/` web root, `src/` classes (PSR-4 `App\`), `templates/`, `bin/` command-line jobs, `database/migrations/*.sql` (applied once each by `bin/migrate.php`), `storage/` (logs, sessions, never committed), `docs/`.

## Open items
- Decisions made by Mike (2026-10-20): blog text wins over the Word files; picture posts become gallery albums (draft, reviewed by Mike), kept at their old URLs and out of the story library; carry image credits over; accept the first spelling round.
- Not yet done on the real server: DreamHost setup, real Blogger import, image import, spelling apply. The work so far was verified against a throwaway local database.
- Images: `bin/import-images.php takeout.zip` self-hosts post images (1,116 matched of ~1,207; ~53 are third-party hotlinks or not in the Takeout), rewrites posts, and creates DRAFT gallery items (~1,095) in DRAFT albums by year with credits parsed from `_by_Name` file names. Mike must review/publish them. Never copy a Takeout file by its name; everything is re-encoded by `Images::store` (extension from content).
- DONE: public site and CMS styled to the designs (screenshots compared). Still to do: mobile pass at 320px with axe, a real cover image for books, a contact form or contact method, a /feed for old Blogger feed readers, and alt text for the ~1,095 draft gallery items.
- Comments are stored privately in `comments_archive` (215) and not shown anywhere yet; decide whether to display them.
- Confirm tag names after import (capitalised automatically; e.g. "Elust", "Sugasm"). Merge duplicates in Tags.
- Decide which groups of numbered titles (about 8 found, for example Pet for MAGA 1-3) to turn into series; chapter order is set in the story editor.
- DreamHost: create database, domain pointing to `<project>/public`, SSH key and the four GitHub secrets (see docs/SETUP.md).
