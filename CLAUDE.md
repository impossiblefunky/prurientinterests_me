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

## Layout
`public/` web root, `src/` classes (PSR-4 `App\`), `templates/`, `bin/` command-line jobs, `database/migrations/*.sql` (applied once each by `bin/migrate.php`), `storage/` (logs, sessions, never committed), `docs/`.

## Open items
- Waiting on Mike: allow `*.dl.dropboxusercontent.com` in the cloud environment's Network access (or upload `feed.atom` and the 15 design JPGs to the repo). Then: dry-run the Blogger import, review counts, import, run spellcheck and compare-docx, restyle to the designs.
- Apply the Claude Design handoff (typography, colors, layouts) to `public/assets/site.css` and `templates/`.
- Run the Blogger import and report: counts, external images (download and self-host), comments (stored privately in `comments_archive`, not shown).
- Decide the site `author` name shown in schema and cards; confirm tag names after import.
- Add the other admin screens' polish from the CMS designs (page manager, tag merge).
- DreamHost: create database, domain pointing to `<project>/public`, SSH key and the four GitHub secrets (see docs/SETUP.md).
