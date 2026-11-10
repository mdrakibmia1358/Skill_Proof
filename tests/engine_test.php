<?php
// Run with: php -n tests/engine_test.php
// This suite never loads database configuration and all HTTP transports are mocked.
if (PHP_SAPI !== "cli" || extension_loaded("curl")) {
    fwrite(STDERR, "Run this isolated test suite with php -n tests/engine_test.php.\n");
    exit(1);
}
putenv("GITHUB_TOKEN");
require __DIR__ . "/support/github_mock.php";

if (($argv[1] ?? "") === "--baseline") {
    putenv("SKILLPROOF_TEST_API_FIXTURES=" . $argv[3]);
    putenv("SKILLPROOF_TEST_API_LOG=" . $argv[4]);
    require $argv[2];
    echo json_encode(skillproof_analyze_github_user("fixture-user"), JSON_THROW_ON_ERROR);
    exit;
}

require dirname(__DIR__) . "/auth_system/engine/skillproof_engine.php";
$tempDir = sys_get_temp_dir() . "/skillproof-engine-" . bin2hex(random_bytes(8));
mkdir($tempDir, 0700);
register_shutdown_function(function () use ($tempDir) {
    foreach (glob($tempDir . "/*") as $file) unlink($file);
    rmdir($tempDir);
});
putenv("SKILLPROOF_TEST_API_FIXTURES=" . $tempDir . "/fixtures.json");
putenv("SKILLPROOF_TEST_API_LOG=" . $tempDir . "/api.log");

function engine_assert($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}

function engine_fixtures() {
    $user = "https://api.github.com/users/fixture-user";
    $alpha = "https://api.github.com/repos/fixture-user/alpha";
    $beta = "https://api.github.com/repos/fixture-user/beta";
    $repos = [
        ["name" => "alpha", "fork" => false, "stargazers_count" => 3, "forks_count" => 2,
            "description" => "Fixture project", "license" => ["key" => "mit"], "topics" => ["testing"],
            "updated_at" => "2099-01-01T00:00:00Z", "language" => "PHP"],
        ["name" => "beta", "fork" => true, "stargazers_count" => 0, "forks_count" => 0,
            "description" => null, "license" => null, "topics" => [],
            "updated_at" => "2000-01-01T00:00:00Z", "language" => "JavaScript"]
    ];
    $root = [];
    foreach ([".github", ".gitignore", "package.json", "Dockerfile", "tests", "schema.sql", "SECURITY.md"] as $name) {
        $root[] = ["name" => $name, "path" => $name];
    }
    return [
        $user => ["data" => ["login" => "fixture-user"]],
        $user . "/repos?per_page=100&sort=updated" => ["data" => $repos],
        $alpha . "/languages" => ["data" => ["PHP" => 800, "JavaScript" => 200]],
        $alpha . "/readme" => ["data" => ["name" => "README.md", "path" => "docs/README.md"]],
        $alpha . "/contents" => ["data" => $root],
        $alpha . "/contents/.github/workflows" => ["data" => [["name" => "ci.yml", "path" => ".github/workflows/ci.yml"]]],
        $beta . "/languages" => ["data" => ["JavaScript" => 1000]],
        $beta . "/readme" => ["status" => 404, "data" => ["message" => "Not Found"]],
        $beta . "/contents" => ["data" => [["name" => ".env", "path" => ".env"]]],
        $beta . "/contents/.github/workflows" => ["status" => 404, "data" => ["message" => "Not Found"]]
    ];
}

