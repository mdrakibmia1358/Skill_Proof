<?php
session_start();
$is_logged_in = isset($_SESSION["user_email"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkillProof - Recruiter Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-50 text-slate-800 font-sans">

    <!-- Navigation -->
    <header class="bg-slate-950 text-white sticky top-0 z-50 shadow-lg">
        <nav class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">

            <a href="home.php" class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-2xl bg-blue-600 flex items-center justify-center shadow-lg shadow-blue-900/30">
                    <span class="text-white font-extrabold text-lg">SP</span>
                </div>

                <div>
                    <h1 class="text-2xl font-extrabold tracking-wide leading-none">SkillProof</h1>
                    <p class="text-[11px] text-slate-400 mt-1">Developer Assessment</p>
                </div>
            </a>

            <div class="hidden md:flex items-center gap-8 text-sm">
                <a href="home.php" class="text-slate-300 hover:text-white">Home</a>
                <a href="home.php#features" class="text-slate-300 hover:text-white">Features</a>
                <a href="home.php#process" class="text-slate-300 hover:text-white">How It Works</a>
                <a href="skills.php" class="text-slate-300 hover:text-white">Skills</a>
                <a href="recruiter.php" class="text-white font-semibold">Recruiters</a>
                <?php if ($is_logged_in): ?>
                    <a href="auth_system/dashboard.php" class="text-slate-300 hover:text-white">Dashboard</a>
                    <a
                        href="auth_system/logout.php"
                        class="bg-red-600 hover:bg-red-500 text-white px-5 py-2.5 rounded-xl font-bold transition"
                    >
                        Logout
                    </a>
                <?php else: ?>
                    <a href="http://localhost/auth_system/register.php" class="text-slate-300 hover:text-white">Sign Up</a>
                    <a
                        href="http://localhost/auth_system/login.php"
                        class="bg-blue-600 hover:bg-blue-500 text-white px-5 py-2.5 rounded-xl font-bold transition"
                    >
                        Login
                    </a>
                <?php endif; ?>
            </div>

            <button id="menuButton" class="md:hidden bg-slate-800 px-3 py-2 rounded-lg text-sm">
                Menu
            </button>
        </nav>

        <div id="mobileMenu" class="hidden md:hidden border-t border-slate-800 px-6 pb-4 space-y-2 text-sm">
            <a href="home.php" class="block py-2 text-slate-300">Home</a>
            <a href="home.php#features" class="block py-2 text-slate-300">Features</a>
            <a href="home.php#process" class="block py-2 text-slate-300">How It Works</a>
            <a href="skills.php" class="block py-2 text-slate-300">Skills</a>
            <a href="recruiter.php" class="block py-2 text-white font-semibold">Recruiters</a>
            <?php if ($is_logged_in): ?>
                <a href="auth_system/dashboard.php" class="block py-2 text-slate-300">Dashboard</a>
                <a href="auth_system/logout.php" class="block py-2 text-red-300 font-bold">Logout</a>
            <?php else: ?>
                <a href="http://localhost/auth_system/register.php" class="block py-2 text-slate-300">Sign Up</a>
                <a href="http://localhost/auth_system/login.php" class="block py-2 text-blue-300 font-bold">Login</a>
            <?php endif; ?>
        </div>
    </header>

    <!-- Hero -->
    <section class="bg-gradient-to-br from-slate-950 via-blue-950 to-slate-900 text-white">
        <div class="max-w-7xl mx-auto px-6 py-16 grid grid-cols-1 lg:grid-cols-2 gap-10 items-center">

            <div>
                <span class="inline-block bg-white/10 border border-white/10 rounded-full px-4 py-2 text-sm text-blue-100 mb-6">
                    Recruiter Trust Dashboard
                </span>

                <h2 class="text-4xl md:text-5xl font-extrabold leading-tight">
                    Hire developers based on verified skills and real evidence.
                </h2>

                <p class="mt-6 text-slate-300 leading-8 max-w-2xl">
                    SkillProof helps recruiters review trusted developer profiles, skill evidence,
                    verification status, and GitHub-backed technical activity before shortlisting candidates.
                </p>

                <div class="mt-8 flex flex-wrap gap-4">
                    <a
                        href="<?php echo $is_logged_in ? 'auth_system/dashboard.php' : 'http://localhost/auth_system/register.php'; ?>"
                        class="bg-blue-600 hover:bg-blue-500 text-white px-7 py-3 rounded-xl font-bold transition"
                    >
                        Join as Recruiter
                    </a>

                    <a
                        href="skills.php"
                        class="bg-white text-blue-950 px-7 py-3 rounded-xl font-bold hover:bg-blue-50 transition"
                    >
                        View Skills
                    </a>
                </div>
            </div>

            <div class="bg-white text-slate-800 rounded-3xl p-8 shadow-2xl border border-slate-200">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-2xl font-extrabold text-slate-900">Recruiter Overview</h3>
                        <p class="text-sm text-slate-500 mt-1">Candidate verification summary</p>
                    </div>

                    <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-bold">
                        Trusted
                    </span>
                </div>

                <div class="grid grid-cols-3 gap-4 mt-8">
                    <div class="bg-slate-50 rounded-2xl border p-4">
                        <p class="text-3xl font-extrabold text-blue-900">87</p>
                        <p class="text-xs text-slate-500 mt-1">Avg Trust Score</p>
                    </div>

                    <div class="bg-slate-50 rounded-2xl border p-4">
                        <p class="text-3xl font-extrabold text-blue-900">342</p>
                        <p class="text-xs text-slate-500 mt-1">Verified Skills</p>
                    </div>

                    <div class="bg-slate-50 rounded-2xl border p-4">
                        <p class="text-3xl font-extrabold text-blue-900">27</p>
                        <p class="text-xs text-slate-500 mt-1">Requests</p>
                    </div>
                </div>

                <div class="mt-8 space-y-4">
                    <div class="flex items-center justify-between bg-slate-50 border rounded-xl p-4">
                        <div>
                            <p class="font-bold">Frontend Developer</p>
                            <p class="text-xs text-slate-500 mt-1">HTML, CSS, JavaScript</p>
                        </div>
                        <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-bold">
                            Verified
                        </span>
                    </div>

                    <div class="flex items-center justify-between bg-slate-50 border rounded-xl p-4">
                        <div>
                            <p class="font-bold">Backend Developer</p>
                            <p class="text-xs text-slate-500 mt-1">PHP, SQL, Session</p>
                        </div>
                        <span class="bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-xs font-bold">
                            Review
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Recruiter Features -->
    <main class="max-w-7xl mx-auto px-6 py-16">

        <div class="text-center max-w-3xl mx-auto">
            <h2 class="text-3xl md:text-4xl font-extrabold text-slate-900">
                Recruiter Features
            </h2>

            <p class="mt-4 text-slate-500">
                SkillProof reduces hiring uncertainty by connecting candidate claims with real project evidence.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-12">
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                <div class="w-12 h-12 bg-blue-100 text-blue-700 rounded-xl flex items-center justify-center font-extrabold">
                    1
                </div>

                <h3 class="text-xl font-bold mt-5">Verified Profiles</h3>

                <p class="text-sm text-slate-500 mt-3 leading-6">
                    Recruiters can review developer profiles with verified skills, trust score, and evidence history.
                </p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                <div class="w-12 h-12 bg-green-100 text-green-700 rounded-xl flex items-center justify-center font-extrabold">
                    2
                </div>

                <h3 class="text-xl font-bold mt-5">Evidence Review</h3>

                <p class="text-sm text-slate-500 mt-3 leading-6">
                    Skill claims are linked with real project work, repository activity, and documentation quality.
                </p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                <div class="w-12 h-12 bg-orange-100 text-orange-700 rounded-xl flex items-center justify-center font-extrabold">
                    3
                </div>

                <h3 class="text-xl font-bold mt-5">Better Shortlisting</h3>

                <p class="text-sm text-slate-500 mt-3 leading-6">
                    Recruiters can shortlist candidates based on verified skill evidence instead of only CV claims.
                </p>
            </div>
        </div>

        <!-- Candidate Table -->
        <section class="bg-white rounded-3xl border border-slate-200 shadow-sm p-8 mt-12">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <span class="text-xs font-bold tracking-widest text-blue-600 uppercase">
                        Candidate Insights
                    </span>

                    <h2 class="text-2xl font-extrabold text-slate-900 mt-2">
                        Developer Review List
                    </h2>

                    <p class="text-slate-500 mt-2">
                        Recruiters can compare candidates using verified skills and trust indicators.
                    </p>
                </div>

                <a
                    href="<?php echo $is_logged_in ? 'auth_system/dashboard.php' : 'http://localhost/auth_system/register.php'; ?>"
                    class="bg-blue-600 hover:bg-blue-500 text-white px-5 py-3 rounded-xl font-bold text-sm"
                >
                    Request Access
                </a>
            </div>

            <div class="overflow-x-auto mt-8">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                        <tr>
                            <th class="text-left px-4 py-3">Candidate</th>
                            <th class="text-left px-4 py-3">Top Skill</th>
                            <th class="text-left px-4 py-3">Trust Score</th>
                            <th class="text-left px-4 py-3">Status</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <tr>
                            <td class="px-4 py-4 font-semibold">Developer A</td>
                            <td class="px-4 py-4 text-slate-600">JavaScript</td>
                            <td class="px-4 py-4 font-semibold">87%</td>
                            <td class="px-4 py-4">
                                <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-bold">Verified</span>
                            </td>
                        </tr>

                        <tr>
                            <td class="px-4 py-4 font-semibold">Developer B</td>
                            <td class="px-4 py-4 text-slate-600">PHP</td>
                            <td class="px-4 py-4 font-semibold">78%</td>
                            <td class="px-4 py-4">
                                <span class="bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-xs font-bold">Under Review</span>
                            </td>
                        </tr>

                        <tr>
                            <td class="px-4 py-4 font-semibold">Developer C</td>
                            <td class="px-4 py-4 text-slate-600">Git & GitHub</td>
                            <td class="px-4 py-4 font-semibold">88%</td>
                            <td class="px-4 py-4">
                                <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-bold">Verified</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- CTA -->
        <section class="bg-slate-950 text-white rounded-3xl p-10 md:p-14 text-center shadow-xl mt-12">
            <h2 class="text-3xl md:text-4xl font-extrabold">
                Find trusted developers faster.
            </h2>

            <p class="text-slate-300 mt-4 max-w-2xl mx-auto">
                Use SkillProof to review verified skills, evidence records, and trust-based developer profiles.
            </p>

            <div class="mt-8 flex justify-center gap-4 flex-wrap">
                <?php if ($is_logged_in): ?>
                    <a
                        href="auth_system/dashboard.php"
                        class="bg-blue-600 hover:bg-blue-500 text-white px-7 py-3 rounded-xl font-bold transition"
                    >
                        Go to Dashboard
                    </a>

                    <a
                        href="auth_system/logout.php"
                        class="bg-white text-slate-950 px-7 py-3 rounded-xl font-bold hover:bg-slate-100 transition"
                    >
                        Logout
                    </a>
                <?php else: ?>
                    <a
                        href="http://localhost/auth_system/register.php"
                        class="bg-blue-600 hover:bg-blue-500 text-white px-7 py-3 rounded-xl font-bold transition"
                    >
                        Join as Recruiter
                    </a>

                    <a
                        href="http://localhost/auth_system/login.php"
                        class="bg-white text-slate-950 px-7 py-3 rounded-xl font-bold hover:bg-slate-100 transition"
                    >
                        Login
                    </a>
                <?php endif; ?>
            </div>
        </section>

    </main>

    <!-- Footer -->
    <footer class="bg-slate-950 text-slate-400 py-8">
        <div class="max-w-7xl mx-auto px-6 flex flex-col md:flex-row justify-between gap-4 text-sm">
            <p>© 2026 SkillProof. Evidence-Based Developer Assessment Platform.</p>

            <div class="flex gap-5">
                <a href="home.php" class="hover:text-white">Home</a>
                <a href="skills.php" class="hover:text-white">Skills</a>
                <?php if ($is_logged_in): ?>
                    <a href="auth_system/dashboard.php" class="hover:text-white">Dashboard</a>
                <?php else: ?>
                    <a href="http://localhost/auth_system/register.php" class="hover:text-white">Sign Up</a>
                    <a href="http://localhost/auth_system/login.php" class="hover:text-white">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </footer>

    <script>
        const menuButton = document.getElementById("menuButton");
        const mobileMenu = document.getElementById("mobileMenu");

        menuButton.addEventListener("click", function () {
            mobileMenu.classList.toggle("hidden");
        });
    </script>

</body>
</html>