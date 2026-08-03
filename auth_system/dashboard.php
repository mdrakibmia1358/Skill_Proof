<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION["user_email"])) {
    header("Location: login.php");
    exit();
}

$timeout_duration = 300;

if (
    isset($_SESSION["last_activity"]) &&
    (time() - $_SESSION["last_activity"]) > $timeout_duration
) {
    session_unset();
    session_destroy();
    header("Location: login.php?msg=Session timed out due to inactivity");
    exit();
}

$_SESSION["last_activity"] = time();

require_once __DIR__ . "/engine/skillproof_engine.php";

function safe_output($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

$user_email = $_SESSION["user_email"];
$user_role = $_SESSION["user_role"] ?? "Developer";
$login_time = $_SESSION["login_time"] ?? time();

$github_username = $_SESSION["github_username"] ?? "";

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["github_username"])
) {
    $github_username = trim($_POST["github_username"]);

    if ($github_username === "") {
        unset($_SESSION["github_username"]);
        $github_username = "";
    } else {
        $_SESSION["github_username"] = $github_username;
    }
}

$analysis = skillproof_analyze_github_user($github_username);

$github_connected = $analysis["connected"] ?? false;
$github_error = $analysis["error"] ?? "";
$profile = $analysis["profile"] ?? null;
$repoStats = $analysis["repoStats"] ?? [];
$summaryCards = $analysis["summaryCards"] ?? [];
$skills = $analysis["skills"] ?? [];
$dimensions = $analysis["dimensions"] ?? [];
$learningGaps = $analysis["learningGaps"] ?? [];
$activities = $analysis["activities"] ?? [];

$total_repositories = $repoStats["total"] ?? 0;
$original_repositories = $repoStats["original"] ?? 0;
$forked_repositories = $repoStats["forked"] ?? 0;
$total_stars = $repoStats["stars"] ?? 0;
$total_forks = $repoStats["forks"] ?? 0;
$languages_detected = $repoStats["languages_count"] ?? 0;
$readme_count = $repoStats["readme_count"] ?? 0;

$masked_session_id = session_id() !== ""
    ? substr(session_id(), 0, 8) . "••••••••"
    : "Unavailable";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkillProof - Developer Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-100 text-slate-900">

