#!/usr/bin/env python3
"""HTTP + real MariaDB integration tests. All application files/data are disposable copies."""

import argparse
import http.cookiejar
import json
import os
from pathlib import Path
import re
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request


ROOT = Path(__file__).resolve().parents[1]
SUPPORT = Path(__file__).resolve().parent / "support"
TABLES = ["users", "skill_analyses", "skill_scores", "dimension_scores", "learning_gaps", "recruiter_reports"]
PASSWORD = "Synthetic-test-password-123!"


def check(condition, message):
    if not condition:
        raise AssertionError(message)


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class Browser:
    def __init__(self, base):
        self.base = base
        self.cookies = http.cookiejar.CookieJar()
        self.client = urllib.request.build_opener(NoRedirect, urllib.request.HTTPCookieProcessor(self.cookies))

    def request(self, path, data=None, method=None):
        request = urllib.request.Request(
            urllib.parse.urljoin(self.base, path),
            data=None if data is None else urllib.parse.urlencode(data).encode(),
            method=method,
        )
        try:
            response = self.client.open(request, timeout=10)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            body = response.read().decode()
            check(response.status < 500, f"HTTP {response.status} for {path}: {body[:300]}")
            return response.status, response.headers, body

    def get(self, path="/auth_system/dashboard.php"):
        return self.request(path)

    def token(self, path="/auth_system/dashboard.php"):
        status, _, body = self.get(path)
        check(status == 200, f"Token page returned {status}: {path}")
        match = re.search(r'name="csrf_token" value="([^"]+)"', body)
        check(match is not None, f"CSRF field missing: {path}")
        return match.group(1)


