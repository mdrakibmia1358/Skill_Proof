<?php
session_start();

$error_message = "";
$full_name = "";
$email = "";
$github = "";
$role = "Developer";

function safe_output($value) {
    return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

if (isset($_SESSION["user_email"])) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_once __DIR__ . "/db_connect.php";

    $full_name = trim($_POST["fullName"] ?? "");
    $email     = trim(filter_input(INPUT_POST, "email", FILTER_SANITIZE_EMAIL) ?? "");
    $github    = trim($_POST["github"] ?? "");
    $password  = $_POST["password"] ?? "";
    $role      = ($_POST["role"] ?? "Developer") === "Recruiter" ? "Recruiter" : "Developer";

    if (strlen($full_name) < 3) {
        $error_message = "Full name should contain at least 3 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please provide a valid email address.";
    } elseif (strlen($github) < 3) {
        $error_message = "GitHub username should contain at least 3 characters.";
    } elseif (strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } else {
        $pdo = skillproof_db();

        // Duplicate email check - block registering the same email twice
        $checkStmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
        $checkStmt->execute([$email]);

        if ($checkStmt->fetch()) {
            $error_message = "An account with this email already exists. Please login instead.";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $insertStmt = $pdo->prepare(
                "INSERT INTO users (full_name, email, password_hash, role, github_username)
                 VALUES (?, ?, ?, ?, ?)"
            );

            try {
                $insertStmt->execute([$full_name, $email, $password_hash, $role, $github]);

                // Auto-login immediately after successful registration
                session_regenerate_id(true);
                $_SESSION["user_email"]    = $email;
                $_SESSION["user_role"]     = $role;
                $_SESSION["login_time"]    = time();
                $_SESSION["last_activity"] = time();

                header("Location: dashboard.php");
                exit();
            } catch (PDOException $e) {
                // Catches the rare race-condition case where the UNIQUE(email)
                // constraint at the DB level rejects a duplicate we didn't
                // catch above (e.g. two simultaneous submissions).
                if ($e->getCode() === "23000") {
                    $error_message = "An account with this email already exists. Please login instead.";
                } else {
                    $error_message = "Something went wrong while creating your account. Please try again.";
                }
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
    <title>SkillProof - Create Account</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-50 text-slate-800 font-sans">

    <!-- Navigation -->
    <header class="bg-slate-950 text-white sticky top-0 z-50 shadow-lg">
        <nav class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">

            <a href="http://localhost/home.php" class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-2xl bg-blue-600 flex items-center justify-center shadow-lg shadow-blue-900/30">
                    <span class="text-white font-extrabold text-lg">SP</span>
                </div>

                <div>
                    <h1 class="text-2xl font-extrabold tracking-wide leading-none">SkillProof</h1>
                    <p class="text-[11px] text-slate-400 mt-1">Developer Assessment</p>
                </div>
            </a>

            <div class="hidden md:flex items-center gap-8 text-sm">
                <a href="http://localhost/home.php" class="text-slate-300 hover:text-white">Home</a>
                <a href="http://localhost/skills.php" class="text-slate-300 hover:text-white">Skills</a>
                <a href="http://localhost/recruiter.php" class="text-slate-300 hover:text-white">Recruiters</a>
                <a href="register.php" class="text-white font-semibold">Sign Up</a>

                <a
                    href="login.php"
                    class="bg-blue-600 hover:bg-blue-500 text-white px-5 py-2.5 rounded-xl font-bold transition"
                >
                    Login
                </a>
            </div>
        </nav>
    </header>

    <!-- Main Register Section -->
    <main class="min-h-screen bg-gradient-to-br from-slate-100 via-white to-blue-50">

        <section class="max-w-7xl mx-auto px-6 py-16 grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">

            <!-- Left Content -->
            <div>
                <span class="inline-block bg-blue-100 text-blue-700 rounded-full px-4 py-2 text-sm font-semibold mb-6">
                    Developer Verification Portal
                </span>

                <h2 class="text-4xl md:text-5xl font-extrabold text-slate-950 leading-tight">
                    Create your trusted SkillProof profile.
                </h2>

                <p class="mt-5 text-slate-600 leading-8 max-w-xl">
                    Join SkillProof to organize your technical skills, submit real evidence,
                    and build a recruiter-ready developer profile.
                </p>
            </div>

            <!-- Register Card -->
            <div class="bg-white rounded-3xl shadow-2xl border border-slate-200 p-8">
                <div class="text-center mb-7">
                    <h2 class="text-3xl font-extrabold text-blue-950">Create Account</h2>
                    <p class="text-sm text-slate-500 mt-2">
                        Start your SkillProof developer verification profile.
                    </p>
                </div>

                <?php if (!empty($error_message)): ?>
                    <div class="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded-xl mb-5 text-sm">
                        <span class="font-bold">Error:</span>
                        <?php echo safe_output($error_message); ?>
                    </div>
                <?php endif; ?>

                <form id="registerForm" action="register.php" method="POST" class="space-y-5" novalidate>

                    <div>
                        <label for="fullName" class="block text-sm font-bold text-slate-700 mb-2">
                            Full Name
                        </label>
                        <input
                            type="text"
                            id="fullName"
                            name="fullName"
                            required
                            value="<?php echo safe_output($full_name); ?>"
                            class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:outline-none transition"
                            placeholder="Enter your full name"
                        >
                        <p id="nameMessage" class="text-xs text-slate-400 mt-2">
                            Use your real name for a trusted profile.
                        </p>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-bold text-slate-700 mb-2">
                            Email Address
                        </label>
                        <input
                            type="text"
                            inputmode="email"
                            id="email"
                            name="email"
                            required
                            value="<?php echo safe_output($email); ?>"
                            class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:outline-none transition"
                            placeholder="Enter your email address"
                        >
                        <p id="emailMessage" class="text-xs text-slate-400 mt-2">
                            Use a valid email format, for example student@university.edu
                        </p>
                    </div>

                    <div>
                        <label for="github" class="block text-sm font-bold text-slate-700 mb-2">
                            GitHub Username
                        </label>
                        <input
                            type="text"
                            id="github"
                            name="github"
                            required
                            value="<?php echo safe_output($github); ?>"
                            class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:outline-none transition"
                            placeholder="Enter your GitHub username"
                        >
                        <p id="githubMessage" class="text-xs text-slate-400 mt-2">
                            This will be used as skill evidence reference.
                        </p>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-bold text-slate-700 mb-2">
                            Password
                        </label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:outline-none transition"
                            placeholder="Create a password"
                        >

                        <div class="mt-3 flex items-center justify-between">
                            <label class="flex items-center gap-2 text-sm text-slate-600">
                                <input type="checkbox" id="showPassword" class="rounded border-slate-300">
                                Show password
                            </label>
                            <span id="strengthLabel" class="text-xs font-semibold text-slate-400">
                                Strength: -
                            </span>
                        </div>

                        <div class="mt-3">
                            <div class="w-full h-2 bg-slate-200 rounded-full overflow-hidden">
                                <div id="strengthBar" class="h-2 bg-slate-300 rounded-full transition-all duration-300" style="width: 0%;"></div>
                            </div>
                            <p id="strengthText" class="text-xs text-slate-500 mt-2">
                                Use at least 8 characters with uppercase, number, and special character.
                            </p>
                        </div>
                    </div>

                    <div>
                        <label for="role" class="block text-sm font-bold text-slate-700 mb-2">
                            Account Role
                        </label>
                        <select
                            id="role"
                            name="role"
                            class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:outline-none transition bg-white"
                        >
                            <option value="Developer" <?php echo $role === "Developer" ? "selected" : ""; ?>>Developer</option>
                            <option value="Recruiter" <?php echo $role === "Recruiter" ? "selected" : ""; ?>>Recruiter</option>
                        </select>
                    </div>

                    <button
                        type="submit"
                        class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-xl transition shadow-lg shadow-blue-900/20"
                    >
                        Create Account
                    </button>
                </form>

                <p class="text-center text-sm text-slate-500 mt-6">
                    Already have an account?
                    <a href="login.php" class="text-blue-700 font-bold hover:underline">
                        Login
                    </a>
                </p>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="bg-slate-950 text-slate-400 py-8">
        <div class="max-w-7xl mx-auto px-6 flex flex-col md:flex-row justify-between gap-4 text-sm">
            <p>© 2026 SkillProof. Evidence-Based Developer Assessment Platform.</p>
        </div>
    </footer>

    <script>
        const registerForm = document.getElementById("registerForm");

        const fullName = document.getElementById("fullName");
        const nameMessage = document.getElementById("nameMessage");

        const email = document.getElementById("email");
        const emailMessage = document.getElementById("emailMessage");

        const github = document.getElementById("github");
        const githubMessage = document.getElementById("githubMessage");

        const password = document.getElementById("password");
        const showPassword = document.getElementById("showPassword");
        const strengthBar = document.getElementById("strengthBar");
        const strengthText = document.getElementById("strengthText");
        const strengthLabel = document.getElementById("strengthLabel");

        function validEmail(value) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        }

        function setInputState(input, message, valid, validText, invalidText) {
            if (input.value.trim().length === 0) {
                input.className = "w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:outline-none transition";
                message.className = "text-xs text-slate-400 mt-2";
                message.textContent = invalidText;
                return false;
            }

            if (valid) {
                input.className = "w-full px-4 py-3 border border-green-400 bg-green-50 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 focus:outline-none transition";
                message.className = "text-xs text-green-600 mt-2 font-semibold";
                message.textContent = validText;
                return true;
            }

            input.className = "w-full px-4 py-3 border border-red-400 bg-red-50 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 focus:outline-none transition";
            message.className = "text-xs text-red-600 mt-2 font-semibold";
            message.textContent = invalidText;
            return false;
        }

        fullName.addEventListener("input", function () {
            setInputState(fullName, nameMessage, fullName.value.trim().length >= 3,
                "Name looks good.", "Full name should contain at least 3 characters.");
        });

        email.addEventListener("input", function () {
            setInputState(email, emailMessage, validEmail(email.value.trim()),
                "Valid email format.", "Use a valid email format, for example student@university.edu");
        });

        github.addEventListener("input", function () {
            setInputState(github, githubMessage, github.value.trim().length >= 3,
                "GitHub username looks good.", "GitHub username should contain at least 3 characters.");
        });

        showPassword.addEventListener("change", function () {
            password.type = this.checked ? "text" : "password";
        });

        password.addEventListener("input", function () {
            const value = password.value;
            let score = 0;

            if (value.length >= 8) score++;
            if (/[A-Z]/.test(value)) score++;
            if (/[0-9]/.test(value)) score++;
            if (/[^A-Za-z0-9]/.test(value)) score++;

            if (value.length === 0) {
                strengthBar.style.width = "0%";
                strengthBar.className = "h-2 bg-slate-300 rounded-full transition-all duration-300";
                strengthText.textContent = "Use at least 8 characters with uppercase, number, and special character.";
                strengthLabel.textContent = "Strength: -";
                strengthLabel.className = "text-xs font-semibold text-slate-400";
            } else if (score <= 1) {
                strengthBar.style.width = "30%";
                strengthBar.className = "h-2 bg-red-500 rounded-full transition-all duration-300";
                strengthText.textContent = "Weak password. Add more characters, number, uppercase, or special symbol.";
                strengthLabel.textContent = "Strength: Weak";
                strengthLabel.className = "text-xs font-semibold text-red-600";
            } else if (score <= 3) {
                strengthBar.style.width = "65%";
                strengthBar.className = "h-2 bg-yellow-500 rounded-full transition-all duration-300";
                strengthText.textContent = "Medium password. Add all recommended rules for stronger security.";
                strengthLabel.textContent = "Strength: Medium";
                strengthLabel.className = "text-xs font-semibold text-yellow-600";
            } else {
                strengthBar.style.width = "100%";
                strengthBar.className = "h-2 bg-green-500 rounded-full transition-all duration-300";
                strengthText.textContent = "Strong password.";
                strengthLabel.textContent = "Strength: Strong";
                strengthLabel.className = "text-xs font-semibold text-green-600";
            }
        });

        // Only block submission when client-side checks fail.
        // Otherwise let the form really POST to register.php.
        registerForm.addEventListener("submit", function (event) {
            const nameOk = fullName.value.trim().length >= 3;
            const emailOk = validEmail(email.value.trim());
            const githubOk = github.value.trim().length >= 3;
            const passwordOk = password.value.length >= 8;

            setInputState(fullName, nameMessage, nameOk, "Name looks good.", "Full name should contain at least 3 characters.");
            setInputState(email, emailMessage, emailOk, "Valid email format.", "Use a valid email format, for example student@university.edu");
            setInputState(github, githubMessage, githubOk, "GitHub username looks good.", "GitHub username should contain at least 3 characters.");

            if (!(nameOk && emailOk && githubOk && passwordOk)) {
                event.preventDefault();
            }
            // if all valid: do nothing -> browser submits the form normally to register.php
        });
    </script>

</body>
</html>