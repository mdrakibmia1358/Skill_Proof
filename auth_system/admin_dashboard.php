<?php
// admin_dashboard.php
// Admin role: manages user accounts, monitors platform health.

session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . "/db_connect.php";
require_once __DIR__ . "/csrf.php";

function safe_output($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

// 1. Session guard - only logged-in Admins may view this page.
if (!isset($_SESSION["user_email"])) {
    header("Location: login.php");
    exit();
}

if (($_SESSION["user_role"] ?? "") !== "Admin") {
    header("Location: " . (($_SESSION["user_role"] ?? "") === "Recruiter" ? "recruiter_dashboard.php" : "dashboard.php"));
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

$admin_email = $_SESSION["user_email"];
$pdo = skillproof_db();
$csrf_token = sp_csrf_token();

$actionMessage = "";
$actionError = "";

$adminSelfStmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
$adminSelfStmt->execute([$admin_email]);
$adminSelfRow = $adminSelfStmt->fetch();
$admin_user_id = $adminSelfRow ? (int) $adminSelfRow["user_id"] : null;

// 2. Handle user management actions (block / unblock / delete).
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!sp_csrf_verify($_POST["csrf_token"] ?? "")) {
        $actionError = "Your session expired. Please try again.";
    } else {
        $action = $_POST["action"] ?? "";
        $target_id = isset($_POST["user_id"]) ? (int) $_POST["user_id"] : 0;

        if ($target_id > 0 && $target_id === $admin_user_id) {
            $actionError = "You cannot perform this action on your own account.";
        } elseif ($action === "toggle_status" && $target_id > 0) {
            $targetStmt = $pdo->prepare("SELECT status, full_name FROM users WHERE user_id = ? LIMIT 1");
            $targetStmt->execute([$target_id]);
            $target = $targetStmt->fetch();

            if ($target) {
                $newStatus = $target["status"] === "Active" ? "Blocked" : "Active";
                $updateStmt = $pdo->prepare("UPDATE users SET status = ? WHERE user_id = ?");
                $updateStmt->execute([$newStatus, $target_id]);
                $actionMessage = safe_output($target["full_name"]) . " is now " . $newStatus . ".";
            }
        } elseif ($action === "delete_user" && $target_id > 0) {
            $targetStmt = $pdo->prepare("SELECT role, full_name FROM users WHERE user_id = ? LIMIT 1");
            $targetStmt->execute([$target_id]);
            $target = $targetStmt->fetch();

            if ($target && $target["role"] === "Admin") {
                $adminCountStmt = $pdo->query("SELECT COUNT(*) AS c FROM users WHERE role = 'Admin'");
                $adminCount = (int) ($adminCountStmt->fetch()["c"] ?? 0);

                if ($adminCount <= 1) {
                    $actionError = "Cannot delete the last remaining Admin account.";
                    $target = null;
                }
            }

            if ($target) {
                $deleteStmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                $deleteStmt->execute([$target_id]);
                $actionMessage = safe_output($target["full_name"]) . "'s account has been deleted.";
            }
        }
    }
}

// 3. Platform-wide stats.
$totalUsers = (int) $pdo->query("SELECT COUNT(*) AS c FROM users")->fetch()["c"];
$totalDevelopers = (int) $pdo->query("SELECT COUNT(*) AS c FROM users WHERE role = 'Developer'")->fetch()["c"];
$totalRecruiters = (int) $pdo->query("SELECT COUNT(*) AS c FROM users WHERE role = 'Recruiter'")->fetch()["c"];
$verifiedDevelopers = (int) $pdo->query("SELECT COUNT(DISTINCT user_id) AS c FROM skill_analyses WHERE trust_score >= 50")->fetch()["c"];
$totalAnalyses = (int) $pdo->query("SELECT COUNT(*) AS c FROM skill_analyses")->fetch()["c"];
$totalReports = (int) $pdo->query("SELECT COUNT(*) AS c FROM recruiter_reports")->fetch()["c"];
$blockedUsers = (int) $pdo->query("SELECT COUNT(*) AS c FROM users WHERE status = 'Blocked'")->fetch()["c"];

// 4. All users for the management table.
$allUsersStmt = $pdo->query(
    "SELECT user_id, full_name, email, role, status, github_username, created_at
     FROM users ORDER BY created_at DESC"
);
$allUsers = $allUsersStmt->fetchAll();

