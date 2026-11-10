<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION["user_email"])) {
    header("Location: login.php");
    exit();
}

if (($_SESSION["user_role"] ?? "") === "Recruiter") {
    header("Location: recruiter_dashboard.php");
    exit();
}

$timeout_duration = 300;

if (
    isset($_SESSION["last_activity"]) &&
    (time() - $_SESSION["last_activity"]) > $timeout_duration
) {
    session_unset();
    session_destroy();
    session_id("");
    session_start();
    $_SESSION["login_flash"] = "Session timed out due to inactivity";
    header("Location: login.php");
    exit();
}

$_SESSION["last_activity"] = time();

require_once __DIR__ . "/engine/skillproof_engine.php";
require_once __DIR__ . "/csrf.php";

function safe_output($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

$csrf_token = sp_csrf_token();

require_once __DIR__ . "/db_connect.php";
$pdo = skillproof_db();

$user_email = $_SESSION["user_email"];
$user_role = $_SESSION["user_role"] ?? "Developer";
$login_time = $_SESSION["login_time"] ?? time();

$userLookup = $pdo->prepare("SELECT user_id, github_username FROM users WHERE email = ? LIMIT 1");
$userLookup->execute([$user_email]);
$userRow = $userLookup->fetch();
$current_user_id = $userRow ? (int) $userRow["user_id"] : null;
$github_username = $userRow["github_username"] ?? "";
$github_error = "";
$flash = $_SESSION["dashboard_flash"] ?? [];
unset($_SESSION["dashboard_flash"]);
$status_message = $flash["message"] ?? "";
$status_type = $flash["type"] ?? "success";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!sp_csrf_verify($_POST["csrf_token"] ?? "")) {
        $status_message = "Your session expired. Please try again.";
        $status_type = "error";
    } elseif (($_POST["action"] ?? "") !== "analyze_github") {
        $status_message = "Invalid dashboard action.";
        $status_type = "error";
    } elseif (!$current_user_id || $github_username === "") {
        $status_message = "Add a GitHub username to your profile before analyzing.";
        $status_type = "error";
    } else {
        // Consume this form token before the API call. A replay cannot run or save again.
        unset($_SESSION["csrf_token"]);
        sp_csrf_token();
        $analysisResult = skillproof_analyze_github_user($github_username);

        if (!($analysisResult["connected"] ?? false) || !empty($analysisResult["error"] ?? "")) {
            $status_message = ($analysisResult["error"] ?: "GitHub analysis failed.") . " Your previous result was kept.";
            $status_type = "error";
        } else {
            $repoStats = $analysisResult["repoStats"] ?? [];
            $skills = $analysisResult["skills"] ?? [];
            $dimensions = $analysisResult["dimensions"] ?? [];
            $learningGaps = $analysisResult["learningGaps"] ?? [];

            try {
                $pdo->beginTransaction();
                $insertAnalysis = $pdo->prepare(
                    "INSERT INTO skill_analyses
                        (user_id, github_username, total_repositories, original_repositories,
                        forked_repositories, total_stars, total_forks, languages_detected, readme_count,
                        trust_score, career_score)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $insertAnalysis->execute([
                    $current_user_id, $github_username, $repoStats["total"] ?? 0,
                    $repoStats["original"] ?? 0, $repoStats["forked"] ?? 0,
                    $repoStats["stars"] ?? 0, $repoStats["forks"] ?? 0,
                    $repoStats["languages_count"] ?? 0, $repoStats["readme_count"] ?? 0,
                    $analysisResult["scores"]["trust"] ?? 0, $analysisResult["scores"]["career"] ?? 0
                ]);
                $newAnalysisId = (int) $pdo->lastInsertId();

                $insertSkill = $pdo->prepare(
                    "INSERT INTO skill_scores (analysis_id, skill_name, score, level, evidence, status) VALUES (?, ?, ?, ?, ?, ?)"
                );
                foreach ($skills as $skill) {
                    $insertSkill->execute([
                        $newAnalysisId, $skill["name"] ?? "", $skill["score"] ?? 0,
                        $skill["level"] ?? "", $skill["evidence"] ?? "",
                        $skill["status"] ?? "Not Detected"
                    ]);
                }

                $insertDimension = $pdo->prepare(
                    "INSERT INTO dimension_scores (analysis_id, dimension_name, score, evidence) VALUES (?, ?, ?, ?)"
                );
                foreach ($dimensions as $dimension) {
                    $insertDimension->execute([
                        $newAnalysisId, $dimension["name"] ?? "", $dimension["score"] ?? 0,
                        $dimension["evidence"] ?? ""
                    ]);
                }

                $insertGap = $pdo->prepare(
                    "INSERT INTO learning_gaps (analysis_id, title, description, priority) VALUES (?, ?, ?, ?)"
                );
                foreach ($learningGaps as $gap) {
                    $insertGap->execute([
                        $newAnalysisId, $gap["title"] ?? "",
                        $gap["text"] ?? ($gap["description"] ?? ""),
                        $gap["priority"] ?? "Low Priority"
                    ]);
                }
                $pdo->commit();
                $status_message = "GitHub analysis saved successfully.";
                $status_type = "success";
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("SkillProof dashboard analysis save failed: " . $e->getMessage());
                $status_message = "The analysis could not be saved. Your previous result was kept.";
                $status_type = "error";
            }
        }
    }
    $_SESSION["dashboard_flash"] = ["message" => $status_message, "type" => $status_type];
    header("Location: dashboard.php#github", true, 303);
    exit();
}

