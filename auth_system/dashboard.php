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

if (isset($_SESSION["last_activity"]) && (time() - $_SESSION["last_activity"] > $timeout_duration)) {
    session_unset();
    session_destroy();
    header("Location: login.php?msg=Session timed out due to inactivity");
    exit();
}

$_SESSION["last_activity"] = time();

function safe_output($value) {
    return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

$user_email = $_SESSION["user_email"] ?? "student@university.edu";
$user_role = $_SESSION["user_role"] ?? "Developer";
$login_time = $_SESSION["login_time"] ?? time();

$stats = [
    ["title" => "Total Developers", "value" => "1,245", "note" => "Registered developer profiles"],
    ["title" => "Verified Skills", "value" => "342", "note" => "Skills verified with evidence"],
    ["title" => "Pending Reviews", "value" => "18", "note" => "Waiting for admin verification"],
    ["title" => "Recruiter Requests", "value" => "27", "note" => "Recruiter profile check requests"]
];

$skills = [
    ["name" => "JavaScript", "level" => "Intermediate", "evidence" => "GitHub Project", "status" => "Verified"],
    ["name" => "PHP", "level" => "Beginner", "evidence" => "Login System", "status" => "Pending"],
    ["name" => "Git & GitHub", "level" => "Intermediate", "evidence" => "Branch and PR Workflow", "status" => "Verified"],
    ["name" => "Tailwind CSS", "level" => "Beginner", "evidence" => "Dashboard UI", "status" => "Verified"]
];

$activities = [
    "New PHP authentication system added.",
    "Dashboard session protection tested successfully.",
    "Invalid login and logout lifecycle verified.",
    "Skill verification dashboard UI updated."
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkillProof - Protected Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-100 min-h-screen text-slate-800">

    <div class="flex min-h-screen">

        <!-- Sidebar -->
        <aside class="hidden md:flex md:w-64 bg-slate-950 text-white flex-col">
            <div class="px-6 py-6 border-b border-slate-800">
                <h1 class="text-2xl font-extrabold tracking-wide">SkillProof</h1>
                <p class="text-xs text-slate-400 mt-1">Verified developer assessment</p>
            </div>

            <nav class="flex-1 px-4 py-6 space-y-2 text-sm">
                <a href="#" class="block px-4 py-3 rounded-lg bg-blue-600 font-semibold">Dashboard</a>
                <a href="#skills" class="block px-4 py-3 rounded-lg hover:bg-slate-800">Skill Verification</a>
                <a href="#activity" class="block px-4 py-3 rounded-lg hover:bg-slate-800">Recent Activity</a>
                <a href="#security" class="block px-4 py-3 rounded-lg hover:bg-slate-800">Session Security</a>
            </nav>

            <div class="p-4 border-t border-slate-800">
                <a href="logout.php" class="block text-center bg-red-600 hover:bg-red-500 text-white font-bold py-3 rounded-lg">
                    Secure Log-Out
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1">

            <!-- Top Bar -->
            <header class="bg-white border-b border-slate-200 px-6 py-4 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">Developer Dashboard</h2>
                    <p class="text-sm text-slate-500">
                        Welcome,
                        <span class="font-semibold text-blue-700">
                            <?php echo safe_output($user_email); ?>
                        </span>
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <span class="text-xs bg-green-100 text-green-700 px-3 py-2 rounded-full font-bold">
                        <?php echo safe_output($user_role); ?>
                    </span>

                    <a href="logout.php" class="md:hidden bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-bold">
                        Logout
                    </a>
                </div>
            </header>

            <section class="p-6 space-y-6">

                <!-- Hero Section -->
                <div class="bg-gradient-to-r from-blue-900 to-slate-900 text-white rounded-2xl p-6 shadow">
                    <h3 class="text-2xl font-bold">Skill Verification Overview</h3>
                    <p class="text-sm text-blue-100 mt-2 max-w-3xl">
                        SkillProof helps developers prove their skills using real evidence such as projects,
                        GitHub work, and verification requests. This dashboard shows the current prototype
                        information for verified skills, recruiter interest, and session security.
                    </p>

                    <div class="mt-5 flex flex-wrap gap-3">
                        <a href="#skills" class="bg-white text-blue-900 px-4 py-2 rounded-lg text-sm font-bold">
                            View Skills
                        </a>
                        <a href="#security" class="bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-bold">
                            Check Session
                        </a>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                    <?php foreach ($stats as $item): ?>
                        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                            <p class="text-sm text-slate-500 font-semibold">
                                <?php echo safe_output($item["title"]); ?>
                            </p>
                            <h3 class="text-3xl font-extrabold text-slate-900 mt-2">
                                <?php echo safe_output($item["value"]); ?>
                            </h3>
                            <p class="text-xs text-slate-400 mt-2">
                                <?php echo safe_output($item["note"]); ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Dashboard Grid -->
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

                    <!-- Skill Table -->
                    <div id="skills" class="xl:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                            <div>
                                <h3 class="font-bold text-slate-900">Skill Verification Table</h3>
                                <p class="text-xs text-slate-500">Prototype data for submitted developer skills</p>
                            </div>
                            <span class="text-xs bg-blue-100 text-blue-700 px-3 py-1 rounded-full font-bold">
                                Week 4 Prototype
                            </span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                                    <tr>
                                        <th class="text-left px-5 py-3">Skill</th>
                                        <th class="text-left px-5 py-3">Level</th>
                                        <th class="text-left px-5 py-3">Evidence</th>
                                        <th class="text-left px-5 py-3">Status</th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($skills as $skill): ?>
                                        <tr>
                                            <td class="px-5 py-4 font-semibold text-slate-800">
                                                <?php echo safe_output($skill["name"]); ?>
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
                                                <?php else: ?>
                                                    <span class="bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-xs font-bold">
                                                        Pending
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div id="activity" class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                        <h3 class="font-bold text-slate-900">Recent Activity</h3>
                        <p class="text-xs text-slate-500 mt-1">Latest prototype actions</p>

                        <div class="mt-5 space-y-4">
                            <?php foreach ($activities as $activity): ?>
                                <div class="flex gap-3">
                                    <div class="w-2 h-2 bg-blue-600 rounded-full mt-2"></div>
                                    <p class="text-sm text-slate-600">
                                        <?php echo safe_output($activity); ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Security Section -->
                <div id="security" class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                        <p class="text-xs text-slate-500 uppercase font-bold">User Email</p>
                        <p class="mt-2 text-sm font-semibold text-blue-900">
                            <?php echo safe_output($user_email); ?>
                        </p>
                    </div>

                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                        <p class="text-xs text-slate-500 uppercase font-bold">Session Status</p>
                        <p class="mt-2 text-sm font-semibold text-green-700">
                            Active and Protected
                        </p>
                    </div>

                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                        <p class="text-xs text-slate-500 uppercase font-bold">Authentication Time</p>
                        <p class="mt-2 text-sm font-semibold text-slate-800">
                            <?php echo date("Y-m-d H:i:s", $login_time); ?>
                        </p>
                    </div>
                </div>

                <!-- Session Metadata -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                    <h3 class="font-bold text-slate-900">Session Security Metadata</h3>
                    <p class="text-sm text-slate-500 mt-1">
                        This section proves that the dashboard is protected by PHP session data.
                    </p>

                    <div class="mt-4 bg-slate-50 rounded-lg p-4 text-xs text-slate-600 space-y-2">
                        <p>
                            <strong>Session Token:</strong>
                            <code class="bg-white border px-2 py-1 rounded text-blue-700">
                                <?php echo safe_output(session_id()); ?>
                            </code>
                        </p>

                        <p>
                            <strong>Session Timeout:</strong>
                            300 seconds of inactivity
                        </p>

                        <p>
                            <strong>Access Control:</strong>
                            Direct dashboard access without login redirects to login.php
                        </p>
                    </div>
                </div>

            </section>
        </main>
    </div>

</body>
</html>