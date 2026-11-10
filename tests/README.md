# SkillProof regression tests

Run the engine suite (no database or network required):

```sh
php -n tests/engine_test.php
```

It checks scoring against explicit expected values and the Git `HEAD` engine, request failures, optional absence, response validation, request reduction and unchanged sampling limits. Native cURL is disabled so every response is supplied by the test mock.

Run the native PHP stream fallback against a loopback HTTP mock:

```sh
python3 tests/stream_transport_test.py
```

Run the HTTP/database suite with Python 3, PHP (PDO MySQL enabled), and MariaDB server plus `mariadb-install-db`:

```sh
python3 tests/integration.py
```

For MariaDB unpacked into a private prefix instead of installed system-wide:

```sh
python3 tests/integration.py --mariadb-basedir /path/to/private-mariadb/usr
```

For private shared libraries, set `LD_LIBRARY_PATH` to that prefix's library directory. These tests need permission to create local sockets. They do not install packages or start a system service.

The runner starts a fresh MariaDB instance with `--no-defaults`, a temporary data directory, a private Unix socket and networking disabled. It imports only the DDL from `schema.sql`, excluding seed data. It copies an explicit set of application files into a temporary directory and supplies a separate `db_connect.php`; the application's database configuration and production data are never read. Sessions and synthetic accounts are disposable. Both servers stop and all temporary files are removed on exit; `--keep-temp` retains synthetic artifacts for debugging.

The PHP server binds only to loopback. Test-local cURL mocks supply every GitHub response and record requests. Native cURL functions and HTTP(S) stream wrappers are disabled in that server, and unknown mock URLs fail immediately. No GitHub token is passed. A real MariaDB trigger injects a late insert failure so transaction rollback is verified against actual InnoDB tables.

The suite reports each passed scenario separately. It covers registration, login/logout and recruiter access, dashboard GET/refresh, authenticated manual POST, CSRF and POST replay, saved analysis rows, repository-detail 403/429/network/required-404/invalid-JSON failures, optional 404s, avoided workflow requests, rollback, session flash messages and cross-user access boundaries.

Expected absence is limited to README/workflow HTTP 404s. Required root-contents/languages 404s abort the attempt, including repositories whose contents cannot be read because they are empty or unavailable. Existing scores remain saved. The original one-page limit of 100 repositories and detailed sample of 20 are unchanged. Workflow lookups are skipped only when a root listing below GitHub's [1,000-entry contents limit](https://docs.github.com/en/rest/repos/contents#get-repository-content) proves `.github` absent; the README endpoint remains authoritative.