$latestAnalysis = null;
$skills = [];
$dimensions = [];
$learningGaps = [];
$analysisHistory = [];
if ($current_user_id) {
    $latestStmt = $pdo->prepare("SELECT * FROM skill_analyses WHERE user_id = ? ORDER BY analyzed_at DESC, analysis_id DESC LIMIT 1");
    $latestStmt->execute([$current_user_id]);
    $latestAnalysis = $latestStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($latestAnalysis) {
        $skillStmt = $pdo->prepare("SELECT skill_name, score, level, evidence, status FROM skill_scores WHERE analysis_id = ? ORDER BY score DESC, skill_name ASC");
        $skillStmt->execute([$latestAnalysis["analysis_id"]]);
        foreach ($skillStmt->fetchAll(PDO::FETCH_ASSOC) as $skill) {
            $skills[] = [
                "name" => $skill["skill_name"], "score" => (int) $skill["score"],
                "level" => $skill["level"], "evidence" => $skill["evidence"],
                "status" => $skill["status"]
            ];
        }

        $dimensionStmt = $pdo->prepare("SELECT dimension_name, score, evidence FROM dimension_scores WHERE analysis_id = ? ORDER BY dimension_id ASC");
        $dimensionStmt->execute([$latestAnalysis["analysis_id"]]);
        foreach ($dimensionStmt->fetchAll(PDO::FETCH_ASSOC) as $dimension) {
            $dimensions[] = [
                "name" => $dimension["dimension_name"], "score" => (int) $dimension["score"],
                "evidence" => $dimension["evidence"]
            ];
        }

        $gapStmt = $pdo->prepare("SELECT title, description, priority FROM learning_gaps WHERE analysis_id = ? ORDER BY gap_id ASC");
        $gapStmt->execute([$latestAnalysis["analysis_id"]]);
        foreach ($gapStmt->fetchAll(PDO::FETCH_ASSOC) as $gap) {
            $learningGaps[] = [
                "title" => $gap["title"], "text" => $gap["description"], "priority" => $gap["priority"]
            ];
        }
    }

    $historyStmt = $pdo->prepare(
        "SELECT github_username, total_repositories, total_stars, analyzed_at
        FROM skill_analyses WHERE user_id = ? ORDER BY analyzed_at DESC, analysis_id DESC LIMIT 5"
    );
    $historyStmt->execute([$current_user_id]);
    $analysisHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
}

