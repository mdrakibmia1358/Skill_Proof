<?php
// profile.php - Located inside 'auth_system' directory
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

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

// 3. Obtain PDO connection
if (function_exists('skillproof_db')) {
    $pdo = skillproof_db();
} elseif (isset($pdo)) {
    // Uses existing $pdo from db_connect.php
} else {
    die("Error: PDO connection variable not found in db_connect.php.");
}

// 4. Fetch Current User Data via Prepared Statement
try {
    $stmt = $pdo->prepare("SELECT user_id, full_name, email, bio FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$user_email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("User record not found.");
    }

    $user_id = (int) $user['user_id'];
} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
}

// 5. Parameterized UPDATE Logic (SQL Injection Defense)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bio = trim($_POST['bio'] ?? '');
    
    try {
        $update_stmt = $pdo->prepare("UPDATE users SET bio = :bio WHERE user_id = :user_id");
        $updated = $update_stmt->execute([
            'bio'      => $bio,
            'user_id'  => $user_id
        ]);

        if ($updated) {
            $message = "Profile bio updated successfully!";
            $user['bio'] = $bio;
        }
    } catch (PDOException $e) {
        $error = "Failed to update profile: " . $e->getMessage();
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
            <a href="dashboard.php" class="hover:text-white transition">Dashboard</a>
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
                    <span class="w-2 h-2 rounded-full bg-blue-400 animate-pulse"></span>
                    <span>Verified Developer Account</span>
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

            <!-- Right Column: Developer Trust Profile Overview Card -->
            <div class="lg:col-span-5">
                <div class="bg-white text-slate-900 rounded-3xl p-8 shadow-2xl space-y-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="font-extrabold text-xl text-slate-900 tracking-tight">Developer Trust Profile</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Evidence-backed skill overview</p>
                        </div>
                        <span class="bg-emerald-100 text-emerald-700 text-xs font-bold px-3 py-1 rounded-full">
                            Verified
                        </span>
                    </div>

                    <div class="space-y-4 pt-2">
                        <div>
                            <div class="flex justify-between text-xs font-bold mb-1.5">
                                <span class="text-slate-800">JavaScript</span>
                                <span class="text-emerald-600">Verified</span>
                            </div>
                            <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                <div class="bg-emerald-500 h-full w-[85%] rounded-full"></div>
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between text-xs font-bold mb-1.5">
                                <span class="text-slate-800">PHP Authentication</span>
                                <span class="text-amber-600">Pending</span>
                            </div>
                            <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                <div class="bg-amber-500 h-full w-[65%] rounded-full"></div>
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between text-xs font-bold mb-1.5">
                                <span class="text-slate-800">Git & GitHub</span>
                                <span class="text-emerald-600">Verified</span>
                            </div>
                            <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                <div class="bg-emerald-500 h-full w-[90%] rounded-full"></div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3 pt-4 border-t border-slate-100">
                        <div class="bg-slate-50 p-3 rounded-2xl text-center border border-slate-100">
                            <span class="block text-2xl font-black text-blue-600 leading-tight">87</span>
                            <span class="text-[10px] font-semibold text-slate-500 uppercase">Trust Score</span>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-2xl text-center border border-slate-100">
                            <span class="block text-2xl font-black text-slate-800 leading-tight">12</span>
                            <span class="text-[10px] font-semibold text-slate-500 uppercase">Skills</span>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-2xl text-center border border-slate-100">
                            <span class="block text-2xl font-black text-slate-800 leading-tight">24</span>
                            <span class="text-[10px] font-semibold text-slate-500 uppercase">Repositories</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

</body>
</html>