class Integration:
    def __init__(self, work, basedir, php):
        self.work = work
        self.php = php
        self.basedir = basedir
        self.processes = []
        self.logs = []
        self.passed = []
        self.env = {key: value for key, value in os.environ.items()
                    if not key.startswith("SKILLPROOF_DB_") and key != "GITHUB_TOKEN"}
        self.env.update({
            "GITHUB_TOKEN": "",
            "SKILLPROOF_TEST_DB_SOCKET": str(work / "db.sock"),
            "SKILLPROOF_TEST_API_LOG": str(work / "api.log"),
            "SKILLPROOF_TEST_API_FIXTURES": str(work / "fixtures.json"),
        })

    def command(self, args, **kwargs):
        return subprocess.run(args, env=self.env, text=True, check=True, capture_output=True, **kwargs)

    def start_process(self, args, name):
        log = (self.work / f"{name}.log").open("w")
        self.logs.append(log)
        process = subprocess.Popen(args, env=self.env, stdout=log, stderr=subprocess.STDOUT)
        self.processes.append(process)
        return process

    def db(self, sql, params=None):
        result = self.command([self.php, str(SUPPORT / "database.php")], input=json.dumps({
            "operation": "query", "sql": sql, "params": params or [],
        }))
        return json.loads(result.stdout)

    def snapshot(self):
        return {table: self.db(f"SELECT * FROM {table} ORDER BY 1") for table in TABLES}

    def api_calls(self):
        return (self.work / "api.log").read_text().splitlines()

    def configure(self, overrides=None, github_directory=True):
        base = "https://api.github.com"
        repo = base + "/repos/mock-developer/evidence"
        files = ["README.md", "composer.json", ".gitignore", "tests", "schema.sql"]
        if github_directory:
            files.append(".github")
        fixtures = {
            base + "/users/mock-developer": {"data": {"login": "mock-developer", "id": 100}},
            base + "/users/mock-developer/repos?per_page=100&sort=updated": {"data": [{
                "name": "evidence", "fork": False, "stargazers_count": 3, "forks_count": 1,
                "description": "Synthetic evidence", "license": {"key": "mit"}, "topics": ["sample"],
                "language": "PHP", "updated_at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
            }]},
            repo + "/languages": {"data": {"PHP": 800, "JavaScript": 200}},
            repo + "/readme": {"data": {"name": "README.md", "path": "README.md", "content": "VGVzdA=="}},
            repo + "/contents": {"data": [{"name": name, "path": name, "type": "dir" if name in ("tests", ".github") else "file"} for name in files]},
            repo + "/contents/.github/workflows": {"data": [{"name": "ci.yml", "path": ".github/workflows/ci.yml", "type": "file"}]},
        }
        for endpoint, response in (overrides or {}).items():
            fixtures[repo + endpoint] = response
        (self.work / "fixtures.json").write_text(json.dumps(fixtures))
        (self.work / "api.log").write_text("")

    def setup(self):
        (self.work / "isolated-test-marker").touch()
        datadir = self.work / "data"
        initializer = self.basedir / "bin/mariadb-install-db"
        server = self.basedir / "sbin/mariadbd"
        if not server.exists():
            server = self.basedir / "bin/mariadbd"
        for executable in (initializer, server):
            check(executable.is_file(), f"MariaDB executable missing: {executable}")
        self.command([str(initializer), "--no-defaults", f"--basedir={self.basedir}",
                      f"--datadir={datadir}", "--auth-root-authentication-method=normal", "--skip-test-db"])
        database = self.start_process([
            str(server), "--no-defaults", f"--basedir={self.basedir}", f"--datadir={datadir}",
            f"--socket={self.work / 'db.sock'}", f"--pid-file={self.work / 'db.pid'}",
            "--skip-networking", "--skip-log-bin", "--innodb-buffer-pool-size=32M",
        ], "mariadb")
        for _ in range(100):
            check(database.poll() is None, "Private MariaDB exited; see mariadb.log")
            if (self.work / "db.sock").exists():
                break
            time.sleep(0.1)
        else:
            raise RuntimeError("Private MariaDB socket did not appear")
        # Import unchanged application DDL only. Never import repository seed accounts/data.
        ddl = (ROOT / "schema.sql").read_text().split("-- Seed Data")[0]
        ddl = re.sub(r"CREATE DATABASE.*?;|USE skillproof_db;", "", ddl, flags=re.S)
        self.command([self.php, str(SUPPORT / "database.php")], input=json.dumps({"operation": "initialize", "ddl": ddl}))

        app = self.work / "app"
        target = app / "auth_system"
        target.mkdir(parents=True)
        # Explicit source selection prevents even reading db_config.local.php or production credentials.
        for name in ("register.php", "login.php", "logout.php", "dashboard.php", "recruiter_dashboard.php", "profile.php", "report.php", "csrf.php"):
            shutil.copy2(ROOT / "auth_system" / name, target / name)
        for directory in ("engine", "vendor"):
            shutil.copytree(ROOT / "auth_system" / directory, target / directory)
        (target / "db_connect.php").write_text('''<?php
function skillproof_db(): PDO {
    static $pdo;
    return $pdo ??= new PDO("mysql:unix_socket=" . getenv("SKILLPROOF_TEST_DB_SOCKET") . ";dbname=skillproof_test;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}
''')
        shutil.copy2(SUPPORT / "github_mock.php", self.work / "github_mock.php")
        router = self.work / "router.php"
        router.write_text('''<?php
require __DIR__ . "/github_mock.php";
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$file = realpath(__DIR__ . "/app" . $path);
if (!$file || strpos($file, __DIR__ . "/app/") !== 0 || !is_file($file)) {
    http_response_code(404);
    exit("Not found");
}
chdir(dirname($file));
require $file;
''')
        sessions = self.work / "sessions"
        sessions.mkdir()
        self.configure()
        with socket.socket() as probe:
            probe.bind(("127.0.0.1", 0))
            port = probe.getsockname()[1]
        self.base = f"http://127.0.0.1:{port}"
        php_server = self.start_process([
            self.php, "-d", f"session.save_path={sessions}", "-d", "display_errors=0",
            "-d", "disable_functions=curl_init,curl_setopt_array,curl_exec,curl_getinfo,curl_error,curl_close",
            "-S", f"127.0.0.1:{port}", "-t", str(app), str(router),
        ], "php")
        for _ in range(100):
            check(php_server.poll() is None, "Private PHP server exited; see php.log")
            try:
                with socket.create_connection(("127.0.0.1", port), timeout=0.1):
                    break
            except OSError:
                time.sleep(0.1)
        else:
            raise RuntimeError("Private PHP server did not start")

    def passed_test(self, name):
        self.passed.append(name)
        print(f"PASS {name}", flush=True)

    def register(self, email, github, role="Developer"):
        browser = Browser(self.base)
        token = browser.token("/auth_system/register.php")
        response = browser.request("/auth_system/register.php", {
            "csrf_token": token, "fullName": "Synthetic " + role, "email": email,
            "github": github, "password": PASSWORD, "role": role,
        })
        expected = "recruiter_dashboard.php" if role == "Recruiter" else "dashboard.php"
        check(response[0] in (302, 303) and response[1].get("Location") == expected, "Registration redirect failed")
        return browser

    def analyze(self, browser, token=None, extra=None):
        data = {"csrf_token": token if token is not None else browser.token(), "action": "analyze_github"}
        data.update(extra or {})
        response = browser.request("/auth_system/dashboard.php", data)
        check(response[0] == 303, f"Manual POST must use 303 PRG; got {response[0]}")
        location = response[1].get("Location", "")
        check(location.startswith("dashboard.php") and "?" not in location, "POST redirect contains URL-controlled feedback")
        page = browser.get("/auth_system/" + location)
        check(page[0] == 200, "Redirected dashboard failed")
        return page[2]

    def run(self):
        self.setup()
        developer = self.register("developer@example.test", "mock-developer")
        check(len(self.db("SELECT * FROM users")) == 1, "Registration did not insert one user")
        check(not self.db("SELECT * FROM skill_analyses"), "Registration created an analysis")
        check(not self.api_calls(), "Registration called GitHub")
        self.passed_test("developer registration creates account with zero GitHub requests")

        before = self.snapshot()
        for _ in range(3):
            check(developer.get()[0] == 200, "Dashboard GET failed")
        developer.get("/auth_system/dashboard.php?action=analyze_github&analyze=1")
        check(not self.api_calls() and self.snapshot() == before, "GET/refresh caused API calls or database writes")
        self.passed_test("dashboard GET, query action and repeated refresh make zero API requests/inserts")

        invalid = self.analyze(developer, "invalid-csrf")
        check("expired" in invalid.lower(), "Invalid CSRF feedback missing")
        check(not self.api_calls() and self.snapshot() == before, "Invalid CSRF executed analysis")
        developer.request("/auth_system/dashboard.php", {"csrf_token[]": "array", "action": "analyze_github"})
        developer.get()
        self.analyze(developer, extra={"action": "unknown"})
        check(not self.api_calls() and self.snapshot() == before, "Array CSRF or unknown action executed analysis")
        self.passed_test("invalid/string-array CSRF and invalid action perform zero API requests/inserts")

        anonymous = Browser(self.base)
        for method, data in (("GET", None), ("POST", {"action": "analyze_github", "csrf_token": "fake"})):
            response = anonymous.request("/auth_system/dashboard.php", data, method)
            check(response[0] in (302, 303) and response[1].get("Location") == "login.php", "Unauthenticated request allowed")
        check(not self.api_calls() and self.snapshot() == before, "Unauthenticated request executed analysis")
        self.passed_test("unauthenticated GET/POST cannot analyze")

        token = developer.token()
        success = self.analyze(developer, token, {"user_id": "9999", "analysis_id": "9999", "github_username": "another-user"})
        check("GitHub analysis saved successfully." in success, "Successful analysis flash missing")
        analyses = self.db("SELECT * FROM skill_analyses")
        check(len(analyses) == 1, "Successful analysis did not save exactly once")
        row = analyses[0]
        expected = {"github_username": "mock-developer", "total_repositories": 1, "original_repositories": 1,
                    "forked_repositories": 0, "total_stars": 3, "total_forks": 1, "languages_detected": 2, "readme_count": 1}
        check(all(str(row[key]) == str(value) for key, value in expected.items()), "Saved repository statistics incorrect")
        check(int(row["trust_score"]) == 86 and int(row["career_score"]) == 81, "Calculated scores not persisted correctly")
        for table in ("skill_scores", "dimension_scores", "learning_gaps"):
            check(bool(self.db(f"SELECT * FROM {table} WHERE analysis_id = ?", [row["analysis_id"]])), f"Missing {table}")
        check(len(self.api_calls()) == 6, "Unexpected successful analysis request count")
        self.passed_test("authenticated manual analysis saves scores, dimensions and learning gaps atomically")

        before = self.snapshot()
        calls = self.api_calls()
        for _ in range(3):
            page = developer.get()[2]
            check("GitHub analysis saved successfully." not in page, "Success flash repeated")
        check(self.snapshot() == before and self.api_calls() == calls, "Success refresh repeated analysis")
        self.analyze(developer, token)
        check(self.snapshot() == before and self.api_calls() == calls, "Replayed POST duplicated analysis")
        self.passed_test("success flash consumed once; repeated refresh and POST replay cannot duplicate results")

        for status in (403, 429):
            for endpoint in ("/languages", "/readme", "/contents", "/contents/.github/workflows"):
                self.configure({endpoint: {"status": status, "data": {"message": "rate limit"}}})
                before = self.snapshot()
                failure = self.analyze(developer)
                check("rate limit" in failure.lower(), f"HTTP {status} {endpoint} not reported")
                check("GitHub analysis saved successfully." not in failure, "Failed analysis showed success")
                check(self.snapshot() == before, f"HTTP {status} {endpoint} persisted incomplete result")
                calls = self.api_calls()
                for _ in range(2):
                    developer.get()
                check(self.api_calls() == calls and self.snapshot() == before, "Failed analysis refresh retried or inserted")
                self.passed_test(f"repository {endpoint} HTTP {status} preserves previous result; refresh is inert")

        for endpoint, response, label in (
            ("/contents", {"status": 0, "error": "Simulated network failure"}, "network failure"),
            ("/languages", {"status": 404, "data": {"message": "Not found"}}, "required languages 404"),
            ("/contents", {"status": 404, "data": {"message": "Not found"}}, "required contents 404"),
            ("/readme", {"status": 500, "data": {"message": "Unavailable"}}, "optional endpoint HTTP 500"),
            ("/languages", {"status": 200, "raw": "invalid-json"}, "invalid JSON"),
        ):
            self.configure({endpoint: response})
            before = self.snapshot()
            failure = self.analyze(developer)
            check("GitHub analysis saved successfully." not in failure, f"{label} showed success")
            check(self.snapshot() == before, f"{label} persisted incomplete result")
            self.passed_test(f"{label} preserves last successful analysis and all child rows")

        self.configure({"/readme": {"status": 404, "data": {"message": "No README"}},
                        "/contents/.github/workflows": {"status": 404, "data": {"message": "No workflows"}}})
        before_count = len(self.db("SELECT * FROM skill_analyses"))
        success = self.analyze(developer)
        check("GitHub analysis saved successfully." in success, "Expected optional 404 rejected")
        check(len(self.db("SELECT * FROM skill_analyses")) == before_count + 1, "Optional 404 analysis not saved")
        check(int(self.db("SELECT readme_count FROM skill_analyses ORDER BY analysis_id DESC LIMIT 1")[0]["readme_count"]) == 0, "Missing README counted as present")
        self.passed_test("optional README/workflows 404 produces a complete saved analysis")

        self.configure(github_directory=False)
        success = self.analyze(developer)
        check("GitHub analysis saved successfully." in success, "Absent .github analysis failed")
        check(len(self.api_calls()) == 5 and not any(url.endswith("/workflows") for url in self.api_calls()), "Unnecessary workflows request made")
        self.passed_test("complete root listing without .github avoids unnecessary workflow request")

        self.configure()
        before = self.snapshot()
        self.db("CREATE TRIGGER skillproof_test_fail_gap BEFORE INSERT ON learning_gaps FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Injected test insert failure'")
        try:
            failure = self.analyze(developer)
            check("could not be saved" in failure, "Database failure feedback missing")
            check(self.snapshot() == before, "Insert failure failed to roll back parent/earlier child inserts")
        finally:
            self.db("DROP TRIGGER skillproof_test_fail_gap")
        self.passed_test("late learning-gap insert failure rolls back analysis, skill and dimension inserts")

        self.configure()
        second = self.register("second@example.test", "second-developer")
        owner_id = self.db("SELECT user_id FROM users WHERE email = ?", ["developer@example.test"])[0]["user_id"]
        analysis_id = self.db("SELECT MAX(analysis_id) AS id FROM skill_analyses")[0]["id"]
        for path in (f"dashboard.php?user_id={owner_id}&analysis_id={analysis_id}", f"profile.php?user_id={owner_id}&analysis_id={analysis_id}"):
            page = second.get("/auth_system/" + path)[2]
            check("mock-developer" not in page and "second-developer" in page, f"Cross-user data leak: {path}")
        report = second.get(f"/auth_system/report.php?user_id={owner_id}")
        check(report[0] in (302, 303) or report[0] == 403, "Developer could read recruiter-only report")
        self.passed_test("developer dashboard/profile ignore another user's IDs; recruiter report is inaccessible")

        recruiter = self.register("recruiter@example.test", "mock-recruiter", "Recruiter")
        before = self.snapshot()
        check(recruiter.get()[1].get("Location") == "recruiter_dashboard.php", "Recruiter developer-dashboard guard changed")
        recruiter_post = recruiter.request("/auth_system/dashboard.php", {
            "action": "analyze_github", "csrf_token": recruiter.token("/auth_system/profile.php"),
        })
        check(recruiter_post[1].get("Location") == "recruiter_dashboard.php", "Recruiter POST bypassed role guard")
        check("mock-developer" in recruiter.get("/auth_system/recruiter_dashboard.php")[2], "Recruiter cannot search saved developer results")
        check(second.get("/auth_system/recruiter_dashboard.php")[1].get("Location") == "dashboard.php", "Developer accessed recruiter dashboard")
        check(not self.api_calls() and self.snapshot() == before, "Role routing/search triggered GitHub or writes")
        self.passed_test("recruiter registration/access/search and developer role boundaries remain intact")

        spoof = "SPOOFED-SUCCESS-FROM-QUERY"
        check(spoof not in developer.get("/auth_system/dashboard.php?status=" + spoof + "&status_type=success")[2], "Dashboard trusts URL success message")
        check(spoof not in anonymous.get("/auth_system/login.php?msg=" + spoof)[2], "Login trusts URL success message")
        check(second.get("/auth_system/logout.php")[1].get("Location") == "login.php", "Logout redirect failed")
        login = second.get("/auth_system/login.php")[2]
        check("logged out successfully" in login, "Logout flash missing")
        check("logged out successfully" not in second.get("/auth_system/login.php")[2], "Logout flash repeated")
        check(second.get()[1].get("Location") == "login.php", "Logout retained authentication")
        login = second.request("/auth_system/login.php", {"csrf_token": second.token("/auth_system/login.php"),
                                                       "email": "second@example.test", "password": PASSWORD})
        check(login[0] in (302, 303) and login[1].get("Location") == "dashboard.php", "Existing developer login failed")
        check(second.get()[0] == 200 and not self.api_calls(), "Login triggered GitHub or failed")
        self.passed_test("URL success spoofing ignored; logout flash is one-use; logout/login preserve auth behavior")

        for browser, path, role in ((developer, "dashboard.php", "Developer"),
                                    (recruiter, "recruiter_dashboard.php", "Recruiter")):
            session_id = next(cookie.value for cookie in browser.cookies if cookie.name == "PHPSESSID")
            session_file = self.work / "sessions" / ("sess_" + session_id)
            expired, replacements = re.subn(r"last_activity\|i:\d+;", "last_activity|i:1;", session_file.read_text())
            check(replacements == 1, "Synthetic session missing activity timestamp")
            session_file.write_text(expired)
            before = self.snapshot()
            response = browser.get("/auth_system/" + path)
            check(response[1].get("Location") == "login.php", "Expired session was not redirected")
            check("Session timed out due to inactivity" in browser.get("/auth_system/login.php")[2], "Timeout flash missing")
            check("Session timed out due to inactivity" not in browser.get("/auth_system/login.php")[2], "Timeout flash repeated")
            check(browser.get()[1].get("Location") == "login.php", "Expired session retained authentication")
            check(self.snapshot() == before and not self.api_calls(), "Timeout triggered API calls or database writes")
            self.passed_test(f"{role} session timeout clears authentication and shows one-use session flash")

    def close(self):
        for process in reversed(self.processes):
            if process.poll() is None:
                process.terminate()
                try:
                    process.wait(timeout=10)
                except subprocess.TimeoutExpired:
                    process.kill()
                    process.wait()
        for log in self.logs:
            log.close()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--mariadb-basedir", type=Path, default=Path("/usr"), help="MariaDB prefix containing bin/mariadb-install-db and sbin/mariadbd")
    parser.add_argument("--php", default=shutil.which("php"))
    parser.add_argument("--keep-temp", action="store_true", help="Keep synthetic fixtures, logs and isolated DB after stopping servers")
    args = parser.parse_args()
    work = Path(tempfile.mkdtemp(prefix="skillproof-integration-"))
    runner = Integration(work, args.mariadb_basedir.resolve(), args.php)
    failed = False
    try:
        runner.run()
        print(f"\n{len(runner.passed)} integration checks passed (mocked GitHub; isolated MariaDB).")
    except Exception:
        failed = True
        for name in ("php.log", "mariadb.log"):
            path = work / name
            if path.exists():
                print(f"\n{name} (last 30 lines):\n" + "\n".join(path.read_text().splitlines()[-30:]))
        raise
    finally:
        runner.close()
        if args.keep_temp:
            print(f"Synthetic test artifacts retained: {work}")
        else:
            shutil.rmtree(work)
        if failed:
            print(f"{len(runner.passed)} integration checks passed before failure.")


if __name__ == "__main__":
    main()
