<?php
// recruiter_dashboard.php
// FR-8: Recruiter Dashboard and PDF Report Generation

session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . "/db_connect.php";

function safe_output($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

// 1. Session guard - only logged-in Recruiters may view this page.
if (!isset($_SESSION["user_email"])) {
    header("Location: login.php");
    exit();
}

if (($_SESSION["user_role"] ?? "") !== "Recruiter") {
    // Developers who somehow land here are sent back to their own dashboard.
    header("Location: dashboard.php");
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

$user_email = $_SESSION["user_email"];
$pdo = skillproof_db();

// 2. Read filters from the query string (GET so results are shareable/bookmarkable).
$skillFilter = trim($_GET["skill"] ?? "");
$roleFilter  = trim($_GET["role"] ?? "");
$minTrust    = isset($_GET["min_trust"]) && $_GET["min_trust"] !== "" ? max(0, min(100, (int) $_GET["min_trust"])) : 0;
$maxTrust    = isset($_GET["max_trust"]) && $_GET["max_trust"] !== "" ? max(0, min(100, (int) $_GET["max_trust"])) : 100;

if ($minTrust > $maxTrust) {
    [$minTrust, $maxTrust] = [$maxTrust, $minTrust];
}

$careerRoles = ["Backend Developer", "Full Stack Developer", "Database Developer", "Software Engineer"];

// 3. Build the developer search query.
//    - Only the LATEST analysis per developer is considered (subquery picks MAX(analysis_id) per user).
//    - Skill filter matches against skill_scores for that latest analysis (score >= 50 = meaningful evidence).
//    - Career role filter matches against career_fit_scores (match_score >= 50 = meaningful fit).
$sql = "
    SELECT u.user_id, u.full_name, u.email, u.github_username, u.bio,
           sa.analysis_id, sa.total_repositories, sa.total_stars, sa.languages_detected,
           sa.trust_score, sa.career_score, sa.analyzed_at
    FROM users u
    INNER JOIN (
        SELECT sa1.*
        FROM skill_analyses sa1
        INNER JOIN (
            SELECT user_id, MAX(analysis_id) AS max_id
            FROM skill_analyses
            GROUP BY user_id
        ) latest ON latest.user_id = sa1.user_id AND latest.max_id = sa1.analysis_id
    ) sa ON sa.user_id = u.user_id
    WHERE u.role = 'Developer'
      AND sa.trust_score BETWEEN :min_trust AND :max_trust
";

$params = [
    "min_trust" => $minTrust,
    "max_trust" => $maxTrust,
];

if ($skillFilter !== "") {
    $sql .= " AND EXISTS (
        SELECT 1 FROM skill_scores ss
        WHERE ss.analysis_id = sa.analysis_id
          AND ss.skill_name LIKE :skill_filter
          AND ss.score >= 50
    )";
    $params["skill_filter"] = "%" . $skillFilter . "%";
}

if ($roleFilter !== "" && in_array($roleFilter, $careerRoles, true)) {
    $sql .= " AND EXISTS (
        SELECT 1 FROM career_fit_scores cf
        WHERE cf.analysis_id = sa.analysis_id
          AND cf.role_name = :role_filter
          AND cf.match_score >= 50
    )";
    $params["role_filter"] = $roleFilter;
}

$sql .= " ORDER BY sa.trust_score DESC, u.full_name ASC LIMIT 50";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$developers = $stmt->fetchAll();

// 4. For each result, pull its top 3 verified skills and best-fit career role
//    (small per-row query, result set is capped at 50).
$topSkillsStmt = $pdo->prepare(
    "SELECT skill_name, score FROM skill_scores
     WHERE analysis_id = ? AND score > 0
     ORDER BY score DESC LIMIT 3"
);

$bestFitStmt = $pdo->prepare(
    "SELECT role_name, match_score FROM career_fit_scores
     WHERE analysis_id = ? ORDER BY match_score DESC LIMIT 1"
);

foreach ($developers as &$dev) {
    $topSkillsStmt->execute([$dev["analysis_id"]]);
    $dev["top_skills"] = $topSkillsStmt->fetchAll();

    $bestFitStmt->execute([$dev["analysis_id"]]);
    $dev["best_fit"] = $bestFitStmt->fetch();
}
unset($dev);

// 5. Quick platform-wide counters for the summary cards.
$totalDevelopersStmt = $pdo->query("SELECT COUNT(*) AS c FROM users WHERE role = 'Developer'");
$totalDevelopers = (int) ($totalDevelopersStmt->fetch()["c"] ?? 0);

