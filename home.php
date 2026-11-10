<?php
session_start();
$is_logged_in = isset($_SESSION["user_email"]);
$dashboard_link = (($_SESSION["user_role"] ?? "") === "Recruiter") ? "auth_system/recruiter_dashboard.php" : "auth_system/dashboard.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkillProof - Evidence-Based Developer Assessment Platform</title>
    <link rel="stylesheet" href="assets/css/tailwind.css?v=20261110">
    <script src="assets/js/navigation.js?v=20261110" defer></script>
</head>

<body class="bg-slate-50 text-slate-800 font-sans">

    <!-- Navigation -->
    <header class="bg-slate-950 text-white sticky top-0 z-50 shadow-lg">
        <nav aria-label="Main navigation" class="max-w-7xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between gap-3">

            <!-- Logo -->
            <a href="home.php" class="flex shrink-0 items-center gap-3">
                <div class="w-11 h-11 rounded-2xl bg-blue-600 flex items-center justify-center shadow-lg shadow-blue-900/30">
                    <span class="text-white font-extrabold text-lg">SP</span>
                </div>

                <div>
                    <h1 class="text-2xl font-extrabold tracking-wide leading-none">
                        SkillProof
                    </h1>
                    <p class="text-[11px] text-slate-400 mt-1">
                        Developer Assessment
                    </p>
                </div>
            </a>

            <!-- Desktop Menu -->
            <div class="hidden lg:flex items-center gap-8 text-sm">
                <a href="home.php" class="text-white font-semibold">Home</a>
                <a href="#features" class="text-slate-300 hover:text-white">Features</a>
                <a href="#process" class="text-slate-300 hover:text-white">How It Works</a>
                <a href="skills.php" class="text-slate-300 hover:text-white">Skills</a>
                <a href="recruiter.php" class="text-slate-300 hover:text-white">Recruiters</a>
                <?php if ($is_logged_in): ?>
                    <a href="<?php echo $dashboard_link; ?>" class="text-slate-300 hover:text-white">Dashboard</a>
                    <a href="auth_system/profile.php" class="text-slate-300 hover:text-white">Profile</a>
                    <a
                        href="auth_system/logout.php"
                        class="bg-red-600 hover:bg-red-500 text-white px-5 py-2.5 rounded-xl font-bold transition"
                    >
                        Logout
                    </a>
                <?php else: ?>
                    <a href="/auth_system/register.php" class="text-slate-300 hover:text-white">Sign Up</a>
                    <a
                        href="/auth_system/login.php"
                        class="bg-blue-600 hover:bg-blue-500 text-white px-5 py-2.5 rounded-xl font-bold transition"
                    >
                        Login
                    </a>
                <?php endif; ?>
            </div>

            <!-- Mobile Menu Button -->
            <button
                id="menuButton" type="button" data-menu-breakpoint="1024" aria-controls="mobileMenu" aria-expanded="false"
                class="lg:hidden shrink-0 min-h-[44px] bg-slate-800 px-3 py-2 rounded-lg text-sm"
            >
                Menu
            </button>
        </nav>

        <!-- Mobile Menu -->
        <div id="mobileMenu" class="hidden lg:hidden max-h-[calc(100dvh-80px)] overflow-y-auto border-t border-slate-800 px-4 sm:px-6 pb-4 space-y-2 text-sm">
            <a href="home.php" class="block py-3 text-white font-semibold">Home</a>
            <a href="#features" class="block py-3 text-slate-300">Features</a>
            <a href="#process" class="block py-3 text-slate-300">How It Works</a>
            <a href="skills.php" class="block py-3 text-slate-300">Skills</a>
            <a href="recruiter.php" class="block py-3 text-slate-300">Recruiters</a>
            <?php if ($is_logged_in): ?>
                <a href="<?php echo $dashboard_link; ?>" class="block py-3 text-slate-300">Dashboard</a>
                <a href="auth_system/profile.php" class="block py-3 text-slate-300">Profile</a>
                <a href="auth_system/logout.php" class="block py-3 text-red-300 font-bold">Logout</a>
            <?php else: ?>
                <a href="/auth_system/register.php" class="block py-3 text-slate-300">Sign Up</a>
                <a href="/auth_system/login.php" class="block py-3 text-blue-300 font-bold">Login</a>
            <?php endif; ?>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-slate-950 via-blue-950 to-slate-900 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-12 sm:py-20 grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">

            <!-- Hero Text -->
            <div>
                <span class="inline-block bg-white/10 border border-white/10 rounded-full px-4 py-2 text-sm text-blue-100 mb-6">
                    Evidence-Based Developer Assessment Platform
                </span>

                <h2 class="text-3xl sm:text-4xl md:text-6xl font-extrabold leading-tight">
                    Verify skills with real evidence, not only CV claims.
                </h2>

                <p class="mt-6 text-lg text-slate-300 leading-8 max-w-2xl">
                    SkillProof helps developers prove their technical skills using project work,
                    GitHub activity, verification records, and skill evidence. Recruiters can review
                    trusted profiles before making hiring decisions.
                </p>

                <div class="mt-8 flex flex-col sm:flex-row sm:flex-wrap gap-4 text-center">
                    <?php if ($is_logged_in): ?>
                        <a
                            href="<?php echo $dashboard_link; ?>"
                            class="bg-blue-600 hover:bg-blue-500 text-white px-7 py-3 rounded-xl font-bold shadow-lg shadow-blue-900/30 transition"
                        >
                            Go to Dashboard
                        </a>

                        <a
                            href="auth_system/logout.php"
                            class="bg-white text-blue-950 px-7 py-3 rounded-xl font-bold hover:bg-blue-50 transition"
                        >
                            Logout
                        </a>
                    <?php else: ?>
                        <a
                            href="/auth_system/login.php"
                            class="bg-blue-600 hover:bg-blue-500 text-white px-7 py-3 rounded-xl font-bold shadow-lg shadow-blue-900/30 transition"
                        >
                            Login to Dashboard
                        </a>

                        <a
                            href="/auth_system/register.php"
                            class="bg-white text-blue-950 px-7 py-3 rounded-xl font-bold hover:bg-blue-50 transition"
                        >
                            Create Account
                        </a>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 mt-10 max-w-xl">
                    <div class="flex items-center justify-between gap-4 sm:block bg-white/10 border border-white/10 rounded-2xl p-4 sm:p-5">
                        <p class="text-3xl font-extrabold">1.2K+</p>
                        <p class="text-xs text-slate-300 mt-1">Developers</p>
                    </div>

                    <div class="flex items-center justify-between gap-4 sm:block bg-white/10 border border-white/10 rounded-2xl p-4 sm:p-5">
                        <p class="text-3xl font-extrabold">342</p>
                        <p class="text-xs text-slate-300 mt-1">Verified Skills</p>
                    </div>

                    <div class="flex items-center justify-between gap-4 sm:block bg-white/10 border border-white/10 rounded-2xl p-4 sm:p-5">
                        <p class="text-3xl font-extrabold">27</p>
                        <p class="text-xs text-slate-300 mt-1">Recruiters</p>
                    </div>
                </div>
            </div>

            <!-- Hero Dashboard Card -->
            <div class="bg-white text-slate-800 rounded-3xl shadow-2xl p-5 sm:p-8 border border-slate-200">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
                    <div>
                        <h3 class="text-2xl font-extrabold text-slate-900">
                            Developer Trust Profile
                        </h3>
                        <p class="text-sm text-slate-500 mt-1">
                            Evidence-backed skill overview
                        </p>
                    </div>

                    <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-bold">
                        Verified
                    </span>
                </div>

                <div class="space-y-5">
                    <div>
                        <div class="flex flex-wrap justify-between gap-2 text-sm mb-2">
                            <span class="font-bold text-slate-700">JavaScript</span>
                            <span class="text-green-700 font-bold">Verified</span>
                        </div>
                        <div class="h-2 bg-slate-200 rounded-full">
                            <div class="h-2 bg-green-500 rounded-full w-[90%]"></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex flex-wrap justify-between gap-2 text-sm mb-2">
                            <span class="font-bold text-slate-700">PHP Authentication</span>
                            <span class="text-yellow-600 font-bold">Pending</span>
                        </div>
                        <div class="h-2 bg-slate-200 rounded-full">
                            <div class="h-2 bg-yellow-500 rounded-full w-[70%]"></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex flex-wrap justify-between gap-2 text-sm mb-2">
                            <span class="font-bold text-slate-700">Git & GitHub</span>
                            <span class="text-green-700 font-bold">Verified</span>
                        </div>
                        <div class="h-2 bg-slate-200 rounded-full">
                            <div class="h-2 bg-green-500 rounded-full w-[85%]"></div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 mt-8">
                    <div class="flex items-center justify-between gap-4 sm:block bg-slate-50 rounded-2xl p-4 border">
                        <p class="text-2xl font-extrabold text-blue-900">87</p>
                        <p class="text-xs text-slate-500 mt-1">Trust Score</p>
                    </div>

                    <div class="flex items-center justify-between gap-4 sm:block bg-slate-50 rounded-2xl p-4 border">
                        <p class="text-2xl font-extrabold text-blue-900">12</p>
                        <p class="text-xs text-slate-500 mt-1">Skills</p>
                    </div>

                    <div class="flex items-center justify-between gap-4 sm:block bg-slate-50 rounded-2xl p-4 border">
                        <p class="text-2xl font-extrabold text-blue-900">24</p>
                        <p class="text-xs text-slate-500 mt-1">Repositories</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="scroll-mt-24 max-w-7xl mx-auto px-4 sm:px-6 py-10 sm:py-16">
        <div class="text-center max-w-3xl mx-auto">
            <h2 class="text-3xl md:text-4xl font-extrabold text-slate-900">
                What SkillProof Provides
            </h2>

            <p class="mt-4 text-slate-500">
                SkillProof connects developer skills with real evidence, verification status,
                and recruiter-ready profile insights.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-12">
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm hover:shadow-md transition">
                <div class="w-12 h-12 bg-blue-100 text-blue-700 rounded-xl flex items-center justify-center font-extrabold">
                    1
                </div>

                <h3 class="text-xl font-bold mt-5">Skill Evidence</h3>

                <p class="text-sm text-slate-500 mt-3 leading-6">
                    Developers can submit skills with GitHub projects, portfolio links,
                    and proof of practical work.
                </p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm hover:shadow-md transition">
                <div class="w-12 h-12 bg-green-100 text-green-700 rounded-xl flex items-center justify-center font-extrabold">
                    2
                </div>

                <h3 class="text-xl font-bold mt-5">Verification Status</h3>

                <p class="text-sm text-slate-500 mt-3 leading-6">
                    Each skill can be marked as verified, pending, or under review based
                    on submitted evidence.
                </p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm hover:shadow-md transition">
                <div class="w-12 h-12 bg-orange-100 text-orange-700 rounded-xl flex items-center justify-center font-extrabold">
                    3
                </div>

                <h3 class="text-xl font-bold mt-5">Recruiter Trust</h3>

                <p class="text-sm text-slate-500 mt-3 leading-6">
                    Recruiters can review trusted developer profiles before shortlisting
                    candidates.
                </p>
            </div>
        </div>
    </section>

    <!-- User Roles Section -->
    <section class="bg-white border-y border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-10 sm:py-16">
            <div class="text-center max-w-3xl mx-auto">
                <h2 class="text-3xl md:text-4xl font-extrabold text-slate-900">
                    Built for Developers and Recruiters
                </h2>

                <p class="mt-4 text-slate-500">
                    The platform supports both skill submitters and profile reviewers.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-12">
                <div class="bg-slate-50 rounded-3xl border border-slate-200 p-5 sm:p-8">
                    <h3 class="text-2xl font-extrabold text-blue-900">For Developers</h3>

                    <ul class="mt-5 space-y-3 text-sm text-slate-600">
                        <li>• Create a trusted developer profile</li>
                        <li>• Submit skills with evidence links</li>
                        <li>• Track verification status</li>
                        <li>• Improve recruiter visibility</li>
                    </ul>
                </div>

                <div class="bg-slate-50 rounded-3xl border border-slate-200 p-5 sm:p-8">
                    <h3 class="text-2xl font-extrabold text-blue-900">For Recruiters</h3>

                    <ul class="mt-5 space-y-3 text-sm text-slate-600">
                        <li>• Review evidence-backed skills</li>
                        <li>• Check trusted developer profiles</li>
                        <li>• Reduce CV-based uncertainty</li>
                        <li>• Make better shortlisting decisions</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Process Section -->
    <section id="process" class="scroll-mt-24 max-w-7xl mx-auto px-4 sm:px-6 py-10 sm:py-16">
        <h2 class="text-3xl font-extrabold text-slate-900 text-center">
            How It Works
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 mt-12">
            <div class="text-center">
                <div class="mx-auto w-14 h-14 rounded-full bg-blue-600 text-white flex items-center justify-center font-extrabold shadow-lg">
                    01
                </div>
                <h3 class="font-bold mt-4">Create Profile</h3>
                <p class="text-sm text-slate-500 mt-2">
                    Developer creates a SkillProof account.
                </p>
            </div>

            <div class="text-center">
                <div class="mx-auto w-14 h-14 rounded-full bg-blue-600 text-white flex items-center justify-center font-extrabold shadow-lg">
                    02
                </div>
                <h3 class="font-bold mt-4">Submit Skills</h3>
                <p class="text-sm text-slate-500 mt-2">
                    Skills are submitted with evidence links.
                </p>
            </div>

            <div class="text-center">
                <div class="mx-auto w-14 h-14 rounded-full bg-blue-600 text-white flex items-center justify-center font-extrabold shadow-lg">
                    03
                </div>
                <h3 class="font-bold mt-4">Verify Evidence</h3>
                <p class="text-sm text-slate-500 mt-2">
                    Evidence is reviewed and marked with status.
                </p>
            </div>

            <div class="text-center">
                <div class="mx-auto w-14 h-14 rounded-full bg-blue-600 text-white flex items-center justify-center font-extrabold shadow-lg">
                    04
                </div>
                <h3 class="font-bold mt-4">Recruiter Review</h3>
                <p class="text-sm text-slate-500 mt-2">
                    Recruiters view trusted skill profiles.
                </p>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 pb-10 sm:pb-16">
        <div class="bg-slate-950 text-white rounded-3xl p-5 sm:p-10 md:p-14 text-center shadow-xl">
            <h2 class="text-3xl md:text-4xl font-extrabold">
                Ready to build a trusted developer profile?
            </h2>

            <p class="text-slate-300 mt-4 max-w-2xl mx-auto">
                Start with your SkillProof account and access the developer dashboard to manage
                skill verification and evidence records.
            </p>

            <div class="mt-8 flex flex-col sm:flex-row justify-center gap-4 sm:flex-wrap text-center">
                <?php if ($is_logged_in): ?>
                    <a
                        href="<?php echo $dashboard_link; ?>"
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
                        href="/auth_system/login.php"
                        class="bg-blue-600 hover:bg-blue-500 text-white px-7 py-3 rounded-xl font-bold transition"
                    >
                        Login
                    </a>

                    <a
                        href="/auth_system/register.php"
                        class="bg-white text-slate-950 px-7 py-3 rounded-xl font-bold hover:bg-slate-100 transition"
                    >
                        Sign Up
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-slate-950 text-slate-400 py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 flex flex-col md:flex-row justify-between gap-4 text-sm">
            <p>© 2026 SkillProof. Evidence-Based Developer Assessment Platform.</p>

            <div class="flex flex-wrap gap-x-5 gap-y-3">
                <a href="home.php" class="hover:text-white">Home</a>
                <a href="skills.php" class="hover:text-white">Skills</a>
                <a href="recruiter.php" class="hover:text-white">Recruiter</a>
                <?php if ($is_logged_in): ?>
                    <a href="<?php echo $dashboard_link; ?>" class="hover:text-white">Dashboard</a>
                <?php else: ?>
                    <a href="/auth_system/login.php" class="hover:text-white">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </footer>

</body>
</html>
