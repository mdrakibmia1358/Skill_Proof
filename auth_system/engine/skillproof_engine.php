<?php

function sp_score_limit($value) {
    return max(0, min(100, (int) round($value)));
}

function sp_contains_text($text, $search) {
    return strpos($text, $search) !== false;
}

function sp_ends_with_text($text, $ending) {
    if ($ending === "") {
        return true;
    }

    return substr($text, -strlen($ending)) === $ending;
}

// Keep transport failures distinct from an optional resource being absent.
function sp_api_response($response, $httpCode, $networkError = false) {
    $errorType = "";
    $error = "";
    $data = null;

    if ($response === false || $networkError || $httpCode === 0) {
        $errorType = "network";
        $error = "GitHub connection failed. Please try again later.";
    } elseif ($httpCode === 404) {
        $errorType = "not_found";
        $error = "Required GitHub user or repository data was not found.";
    } elseif ($httpCode === 403 || $httpCode === 429) {
        $errorType = "rate_limit";
        $error = "GitHub API access was denied or rate limited (HTTP " . $httpCode . "). Please try again later.";
    } elseif ($httpCode < 200 || $httpCode >= 300) {
        $errorType = "http";
        $error = "GitHub API request failed (HTTP " . $httpCode . "). Please try again later.";
    } else {
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            $errorType = "invalid_response";
            $error = "GitHub returned invalid data. Please try again later.";
            $data = null;
        }
    }

    return [
        "ok" => $errorType === "",
        "data" => $data,
        "error" => $error,
        "http_status" => $httpCode,
        "error_type" => $errorType
    ];
}

function sp_api_get($url) {
    $headers = [
        "Accept: application/vnd.github+json",
        "User-Agent: SkillProof-Analysis-Engine-v2"
    ];

    $token = getenv("GITHUB_TOKEN");
    if (!empty($token)) {
        $headers[] = "Authorization: Bearer " . $token;
    }

    if (function_exists("curl_init")) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => $headers
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        // PHP 8 frees CurlHandle objects automatically; curl_close is deprecated in 8.5.
        if (PHP_VERSION_ID < 80000) curl_close($ch);
        return sp_api_response($response, $httpCode, $error !== "");
    }

    $context = stream_context_create([
        "http" => [
            "method" => "GET",
            "header" => implode("\r\n", $headers) . "\r\n",
            "timeout" => 25,
            "ignore_errors" => true,
            // Match cURL: do not follow redirects carrying the Authorization header.
            "follow_location" => 0
        ]
    ]);
    $response = @file_get_contents($url, false, $context);
    $responseHeaders = function_exists("http_get_last_response_headers")
        ? (http_get_last_response_headers() ?? [])
        : ($http_response_header ?? []);
    $httpCode = 0;
    foreach ($responseHeaders as $header) {
        if (preg_match("{^HTTP/\\S+\\s(\\d{3})}", $header, $match)) {
            $httpCode = (int) $match[1];
        }
    }
    return sp_api_response($response, $httpCode);
}

// Validate evidence before scoring it; a malformed success response is not absence.
function sp_api_data_valid($data, $kind) {
    if (!is_array($data)) return false;
    if ($kind === "profile") return isset($data["login"]) && is_string($data["login"]);
    if ($kind === "readme") return isset($data["path"]) && is_string($data["path"]);
    if ($kind === "languages") {
        foreach ($data as $language => $bytes) {
            if (!is_string($language) || !is_int($bytes) || $bytes < 0) return false;
        }
        return true;
    }
    if ($data !== [] && array_keys($data) !== range(0, count($data) - 1)) return false;
    foreach ($data as $item) {
        if (!is_array($item) || !isset($item["name"]) || !is_string($item["name"]) || $item["name"] === "") return false;
        if ($kind === "contents" && (!isset($item["path"]) || !is_string($item["path"]))) return false;
        if ($kind === "repos") {
            if (!isset($item["fork"]) || !is_bool($item["fork"])) return false;
            foreach (["stargazers_count", "forks_count"] as $field) {
                if (!isset($item[$field]) || !is_int($item[$field]) || $item[$field] < 0) return false;
            }
            foreach (["description", "language"] as $field) {
                if (!array_key_exists($field, $item) || ($item[$field] !== null && !is_string($item[$field]))) return false;
            }
            if (!isset($item["updated_at"]) || !is_string($item["updated_at"]) || strtotime($item["updated_at"]) === false) return false;
            if (!array_key_exists("license", $item) || ($item["license"] !== null && !is_array($item["license"]))) return false;
            if (!isset($item["topics"]) || !is_array($item["topics"])) return false;
        }
    }
    return true;
}

