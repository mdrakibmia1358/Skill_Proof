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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkillProof - User Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">

    <nav class="bg-blue-950 text-white px-6 py-4 flex justify-between items-center shadow-md">
        <span class="text-xl font-bold tracking-wider">SkillProof Admin</span>

        <div class="flex items-center gap-4">
            <span class="text-sm bg-blue-800 px-3 py-1 rounded-full">
                <?php echo htmlspecialchars($_SESSION["user_role"]); ?>
            </span>

            <a
                href="logout.php"
                class="bg-red-600 hover:bg-red-500 text-white text-sm font-bold px-4 py-2 rounded-lg transition duration-200"
            >
                Secure Log-Out
            </a>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto p-6 mt-8">
        <div class="bg-white p-8 rounded-xl shadow-sm border border-gray-200">
            <h2 class="text-2xl font-bold text-blue-900 mb-2">Welcome Back!</h2>

            <p class="text-gray-600">
                You are securely authenticated as:
                <span class="font-bold text-gray-800">
                    <?php echo htmlspecialchars($_SESSION["user_email"]); ?>
                </span>
            </p>

            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                <div class="bg-blue-50 border border-blue-100 p-4 rounded-lg">
                    <p class="text-xs text-gray-500 uppercase font-bold">User Email</p>
                    <p class="text-sm font-semibold text-blue-900 mt-1">
                          <?php echo htmlspecialchars($_SESSION["user_email"]); ?>
                    </p>
                </div>

                <div class="bg-green-50 border border-green-100 p-4 rounded-lg">
                   <p class="text-xs text-gray-500 uppercase font-bold">User Role</p>
                   <p class="text-sm font-semibold text-green-800 mt-1">
                        <?php echo htmlspecialchars($_SESSION["user_role"]); ?>
                   </p>
                </div>

                <div class="bg-orange-50 border border-orange-100 p-4 rounded-lg">
                    <p class="text-xs text-gray-500 uppercase font-bold">Session Status</p>
                    <p class="text-sm font-semibold text-orange-800 mt-1">
                         Active and Protected
                    </p>
                </div>
            </div>
            

            <div class="mt-6 border-t border-gray-100 pt-6">
                <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-2">
                    Security Logging Metadata
                </h3>

                <ul class="text-xs text-gray-500 space-y-1">
                    <li>
                        Session Token:
                        <code class="bg-gray-100 px-1 py-0.5 rounded text-blue-800">
                            <?php echo session_id(); ?>
                        </code>
                    </li>

                    <li>
                        Authentication Time:
                        <?php echo date("Y-m-d H:i:s", $_SESSION["login_time"]); ?>
                    </li>
                </ul>
            </div>
        </div>
    </main>

</body>
</html>