<div class="min-h-screen flex">

    <aside class="w-64 bg-slate-950 text-white fixed top-0 left-0 h-full hidden lg:flex flex-col">
        <div class="p-6 border-b border-slate-800">
            <h1 class="text-2xl font-extrabold">SkillProof</h1>
            <p class="text-xs text-slate-400 mt-1">Evidence-based developer assessment</p>
        </div>

        <nav class="p-4 space-y-2 text-sm">
            <a href="#dashboard" class="block px-4 py-3 rounded-lg bg-blue-600 font-bold">Dashboard</a>
            <a href="#github" class="block px-4 py-3 rounded-lg hover:bg-slate-800">GitHub Connection</a>
            <a href="#skills" class="block px-4 py-3 rounded-lg hover:bg-slate-800">Skill Verification</a>
            <a href="#dimensions" class="block px-4 py-3 rounded-lg hover:bg-slate-800">Score Dimensions</a>
            <a href="#gaps" class="block px-4 py-3 rounded-lg hover:bg-slate-800">Learning Gap</a>
            <a href="#security" class="block px-4 py-3 rounded-lg hover:bg-slate-800">Account Security</a>
        </nav>

        <div class="mt-auto p-4">
            <a href="logout.php" class="block text-center bg-red-600 hover:bg-red-500 text-white font-bold py-3 rounded-lg">
                Logout
            </a>
        </div>
    </aside>

    <main class="flex-1 lg:ml-64">

        <header class="bg-white border-b border-slate-200 px-6 py-5 flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-extrabold text-slate-950">Developer Dashboard</h2>
                <p class="text-sm text-slate-500">
                    Welcome,
                    <span class="font-bold text-blue-700"><?php echo safe_output($user_email); ?></span>
                </p>
            </div>

            <span class="px-4 py-2 rounded-full bg-green-100 text-green-700 text-sm font-bold">
                <?php echo safe_output($user_role); ?>
            </span>
        </header>

        <section id="dashboard" class="p-6 space-y-6">

            <div class="bg-gradient-to-r from-blue-900 to-slate-950 text-white rounded-2xl p-8 shadow">
                <h3 class="text-3xl font-extrabold mb-3">Real-Time Skill Verification</h3>
                <p class="text-blue-100 max-w-4xl leading-relaxed">
                    SkillProof calculates scores only from connected GitHub public data.
                    If GitHub is not connected, all score values remain 0.
                </p>
            </div>

            <div id="github" class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
                <h3 class="text-xl font-extrabold">Connect GitHub Account</h3>
                <p class="text-sm text-slate-500 mt-1">
                    Enter a public GitHub username to calculate real-time repository, language, and documentation scores.
                </p>

                <?php if (!empty($github_error)): ?>
                    <div class="mt-4 bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded-xl text-sm">
                        <?php echo safe_output($github_error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="dashboard.php#github" class="mt-5 flex flex-col md:flex-row gap-3">
                    <input
                        type="text"
                        name="github_username"
                        value="<?php echo safe_output($github_username); ?>"
                        placeholder="Enter GitHub username"
                        class="flex-1 px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none"
                    >

                    <button
                        type="submit"
                        class="bg-blue-600 hover:bg-blue-500 text-white px-6 py-3 rounded-xl font-bold"
                    >
                        Analyze GitHub
                    </button>
                </form>

                <div class="mt-4 text-sm">
                    <?php if ($github_connected): ?>
                        <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full font-bold">
                            Connected: <?php echo safe_output($github_username); ?>
                        </span>
                    <?php else: ?>
                        <span class="bg-slate-100 text-slate-600 px-3 py-1 rounded-full font-bold">
                            Not Connected
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <?php foreach ($summaryCards as $card): ?>
                    <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
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
                    <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-xl font-extrabold">Skill Score Matrix</h3>
                            <p class="text-sm text-slate-500">Scores are calculated from real GitHub evidence.</p>
                        </div>
                        <span class="bg-blue-100 text-blue-700 text-xs font-bold px-3 py-1 rounded-full">
                            Real-Time Data
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
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

                <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
                    <h3 class="text-xl font-extrabold">Real-Time Activity</h3>
                    <p class="text-sm text-slate-500 mb-5">Latest dashboard analysis status</p>

                    <ul class="space-y-4 text-sm text-slate-700">
                        <?php foreach ($activities as $activity): ?>
                            <li class="flex gap-3">
                                <span class="w-2 h-2 rounded-full bg-blue-600 mt-2"></span>
                                <span><?php echo safe_output($activity); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div id="dimensions" class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
                <p class="text-xs uppercase tracking-widest text-blue-700 font-extrabold">Evidence Dimensions</p>
                <h3 class="text-xl font-extrabold mt-1">Assessment Dimension Scores</h3>
                <p class="text-sm text-slate-500">
                    These scores are 0 until GitHub data is connected.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mt-5">
                    <?php foreach ($dimensions as $dimension): ?>
                        <div class="border border-slate-200 rounded-xl p-5 bg-slate-50">
                            <div class="flex items-center justify-between">
                                <h4 class="font-extrabold"><?php echo safe_output($dimension["name"]); ?></h4>
                                <span class="text-blue-800 font-extrabold">
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

            <div id="gaps" class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
                <p class="text-xs uppercase tracking-widest text-blue-700 font-extrabold">Learning Gap</p>
                <h3 class="text-xl font-extrabold mt-1">Recommended Improvements</h3>

                <div class="mt-5 space-y-4">
                    <?php foreach ($learningGaps as $gap): ?>
                        <div class="border border-slate-200 rounded-xl p-4 bg-slate-50">
                            <div class="flex items-center justify-between gap-4">
                                <h4 class="font-extrabold"><?php echo safe_output($gap["title"]); ?></h4>
                                <span class="text-xs bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full font-bold whitespace-nowrap">
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

            <div id="security" class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
                <h3 class="text-xl font-extrabold">Account Protection Details</h3>
                <p class="text-sm text-slate-500 mt-1">
                    This section proves that the dashboard is protected by PHP session data.
                </p>

                <div class="mt-5 bg-slate-50 rounded-xl p-5 text-sm space-y-2">
                    <p><strong>User Email:</strong> <span class="text-blue-800 font-bold"><?php echo safe_output($user_email); ?></span></p>
                    <p><strong>GitHub Username:</strong> <?php echo $github_connected ? safe_output($github_username) : "Not Connected"; ?></p>
                    <p><strong>Account Status:</strong> <span class="text-green-700 font-bold">Active and Protected</span></p>
                    <p><strong>Access Token:</strong> <code class="bg-white border px-2 py-1 rounded text-blue-800"><?php echo safe_output($masked_session_id); ?></code></p>
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