$has_saved_analysis = $latestAnalysis !== null;
$github_connected = $has_saved_analysis && $latestAnalysis["github_username"] === $github_username;
$analysis_username_changed = $has_saved_analysis && !$github_connected;
$repoStats = [
    "total" => (int) ($latestAnalysis["total_repositories"] ?? 0),
    "original" => (int) ($latestAnalysis["original_repositories"] ?? 0),
    "forked" => (int) ($latestAnalysis["forked_repositories"] ?? 0),
    "stars" => (int) ($latestAnalysis["total_stars"] ?? 0),
    "forks" => (int) ($latestAnalysis["total_forks"] ?? 0),
    "languages_count" => (int) ($latestAnalysis["languages_detected"] ?? 0),
    "readme_count" => (int) ($latestAnalysis["readme_count"] ?? 0)
];
$dimensionScores = [];
foreach ($dimensions as $dimension) {
    $dimensionScores[$dimension["name"]] = $dimension["score"];
}
$verifiedSkills = 0;
foreach ($skills as $skill) {
    if ($skill["score"] >= 60) {
        $verifiedSkills++;
    }
}
$summaryCards = [
    ["title" => "Trust Score", "value" => (int) ($latestAnalysis["trust_score"] ?? 0) . "%", "note" => $has_saved_analysis ? "Saved analysis result" : "Not analyzed yet", "color" => $has_saved_analysis ? "text-green-700 bg-green-50" : "text-slate-500 bg-slate-100"],
    ["title" => "Career Readiness", "value" => (int) ($latestAnalysis["career_score"] ?? 0) . "%", "note" => $has_saved_analysis ? "Based on saved evidence" : "Not analyzed yet", "color" => $has_saved_analysis ? "text-blue-800 bg-blue-50" : "text-slate-500 bg-slate-100"],
    ["title" => "Repository Quality", "value" => (int) ($dimensionScores["Repository Quality"] ?? 0) . "%", "note" => $has_saved_analysis ? "Saved repository analysis" : "Not analyzed yet", "color" => $has_saved_analysis ? "text-indigo-800 bg-indigo-50" : "text-slate-500 bg-slate-100"],
    ["title" => "Verified Skills", "value" => $verifiedSkills, "note" => $has_saved_analysis ? "Skills above threshold" : "Not analyzed yet", "color" => $has_saved_analysis ? "text-green-700 bg-green-50" : "text-slate-500 bg-slate-100"]
];
if (!$has_saved_analysis) {
    require_once __DIR__ . "/engine/skillproof_engine.php";
    $defaultResult = sp_make_default_result($github_username);
    $skills = $defaultResult["skills"];
    $dimensions = $defaultResult["dimensions"];
    $learningGaps = $defaultResult["learningGaps"];
}
$activities = $has_saved_analysis
    ? ["Latest saved analysis: " . ($latestAnalysis["analyzed_at"] ?? "date unavailable") . "."]
    : ["No GitHub analysis has been saved yet.", "Use Analyze GitHub to create your first result."];
$masked_session_id = session_id() !== "" ? substr(session_id(), 0, 8) . "••••••••" : "Unavailable";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkillProof - Developer Dashboard</title>
    <link rel="stylesheet" href="../assets/css/tailwind.css?v=20261110">
    <script src="../assets/js/navigation.js?v=20261110" defer></script>
</head>

<body class="bg-slate-100 text-slate-900">

