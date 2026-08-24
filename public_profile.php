<?php
// public_profile.php
// FR-9: Public Profile - accessible WITHOUT login, via a unique public_slug.
// Recruiters (or anyone with the link) can view this without an account.

require_once __DIR__ . "/auth_system/db_connect.php";
$pdo = skillproof_db();

function safe_output($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

$slug = trim($_GET["slug"] ?? "");
$developer = null;
$notFound = true;

if ($slug !== "") {
    $devStmt = $pdo->prepare(
        "SELECT user_id, full_name, github_username, bio
         FROM users WHERE public_slug = ? AND role = 'Developer' LIMIT 1"
    );
    $devStmt->execute([$slug]);
    $developer = $devStmt->fetch();
    $notFound = !$developer;
}

$analysis = null;
$skills = [];
$dimensions = [];
$careerFit = [];
$history = [];

if (!$notFound) {
    $analysisStmt = $pdo->prepare(
        "SELECT * FROM skill_analyses WHERE user_id = ? ORDER BY analysis_id DESC LIMIT 1"
    );
    $analysisStmt->execute([$developer["user_id"]]);
    $analysis = $analysisStmt->fetch();

    if ($analysis) {
        $skillStmt = $pdo->prepare(
            "SELECT skill_name, score, level, status
             FROM skill_scores WHERE analysis_id = ? AND score > 0 ORDER BY score DESC"
        );
        $skillStmt->execute([$analysis["analysis_id"]]);
        $skills = $skillStmt->fetchAll();

        $dimStmt = $pdo->prepare(
            "SELECT dimension_name, score FROM dimension_scores WHERE analysis_id = ? ORDER BY score DESC"
        );
        $dimStmt->execute([$analysis["analysis_id"]]);
        $dimensions = $dimStmt->fetchAll();

        $fitStmt = $pdo->prepare(
            "SELECT role_name, match_score FROM career_fit_scores WHERE analysis_id = ? ORDER BY match_score DESC"
        );
        $fitStmt->execute([$analysis["analysis_id"]]);
        $careerFit = $fitStmt->fetchAll();
    }

    // Growth trend: up to the last 10 sync runs for this developer, oldest first,
    // so the chart reads left-to-right as "over time".
    $historyStmt = $pdo->prepare(
        "SELECT trust_score, total_repositories, total_stars, analyzed_at
         FROM skill_analyses WHERE user_id = ? ORDER BY analysis_id DESC LIMIT 10"
    );
    $historyStmt->execute([$developer["user_id"]]);
    $history = array_reverse($historyStmt->fetchAll());
}

// The engine's fixed, non-language skills (everything else in skill_scores
// comes straight from GitHub's per-repo language stats, so it's a language).
$nonLanguageSkills = ["SQL", "Git & GitHub", "Documentation", "Testing", "Security Awareness", "DevOps"];

$languageSkills = array_slice(array_values(array_filter($skills, function ($s) use ($nonLanguageSkills) {
    return !in_array($s["skill_name"], $nonLanguageSkills, true);
})), 0, 8);

$historyLabels = array_map(function ($h) {
    return date("M j", strtotime($h["analyzed_at"]));
}, $history);
$historyTrust = array_map(function ($h) { return (int) $h["trust_score"]; }, $history);
$historyRepos = array_map(function ($h) { return (int) $h["total_repositories"]; }, $history);

$languageLabels = array_map(function ($s) { return $s["skill_name"]; }, $languageSkills);
$languageScores = array_map(function ($s) { return (int) $s["score"]; }, $languageSkills);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $developer ? safe_output($developer["full_name"]) . " - " : ""; ?>SkillProof Public Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body class="bg-slate-100 text-slate-900">

    <nav class="bg-slate-950 text-white px-6 py-4 flex items-center justify-between">
        <a href="home.php" class="flex items-center gap-2 font-extrabold text-lg">
            <span class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-sm">SP</span>
            SkillProof
        </a>
        <a href="home.php" class="text-slate-300 hover:text-white text-sm">Learn more about SkillProof &rarr;</a>
    </nav>

    <main class="max-w-5xl mx-auto px-6 py-10">

    <?php if ($notFound): ?>
        <div class="bg-white border border-slate-200 rounded-2xl p-10 text-center">
            <h1 class="text-2xl font-extrabold text-slate-900">Profile Not Found</h1>
            <p class="text-slate-500 mt-2">
                This public profile link is invalid, or the developer hasn't verified their GitHub account yet.
            </p>
            <a href="home.php" class="inline-block mt-6 bg-blue-600 hover:bg-blue-500 text-white px-6 py-3 rounded-xl font-bold">
                Back to SkillProof
            </a>
        </div>
    <?php else: ?>

        <div class="bg-slate-950 text-white rounded-2xl p-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <span class="inline-block bg-blue-600/20 text-blue-300 text-xs font-bold px-3 py-1 rounded-full mb-3">
                    Public SkillProof Profile
                </span>
                <h1 class="text-3xl font-extrabold"><?php echo safe_output($developer["full_name"]); ?></h1>
                <?php if (!empty($developer["github_username"])): ?>
                    <a
                        href="https://github.com/<?php echo urlencode($developer["github_username"]); ?>"
                        target="_blank" rel="noopener"
                        class="text-blue-300 hover:text-blue-200 text-sm inline-block mt-1"
                    >
                        github.com/<?php echo safe_output($developer["github_username"]); ?> &rarr;
                    </a>
                <?php endif; ?>
                <?php if (!empty($developer["bio"])): ?>
                    <p class="text-slate-300 text-sm mt-3 max-w-xl"><?php echo safe_output($developer["bio"]); ?></p>
                <?php endif; ?>
            </div>
            <?php if ($analysis): ?>
                <div class="bg-white/10 rounded-xl p-5 text-center min-w-[140px]">
                    <div class="text-3xl font-black"><?php echo (int) $analysis["trust_score"]; ?>%</div>
                    <div class="text-xs text-slate-300 uppercase font-bold tracking-wide">Trust Score</div>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!$analysis): ?>

            <div class="bg-white border border-slate-200 rounded-2xl p-8 mt-6 text-center">
                <p class="text-slate-500">This developer hasn't connected a verified GitHub account with public repositories yet.</p>
            </div>

        <?php else: ?>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6">
                <div class="bg-white border border-slate-200 rounded-xl p-5">
                    <p class="text-xs font-bold uppercase text-slate-500">Career Readiness</p>
                    <p class="text-2xl font-extrabold mt-1"><?php echo (int) $analysis["career_score"]; ?>%</p>
                </div>
                <div class="bg-white border border-slate-200 rounded-xl p-5">
                    <p class="text-xs font-bold uppercase text-slate-500">Public Repositories</p>
                    <p class="text-2xl font-extrabold mt-1"><?php echo (int) $analysis["total_repositories"]; ?></p>
                </div>
                <div class="bg-white border border-slate-200 rounded-xl p-5">
                    <p class="text-xs font-bold uppercase text-slate-500">Total Stars</p>
                    <p class="text-2xl font-extrabold mt-1"><?php echo (int) $analysis["total_stars"]; ?></p>
                </div>
            </div>

            <!-- Career Fit -->
            <?php if (!empty($careerFit)): ?>
                <div class="bg-white border border-slate-200 rounded-2xl p-6 mt-6">
                    <h2 class="text-lg font-extrabold">Career Fit</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4">
                        <?php foreach ($careerFit as $index => $fit): ?>
                            <div class="border border-slate-200 rounded-xl p-4 <?php echo $index === 0 ? "ring-2 ring-blue-500" : ""; ?>">
                                <div class="flex justify-between items-center">
                                    <span class="font-semibold text-sm">
                                        <?php echo safe_output($fit["role_name"]); ?>
                                        <?php if ($index === 0 && $fit["match_score"] > 0): ?>
                                            <span class="ml-1 text-[10px] bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-bold">Best Fit</span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="font-extrabold text-blue-900"><?php echo (int) $fit["match_score"]; ?>%</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Charts: FR-9 activity analytics -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
                <div class="bg-white border border-slate-200 rounded-2xl p-6">
                    <h2 class="text-lg font-extrabold">Programming Language Distribution</h2>
                    <p class="text-xs text-slate-500 mb-4">Verified skill score by language</p>
                    <?php if (empty($languageLabels)): ?>
                        <p class="text-sm text-slate-400">Not enough language evidence yet.</p>
                    <?php else: ?>
                        <canvas id="languageChart" height="220"></canvas>
                    <?php endif; ?>
                </div>

                <div class="bg-white border border-slate-200 rounded-2xl p-6">
                    <h2 class="text-lg font-extrabold">Growth Trend</h2>
                    <p class="text-xs text-slate-500 mb-4">Trust score and repository count across sync history</p>
                    <?php if (count($history) < 2): ?>
                        <p class="text-sm text-slate-400">Not enough sync history yet - re-sync over time to build this trend.</p>
                    <?php else: ?>
                        <canvas id="growthChart" height="220"></canvas>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Skill matrix -->
            <div class="bg-white border border-slate-200 rounded-2xl p-6 mt-6">
                <h2 class="text-lg font-extrabold">Verified Skills</h2>
                <div class="overflow-x-auto mt-4">
                    <table class="w-full text-sm">
                        <thead class="text-slate-500 uppercase text-xs border-b border-slate-100">
                            <tr>
                                <th class="text-left py-2">Skill</th>
                                <th class="text-left py-2">Score</th>
                                <th class="text-left py-2">Level</th>
                                <th class="text-left py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach (array_slice($skills, 0, 15) as $skill): ?>
                                <tr>
                                    <td class="py-2 font-semibold"><?php echo safe_output($skill["skill_name"]); ?></td>
                                    <td class="py-2 text-blue-800 font-bold"><?php echo (int) $skill["score"]; ?>%</td>
                                    <td class="py-2 text-slate-600"><?php echo safe_output($skill["level"]); ?></td>
                                    <td class="py-2 text-slate-600"><?php echo safe_output($skill["status"]); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <p class="text-xs text-slate-400 text-center mt-6">
                This profile reflects publicly available GitHub evidence, verified by SkillProof's Analysis Engine.
                Last synced <?php echo safe_output(date("M j, Y", strtotime($analysis["analyzed_at"]))); ?>.
            </p>

        <?php endif; ?>
    <?php endif; ?>

    </main>

    <?php if (!$notFound && $analysis): ?>
    <script>
        <?php if (!empty($languageLabels)): ?>
        new Chart(document.getElementById('languageChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($languageLabels); ?>,
                datasets: [{
                    label: 'Score',
                    data: <?php echo json_encode($languageScores); ?>,
                    backgroundColor: '#2563eb',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, max: 100 } }
            }
        });
        <?php endif; ?>

        <?php if (count($history) >= 2): ?>
        new Chart(document.getElementById('growthChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($historyLabels); ?>,
                datasets: [
                    {
                        label: 'Trust Score (%)',
                        data: <?php echo json_encode($historyTrust); ?>,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37,99,235,0.1)',
                        tension: 0.3,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Repositories',
                        data: <?php echo json_encode($historyRepos); ?>,
                        borderColor: '#16a34a',
                        backgroundColor: 'rgba(22,163,74,0.1)',
                        tension: 0.3,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: { type: 'linear', position: 'left', beginAtZero: true, max: 100 },
                    y1: { type: 'linear', position: 'right', beginAtZero: true, grid: { drawOnChartArea: false } }
                }
            }
        });
        <?php endif; ?>
    </script>
    <?php endif; ?>

</body>
</html>