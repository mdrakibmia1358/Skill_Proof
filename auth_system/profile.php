<?php
// profile.php - Located inside 'auth_system' directory

session_start();
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/public_slug.php';

// 1. Correct relative path to db_connect.php inside auth_system
require_once 'db_connect.php';

// 2. Session Guard (Redirect unauthenticated users)
if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}

$user_email = $_SESSION['user_email'];
$message = "";
$error = "";
$csrf_token = sp_csrf_token();

// 3. Obtain PDO connection
if (function_exists('skillproof_db')) {
    $pdo = skillproof_db();
} elseif (isset($pdo)) {
    // Uses existing $pdo from db_connect.php
} else {
    error_log("SkillProof: PDO connection variable not found in db_connect.php.");
    die("A system error occurred. Please try again later.");
}

// 4. Fetch Current User Data via Prepared Statement
try {
    $stmt = $pdo->prepare("SELECT user_id, full_name, email, bio, github_username, role, public_slug FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$user_email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        error_log("SkillProof: User record not found for email: " . $user_email);
        die("A system error occurred. Please try again later.");
    }

    $user_id = (int) $user['user_id'];

    // FR-9: Every Developer gets a unique public profile URL. Generate
    // one lazily for accounts created before this feature existed.
    if (($user['role'] ?? '') === 'Developer') {
        $user['public_slug'] = sp_ensure_public_slug($pdo, $user_id, $user['public_slug'] ?? null);
    }
} catch (PDOException $e) {
    // Log the real error for developers; never expose raw exception
    // details to the end user (prevents information disclosure).
    error_log("SkillProof profile.php SELECT failed: " . $e->getMessage());
    die("A system error occurred. Please try again later.");
}

// 4b. Real data for the profile summary card - no more hardcoded numbers.
$latestAnalysis = null;
$topSkills = [];
$verifiedSkillsCount = 0;
$reportsGenerated = 0;

if (($user['role'] ?? '') === 'Developer') {
    $analysisStmt = $pdo->prepare("SELECT * FROM skill_analyses WHERE user_id = ? ORDER BY analysis_id DESC LIMIT 1");
    $analysisStmt->execute([$user_id]);
    $latestAnalysis = $analysisStmt->fetch(PDO::FETCH_ASSOC);

    if ($latestAnalysis) {
        $skillsStmt = $pdo->prepare(
            "SELECT skill_name, score, status FROM skill_scores WHERE analysis_id = ? AND score > 0 ORDER BY score DESC LIMIT 3"
        );
        $skillsStmt->execute([$latestAnalysis["analysis_id"]]);
        $topSkills = $skillsStmt->fetchAll(PDO::FETCH_ASSOC);

        $countStmt = $pdo->prepare("SELECT COUNT(*) AS c FROM skill_scores WHERE analysis_id = ? AND score >= 50");
        $countStmt->execute([$latestAnalysis["analysis_id"]]);
        $verifiedSkillsCount = (int) ($countStmt->fetch()["c"] ?? 0);
    }
} elseif (($user['role'] ?? '') === 'Recruiter') {
    $reportsStmt = $pdo->prepare("SELECT COUNT(*) AS c FROM recruiter_reports WHERE recruiter_id = ?");
    $reportsStmt->execute([$user_id]);
    $reportsGenerated = (int) ($reportsStmt->fetch()["c"] ?? 0);
}

