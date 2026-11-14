# SkillProof: Email OTP password recovery (staging feature)

This work is **not deployed** by merging a PR. Validate on a private staging installation first. Do not publish SMTP credentials.

## Features
- A "Forgot your password?" link on the real PHP login page.
- Six-digit single-use code emailed to the user's registered email.
- Ten-minute code expiry; five wrong attempts invalidate the code.
- At most one code request per account per minute, three within an hour.
- HMAC-SHA256 digests stored in MySQL. Plaintext codes and SMTP secrets are never stored.
- Generic response for registered/unregistered email addresses.
- Reset changes the saved password hash; existing account/analysis rows are retained.
- Saved GitHub analyses are shown only for a matching current GitHub username in the profile card; old history is preserved.

## Before any deployment

1. Create a **private** staging installation and make a full backup of the database.
2. Install dependencies locally using Composer: \`composer install --no-dev --optimize-autoloader\`. The \`auth_system/vendor_mailer\` folder is generated; do not commit it.
3. In staging phpMyAdmin, select the database and import only \`deployment/migrations/20261111_password_reset_codes.sql\`. **Do not import \`schema.sql\`**; it can drop existing data.
4. Make a copy of \`auth_system/password_reset_config.example.php\` as \`auth_system/password_reset_config.local.php\` **on hosting only**.
5. Enter external SMTP details. Gmail: \`smtp.gmail.com\`, port \`587\`, STARTTLS, and a dedicated Gmail App Password (requires a compatible account with 2-Step Verification). **Do not use the Gmail login password.**
6. Generate a random \`code_pepper\` locally by running \`php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'\`. Keep it unchanged after launching; changing it invalidates outstanding reset codes.
7. Upload the new app files, \`auth_system/vendor_mailer\` with its \`.htaccess\`, and the private config. Never overwrite \`auth_system/db_config.local.php\` or the existing \`auth_system/vendor/\` folder. Keep the Google verification HTML file.
8. Confirm GET to \`/auth_system/password_reset_config.local.php\` and \`/auth_system/vendor_mailer/autoload.php\` return 403; these are not public endpoints.
9. Test with a **disposable** registered email account, without exposing real mailboxes/passwords in screenshots. Verify proper delivery, expiry, 5 wrong attempts, resend throttling, single-use, email enumeration response, old password invalid, new password works, and other accounts unaffected.
10. Re-run application integration, engine, responsive, PHP lint and test SMTP on the **actual** InfinityFree host. SMTP transport behavior cannot be proven by local unit tests.

InfinityFree supports outbound SMTP using **port 587 and STARTTLS** to a separate service, not PHP \`mail()\`. Source: https://forum.infinityfree.com/t/smtp-support-and-pwa-installation-issues/121369 (Oct 2026).

## Important limitations before public release
- This is an initial implementation. **Do not merge or deploy until full reset-flow integration tests and a security review pass.**
- The current application's PHP sessions are not centrally revocable. Existing authenticated sessions may remain active until session expiry after a password reset. A future enhancement should invalidate all pre-reset sessions.
- Current rate limits are per *existing account*, not global or per IP. Add abuse protections and monitoring before high-volume/public use.
- Gmail sending quota/anti-abuse limits can interrupt delivery. Consider a dedicated transactional SMTP service if volume rises.
- PHPMailer dependency installation and hosting \`.htaccess\` behavior must be verified separately.