// 5. Recent activity feed.
$recentSyncsStmt = $pdo->query(
    "SELECT u.full_name, sa.github_username, sa.trust_score, sa.analyzed_at
     FROM skill_analyses sa JOIN users u ON u.user_id = sa.user_id
     ORDER BY sa.analysis_id DESC LIMIT 8"
);
$recentSyncs = $recentSyncsStmt->fetchAll();

$recentReportsStmt = $pdo->query(
    "SELECT r.full_name AS recruiter_name, d.full_name AS developer_name, rr.generated_at
     FROM recruiter_reports rr
     JOIN users r ON r.user_id = rr.recruiter_id
     JOIN users d ON d.user_id = rr.developer_id
     ORDER BY rr.report_id DESC LIMIT 8"
);
$recentReports = $recentReportsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkillProof - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-100 text-slate-900">

<div class="min-h-screen flex">

    <aside class="w-64 bg-slate-950 text-white fixed top-0 left-0 h-full hidden lg:flex flex-col">
        <div class="p-6 border-b border-slate-800">
            <h1 class="text-2xl font-extrabold">SkillProof</h1>
            <p class="text-xs text-slate-400 mt-1">Admin Portal</p>
        </div>

        <nav class="p-4 space-y-2 text-sm">
            <a href="../home.php" class="block px-4 py-3 rounded-lg hover:bg-slate-800 mb-2 border-b border-slate-800 pb-4">
                &larr; Back to Home
            </a>
            <a href="admin_dashboard.php" class="block px-4 py-3 rounded-lg bg-blue-600 font-bold">Platform Overview</a>
            <a href="#users" class="block px-4 py-3 rounded-lg hover:bg-slate-800">Manage Users</a>
            <a href="#activity" class="block px-4 py-3 rounded-lg hover:bg-slate-800">Recent Activity</a>
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
                <h2 class="text-2xl font-extrabold text-slate-950">Admin Dashboard</h2>
                <p class="text-sm text-slate-500">
                    Signed in as <span class="font-bold text-blue-700"><?php echo safe_output($admin_email); ?></span>
                </p>
            </div>
            <span class="px-4 py-2 rounded-full bg-purple-100 text-purple-700 text-sm font-bold">Admin</span>
        </header>

        <section class="p-6 space-y-6">

            <?php if (!empty($actionMessage)): ?>
                <div class="bg-emerald-50 border border-emerald-300 text-emerald-700 px-4 py-3 rounded-xl text-sm">
                    <?php echo $actionMessage; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($actionError)): ?>
                <div class="bg-red-50 border border-red-300 text-red-600 px-4 py-3 rounded-xl text-sm">
                    <?php echo safe_output($actionError); ?>
                </div>
            <?php endif; ?>

            <!-- Platform Overview -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Total Users</p>
                    <h3 class="text-3xl font-extrabold mt-2"><?php echo $totalUsers; ?></h3>
                </div>
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Developers</p>
                    <h3 class="text-3xl font-extrabold mt-2"><?php echo $totalDevelopers; ?></h3>
                </div>
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Recruiters</p>
                    <h3 class="text-3xl font-extrabold mt-2"><?php echo $totalRecruiters; ?></h3>
                </div>
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Verified (Trust &ge; 50)</p>
                    <h3 class="text-3xl font-extrabold mt-2"><?php echo $verifiedDevelopers; ?></h3>
                </div>
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">GitHub Syncs Run</p>
                    <h3 class="text-3xl font-extrabold mt-2"><?php echo $totalAnalyses; ?></h3>
                </div>
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">PDF Reports Generated</p>
                    <h3 class="text-3xl font-extrabold mt-2"><?php echo $totalReports; ?></h3>
                </div>
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm col-span-2 md:col-span-2">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Blocked Accounts</p>
                    <h3 class="text-3xl font-extrabold mt-2 <?php echo $blockedUsers > 0 ? 'text-red-600' : ''; ?>"><?php echo $blockedUsers; ?></h3>
                </div>
            </div>

            <!-- User Management -->
            <div id="users" class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-100">
                    <h3 class="text-xl font-extrabold">Manage Users</h3>
                    <p class="text-sm text-slate-500">Block accounts that violate platform policy, or remove them entirely.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                            <tr>
                                <th class="text-left px-5 py-3">User</th>
                                <th class="text-left px-5 py-3">Role</th>
                                <th class="text-left px-5 py-3">Status</th>
                                <th class="text-left px-5 py-3">Registered</th>
                                <th class="text-left px-5 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($allUsers as $u): ?>
                                <tr>
                                    <td class="px-5 py-4">
                                        <div class="font-bold"><?php echo safe_output($u["full_name"]); ?></div>
                                        <div class="text-slate-400 text-xs"><?php echo safe_output($u["email"]); ?></div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="text-xs font-bold px-2 py-1 rounded-full
                                            <?php
                                            echo $u["role"] === "Admin" ? "bg-purple-100 text-purple-700"
                                                : ($u["role"] === "Recruiter" ? "bg-blue-100 text-blue-700" : "bg-slate-100 text-slate-700");
                                            ?>">
                                            <?php echo safe_output($u["role"]); ?>
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="text-xs font-bold px-2 py-1 rounded-full <?php echo $u["status"] === "Active" ? "bg-emerald-100 text-emerald-700" : "bg-red-100 text-red-700"; ?>">
                                            <?php echo safe_output($u["status"]); ?>
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-slate-500 text-xs">
                                        <?php echo safe_output(date("M j, Y", strtotime($u["created_at"]))); ?>
                                    </td>
                                    <td class="px-5 py-4">
                                        <?php if ((int) $u["user_id"] === $admin_user_id): ?>
                                            <span class="text-xs text-slate-400">This is you</span>
                                        <?php else: ?>
                                            <div class="flex gap-2">
                                                <form method="POST" action="admin_dashboard.php#users">
                                                    <input type="hidden" name="csrf_token" value="<?php echo safe_output($csrf_token); ?>">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="user_id" value="<?php echo (int) $u["user_id"]; ?>">
                                                    <button type="submit" class="text-xs font-bold px-3 py-1.5 rounded-lg <?php echo $u["status"] === "Active" ? "bg-amber-100 text-amber-700 hover:bg-amber-200" : "bg-emerald-100 text-emerald-700 hover:bg-emerald-200"; ?>">
                                                        <?php echo $u["status"] === "Active" ? "Block" : "Unblock"; ?>
                                                    </button>
                                                </form>
                                                <form method="POST" action="admin_dashboard.php#users" onsubmit="return confirm('Permanently delete this account? This cannot be undone.');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo safe_output($csrf_token); ?>">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="user_id" value="<?php echo (int) $u["user_id"]; ?>">
                                                    <button type="submit" class="text-xs font-bold px-3 py-1.5 rounded-lg bg-red-100 text-red-700 hover:bg-red-200">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Activity -->
            <div id="activity" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
                    <h3 class="text-lg font-extrabold mb-4">Recent GitHub Syncs</h3>
                    <?php if (empty($recentSyncs)): ?>
                        <p class="text-sm text-slate-400">No syncs recorded yet.</p>
                    <?php else: ?>
                        <ul class="space-y-3 text-sm">
                            <?php foreach ($recentSyncs as $sync): ?>
                                <li class="flex justify-between items-center border-b border-slate-50 pb-2">
                                    <div>
                                        <span class="font-semibold"><?php echo safe_output($sync["full_name"]); ?></span>
                                        <span class="text-slate-400 text-xs block"><?php echo safe_output($sync["github_username"]); ?></span>
                                    </div>
                                    <div class="text-right">
                                        <span class="font-bold text-blue-800"><?php echo (int) $sync["trust_score"]; ?>%</span>
                                        <span class="text-slate-400 text-xs block"><?php echo safe_output(date("M j, g:i A", strtotime($sync["analyzed_at"]))); ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
                    <h3 class="text-lg font-extrabold mb-4">Recent PDF Reports</h3>
                    <?php if (empty($recentReports)): ?>
                        <p class="text-sm text-slate-400">No reports generated yet.</p>
                    <?php else: ?>
                        <ul class="space-y-3 text-sm">
                            <?php foreach ($recentReports as $report): ?>
                                <li class="flex justify-between items-center border-b border-slate-50 pb-2">
                                    <div>
                                        <span class="font-semibold"><?php echo safe_output($report["recruiter_name"]); ?></span>
                                        <span class="text-slate-400 text-xs block">viewed <?php echo safe_output($report["developer_name"]); ?></span>
                                    </div>
                                    <span class="text-slate-400 text-xs"><?php echo safe_output(date("M j, g:i A", strtotime($report["generated_at"]))); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

        </section>
    </main>
</div>

</body>
</html>