// 5. Parameterized UPDATE Logic (SQL Injection Defense)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !sp_csrf_verify($_POST['csrf_token'] ?? '')) {
    $error = "Your session expired. Please try saving again.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bio = trim($_POST['bio'] ?? '');
    $is_admin = ($user['role'] ?? '') === 'Admin';
    $github_username = $is_admin ? ($user['github_username'] ?? '') : trim($_POST['github_username'] ?? $user['github_username']);

    if (!$is_admin && strlen($github_username) < 3) {
        $error = "GitHub username should contain at least 3 characters.";
    } else {
        try {
            $update_stmt = $pdo->prepare("UPDATE users SET bio = :bio, github_username = :github_username WHERE user_id = :user_id");
            $updated = $update_stmt->execute([
                'bio'             => $bio,
                'github_username' => $github_username,
                'user_id'         => $user_id
            ]);

            if ($updated) {
                $changed_github = ($github_username !== $user['github_username']);
                $user['bio'] = $bio;
                $user['github_username'] = $github_username;
                $message = $changed_github
                    ? "Profile updated! Visit your Dashboard to re-sync scores for the new GitHub account."
                    : "Profile bio updated successfully!";
            }
        } catch (PDOException $e) {
            // Log the real error for developers; show only a generic
            // message to the user.
            error_log("SkillProof profile.php UPDATE failed: " . $e->getMessage());
            $error = "Failed to update profile. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkillProof - My Profile</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#0B132B] text-slate-100 min-h-screen font-sans antialiased">

    <!-- SkillProof Navigation Bar -->
    <nav class="bg-[#0B132B] border-b border-slate-800/80 px-6 lg:px-12 py-4 flex items-center justify-between sticky top-0 z-50">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center font-extrabold text-white text-lg shadow-lg shadow-blue-600/30">
                SP
            </div>
            <div>
                <h1 class="text-white font-bold text-lg leading-none tracking-tight">SkillProof</h1>
                <span class="text-[10px] text-slate-400 tracking-wider uppercase font-semibold">Developer Assessment</span>
            </div>
        </div>
        
        <div class="hidden md:flex items-center space-x-8 text-sm font-medium text-slate-300">
            <a href="../home.php" class="hover:text-white transition">Home</a>
            <a href="../skills.php" class="hover:text-white transition">Skills</a>
            <a href="<?php
                $r = $user['role'] ?? '';
                echo $r === 'Admin' ? 'admin_dashboard.php' : ($r === 'Recruiter' ? 'recruiter_dashboard.php' : 'dashboard.php');
            ?>" class="hover:text-white transition">Dashboard</a>
            <a href="profile.php" class="text-white font-semibold border-b-2 border-blue-500 pb-1">Profile</a>
        </div>

        <div>
            <a href="logout.php" class="bg-red-500/90 hover:bg-red-600 text-white px-5 py-2 rounded-xl text-xs font-bold shadow-md shadow-red-500/20 transition">
                Logout
            </a>
        </div>
    </nav>

    <!-- Main Content Container -->
    <main class="max-w-6xl mx-auto px-6 py-12">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start">
            
            <!-- Left Column: Profile Form -->
            <div class="lg:col-span-7 space-y-6">
                <div class="inline-flex items-center space-x-2 bg-slate-800/80 border border-slate-700/60 rounded-full px-4 py-1.5 text-xs text-slate-300 font-medium">
                    <span class="w-2 h-2 rounded-full <?php echo (in_array($user['role'] ?? '', ['Recruiter', 'Admin'], true) || ($latestAnalysis && ($latestAnalysis['trust_score'] ?? 0) > 0)) ? 'bg-blue-400 animate-pulse' : 'bg-slate-500'; ?>"></span>
                    <span>
                        <?php
                        if (($user['role'] ?? '') === 'Admin') {
                            echo 'Administrator Account';
                        } elseif (($user['role'] ?? '') === 'Recruiter') {
                            echo 'Recruiter Account';
                        } elseif ($latestAnalysis && ($latestAnalysis['trust_score'] ?? 0) > 0) {
                            echo 'Verified Developer Account';
                        } else {
                            echo 'GitHub Not Yet Verified';
                        }
                        ?>
                    </span>
                </div>

                <h1 class="text-4xl lg:text-5xl font-extrabold text-white tracking-tight leading-tight">
                    Manage your <span class="text-blue-500">SkillProof</span> developer profile.
                </h1>
                
                <p class="text-slate-400 text-sm leading-relaxed">
                    SkillProof helps developers prove their technical skills using project work, GitHub activity, and verification records. Keep your profile updated for recruiter evaluation.
                </p>

                <!-- Feedback Notifications -->
                <?php if (!empty($message)): ?>
                    <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 px-4 py-3 rounded-xl text-sm font-medium flex items-center space-x-2">
                        <span>✓</span>
                        <span><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded-xl text-sm font-medium">
                        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <!-- Profile Form -->
                <div class="bg-slate-900/60 border border-slate-800/80 rounded-3xl p-6 lg:p-8 shadow-2xl backdrop-blur-sm">
                    <form action="profile.php" method="POST" class="space-y-5">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Full Name</label>
                            <input type="text" disabled 
                                value="<?php echo htmlspecialchars($user['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                                class="w-full bg-slate-800/50 border border-slate-700/60 text-slate-300 px-4 py-3 rounded-xl text-sm focus:outline-none cursor-not-allowed">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Registered Email</label>
                            <input type="email" disabled 
                                value="<?php echo htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                                class="w-full bg-slate-800/50 border border-slate-700/60 text-slate-300 px-4 py-3 rounded-xl text-sm focus:outline-none cursor-not-allowed">
                        </div>

                        <?php if (($user['role'] ?? '') !== 'Admin'): ?>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">GitHub Username</label>
                            <input type="text" name="github_username" required minlength="3"
                                value="<?php echo htmlspecialchars($user['github_username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                class="w-full bg-slate-800/90 border border-slate-700 text-white px-4 py-3 rounded-xl text-sm focus:outline-none focus:border-blue-500 transition">
                            <?php if (($user['role'] ?? '') === 'Developer'): ?>
                                <p class="text-xs text-slate-500 mt-2">
                                    This is the only GitHub account SkillProof will verify for you. Changing it here
                                    and re-syncing from the Dashboard replaces your previous evidence with this account's data.
                                </p>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Developer Bio & Summary</label>
                            <textarea name="bio" rows="4" 
                                class="w-full bg-slate-800/90 border border-slate-700 text-white px-4 py-3 rounded-xl text-sm focus:outline-none focus:border-blue-500 transition placeholder-slate-500" 
                                placeholder="Describe your core technical stack, GitHub projects, and experience..."><?php echo htmlspecialchars($user['bio'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>

                        <div class="pt-2">
                            <button type="submit" 
                                class="w-full sm:w-auto bg-blue-600 hover:bg-blue-500 text-white font-semibold px-6 py-3 rounded-xl text-sm shadow-lg shadow-blue-600/30 transition duration-200">
                                Save Profile Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right Column: Real profile summary (role-aware) -->
            <div class="lg:col-span-5">
                <?php if (($user['role'] ?? '') === 'Admin'): ?>

                    <div class="bg-white text-slate-900 rounded-3xl p-8 shadow-2xl space-y-6">
                        <div>
                            <h3 class="font-extrabold text-xl text-slate-900 tracking-tight">Administrator</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Manage users and monitor platform health</p>
                        </div>
                        <a href="admin_dashboard.php" class="block text-center bg-slate-900 hover:bg-slate-800 text-white font-semibold px-6 py-3 rounded-xl text-sm transition">
                            Go to Admin Dashboard
                        </a>
                    </div>

                <?php elseif (($user['role'] ?? '') === 'Recruiter'): ?>

                    <div class="bg-white text-slate-900 rounded-3xl p-8 shadow-2xl space-y-6">
                        <div>
                            <h3 class="font-extrabold text-xl text-slate-900 tracking-tight">Recruiter Activity</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Your report generation history</p>
                        </div>
                        <div class="bg-slate-50 p-6 rounded-2xl text-center border border-slate-100">
                            <span class="block text-4xl font-black text-blue-600 leading-tight"><?php echo (int) $reportsGenerated; ?></span>
                            <span class="text-xs font-semibold text-slate-500 uppercase mt-1 block">PDF Reports Generated</span>
                        </div>
                        <a href="recruiter_dashboard.php" class="block text-center bg-blue-600 hover:bg-blue-500 text-white font-semibold px-6 py-3 rounded-xl text-sm transition">
                            Search Developers
                        </a>
                    </div>

                <?php elseif ($latestAnalysis): ?>

                    <div class="bg-white text-slate-900 rounded-3xl p-8 shadow-2xl space-y-6">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="font-extrabold text-xl text-slate-900 tracking-tight">Developer Trust Profile</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Evidence-backed skill overview</p>
                            </div>
                            <span class="<?php echo ($latestAnalysis['trust_score'] ?? 0) >= 50 ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'; ?> text-xs font-bold px-3 py-1 rounded-full">
                                <?php echo ($latestAnalysis['trust_score'] ?? 0) >= 50 ? 'Verified' : 'Building Evidence'; ?>
                            </span>
                        </div>

                        <div class="space-y-4 pt-2">
                            <?php if (empty($topSkills)): ?>
                                <p class="text-sm text-slate-400">
                                    No verified skills yet. Re-sync from your Dashboard once your GitHub account has public repositories.
                                </p>
                            <?php else: ?>
                                <?php foreach ($topSkills as $skill):
                                    $score = (int) ($skill['score'] ?? 0);
                                    $barColor = $score >= 70 ? 'bg-emerald-500' : ($score >= 40 ? 'bg-amber-500' : 'bg-red-400');
                                    $labelColor = $score >= 70 ? 'text-emerald-600' : ($score >= 40 ? 'text-amber-600' : 'text-red-500');
                                ?>
                                    <div>
                                        <div class="flex justify-between text-xs font-bold mb-1.5">
                                            <span class="text-slate-800"><?php echo htmlspecialchars($skill['skill_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="<?php echo $labelColor; ?>"><?php echo htmlspecialchars($skill['status'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                        <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                            <div class="<?php echo $barColor; ?> h-full" style="width: <?php echo $score; ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="grid grid-cols-3 gap-3 pt-4 border-t border-slate-100">
                            <div class="bg-slate-50 p-3 rounded-2xl text-center border border-slate-100">
                                <span class="block text-2xl font-black text-blue-600 leading-tight"><?php echo (int) ($latestAnalysis['trust_score'] ?? 0); ?></span>
                                <span class="text-[10px] font-semibold text-slate-500 uppercase">Trust Score</span>
                            </div>
                            <div class="bg-slate-50 p-3 rounded-2xl text-center border border-slate-100">
                                <span class="block text-2xl font-black text-slate-800 leading-tight"><?php echo (int) $verifiedSkillsCount; ?></span>
                                <span class="text-[10px] font-semibold text-slate-500 uppercase">Verified Skills</span>
                            </div>
                            <div class="bg-slate-50 p-3 rounded-2xl text-center border border-slate-100">
                                <span class="block text-2xl font-black text-slate-800 leading-tight"><?php echo (int) ($latestAnalysis['total_repositories'] ?? 0); ?></span>
                                <span class="text-[10px] font-semibold text-slate-500 uppercase">Repositories</span>
                            </div>
                        </div>

                        <p class="text-[11px] text-slate-400">
                            Last synced: <?php echo htmlspecialchars($latestAnalysis['analyzed_at'] ?? '—', ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                    </div>

                <?php else: ?>

                    <div class="bg-white text-slate-900 rounded-3xl p-8 shadow-2xl space-y-4 text-center">
                        <h3 class="font-extrabold text-xl text-slate-900 tracking-tight">Developer Trust Profile</h3>
                        <p class="text-sm text-slate-500">
                            No GitHub analysis yet. Visit your
                            <a href="dashboard.php" class="text-blue-600 font-semibold underline">Dashboard</a>
                            to connect and verify your GitHub account.
                        </p>
                    </div>

                <?php endif; ?>
            </div>

            <?php if (($user['role'] ?? '') === 'Developer' && !empty($user['public_slug'])):
                $publicUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://')
                    . $_SERVER['HTTP_HOST'] . '/public_profile.php?slug=' . urlencode($user['public_slug']);
            ?>
                <div class="lg:col-span-12 bg-white border border-slate-800/10 rounded-3xl p-6 shadow-2xl">
                    <h3 class="font-extrabold text-lg text-slate-900">Your Public Profile Link</h3>
                    <p class="text-sm text-slate-500 mt-1">
                        Share this link with recruiters. It shows your verified skills, career fit, and activity charts -
                        no login required to view it.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-3 mt-4">
                        <input
                            id="publicProfileUrl" type="text" readonly
                            value="<?php echo htmlspecialchars($publicUrl, ENT_QUOTES, 'UTF-8'); ?>"
                            class="flex-1 px-4 py-3 border border-slate-200 bg-slate-50 text-slate-600 rounded-xl text-sm focus:outline-none"
                        >
                        <button
                            type="button" onclick="sp_copyPublicUrl()"
                            class="bg-slate-900 hover:bg-slate-800 text-white px-6 py-3 rounded-xl font-bold text-sm whitespace-nowrap"
                        >
                            Copy Link
                        </button>
                        <a
                            href="<?php echo htmlspecialchars($publicUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"
                            class="bg-blue-600 hover:bg-blue-500 text-white px-6 py-3 rounded-xl font-bold text-sm text-center whitespace-nowrap"
                        >
                            Preview
                        </a>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <script>
        function sp_copyPublicUrl() {
            const input = document.getElementById("publicProfileUrl");
            input.select();
            input.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(input.value).catch(() => document.execCommand("copy"));
        }
    </script>

</body>
</html>