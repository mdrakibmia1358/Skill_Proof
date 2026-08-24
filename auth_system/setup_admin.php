<?php
// setup_admin.php
// ONE-TIME USE: creates the first Admin account.
// Admin accounts are intentionally NOT offered on the public register.php
// form (a self-signup form must never be able to grant admin access).
// This script disables itself automatically once any Admin account exists.
//
// SECURITY: Delete this file after creating your Admin account, or at
// minimum move it somewhere not web-accessible. It is safe to leave in
// place during development since it refuses to run once an Admin exists,
// but it should not ship to a real production server.

session_start();
require_once __DIR__ . "/db_connect.php";
require_once __DIR__ . "/csrf.php";
require_once __DIR__ . "/public_slug.php";

$pdo = skillproof_db();
$csrf_token = sp_csrf_token();

$adminExistsStmt = $pdo->query("SELECT COUNT(*) AS c FROM users WHERE role = 'Admin'");
$adminExists = (int) ($adminExistsStmt->fetch()["c"] ?? 0) > 0;

$error_message = "";
$success_message = "";

if (!$adminExists && $_SERVER["REQUEST_METHOD"] === "POST") {
    if (!sp_csrf_verify($_POST["csrf_token"] ?? "")) {
        $error_message = "Your session expired. Please reload this page and try again.";
    } else {
        $full_name = trim($_POST["full_name"] ?? "");
        $email = trim(filter_input(INPUT_POST, "email", FILTER_SANITIZE_EMAIL) ?? "");
        $password = $_POST["password"] ?? "";

        if (empty($full_name) || empty($email) || empty($password)) {
            $error_message = "All fields are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please provide a valid email address.";
        } elseif (strlen($password) < 8) {
            $error_message = "Password must be at least 8 characters.";
        } else {
            $existingStmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
            $existingStmt->execute([$email]);

            if ($existingStmt->fetch()) {
                $error_message = "An account with that email already exists.";
            } else {
                $password_hash = password_hash($password, PASSWORD_BCRYPT, ["cost" => 12]);

                $insertStmt = $pdo->prepare(
                    "INSERT INTO users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'Admin', 'Active')"
                );
                $insertStmt->execute([$full_name, $email, $password_hash]);
                $new_admin_id = (int) $pdo->lastInsertId();
                sp_ensure_public_slug($pdo, $new_admin_id, null);

                $success_message = "Admin account created. This setup page is now disabled. You can delete this file and log in at login.php.";
                $adminExists = true;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkillProof - Admin Setup</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-6">

    <div class="bg-white border border-slate-200 rounded-2xl shadow-xl p-8 max-w-md w-full">
        <h1 class="text-2xl font-extrabold text-slate-900">Admin Setup</h1>
        <p class="text-sm text-slate-500 mt-1">One-time creation of the first Admin account.</p>

        <?php if ($adminExists && empty($success_message)): ?>
            <div class="bg-amber-50 border border-amber-300 text-amber-700 px-4 py-3 rounded-xl text-sm mt-5">
                An Admin account already exists. This setup page is disabled.
                Please delete <code>auth_system/setup_admin.php</code> from the server.
            </div>
            <a href="login.php" class="block text-center bg-slate-900 hover:bg-slate-800 text-white px-6 py-3 rounded-xl font-bold mt-4">
                Go to Login
            </a>
        <?php elseif (!empty($success_message)): ?>
            <div class="bg-emerald-50 border border-emerald-300 text-emerald-700 px-4 py-3 rounded-xl text-sm mt-5">
                <?php echo htmlspecialchars($success_message, ENT_QUOTES, "UTF-8"); ?>
            </div>
            <a href="login.php" class="block text-center bg-blue-600 hover:bg-blue-500 text-white px-6 py-3 rounded-xl font-bold mt-4">
                Go to Login
            </a>
        <?php else: ?>
            <?php if (!empty($error_message)): ?>
                <div class="bg-red-50 border border-red-300 text-red-600 px-4 py-3 rounded-xl text-sm mt-5">
                    <?php echo htmlspecialchars($error_message, ENT_QUOTES, "UTF-8"); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="setup_admin.php" class="space-y-4 mt-5">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, "UTF-8"); ?>">

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Full Name</label>
                    <input type="text" name="full_name" required
                        class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Email</label>
                    <input type="email" name="email" required
                        class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Password</label>
                    <input type="password" name="password" required minlength="8"
                        class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    <p class="text-xs text-slate-400 mt-1">At least 8 characters.</p>
                </div>
                <button type="submit" class="w-full bg-slate-900 hover:bg-slate-800 text-white px-6 py-3 rounded-xl font-bold">
                    Create Admin Account
                </button>
            </form>
        <?php endif; ?>
    </div>

</body>
</html>