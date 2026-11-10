# SkillProof: deploy the PHP + MySQL application

This repository is a plain PHP + MySQL website. **Do not deploy the entire
application as a Vercel/Netlify static site**: server-side authentication and
SQL access will not work.

## Free demonstration host: InfinityFree

1. Create an account at https://www.infinityfree.com/, and create a hosting
   account with a free subdomain. Open its Control Panel.
2. In **MySQL Databases**, create a database named `skillproof`.
   Copy the **full assigned database name**, **MySQL hostname**, and **database
   username**, plus the **hosting account's MySQL password**. The DB host is
   not `localhost` on InfinityFree.
3. Open **phpMyAdmin**, select the new database, choose **Import**, and import
   `deployment/schema-production.sql`. **Never import `schema.sql` into a
   live database**: it includes destructive DROP statements and known demo users.
4. In a local copy, duplicate `auth_system/db_config.example.php` as
   `auth_system/db_config.local.php`. Insert the four real MySQL details.
   Do **not** commit, upload to GitHub, post, or share this secret file.
5. Using InfinityFree File Manager or FTP, upload to the website's `htdocs/`
   directory:
   - `index.php`, `home.php`, `skills.php`, `recruiter.php`, `styles.css`
   - the entire `auth_system/` folder, including `.htaccess`, `vendor/`,
     and your local `db_config.local.php` file.
   - Optional: additional static demo HTML and `dashboard-app/` files.
   Do not upload Git history, original schema.sql, README, or documents.
6. Verify URLs on your actual subdomain:
   - `/` displays homepage
   - `/auth_system/register.php` creates a new developer account
   - `/auth_system/login.php` logs in
   - `/auth_system/dashboard.php` displays analysis
   - `/auth_system/logout.php` logs out
7. Confirm `/auth_system/db_config.local.php` is **403 Forbidden**.
   If it isn't blocked, remove the secret file from the web directory and
   correct the `.htaccess` rule before accepting users.

## Operational notes
- This is a student prototype, not a security-audited production service.
- GitHub API requests from the PHP engine may encounter rate limits or
  hosting resource limits. Test GitHub skill analysis after registration.
- SSL/HTTPS should be enabled for the subdomain before collecting credentials.
- Free shared hosting is for demonstrations and low usage; check host terms.
- Automatic deployment from GitHub is **not** configured for InfinityFree.
  Upload changed files again via FTP/File Manager when you update the website.
- Do not publicly expose demonstration passwords or import seed users.
- Never run a DROP-based schema import against user data; make backups and use
  migrations to change schema in the future.
