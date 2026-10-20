# Setting up prurientinterests.me on DreamHost (no terminal needed)

Words used below. **Repo**: the project's folder on GitHub. **Secret**: a private value stored in GitHub, never in the code. **Workflow**: a button on GitHub's Actions tab that does a job for you. **Migration**: a numbered file that builds the database tables.

Facts for this site: DreamHost user `cashiers`, server `pdx1-shared-a1-04.dreamhost.com`, project folder `/home/cashiers/prurientinterests.me`, website address `https://prurientinterests.me`. The old blog is at pornoperson.blogspot.com and is not touched until the very end.

## 1. DreamHost: add the website
Websites > Manage Websites > Add Website > Fully Hosted. Domain `prurientinterests.me`, user `cashiers`, web directory `prurientinterests.me/public`, PHP 8.3, free Let's Encrypt certificate, remove "www".

## 2. DreamHost: a database
Websites > MySQL Databases. Create a hostname `mysql.prurientinterests.me`, then a database (for example `prurient`), a database user (for example `prurientdb`) and a long password. Keep the four values: host, database name, user, password.

## 3. A deploy key (a lock and key pair for GitHub to log in as `cashiers`)
You will do this in the browser: GitHub shows you the private half once; the public half goes into DreamHost. Claude will give you each click when you get here.

## 4. GitHub secrets (Settings > Secrets and variables > Actions > New repository secret)
| Name | Value |
|---|---|
| `DH_HOST` | `pdx1-shared-a1-04.dreamhost.com` |
| `DH_USER` | `cashiers` |
| `DH_PATH` | `/home/cashiers/prurientinterests.me` |
| `DH_SSH_PRIVATE_KEY` | the private half of the deploy key |
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | the four database values from step 2 |

The deploy refuses to run unless `DH_PATH` is one dedicated folder with "prurient" in its name, so it can never overwrite your other websites.

## 5. Deploy
Push to `main`, or Actions > **Deploy to DreamHost** > Run workflow.

## 6. Server tasks (buttons, not commands)
Actions > **Server task** > Run workflow > choose a task. In this order:
1. `check` (proves GitHub can log in)
2. `write-env` (writes the private settings file on the server from your secrets)
3. Run **Deploy** again so the database tables are built (or run `migrate`)
4. `create-admin` (first add a GitHub secret `ADMIN_PASSWORD` of 12 or more characters, fill in your email and name, run it, then delete that secret)

## 7. Bring the writing in
Upload `feed.atom` and the Takeout zip (rename it `takeout.zip`) into `storage/import/` on the server with DreamHost's File Manager (or any SFTP program). Then run, one at a time, from Server task: `import-blogger-dry-run`, `import-blogger`, `import-images-dry-run`, `import-images`, `spellcheck`, `apply-spelling-preview`, `apply-spelling`. Reports (`spellcheck`, image reports) appear as downloadable files at the bottom of the run's page.

Every changed story keeps its old text under **Earlier versions** in the CMS. Imported gallery images are drafts until you publish them.

## 8. Before launch
Review the imported stories and the draft gallery in the admin, then check /sitemap.xml. Only then discuss pointing the old blogspot addresses at the new site. Nothing about the live blog changes before that.