function sp_api_evidence($url, $kind, $optional = false) {
    $result = sp_api_get($url);
    if (!$result["ok"]) {
        // Only README and workflow absence are optional. Required 404s fail closed.
        if ($optional && $result["error_type"] === "not_found") return null;
        throw new RuntimeException($result["error"]);
    }
    if (!sp_api_data_valid($result["data"], $kind)) {
        throw new RuntimeException("GitHub returned incomplete repository data. Please try again later.");
    }
    return $result["data"];
}

function sp_skill_level($score) {
    if ($score >= 85) return "Strong";
    if ($score >= 70) return "Good";
    if ($score >= 50) return "Developing";
    if ($score > 0) return "Low Evidence";
    return "Not Detected";
}

function sp_skill_status($score, $connected) {
    if (!$connected) return "Not Connected";
    if ($score >= 60) return "Verified";
    if ($score > 0) return "Low Evidence";
    return "Not Detected";
}

function sp_zero_skill_scores() {
    return [
        "Git & GitHub" => ["score" => 0, "evidence" => "No GitHub repository evidence analyzed.", "type" => "platform"],
        "Documentation" => ["score" => 0, "evidence" => "No README or repository description analyzed.", "type" => "platform"],
        "Testing" => ["score" => 0, "evidence" => "No testing evidence analyzed.", "type" => "platform"],
        "Security Awareness" => ["score" => 0, "evidence" => "No security practice evidence analyzed.", "type" => "platform"],
        "DevOps" => ["score" => 0, "evidence" => "No CI/CD or Docker evidence analyzed.", "type" => "platform"]
    ];
}

function sp_make_default_result($username = "", $error = "") {
    return sp_build_result([
        "connected" => false,
        "username" => $username,
        "error" => $error,
        "profile" => null,
        "repoStats" => ["total" => 0, "original" => 0, "forked" => 0, "stars" => 0, "forks" => 0, "languages_count" => 0, "readme_count" => 0, "sample_count" => 0],
        "scores" => ["trust" => 0, "career" => 0, "repository" => 0, "documentation" => 0, "complexity" => 0, "professionalism" => 0, "activity" => 0, "testing" => 0, "security" => 0, "devops" => 0],
        "skillScores" => sp_zero_skill_scores(),
        "activities" => [
            "GitHub username is not connected.",
            "All score values are shown as 0 until valid GitHub data is connected.",
            "Dashboard access protection is active.",
            "Login and logout session flow is working."
        ],
        "learningGaps" => [[
            "title" => "Connect GitHub Username",
            "text" => "No score is calculated until a valid public GitHub username is connected.",
            "priority" => "High Priority"
        ]]
    ]);
}

function sp_make_no_repo_result($username, $profile) {
    return sp_build_result([
        "connected" => true,
        "username" => $username,
        "error" => "No public repositories found for analysis.",
        "profile" => $profile,
        "repoStats" => ["total" => 0, "original" => 0, "forked" => 0, "stars" => 0, "forks" => 0, "languages_count" => 0, "readme_count" => 0, "sample_count" => 0],
        "scores" => ["trust" => 0, "career" => 0, "repository" => 0, "documentation" => 0, "complexity" => 0, "professionalism" => 0, "activity" => 0, "testing" => 0, "security" => 0, "devops" => 0],
        "skillScores" => sp_zero_skill_scores(),
        "activities" => [
            "GitHub username connected: " . $username,
            "No public repositories were found.",
            "No skill score was calculated because repository evidence is missing.",
            "Scores calculated by SkillProof Analysis Engine v2."
        ],
        "learningGaps" => [[
            "title" => "Add Public GitHub Projects",
            "text" => "Create public repositories with source code, README files, and project evidence.",
            "priority" => "High Priority"
        ]]
    ]);
}

