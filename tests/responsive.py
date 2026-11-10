#!/usr/bin/env python3
"""Run real-browser layout checks against disposable PHP/MariaDB fixtures only."""

import argparse
import json
from pathlib import Path
import shutil
import subprocess
import tempfile

from integration import Integration, ROOT, PASSWORD


def prepare(runner):
    runner.setup()
    app = runner.work / "app"
    # Deliberate allowlist: never copy or read application DB configuration.
    for name in ("index.php", "home.php", "skills.php", "recruiter.php", "login.html",
                 "register.html", "skills.html", "recruiter.html", "styles.css"):
        shutil.copy2(ROOT / name, app / name)
    for name in ("assets", "dashboard-app"):
        shutil.copytree(ROOT / name, app / name)
    (runner.work / "router.php").write_text('''<?php
require __DIR__ . "/github_mock.php";
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
if ($path === "/") $path = "/index.php";
$file = realpath(__DIR__ . "/app" . $path);
if (!$file || strpos($file, __DIR__ . "/app/") !== 0 || !is_file($file)) {
    http_response_code(404);
    exit("Not found");
}
if (pathinfo($file, PATHINFO_EXTENSION) !== "php") return false;
chdir(dirname($file));
require $file;
''')

    email = "developer.with.a.long.synthetic.address@responsive.example.test"
    developer = runner.register(email, "mock-developer")
    runner.analyze(developer)
    developer_id = runner.db("SELECT user_id FROM users WHERE email = ?", [email])[0]["user_id"]
    analysis_id = runner.db("SELECT analysis_id FROM skill_analyses WHERE user_id = ?", [developer_id])[0]["analysis_id"]
    long_github = "synthetic-developer-with-a-long-username"
    runner.db("UPDATE users SET full_name = ?, github_username = ?, bio = ? WHERE user_id = ?", [
        "Synthetic Developer With A Long Display Name For Mobile Layout Checks", long_github,
        "Synthetic portfolio: " + "LongUnbrokenPortfolioDescription" * 5, developer_id,
    ])
    runner.db("UPDATE skill_analyses SET github_username = ? WHERE analysis_id = ?", [long_github, analysis_id])
    runner.db("DELETE FROM skill_scores WHERE analysis_id = ?", [analysis_id])
    for status, score in (("Verified", 90), ("Needs Evidence", 55), ("Low Evidence", 25),
                          ("Not Detected", 0), ("Not Connected", 0)):
        runner.db("INSERT INTO skill_scores (analysis_id, skill_name, score, level, evidence, status) VALUES (?, ?, ?, ?, ?, ?)", [
            analysis_id, "PHP" if score == 90 else "Synthetic " + status, score,
            "Intermediate", "Synthetic evidence for responsive browser checks", status,
        ])
    runner.db("DELETE FROM learning_gaps WHERE analysis_id = ?", [analysis_id])
    for priority in ("High Priority", "Medium Priority", "Low Priority"):
        runner.db("INSERT INTO learning_gaps (analysis_id, title, description, priority) VALUES (?, ?, ?, ?)", [
            analysis_id, "Synthetic " + priority, "Evidence to improve in this synthetic test profile.", priority,
        ])
    runner.register("empty.developer@responsive.example.test", "synthetic-empty")
    runner.db("UPDATE users SET github_username = NULL WHERE email = ?", ["empty.developer@responsive.example.test"])
    recruiter_email = "recruiter.with.a.long.synthetic.address@responsive.example.test"
    runner.register(recruiter_email, "synthetic-recruiter", "Recruiter")
    runner.db("UPDATE users SET role = 'Recruiter' WHERE email = ?", [recruiter_email])
    recruiter = runner.login(recruiter_email, "Recruiter")
    if recruiter.get("/auth_system/recruiter_dashboard.php")[0] != 200:
        raise AssertionError("Authorized recruiter fixture cannot access recruiter dashboard")
    return {
        "base": runner.base,
        "accounts": {"developer": email, "empty": "empty.developer@responsive.example.test",
                     "recruiter": recruiter_email},
        "password": PASSWORD,
        "screenshots": str(runner.work / "screenshots"),
    }


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--mariadb-basedir", type=Path, default=Path("/usr"))
    parser.add_argument("--php", default=shutil.which("php"))
    parser.add_argument("--chrome", default=shutil.which("google-chrome") or shutil.which("chromium"))
    parser.add_argument("--keep-temp", action="store_true", help="Retain synthetic fixtures, screenshots and logs")
    args = parser.parse_args()
    if not args.chrome:
        parser.error("Install Chrome/Chromium or pass --chrome /path/to/browser")
    work = Path(tempfile.mkdtemp(prefix="skillproof-integration-responsive-"))
    runner = Integration(work, args.mariadb_basedir.resolve(), args.php)
    try:
        fixture = prepare(runner)
        fixture["chrome"] = args.chrome
        fixture_path = work / "browser-fixture.json"
        fixture_path.write_text(json.dumps(fixture))
        subprocess.run(["node", str(ROOT / "tests/responsive.mjs"), str(fixture_path)],
                       cwd=ROOT, env=runner.env, check=True)
    except Exception:
        for name in ("php.log", "mariadb.log"):
            path = work / name
            if path.exists():
                print(f"\n{name} (last 20 lines):\n" + "\n".join(path.read_text().splitlines()[-20:]))
        raise
    finally:
        runner.close()
        if args.keep_temp:
            print(f"Synthetic browser test artifacts retained: {work}")
        else:
            shutil.rmtree(work)


if __name__ == "__main__":
    main()
