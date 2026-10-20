# Setting up prurientinterests.me on DreamHost

Words used below: **repo** = the project's folder on GitHub. **SSH** = a secure way to run commands on the DreamHost server. **Migration** = a numbered SQL file that builds or changes database tables. **Secret** = a private value stored in GitHub, never in the code.

## 1. In the DreamHost panel
1. Websites > Manage Websites > Add Website: **prurientinterests.me**. Choose **PHP 8.3**. Set the web directory to `<project folder>/public` (for example `/home/USER/prurientinterests.me/public`).
2. Turn on **Let's Encrypt** (free HTTPS) for the domain.
3. Websites > MySQL Databases: create a database, a user and a hostname (for example `mysql.prurientinterests.me`). Write down the database name, user, password and host.
4. Users > Manage Users: make sure your user has **Shell access** (not "FTP only").

## 2. Put the secrets in GitHub
Repo > Settings > Secrets and variables > Actions > New repository secret. Add:
- `DH_HOST`: your DreamHost server name (for example `iad1-shared-b7-01.dreamhost.com`)
- `DH_USER`: your DreamHost shell username
- `DH_PATH`: the project folder, for example `/home/USER/prurientinterests.me`
- `DH_SSH_PRIVATE_KEY`: a new SSH key made just for deploys. Create it on your computer with this command, add the `.pub` file's contents to the server's `~/.ssh/authorized_keys`, and paste the *private* file here:

```bash
ssh-keygen -t ed25519 -f deploy_key -N "" -C "github-deploy"
```

## 3. One-time server setup
Connect to the server (the panel shows the exact address), then create the private settings file *outside* the public folder. Copy `.env.example` to `.env` inside the project folder and fill in the database values and a long random `APP_KEY`. Set `APP_ENV=production` and `APP_URL=https://prurientinterests.me`.

After the first deploy (push to `main`, or Actions > Deploy to DreamHost > Run workflow), create your login:

```bash
php bin/create-admin.php you@example.com "Your Name"
```

Then sign in at https://prurientinterests.me/admin.

## 4. Bring the writing in
The Blogger posts are the master copy. Upload `feed.atom` (from the Takeout) to `storage/import/` on the server, then run these one at a time. `--dry-run` shows the counts without changing anything.

```bash
php bin/import-blogger.php storage/import/feed.atom --dry-run
```

```bash
php bin/import-blogger.php storage/import/feed.atom
```

## 4a. Bring the pictures over
Blogger's pictures live on Google's servers. This copies them onto your own server, so they keep working. Upload the Takeout `.zip` (the big file) to `storage/import/` on the server, then:

```bash
php bin/import-images.php storage/import/takeout-20261019T211304Z-1-001.zip --dry-run
```

```bash
php bin/import-images.php storage/import/takeout-20261019T211304Z-1-001.zip
```

Every image is checked to be a real picture and re-saved (this also removes hidden location data), and the post text is switched to the new copy. Each picture post's images also appear in the CMS under **Images** as **drafts** in draft albums named by year ("Pictures 2009"), with the creator credit filled in when the file name says "_by_Name". Nothing goes public until you publish it. The list of pictures that were not in the Takeout (hosted on other websites) is saved to `storage/reports/images-not-found.txt`.

## 4b. Spell check (nothing changes until you approve it)
This makes a report at `storage/reports/spellcheck.md` listing possible misspellings, names to confirm and doubled words:

```bash
php bin/spellcheck.php
```

Read it. Put corrections you agree with in `docs/spelling-fixes.tsv` (the word that is wrong, a Tab, the right word). Put words that are fine as written in `docs/custom-dictionary.txt`. Then preview, and apply:

```bash
php bin/apply-spelling.php
```

```bash
php bin/apply-spelling.php --write
```

Every changed story keeps its old text under **Earlier versions** in the CMS, so a mistake can be undone.

## 4c. Compare with the Word files
Your Word files might have been edited. This only reports differences, split into "spelling or punctuation" and "wording changed", and changes nothing:

```bash
php bin/compare-docx.php storage/import/confessions.docx --split
```

```bash
php bin/compare-docx.php storage/import/story.doc.docx --title "Exact story title"
```

## 5. Before launch
Review every imported story in the admin, publish the ones that should go live, check the Tags list, and look at the sitemap at /sitemap.xml. Only then point DNS at DreamHost.