function sp_build_result($data) {
    $connected = $data["connected"];
    $scores = $data["scores"];
    $skillScores = $data["skillScores"];
    $repoStats = $data["repoStats"];

    $verifiedSkills = 0;
    foreach ($skillScores as $skillData) {
        if (($skillData["score"] ?? 0) >= 60) $verifiedSkills++;
    }

    $summaryCards = [
        ["title" => "Trust Score", "value" => $scores["trust"] . "%", "note" => $connected ? "Calculated from GitHub data" : "GitHub not connected", "color" => $connected ? "text-green-700 bg-green-50" : "text-slate-500 bg-slate-100"],
        ["title" => "Career Readiness", "value" => $scores["career"] . "%", "note" => $connected ? "Based on project evidence" : "No evidence found", "color" => $connected ? "text-blue-800 bg-blue-50" : "text-slate-500 bg-slate-100"],
        ["title" => "Repository Quality", "value" => $scores["repository"] . "%", "note" => $connected ? "Real repository analysis" : "No repository data", "color" => $connected ? "text-indigo-800 bg-indigo-50" : "text-slate-500 bg-slate-100"],
        ["title" => "Verified Skills", "value" => $verifiedSkills, "note" => $connected ? "Skills above threshold" : "GitHub not connected", "color" => $connected ? "text-green-700 bg-green-50" : "text-slate-500 bg-slate-100"]
    ];

    $skills = [];
    foreach ($skillScores as $name => $skillData) {
        $score = (int)($skillData["score"] ?? 0);
        $skills[] = [
            "name" => $name,
            "score" => $score,
            "level" => sp_skill_level($score),
            "evidence" => $skillData["evidence"] ?? "Detected from GitHub evidence.",
            "status" => sp_skill_status($score, $connected),
            "type" => $skillData["type"] ?? "language"
        ];
    }

    usort($skills, function ($a, $b) {
        if ($b["score"] === $a["score"]) return strcmp($a["name"], $b["name"]);
        return $b["score"] <=> $a["score"];
    });

    $dimensions = [
        ["name" => "Repository Quality", "score" => $scores["repository"], "evidence" => "Original repositories, descriptions, stars, forks, licenses, and topics"],
        ["name" => "Documentation Quality", "score" => $scores["documentation"], "evidence" => "README files and repository descriptions"],
        ["name" => "Project Complexity", "score" => $scores["complexity"], "evidence" => "Detected languages, config files, original repositories, and project structure"],
        ["name" => "Developer Professionalism", "score" => $scores["professionalism"], "evidence" => "Original projects, .gitignore usage, recent activity, licenses, and CI signals"],
        ["name" => "Recent Activity", "score" => $scores["activity"], "evidence" => "Repositories updated within the last 180 days"],
        ["name" => "Testing Readiness", "score" => $scores["testing"], "evidence" => "Test folders, test files, spec files, and testing-related structure"],
        ["name" => "Security Awareness", "score" => $scores["security"], "evidence" => ".gitignore usage, exposed .env check, security files, and dependency configuration"],
        ["name" => "DevOps Readiness", "score" => $scores["devops"], "evidence" => "Dockerfile, Docker Compose, and GitHub Actions workflow evidence"],
        ["name" => "Career Readiness", "score" => $scores["career"], "evidence" => "Combined score from repository, documentation, complexity, professionalism, and security"]
    ];

    return [
        "connected" => $connected,
        "username" => $data["username"],
        "error" => $data["error"],
        "profile" => $data["profile"],
        "repoStats" => $repoStats,
        "scores" => $scores,
        "summaryCards" => $summaryCards,
        "skills" => $skills,
        "dimensions" => $dimensions,
        "learningGaps" => $data["learningGaps"],
        "activities" => $data["activities"]
    ];
}

function skillproof_analyze_github_user($username) {
    $username = trim($username);
    if ($username === "") return sp_make_default_result();

    try {
        return sp_analyze_github_evidence($username);
    } catch (RuntimeException $e) {
        // No partial scores can escape as a successful analysis.
        return sp_make_default_result($username, $e->getMessage());
    }
}

