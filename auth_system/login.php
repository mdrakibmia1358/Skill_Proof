<?php
session_start();

$error_message = "";
$success_message = $_GET['msg'] ?? "";
$email = "";

if (isset($_SESSION['user_email'])) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($email) || empty($password)) {
        $error_message = "All form fields are mandatory.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please provide a valid email format.";
    } elseif (strlen($password) < 8) {
        $error_message = "Security policy requires password to contain at least 8 characters.";
    } else {
        $mock_user_email = "student@university.edu";
        $mock_password_hash = password_hash("secureStudent123", PASSWORD_DEFAULT);

        if ($email === $mock_user_email && password_verify($password, $mock_password_hash)) {
            session_regenerate_id(true);

            $_SESSION["user_email"] = $email;
            $_SESSION["user_role"] = "Developer";
            $_SESSION["login_time"] = time();
            $_SESSION["last_activity"] = time();

            header("Location: dashboard.php");
            exit();
        } else {
            $error_message = "Invalid credential combination. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkillProof - Secure Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans flex items-center justify-center min-h-screen">

    <div class="bg-white p-8 rounded-xl shadow-lg border border-gray-200 w-full max-w-md mx-4">
        <header class="mb-6 text-center">
            <h1 class="text-3xl font-extrabold text-blue-900">SkillProof Portal</h1>
            <p class="text-sm text-gray-500 mt-1">Secure backend login using PHP session.</p>
        </header>

        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 text-sm">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 text-sm">
                <span class="font-bold">Error:</span>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" class="space-y-6">
            <div>
                <label for="email" class="block text-sm font-bold text-gray-700 mb-1">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none"
                    placeholder="e.g., student@university.edu"
                    value="<?php echo htmlspecialchars($email); ?>"
                >
            </div>

            <div>
                <label for="password" class="block text-sm font-bold text-gray-700 mb-1">Security Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none"
                    placeholder="Enter security key"
                >
            </div>

            <button
                type="submit"
                class="w-full bg-blue-900 hover:bg-blue-800 text-white font-bold py-3 rounded-lg transition duration-200"
            >
                Secure Sign-In
            </button>
        </form>

        <div class="mt-6 text-xs text-gray-500 bg-gray-50 p-3 rounded-lg">
            <p><strong>Demo Email:</strong> student@university.edu</p>
            <p><strong>Demo Password:</strong> secureStudent123</p>
        </div>
    </div>

</body>
</html>