<div class="min-h-screen flex">

    <aside class="w-64 bg-slate-950 text-white fixed top-0 left-0 h-full hidden lg:flex flex-col overflow-y-auto">
        <div class="p-6 border-b border-slate-800">
            <h1 class="text-2xl font-extrabold">SkillProof</h1>
            <p class="text-xs text-slate-400 mt-1">Evidence-based developer assessment</p>
        </div>

        <nav class="p-4 space-y-2 text-sm">
            <a href="../home.php" class="block px-4 py-3 rounded-lg hover:bg-slate-800 mb-2 border-b border-slate-800 pb-4">
                &larr; Back to Home
            </a>
            <a href="#dashboard" class="block px-4 py-3 rounded-lg bg-blue-600 font-bold">Dashboard</a>
            <a href="#github" class="block px-4 py-3 rounded-lg hover:bg-slate-800">GitHub Connection</a>
            <a href="#skills" class="block px-4 py-3 rounded-lg hover:bg-slate-800">Skill Verification</a>
            <a href="#dimensions" class="block px-4 py-3 rounded-lg hover:bg-slate-800">Score Dimensions</a>
            <a href="#gaps" class="block px-4 py-3 rounded-lg hover:bg-slate-800">Learning Gap</a>
            <a href="#history" class="block px-4 py-3 rounded-lg hover:bg-slate-800">Analysis History</a>
            <a href="#security" class="block px-4 py-3 rounded-lg hover:bg-slate-800">Account Security</a>
            <a href="profile.php" class="block px-4 py-3 rounded-lg hover:bg-slate-800 border-t border-slate-800 mt-2 pt-4">My Profile</a>
        </nav>

        <div class="mt-auto p-4">
            <a href="logout.php" class="block text-center bg-red-600 hover:bg-red-500 text-white font-bold py-3 rounded-lg">
                Logout
            </a>
        </div>
    </aside>

    <main class="min-w-0 flex-1 lg:ml-64">

        <div class="lg:hidden bg-slate-950 text-white">
            <div class="px-4 sm:px-6 py-3 flex items-center justify-between gap-3">
                <a href="../home.php" class="text-xl font-extrabold">SkillProof</a>
                <button id="menuButton" type="button" aria-controls="mobileMenu" aria-expanded="false" aria-label="Toggle dashboard navigation" data-menu-breakpoint="1024" class="min-h-11 bg-slate-800 px-4 py-2 rounded-lg text-sm font-bold">Menu</button>
            </div>
            <nav id="mobileMenu" aria-label="Dashboard navigation" class="hidden lg:hidden border-t border-slate-800 p-4 space-y-2 text-sm">
                <a href="../home.php" class="block px-4 py-3 rounded-lg hover:bg-slate-800 mb-2 border-b border-slate-800 pb-4">
                    &larr; Back to Home
                </a>
                <a href="#dashboard" class="block px-4 py-3 rounded-lg bg-blue-600 font-bold">Dashboard</a>
                <a href="#github" class="block px-4 py-3 rounded-lg hover:bg-slate-800">GitHub Connection</a>
                <a href="#skills" class="block px-4 py-3 rounded-lg hover:bg-slate-800">Skill Verification</a>
                <a href="#dimensions" class="block px-4 py-3 rounded-lg hover:bg-slate-800">Score Dimensions</a>
                <a href="#gaps" class="block px-4 py-3 rounded-lg hover:bg-slate-800">Learning Gap</a>
                <a href="#history" class="block px-4 py-3 rounded-lg hover:bg-slate-800">Analysis History</a>
                <a href="#security" class="block px-4 py-3 rounded-lg hover:bg-slate-800">Account Security</a>
                <a href="profile.php" class="block px-4 py-3 rounded-lg hover:bg-slate-800 border-t border-slate-800 mt-2 pt-4">My Profile</a>
                <a href="logout.php" class="block px-4 py-3 rounded-lg bg-red-600 hover:bg-red-500 font-bold">Logout</a>
            </nav>
        </div>

        <header class="bg-white border-b border-slate-200 px-4 sm:px-6 py-5 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl sm:text-2xl font-extrabold text-slate-950">Developer Dashboard</h2>
                <p class="text-sm text-slate-500">
                    Welcome,
                    <span class="font-bold text-blue-700 break-all"><?php echo safe_output($user_email); ?></span>
                </p>
            </div>

            <span class="px-4 py-2 rounded-full bg-green-100 text-green-700 text-sm font-bold">
                <?php echo safe_output($user_role); ?>
            </span>
        </header>

        <section id="dashboard" class="p-4 sm:p-6 space-y-6">

            <div class="bg-gradient-to-r from-blue-900 to-slate-950 text-white rounded-2xl p-5 sm:p-8 shadow">
                <h3 class="text-2xl sm:text-3xl font-extrabold mb-3">Saved Skill Verification</h3>
                <p class="text-blue-100 max-w-4xl leading-relaxed">
                    SkillProof displays your latest saved GitHub analysis. Run a manual sync when
                    you want to collect fresh public GitHub evidence.
                </p>
            </div>

            <div id="github" class="bg-white border border-slate-200 rounded-xl p-4 sm:p-6 shadow-sm">
                <h3 class="text-xl font-extrabold">Connect GitHub Account</h3>
                <p class="text-sm text-slate-500 mt-1">
                    Your profile username is used for manual analysis. Dashboard loads never contact GitHub.
                </p>

                <?php if (!empty($status_message)): ?>
                    <div class="mt-4 <?php echo $status_type === "error" ? "bg-red-50 border-red-300 text-red-700" : "bg-green-50 border-green-300 text-green-700"; ?> border px-4 py-3 rounded-xl text-sm">
                        <?php echo safe_output($status_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($github_username)): ?>
                    <div class="mt-4 bg-amber-50 border border-amber-300 text-amber-700 px-4 py-3 rounded-xl text-sm">
                        No GitHub username is on file for your account. Please add one from
                        <a href="profile.php" class="underline font-bold">My Profile</a> before syncing.
                    </div>
                <?php else: ?>
                    <form method="POST" action="dashboard.php#github" class="mt-5 flex flex-col md:flex-row gap-3">
                        <input type="hidden" name="csrf_token" value="<?php echo safe_output($csrf_token); ?>">
                        <input type="hidden" name="action" value="analyze_github">
                        <input
                            type="text"
                            value="<?php echo safe_output($github_username); ?>"
                            readonly
                            class="min-w-0 w-full flex-1 px-4 py-3 border border-slate-200 bg-slate-100 text-slate-500 rounded-xl cursor-not-allowed"
                        >

                        <button
                            type="submit"
                            class="bg-blue-600 hover:bg-blue-500 text-white px-6 py-3 rounded-xl font-bold whitespace-nowrap"
                        >
                            Analyze GitHub
                        </button>
                    </form>
                    <p class="text-xs text-slate-400 mt-2">
                        Locked to the GitHub account on your profile. To verify a different account,
                        update it under <a href="profile.php" class="underline">My Profile</a> first.
                    </p>
                <?php endif; ?>

                <div class="mt-4 text-sm">
                    <?php if ($github_connected): ?>
                        <span class="inline-block max-w-full break-words bg-green-100 text-green-700 px-3 py-1 rounded-full font-bold">
                            Saved result for: <?php echo safe_output($github_username); ?>
                        </span>
                    <?php elseif ($analysis_username_changed): ?>
                        <div class="bg-amber-50 border border-amber-300 text-amber-700 px-4 py-3 rounded-xl">
                            Your saved result belongs to
                            <strong><?php echo safe_output($latestAnalysis["github_username"]); ?></strong>.
                            Your profile now lists <strong><?php echo safe_output($github_username); ?></strong>;
                            analyze GitHub to create a new result.
                        </div>
                    <?php else: ?>
                        <span class="bg-slate-100 text-slate-600 px-3 py-1 rounded-full font-bold">
                            Not analyzed yet
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <?php foreach ($summaryCards as $card): ?>
                    <div class="bg-white border border-slate-200 rounded-xl p-4 sm:p-6 shadow-sm">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                            <?php echo safe_output($card["title"]); ?>
                        </p>
                        <h3 class="text-3xl font-extrabold mt-3">
                            <?php echo safe_output($card["value"]); ?>
                        </h3>
                        <p class="text-sm mt-2 <?php echo safe_output($card["color"]); ?> inline-block px-3 py-1 rounded-full font-semibold">
                            <?php echo safe_output($card["note"]); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6" id="skills">

                <div class="xl:col-span-2 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                    <div class="p-4 sm:p-6 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-xl font-extrabold">Skill Score Matrix</h3>
                            <p class="text-sm text-slate-500">Scores are calculated from real GitHub evidence.</p>
                        </div>
                        <span class="bg-blue-100 text-blue-700 text-xs font-bold px-3 py-1 rounded-full">
                            Saved Data
                        </span>
                    </div>

                    <div class="overflow-x-auto" role="region" aria-label="Skill scores; scroll horizontally to see all columns" tabindex="0">
                        <table class="w-full min-w-[640px] text-sm">
                            <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                                <tr>
                                    <th class="text-left px-5 py-3">Skill</th>
                                    <th class="text-left px-5 py-3">Score</th>
                                    <th class="text-left px-5 py-3">Level</th>
                                    <th class="text-left px-5 py-3">Evidence</th>
                                    <th class="text-left px-5 py-3">Status</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($skills as $skill): ?>
                                    <tr>
                                        <td class="px-5 py-4 font-bold">
                                            <?php echo safe_output($skill["name"]); ?>
                                        </td>

                                        <td class="px-5 py-4">
                                            <div class="font-extrabold text-blue-900">
                                                <?php echo safe_output($skill["score"]); ?>%
                                            </div>
                                            <div class="w-24 bg-slate-200 rounded-full h-2 mt-2">
                                                <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo safe_output($skill["score"]); ?>%;"></div>
                                            </div>
                                        </td>

                                        <td class="px-5 py-4 text-slate-600">
                                            <?php echo safe_output($skill["level"]); ?>
                                        </td>

                                        <td class="px-5 py-4 text-slate-600">
                                            <?php echo safe_output($skill["evidence"]); ?>
                                        </td>

                                        <td class="px-5 py-4">
                                            <?php if ($skill["status"] === "Verified"): ?>
                                                <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-bold">
                                                    Verified
                                                </span>
                                            <?php elseif ($skill["status"] === "Needs Evidence"): ?>
                                                <span class="bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-xs font-bold">
                                                    Needs Evidence
                                                </span>
                                            <?php elseif ($skill["status"] === "Low Evidence"): ?>
                                                <span class="bg-amber-100 text-amber-700 px-3 py-1 rounded-full text-xs font-bold">
                                                    Low Evidence
                                                </span>
                                            <?php elseif ($skill["status"] === "Not Detected"): ?>
                                                <span class="bg-slate-100 text-slate-600 px-3 py-1 rounded-full text-xs font-bold">
                                                    Not Detected
                                                </span>
                                            <?php else: ?>
                                                <span class="bg-slate-100 text-slate-600 px-3 py-1 rounded-full text-xs font-bold">
                                                    Not Connected
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl p-4 sm:p-6 shadow-sm">
                    <h3 class="text-xl font-extrabold">Saved Analysis Activity</h3>
                    <p class="text-sm text-slate-500 mb-5">Latest saved analysis status</p>

                    <ul class="space-y-4 text-sm text-slate-700">
                        <?php foreach ($activities as $activity): ?>
                            <li class="flex gap-3">
                                <span class="w-2 h-2 shrink-0 rounded-full bg-blue-600 mt-2"></span>
                                <span><?php echo safe_output($activity); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div id="dimensions" class="bg-white border border-slate-200 rounded-xl p-4 sm:p-6 shadow-sm">
                <p class="text-xs uppercase tracking-widest text-blue-700 font-extrabold">Evidence Dimensions</p>
                <h3 class="text-xl font-extrabold mt-1">Assessment Dimension Scores</h3>
                <p class="text-sm text-slate-500">
                    These scores come from the latest saved analysis and remain 0 until you run one.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mt-5">
                    <?php foreach ($dimensions as $dimension): ?>
                        <div class="border border-slate-200 rounded-xl p-5 bg-slate-50">
                            <div class="flex items-start justify-between gap-3">
                                <h4 class="font-extrabold"><?php echo safe_output($dimension["name"]); ?></h4>
                                <span class="shrink-0 text-blue-800 font-extrabold">
                                    <?php echo safe_output($dimension["score"]); ?>%
                                </span>
                            </div>

                            <div class="w-full bg-slate-200 rounded-full h-2 mt-4">
                                <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo safe_output($dimension["score"]); ?>%;"></div>
                            </div>

                            <p class="text-sm text-slate-600 mt-3">
                                Evidence: <?php echo safe_output($dimension["evidence"]); ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="gaps" class="bg-white border border-slate-200 rounded-xl p-4 sm:p-6 shadow-sm">
                <p class="text-xs uppercase tracking-widest text-blue-700 font-extrabold">Learning Gap</p>
                <h3 class="text-xl font-extrabold mt-1">Recommended Improvements</h3>

                <div class="mt-5 space-y-4">
                    <?php foreach ($learningGaps as $gap): ?>
                        <div class="border border-slate-200 rounded-xl p-4 bg-slate-50">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4">
                                <h4 class="font-extrabold"><?php echo safe_output($gap["title"]); ?></h4>
                                <span class="self-start text-xs bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full font-bold whitespace-nowrap">
                                    <?php echo safe_output($gap["priority"]); ?>
                                </span>
                            </div>
                            <p class="text-sm text-slate-600 mt-2">
                                <?php echo safe_output($gap["text"]); ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div id="history" class="bg-white border border-slate-200 rounded-xl p-4 sm:p-6 shadow-sm">
                <p class="text-xs uppercase tracking-widest text-blue-700 font-extrabold">Analysis History</p>
                <h3 class="text-xl font-extrabold mt-1">Past GitHub Analysis Runs</h3>
                <p class="text-sm text-slate-500 mb-4">Your last 5 analysis runs saved to the database.</p>
                <?php if (empty($analysisHistory)): ?>
                    <p class="text-sm text-slate-400">No analysis history yet. Run an analysis above to see it here.</p>
                <?php else: ?>
                    <div class="overflow-x-auto" role="region" aria-label="Analysis history; scroll horizontally to see all columns" tabindex="0">
                        <table class="w-full min-w-[560px] text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 text-left text-slate-500">
                                    <th class="py-2">GitHub Username</th>
                                    <th class="py-2">Repositories</th>
                                    <th class="py-2">Stars</th>
                                    <th class="py-2">Analyzed At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($analysisHistory as $entry): ?>
                                    <tr class="border-b border-slate-100">
                                        <td class="py-2 font-bold"><?php echo safe_output($entry["github_username"]); ?></td>
                                        <td class="py-2"><?php echo safe_output($entry["total_repositories"]); ?></td>
                                        <td class="py-2"><?php echo safe_output($entry["total_stars"]); ?></td>
                                        <td class="py-2"><?php echo safe_output($entry["analyzed_at"]); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>


            <div id="security" class="bg-white border border-slate-200 rounded-xl p-4 sm:p-6 shadow-sm">
                <h3 class="text-xl font-extrabold">Account Protection Details</h3>
                <p class="text-sm text-slate-500 mt-1">
                    This section proves that the dashboard is protected by PHP session data.
                </p>

                <div class="mt-5 bg-slate-50 rounded-xl p-4 sm:p-5 text-sm space-y-2 break-words">
                    <p><strong>User Email:</strong> <span class="text-blue-800 font-bold"><?php echo safe_output($user_email); ?></span></p>
                    <p><strong>GitHub Username:</strong>
                        <?php if ($has_saved_analysis): ?>
                            <?php echo safe_output($latestAnalysis["github_username"]); ?> (saved result)
                        <?php else: ?>
                            Not analyzed yet
                        <?php endif; ?>
                    </p>
                    <p><strong>Account Status:</strong> <span class="text-green-700 font-bold">Active and Protected</span></p>
                    <p><strong>Session ID (masked):</strong> <code class="bg-white border px-2 py-1 rounded text-blue-800"><?php echo safe_output($masked_session_id); ?></code></p>
                    <p><strong>Login Time:</strong> <?php echo date("Y-m-d H:i:s", $login_time); ?></p>
                    <p><strong>Auto Lock:</strong> Account automatically locks after 300 seconds of inactivity.</p>
                    <p><strong>Access Rule:</strong> Dashboard cannot be opened without login.</p>
                </div>
            </div>

        </section>
    </main>
</div>

</body>
</html>