$verifiedDevelopersStmt = $pdo->query(
    "SELECT COUNT(DISTINCT user_id) AS c FROM skill_analyses WHERE trust_score >= 50"
);
$verifiedDevelopers = (int) ($verifiedDevelopersStmt->fetch()["c"] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkillProof - Recruiter Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-100 text-slate-900">

<div class="min-h-screen flex">

    <aside class="w-64 bg-slate-950 text-white fixed top-0 left-0 h-full hidden lg:flex flex-col">
        <div class="p-6 border-b border-slate-800">
            <h1 class="text-2xl font-extrabold">SkillProof</h1>
            <p class="text-xs text-slate-400 mt-1">Recruiter Portal</p>
        </div>

        <nav class="p-4 space-y-2 text-sm">
            <a href="../home.php" class="block px-4 py-3 rounded-lg hover:bg-slate-800 mb-2 border-b border-slate-800 pb-4">
                &larr; Back to Home
            </a>
            <a href="recruiter_dashboard.php" class="block px-4 py-3 rounded-lg bg-blue-600 font-bold">Search Developers</a>
            <a href="profile.php" class="block px-4 py-3 rounded-lg hover:bg-slate-800 border-t border-slate-800 mt-2 pt-4">My Profile</a>
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
                <h2 class="text-2xl font-extrabold text-slate-950">Recruiter Dashboard</h2>
                <p class="text-sm text-slate-500">
                    Signed in as <span class="font-bold text-blue-700"><?php echo safe_output($user_email); ?></span>
                </p>
            </div>
            <span class="px-4 py-2 rounded-full bg-blue-100 text-blue-700 text-sm font-bold">Recruiter</span>
        </header>

        <section class="p-6 space-y-6">

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Total Developers</p>
                    <h3 class="text-3xl font-extrabold mt-3"><?php echo safe_output($totalDevelopers); ?></h3>
                </div>
                <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Verified (Trust ≥ 50)</p>
                    <h3 class="text-3xl font-extrabold mt-3"><?php echo safe_output($verifiedDevelopers); ?></h3>
                </div>
                <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Matching Your Filters</p>
                    <h3 class="text-3xl font-extrabold mt-3"><?php echo safe_output(count($developers)); ?></h3>
                </div>
            </div>

            <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
                <h3 class="text-xl font-extrabold mb-4">Search Verified Developer Profiles</h3>

                <form method="GET" action="recruiter_dashboard.php" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-slate-700 mb-2">Skill</label>
                        <input
                            type="text"
                            name="skill"
                            value="<?php echo safe_output($skillFilter); ?>"
                            placeholder="e.g. JavaScript, SQL, Documentation"
                            class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Career Role</label>
                        <select name="role" class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <option value="">Any role</option>
                            <?php foreach ($careerRoles as $roleOption): ?>
                                <option value="<?php echo safe_output($roleOption); ?>" <?php echo $roleFilter === $roleOption ? "selected" : ""; ?>>
                                    <?php echo safe_output($roleOption); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Min Trust Score</label>
                        <input
                            type="number" min="0" max="100" name="min_trust"
                            value="<?php echo safe_output($minTrust); ?>"
                            class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Max Trust Score</label>
                        <input
                            type="number" min="0" max="100" name="max_trust"
                            value="<?php echo safe_output($maxTrust); ?>"
                            class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        >
                    </div>
                    <div class="md:col-span-5 flex gap-3">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white px-6 py-3 rounded-xl font-bold">
                            Search
                        </button>
                        <a href="recruiter_dashboard.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-6 py-3 rounded-xl font-bold">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-100">
                    <h3 class="text-xl font-extrabold">Results</h3>
                    <p class="text-sm text-slate-500">Only developers with at least one completed GitHub analysis are shown.</p>
                </div>

                <?php if (empty($developers)): ?>
                    <p class="p-6 text-sm text-slate-400">
                        No developers match these filters yet. Try widening the trust score range or clearing the skill filter.
                    </p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                                <tr>
                                    <th class="text-left px-5 py-3">Developer</th>
                                    <th class="text-left px-5 py-3">GitHub</th>
                                    <th class="text-left px-5 py-3">Trust Score</th>
                                    <th class="text-left px-5 py-3">Best Fit Role</th>
                                    <th class="text-left px-5 py-3">Top Skills</th>
                                    <th class="text-left px-5 py-3">Report</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($developers as $dev): ?>
                                    <tr>
                                        <td class="px-5 py-4">
                                            <div class="font-bold"><?php echo safe_output($dev["full_name"]); ?></div>
                                            <div class="text-slate-400 text-xs"><?php echo safe_output($dev["email"]); ?></div>
                                        </td>
                                        <td class="px-5 py-4 text-slate-600">
                                            <?php echo $dev["github_username"] ? safe_output($dev["github_username"]) : "—"; ?>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="font-extrabold text-blue-900"><?php echo safe_output($dev["trust_score"]); ?>%</span>
                                        </td>
                                        <td class="px-5 py-4">
                                            <?php if (empty($dev["best_fit"]) || ($dev["best_fit"]["match_score"] ?? 0) <= 0): ?>
                                                <span class="text-slate-400 text-xs">Not enough evidence</span>
                                            <?php else: ?>
                                                <div class="font-semibold text-slate-800"><?php echo safe_output($dev["best_fit"]["role_name"]); ?></div>
                                                <div class="text-xs text-slate-500"><?php echo safe_output($dev["best_fit"]["match_score"]); ?>% match</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-5 py-4">
                                            <?php if (empty($dev["top_skills"])): ?>
                                                <span class="text-slate-400 text-xs">No verified skills yet</span>
                                            <?php else: ?>
                                                <div class="flex flex-wrap gap-1">
                                                    <?php foreach ($dev["top_skills"] as $skill): ?>
                                                        <span class="bg-blue-50 text-blue-700 text-xs font-semibold px-2 py-1 rounded-full">
                                                            <?php echo safe_output($skill["skill_name"]); ?> (<?php echo safe_output($skill["score"]); ?>%)
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-5 py-4">
                                            <a
                                                href="report.php?user_id=<?php echo (int) $dev["user_id"]; ?>"
                                                class="bg-slate-900 hover:bg-slate-800 text-white px-4 py-2 rounded-lg text-xs font-bold"
                                            >
                                                Download PDF
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </section>
    </main>
</div>

</body>
</html>