function sp_analyze_github_evidence($username) {
    $profile = sp_api_evidence("https://api.github.com/users/" . rawurlencode($username), "profile");
    $repos = sp_api_evidence("https://api.github.com/users/" . rawurlencode($username) . "/repos?per_page=100&sort=updated", "repos");
    $totalRepositories = count($repos);
    if ($totalRepositories === 0) return sp_make_no_repo_result($username, $profile);

    $originalRepositories = 0;
    $forkedRepositories = 0;
    $totalStars = 0;
    $totalForks = 0;
    $recentRepositories = 0;
    $descriptionCount = 0;
    $licenseCount = 0;
    $topicCount = 0;
    $languages = [];
    $languageRepoNames = [];
    $readmeCount = 0;
    $gitignoreCount = 0;
    $envFileCount = 0;
    $configFileCount = 0;
    $testEvidenceCount = 0;
    $sqlEvidenceCount = 0;
    $ciCount = 0;
    $dockerCount = 0;
    $securityFileCount = 0;

    foreach ($repos as $repo) {
        $repoName = $repo["name"] ?? "repository";
        if (!empty($repo["fork"])) $forkedRepositories++; else $originalRepositories++;
        $totalStars += (int)($repo["stargazers_count"] ?? 0);
        $totalForks += (int)($repo["forks_count"] ?? 0);
        if (!empty($repo["description"])) $descriptionCount++;
        if (!empty($repo["license"])) $licenseCount++;
        if (!empty($repo["topics"]) && is_array($repo["topics"]) && count($repo["topics"]) > 0) $topicCount++;

        $updatedAt = $repo["updated_at"] ?? "";
        if ($updatedAt !== "") {
            $updatedTime = strtotime($updatedAt);
            if ($updatedTime !== false && $updatedTime >= strtotime("-180 days")) $recentRepositories++;
        }

        $primaryLanguage = $repo["language"] ?? "";
        if (!empty($primaryLanguage)) {
            if (!isset($languages[$primaryLanguage])) $languages[$primaryLanguage] = 0;
            if (!isset($languageRepoNames[$primaryLanguage])) $languageRepoNames[$primaryLanguage] = [];
            $languageRepoNames[$primaryLanguage][$repoName] = true;
        }
    }

    $detailRepoLimit = 20;
    $sampleRepos = array_slice($repos, 0, $detailRepoLimit);
    $sampleCount = max(1, count($sampleRepos));

    foreach ($sampleRepos as $repo) {
        $repoName = $repo["name"] ?? "";
        $base = "https://api.github.com/repos/" . rawurlencode($username) . "/" . rawurlencode($repoName);

        $languageData = sp_api_evidence($base . "/languages", "languages");
        foreach ($languageData as $language => $bytes) {
            if (!isset($languages[$language])) $languages[$language] = 0;
            if (!isset($languageRepoNames[$language])) $languageRepoNames[$language] = [];
            $languages[$language] += (int)$bytes;
            $languageRepoNames[$language][$repoName] = true;
        }

        $readmeData = sp_api_evidence($base . "/readme", "readme", true);
        if ($readmeData !== null) $readmeCount++;

        $contents = sp_api_evidence($base . "/contents", "contents");
        $hasGithubEntry = false;
        foreach ($contents as $item) {
            if ($item["name"] === ".github") $hasGithubEntry = true;
            $name = strtolower($item["name"] ?? "");
            $path = strtolower($item["path"] ?? "");

            if ($name === ".gitignore") $gitignoreCount++;
            if ($name === ".env") $envFileCount++;

            if (in_array($name, [
                "package.json", "composer.json", "requirements.txt", "pyproject.toml",
                "pom.xml", "build.gradle", "vite.config.js", "webpack.config.js",
                "next.config.js", "composer.lock", "package-lock.json"
            ], true)) $configFileCount++;

            if ($name === "dockerfile" || $name === "docker-compose.yml" || $name === "docker-compose.yaml") $dockerCount++;

            if (sp_contains_text($name, "test") || sp_contains_text($path, "test") || sp_contains_text($path, "spec") || sp_contains_text($path, "__tests__")) $testEvidenceCount++;

            if (sp_ends_with_text($name, ".sql") || sp_contains_text($path, "database") || sp_contains_text($path, "migration") || sp_contains_text($path, "schema")) $sqlEvidenceCount++;

            if ($name === "security.md" || sp_contains_text($path, "security") || sp_contains_text($name, "dependabot")) $securityFileCount++;
        }

        // A complete root listing without .github proves workflows are absent.
        // At GitHub's 1,000-entry limit the listing may be truncated, so still check.
        if ($hasGithubEntry || count($contents) >= 1000) {
            $workflowData = sp_api_evidence($base . "/contents/.github/workflows", "contents", true);
            if ($workflowData !== null) $ciCount++;
        }
    }

    $repoDenominator = max(1, $totalRepositories);
    $originalRatio = $originalRepositories / $repoDenominator;
    $recentRatio = $recentRepositories / $repoDenominator;
    $descriptionRatio = $descriptionCount / $repoDenominator;
    $licenseRatio = $licenseCount / $repoDenominator;
    $topicRatio = $topicCount / $repoDenominator;
    $readmeRatio = $readmeCount / $sampleCount;
    $gitignoreRatio = $gitignoreCount / $sampleCount;
    $configRatio = $configFileCount / $sampleCount;
    $testRatio = $testEvidenceCount / $sampleCount;
    $ciRatio = $ciCount / $sampleCount;
    $dockerRatio = $dockerCount / $sampleCount;
    $securityFileRatio = $securityFileCount / $sampleCount;
    $noEnvRatio = $envFileCount === 0 ? 1 : 0;

    $repositoryQuality = sp_score_limit(($originalRatio * 25) + min(25, $originalRepositories * 8) + ($descriptionRatio * 18) + ($licenseRatio * 12) + ($topicRatio * 10) + min(10, $totalStars + $totalForks));
    $documentationScore = sp_score_limit(($readmeRatio * 65) + ($descriptionRatio * 25) + ($topicRatio * 10));
    $projectComplexity = sp_score_limit(min(35, count($languages) * 7) + min(20, $originalRepositories * 5) + ($configRatio * 20) + ($dockerRatio * 15) + ($ciRatio * 10));
    $activityScore = sp_score_limit($recentRatio * 100);
    $developerProfessionalism = sp_score_limit(($originalRatio * 30) + ($recentRatio * 20) + ($gitignoreRatio * 20) + ($ciRatio * 15) + ($licenseRatio * 10) + ($topicRatio * 5));
    $testingReadiness = sp_score_limit($testRatio * 100);
    $securityAwareness = sp_score_limit(($gitignoreRatio * 40) + ($noEnvRatio * 25) + ($configRatio * 15) + ($securityFileRatio * 20));
    $devopsReadiness = sp_score_limit(($ciRatio * 55) + ($dockerRatio * 35) + ($configRatio * 10));

    $skillScores = [];
    $totalLanguageBytes = max(1, array_sum($languages));

    foreach ($languages as $language => $bytes) {
        $repoNames = array_keys($languageRepoNames[$language] ?? []);
        $repoCountForLanguage = count($repoNames);
        $languageShare = ((int)$bytes > 0) ? (((int)$bytes / $totalLanguageBytes) * 100) : 0;
        $repoUsageRatio = ($repoCountForLanguage / $repoDenominator) * 100;

        $score = sp_score_limit(($languageShare * 0.40) + ($repoUsageRatio * 0.25) + ($repositoryQuality * 0.12) + ($documentationScore * 0.10) + ($activityScore * 0.08) + ($projectComplexity * 0.05));
        if ($repoCountForLanguage > 0 && $score < 15) $score = 15;

        $repoExamples = array_slice($repoNames, 0, 3);
        $repoText = count($repoExamples) > 0 ? implode(", ", $repoExamples) : "public repositories";

        $skillScores[$language] = [
            "score" => $score,
            "evidence" => "Detected from GitHub language data. Repositories: " . $repoText . ".",
            "type" => "language"
        ];
    }

    if ($sqlEvidenceCount > 0) {
        $skillScores["SQL"] = [
            "score" => sp_score_limit(65 + ($sqlEvidenceCount * 5)),
            "evidence" => "Detected from SQL files, database folders, schema, or migration evidence.",
            "type" => "language"
        ];
    }

    $skillScores["Git & GitHub"] = [
        "score" => sp_score_limit(($originalRatio * 35) + min(25, $totalRepositories * 5) + ($recentRatio * 25) + ($topicRatio * 5) + min(10, $totalStars + $totalForks)),
        "evidence" => "Calculated from public repositories, original projects, recent updates, stars, forks, and topics.",
        "type" => "platform"
    ];
    $skillScores["Documentation"] = ["score" => $documentationScore, "evidence" => "Calculated from README files, descriptions, and repository topics.", "type" => "platform"];
    $skillScores["Testing"] = ["score" => $testingReadiness, "evidence" => "Calculated from test folders, test files, spec files, and testing-related structure.", "type" => "platform"];
    $skillScores["Security Awareness"] = ["score" => $securityAwareness, "evidence" => "Calculated from .gitignore, exposed .env check, security files, and dependency configuration.", "type" => "platform"];
    $skillScores["DevOps"] = ["score" => $devopsReadiness, "evidence" => "Calculated from GitHub Actions workflow, Dockerfile, Docker Compose, and config files.", "type" => "platform"];

    $careerReadiness = sp_score_limit(($repositoryQuality + $documentationScore + $projectComplexity + $developerProfessionalism + $securityAwareness) / 5);
    $trustScore = sp_score_limit(($repositoryQuality * 0.18) + ($documentationScore * 0.18) + ($activityScore * 0.17) + ($developerProfessionalism * 0.17) + ($projectComplexity * 0.12) + ($securityAwareness * 0.10) + ($testingReadiness * 0.04) + ($devopsReadiness * 0.04));

    $learningGaps = [];
    if ($documentationScore < 60) $learningGaps[] = ["title" => "Improve Documentation", "text" => "Add README files, clear project descriptions, usage steps, screenshots, and setup instructions.", "priority" => "High Priority"];
    if ($testingReadiness < 50) $learningGaps[] = ["title" => "Add Testing Evidence", "text" => "Add test folders, test files, or basic testing documentation to improve testing score.", "priority" => "Medium Priority"];
    if (!isset($skillScores["SQL"])) $learningGaps[] = ["title" => "Add Database Evidence", "text" => "Add SQL files, schema design, migration files, or database documentation to show database skill.", "priority" => "Medium Priority"];
    if ($securityAwareness < 60) $learningGaps[] = ["title" => "Improve Security Practice", "text" => "Use .gitignore, avoid exposing .env files, and add basic security notes.", "priority" => "Medium Priority"];
    if ($devopsReadiness < 40) $learningGaps[] = ["title" => "Add DevOps Evidence", "text" => "Add GitHub Actions, Dockerfile, or deployment configuration to improve DevOps score.", "priority" => "Low Priority"];
    if (count($learningGaps) === 0) $learningGaps[] = ["title" => "Profile Looks Strong", "text" => "GitHub evidence is available and the profile has strong public activity.", "priority" => "Low Priority"];

    $activities = [
        "GitHub username connected: " . $username,
        "Public repositories analyzed: " . $totalRepositories,
        "Detailed repositories scanned: " . count($sampleRepos),
        "Original repositories counted: " . $originalRepositories,
        "Languages detected: " . count($languages),
        "README files found: " . $readmeCount,
        "Scores calculated by SkillProof Analysis Engine v2."
    ];

    return sp_build_result([
        "connected" => true,
        "username" => $username,
        "error" => "",
        "profile" => $profile,
        "repoStats" => [
            "total" => $totalRepositories,
            "original" => $originalRepositories,
            "forked" => $forkedRepositories,
            "stars" => $totalStars,
            "forks" => $totalForks,
            "languages_count" => count($languages),
            "readme_count" => $readmeCount,
            "sample_count" => count($sampleRepos)
        ],
        "scores" => [
            "trust" => $trustScore,
            "career" => $careerReadiness,
            "repository" => $repositoryQuality,
            "documentation" => $documentationScore,
            "complexity" => $projectComplexity,
            "professionalism" => $developerProfessionalism,
            "activity" => $activityScore,
            "testing" => $testingReadiness,
            "security" => $securityAwareness,
            "devops" => $devopsReadiness
        ],
        "skillScores" => $skillScores,
        "activities" => $activities,
        "learningGaps" => $learningGaps
    ]);
}
?>
