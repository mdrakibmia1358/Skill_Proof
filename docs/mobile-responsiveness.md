# Mobile responsiveness and local CSS update

## Changes

- Replaced all 12 runtime Tailwind CDN imports with the same local, minified
  `assets/css/tailwind.css`, built using the pinned Tailwind **3.4.17** CLI.
- Added recursive PHP/HTML/JavaScript content scanning, excluding dependencies
  and tests, plus an explicit safelist for conditional status/validation colors.
  Score percentages remain inline styles. No runtime CSS generation is needed.
- Kept the existing navy/blue desktop design and adjusted smaller breakpoints:
  stacked metric cards and actions, narrower padding, wrapping text and footer
  links, and locally scrolling data tables. The page itself does not hide overflow.
- Added mobile navigation to registration, profile and both dashboards. Shared
  menu behavior updates `aria-expanded`, closes on link selection and Escape,
  restores focus on Escape, and resets when crossing the desktop breakpoint.
- Corrected legacy demo links pointing at `localhost`, gave homepage anchors room
  below the sticky navbar, and kept mobile form text at least 16px.
- Preserved all existing PHP blocks. The only additional PHP expression repeats
  the existing role-dependent profile Dashboard link in its mobile menu.
  Authentication, SQL operations and the GitHub analysis engine are unchanged.

## Changed files

| Group | Files |
| --- | --- |
| Public PHP pages | `home.php`, `skills.php`, `recruiter.php` |
| Account pages | `auth_system/login.php`, `auth_system/register.php`, `auth_system/dashboard.php`, `auth_system/recruiter_dashboard.php`, `auth_system/profile.php` |
| Legacy demos | `login.html`, `register.html`, `skills.html`, `recruiter.html`, `dashboard-app/index.html`, `dashboard-app/app.js`, `styles.css` |
| New runtime assets | `assets/css/tailwind.css`, `assets/js/navigation.js` |
| New build inputs | `assets/css/tailwind.input.css`, `tailwind.config.cjs`, `package.json`, `package-lock.json` |
| New browser tests | `tests/responsive.py`, `tests/responsive.mjs` |
| Documentation/configuration | `.gitignore`, `README.md`, `DEPLOYMENT.md`, `tests/README.md`, `docs/mobile-responsiveness.md` |

The remaining application PHP files were reviewed and syntax checked. `index.php`
already serves `home.php`; logout redirects, report generation, database/CSRF
helpers and the analysis engine do not need responsive presentation edits.

## Verification

- Production CSS build and JavaScript syntax checks pass.
- PHP syntax: all 19 PHP files pass.
- Existing regression suites: 51 engine checks, 8 stream transport checks and
  27 HTTP/database integration checks pass.
- **149 browser checks pass:** 21 page/session states at **320, 375, 390, 430,
  768, 1024 and 1440px**, plus two phone/desktop interaction groups. Coverage
  includes anonymous and signed-in navigation, populated/empty developer
  dashboards/profiles and recruiter dashboard/profile.
- Browser networking blocks every external origin. Checks require HTTP 200 local
  CSS with the correct MIME type and parsed rules, with no Tailwind CDN request.
  They check horizontal page/text overflow, menu behavior, dynamic status colors,
  navigation, form controls and filtering. Wide tables scroll within their cards.
- Screenshots are inspected at representative phone and desktop widths. Automated
  tests use installed Chromium; physical iPhone/Android and Safari checks remain
  a manual follow-up. All database tests use synthetic, disposable local data.

## Upload

Follow the exact runtime file list and asset-first upload order in
[DEPLOYMENT.md](../DEPLOYMENT.md#uploading-the-mobilecss-update-to-an-existing-infinityfree-site).
The compiled CSS is included, so InfinityFree needs no Node.js installation.
Do not upload build dependencies or tests, or replace database configuration/data.

This change has not been committed, pushed, merged or deployed. The live site was
not reachable through the web inspection tool; verification uses the local PHP
application. Recheck CSS requests and mobile navigation on InfinityFree after the
manual upload. Existing legacy HTML demos keep their original client-only forms.