function engine_run($fixtures, $username = "fixture-user") {
    file_put_contents(getenv("SKILLPROOF_TEST_API_FIXTURES"), json_encode($fixtures, JSON_THROW_ON_ERROR));
    file_put_contents(getenv("SKILLPROOF_TEST_API_LOG"), "");
    $result = skillproof_analyze_github_user($username);
    $calls = file(getenv("SKILLPROOF_TEST_API_LOG"), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return [$result, $calls];
}

function engine_assert_failure($result) {
    engine_assert($result["connected"] === false && $result["error"] !== "", "Incomplete analysis reported success");
    engine_assert(array_sum($result["scores"]) === 0, "Partial scores escaped failure result");
}

function engine_subprocess($command) {
    $process = proc_open($command, [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes, dirname(__DIR__));
    if (!is_resource($process)) throw new RuntimeException("Could not start fixture subprocess");
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    engine_assert(proc_close($process) === 0, "Fixture subprocess failed: " . $errors);
    return $output;
}

$passed = 0;
$failed = 0;
function engine_test($name, $test) {
    global $passed, $failed;
    try {
        $test();
        $passed++;
        echo "PASS " . $name . "\n";
    } catch (Throwable $error) {
        $failed++;
        fwrite(STDERR, "FAIL " . $name . ": " . $error->getMessage() . "\n");
    }
}

$alpha = "https://api.github.com/repos/fixture-user/alpha";
$beta = "https://api.github.com/repos/fixture-user/beta";
$user = "https://api.github.com/users/fixture-user";
$repoList = $user . "/repos?per_page=100&sort=updated";

engine_test("successful evidence retains known scores and nine assessment dimensions", function () {
    [$result, $calls] = engine_run(engine_fixtures());
    engine_assert($result["connected"] && $result["error"] === "", "Expected successful analysis");
    engine_assert($result["scores"] === ["trust" => 47, "career" => 45, "repository" => 46, "documentation" => 50,
        "complexity" => 42, "professionalism" => 50, "activity" => 50, "testing" => 50, "security" => 38, "devops" => 50], "Scoring methodology changed");
    engine_assert(count($result["dimensions"]) === 9, "Assessment dimensions changed");
    engine_assert(array_column($result["skills"], "score", "name") === ["SQL" => 70, "JavaScript" => 66,
        "DevOps" => 50, "Documentation" => 50, "Testing" => 50, "Git & GitHub" => 48, "PHP" => 45, "Security Awareness" => 38], "Skill scores changed");
    engine_assert(count($calls) === 9, "Unexpected successful request count");
});

engine_test("full successful result equals pre-change engine with identical evidence", function () use ($tempDir) {
    $baseline = engine_subprocess(["git", "show", "HEAD:auth_system/engine/skillproof_engine.php"]);
    file_put_contents($tempDir . "/baseline.php", $baseline);
    [$actual] = engine_run(engine_fixtures());
    // Stable future/past timestamps ensure activity scoring cannot cross a 180-day boundary.
    $expected = json_decode(engine_subprocess([PHP_BINARY, "-n", "-d", "display_errors=stderr", __FILE__, "--baseline", $tempDir . "/baseline.php",
        getenv("SKILLPROOF_TEST_API_FIXTURES"), $tempDir . "/baseline.log"]), true, 512, JSON_THROW_ON_ERROR);
    engine_assert($actual === $expected, "Successful result differs from original engine");
});

foreach (["/languages", "/readme", "/contents", "/contents/.github/workflows"] as $endpoint) {
    foreach ([403, 429, "network", 500] as $failure) {
        engine_test($failure . " on " . $endpoint . " aborts without partial scores", function () use ($alpha, $endpoint, $failure) {
            $fixtures = engine_fixtures();
            $fixtures[$alpha . $endpoint] = $failure === "network"
                ? ["status" => 0, "error" => "Simulated network failure"]
                : ["status" => $failure, "data" => ["message" => "Simulated API failure"]];
            [$result, $calls] = engine_run($fixtures);
            engine_assert_failure($result);
            engine_assert(end($calls) === $alpha . $endpoint, "Engine continued requests after failure");
        });
    }
}

foreach ([$user, $repoList, $alpha . "/languages", $alpha . "/contents"] as $endpoint) {
    engine_test("required 404 fails: " . parse_url($endpoint, PHP_URL_PATH), function () use ($endpoint) {
        $fixtures = engine_fixtures();
        $fixtures[$endpoint] = ["status" => 404, "data" => ["message" => "Not Found"]];
        [$result, $calls] = engine_run($fixtures);
        engine_assert_failure($result);
        engine_assert(end($calls) === $endpoint, "Required 404 did not stop requests");
    });
}

engine_test("optional README and workflow 404s represent absence", function () use ($alpha) {
    $fixtures = engine_fixtures();
    foreach (["/readme", "/contents/.github/workflows"] as $endpoint) {
        $fixtures[$alpha . $endpoint] = ["status" => 404, "data" => ["message" => "Not Found"]];
    }
    [$result] = engine_run($fixtures);
    engine_assert($result["connected"] && $result["error"] === "", "Optional absence rejected analysis");
    engine_assert($result["repoStats"]["readme_count"] === 0, "Missing README counted as evidence");
    engine_assert($result["scores"]["devops"] === 23, "Missing workflow counted as evidence");
});

foreach ([$user, $repoList, $alpha . "/languages", $alpha . "/readme", $alpha . "/contents", $alpha . "/contents/.github/workflows"] as $endpoint) {
    engine_test("malformed JSON fails: " . parse_url($endpoint, PHP_URL_PATH), function () use ($endpoint) {
        $fixtures = engine_fixtures();
        $fixtures[$endpoint] = ["raw" => "{invalid JSON"];
        [$result] = engine_run($fixtures);
        engine_assert_failure($result);
    });
}

$invalidShapes = [
    $user => [], $repoList => ["message" => "unexpected object"],
    $alpha . "/languages" => ["PHP" => "800"], $alpha . "/readme" => [],
    $alpha . "/contents" => [["name" => ".gitignore"]],
    $alpha . "/contents/.github/workflows" => ["message" => "unexpected object"]
];
foreach ($invalidShapes as $endpoint => $shape) {
    engine_test("invalid success shape fails: " . parse_url($endpoint, PHP_URL_PATH), function () use ($endpoint, $shape) {
        $fixtures = engine_fixtures();
        $fixtures[$endpoint] = ["data" => $shape];
        [$result] = engine_run($fixtures);
        engine_assert_failure($result);
    });
}

engine_test("missing repository scoring metadata fails before any detail requests", function () use ($repoList) {
    $fixtures = engine_fixtures();
    unset($fixtures[$repoList]["data"][0]["fork"]);
    [$result, $calls] = engine_run($fixtures);
    engine_assert_failure($result);
    engine_assert(count($calls) === 2, "Incomplete metadata reached detail scoring");
});

engine_test("malformed repository language fails cleanly without a TypeError", function () use ($repoList) {
    $fixtures = engine_fixtures();
    $fixtures[$repoList]["data"][0]["language"] = ["PHP"];
    [$result] = engine_run($fixtures);
    engine_assert_failure($result);
});

engine_test("complete root without .github skips only workflow request", function () use ($beta) {
    [$result, $calls] = engine_run(engine_fixtures());
    engine_assert(!in_array($beta . "/contents/.github/workflows", $calls, true), "Unnecessary workflow request");
    engine_assert(in_array($beta . "/readme", $calls, true), "README lookup was incorrectly inferred from root contents");
});

engine_test("1,000-entry root still checks workflows when .github is absent", function () use ($beta) {
    $fixtures = engine_fixtures();
    $root = [];
    for ($i = 0; $i < 1000; $i++) $root[] = ["name" => "file" . $i, "path" => "file" . $i];
    $fixtures[$beta . "/contents"]["data"] = $root;
    $fixtures[$beta . "/contents/.github/workflows"] = ["data" => [["name" => "ci.yml", "path" => ".github/workflows/ci.yml"]]];
    [$result, $calls] = engine_run($fixtures);
    engine_assert($result["connected"] && $result["error"] === "", "Large root rejected");
    engine_assert(in_array($beta . "/contents/.github/workflows", $calls, true), "Potentially truncated root skipped workflow lookup");
    engine_assert($result["scores"]["devops"] === 78, "Workflow from large root was not scored");
});

engine_test("empty languages object remains valid evidence", function () use ($alpha) {
    $fixtures = engine_fixtures();
    $fixtures[$alpha . "/languages"] = ["raw" => "{}"];
    [$result] = engine_run($fixtures);
    engine_assert($result["connected"] && $result["error"] === "", "Valid empty language map rejected");
});

engine_test("empty username makes zero API calls", function () {
    [$result, $calls] = engine_run([], "  ");
    engine_assert(!$result["connected"] && $calls === [], "Empty username performed network requests");
});

engine_test("no public repositories remains an unsavable analysis", function () use ($repoList) {
    $fixtures = engine_fixtures();
    $fixtures[$repoList]["data"] = [];
    [$result, $calls] = engine_run($fixtures);
    engine_assert($result["error"] !== "" && count($calls) === 2, "Empty repository list treated as a successful scored analysis");
});

engine_test("detail sampling remains capped at 20 with 100 requested repository records", function () use ($user, $repoList, $alpha) {
    $fixtures = engine_fixtures();
    $template = $fixtures[$repoList]["data"][0];
    $repos = [];
    for ($i = 1; $i <= 21; $i++) {
        $repo = $template;
        $repo["name"] = "sample" . $i;
        $repos[] = $repo;
        foreach (["/languages", "/readme", "/contents", "/contents/.github/workflows"] as $endpoint) {
            $fixtures["https://api.github.com/repos/fixture-user/" . $repo["name"] . $endpoint] = $fixtures[$alpha . $endpoint];
        }
    }
    $fixtures[$repoList]["data"] = $repos;
    [$result, $calls] = engine_run($fixtures);
    engine_assert($result["repoStats"]["total"] === 21 && $result["repoStats"]["sample_count"] === 20, "Repository sample size changed");
    engine_assert(count($calls) === 82 && in_array($repoList, $calls, true), "Repository request limits changed");
    foreach ($calls as $call) engine_assert(strpos($call, "/sample21/") === false, "21st repository was detailed");
});

foreach ([
    [404, "{}", false, "not_found"], [403, "{}", false, "rate_limit"], [429, "{}", false, "rate_limit"],
    [500, "{}", false, "http"], [0, false, true, "network"], [200, "null", false, "invalid_response"],
    [200, "{", false, "invalid_response"], [200, "{}", false, ""]
] as [$status, $body, $network, $type]) {
    engine_test("response classifier: HTTP " . $status . " => " . ($type ?: "success"), function () use ($status, $body, $network, $type) {
        $result = sp_api_response($body, $status, $network);
        engine_assert($result["error_type"] === $type && $result["http_status"] === $status, "Failure classification lost");
        engine_assert($result["ok"] === ($type === ""), "Failure treated as successful response");
    });
}

echo "Engine tests: " . $passed . " passed, " . $failed . " failed.\n";
exit($failed === 0 ? 0 : 1);
