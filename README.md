# SkillProof

SkillProof is an evidence-based developer assessment platform that evaluates developers using real GitHub activity instead of self-reported CV claims.

## Project Overview

The platform analyzes GitHub repositories, commit history, documentation quality, technology usage, and contribution patterns to generate verified skill scores and a Developer Trust Score.

## Main Features

- User registration and login interface
- Developer dashboard
- GitHub evidence-based skill assessment concept
- Verified skills page
- Recruiter dashboard
- Responsive front-end layout
- Mobile-first design using HTML5 and CSS3

## Pages

- `home.html` - Main dashboard page
- `login.html` - User login page
- `register.html` - User registration page
- `skills.html` - Verified skills page
- `recruiter.html` - Recruiter dashboard page
- `styles.css` - Main stylesheet

## Technologies Used

- HTML5
- CSS3
- Flexbox
- CSS Grid
- Media Query
- Git and GitHub

## Team Members

| Role | Name |
|---|---|
| Team Leader | Md. Rakib Mia |
| Member | Nondini Ghosh |
| Member | Sumaiya Khatun |
| Member | Maria Eyamin |

## Week 2 Lab Work

This repository includes:

- Shared GitHub repository setup
- Feature branch workflow
- Individual team member contributions
- Pull request based collaboration
- Responsive dashboard design
- Merge conflict simulation and resolution proof

## Week 3 Lab Work — Client-Side Interactivity & DOM Mechanics

Implemented in [`dashboard-app/`](dashboard-app):

- Real-time DOM validation for the developer registration form (`dashboard-app/app.js`)
- Live email format checking using regular expressions
- Password strength meter (length, uppercase, digit, special-character scoring)
- Reusable, component-based card rendering pattern (`StatsCardComponent`-style factory function mapped over a data array)
- Tailwind CSS utility styling for a responsive layout (`dashboard-app/index.html`)

## Week 4 Lab Work — Server-Side Basics & State Management

Implemented in [`auth_system/`](auth_system):

- `login.php` — server-side form validation, `password_verify()` authentication, `session_regenerate_id(true)` on login (session fixation protection)
- `dashboard.php` — session-guarded route; unauthenticated requests redirect to `login.php`; 5-minute inactivity session timeout (`$timeout_duration = 300`)
- `logout.php` — unsets session variables, clears the session cookie, and destroys the server-side session file

## Week 5 Lab Work — Database Design, SQL & Midterm Prototype

- Normalized (3NF) relational schema in [`schema.sql`](schema.sql): `users`, `skill_analyses`, `skill_scores`, `dimension_scores`, `learning_gaps` — with Primary/Foreign Key constraints
- Entity-Relationship Diagram (Mermaid.js) in [`docs/Project_SRS.md`](docs/Project_SRS.md)
- `auth_system/db_connect.php` — PDO connection layer used by all server-side scripts, with prepared statements throughout for CRUD operations
- Midterm Prototype Demo: normalized database + responsive front-end + PHP/MySQL connectivity + active GitHub branch/PR workflow across all team members

## Week 6 Lab Work — Database Integration & Web Application Security

This week's tasks were distributed among all team members using dedicated feature branches. All branches will be merged into the main repository via Pull Requests (PRs).

| Member | GitHub Username | Assigned Task | Branch Name |
|---|---|---|---|
| Rakib (Leader) | mdrakibmia1358 | Audited `db_connect.php` PDO configuration, added error logging, added `.gitattributes` for cross-platform (Ubuntu/Windows) line-ending consistency, coordinated PR merges | `feature/week6-db-security-rakib` |
| Nondini | nandinighosh225-crypto | Verified `login.php` prepared statements, `password_verify()`, and session regeneration against SQL Injection payloads | `feature/week6-login-security-nondini` |
| Sumaiya | himu903 | Verified `register.php` password hashing (`password_hash`) and duplicate-email prepared statement checks | `feature/week6-register-security-sumaiya` |
| Maria | Maria3577 | Fixed debug error output in `profile.php`, verified `htmlspecialchars()` output escaping and parameterized profile `UPDATE` query against XSS payloads | `feature/week6-profile-xss-maria` |

Full evidence and screenshots for each requirement are documented in [`docs/week6-security-proof.md`](docs/week6-security-proof.md).

Security measures implemented this week:
- PDO with `ERRMODE_EXCEPTION` and disabled emulated prepares
- Parameterized queries (prepared statements) across all SQL operations
- Passwords hashed with `password_hash()` / verified with `password_verify()`
- Session ID regeneration on login (session fixation protection)
- Output escaping with `htmlspecialchars()` to prevent XSS

## How to Run

Open `home.html` in any modern web browser.

```bash
xdg-open home.html