<?php
session_start();

$error_message = "";
$success_message = $_GET["msg"] ?? "";
$email = "";

function safe_output($value) {
    return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

if (isset($_SESSION["user_email"])) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim(filter_input(INPUT_POST, "email", FILTER_SANITIZE_EMAIL) ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($email) || empty($password)) {
        $error_message = "All form fields are mandatory.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please provide a valid email format.";
    } elseif (strlen($password) < 8) {
        $error_message = "Security policy requires password to contain at least 8 characters.";
    } else {
        require_once __DIR__ . "/db_connect.php";
        $pdo = skillproof_db();

        $stmt = $pdo->prepare("SELECT user_id, full_name, email, password_hash, role FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user["password_hash"])) {
            session_regenerate_id(true);
            $_SESSION["user_email"]    = $user["email"];
            $_SESSION["user_role"]     = $user["role"];
            $_SESSION["login_time"]    = time();
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

<body class="min-h-screen bg-slate-100 font-sans text-slate-800">

    <!-- Navigation -->
    <header class="bg-slate-950 text-white sticky top-0 z-50 shadow-lg">
        <nav class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">

            <!-- Logo -->
            <a href="http://localhost/home.php" class="flex items-center gap-3">
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
            <div class="hidden md:flex items-center gap-8 text-sm">
                <a href="http://localhost/home.php" class="text-slate-300 hover:text-white">Home</a>
                <a href="http://localhost/skills.php" class="text-slate-300 hover:text-white">Skills</a>
                <a href="http://localhost/recruiter.php" class="text-slate-300 hover:text-white">Recruiters</a>
                <a href="register.php" class="text-slate-300 hover:text-white">Sign Up</a>
                <a href="login.php" class="bg-blue-600 hover:bg-blue-500 text-white px-5 py-2.5 rounded-xl font-bold transition">
                    Login
                </a>
            </div>

            <!-- Mobile Menu Button -->
            <button id="menuButton" class="md:hidden bg-slate-800 px-3 py-2 rounded-lg text-sm">
                Menu
            </button>
        </nav>

        <!-- Mobile Menu -->
        <div id="mobileMenu" class="hidden md:hidden border-t border-slate-800 px-6 pb-4 space-y-2 text-sm">
            <a href="http://localhost/home.php" class="block py-2 text-slate-300">Home</a>
            <a href="http://localhost/skills.php" class="block py-2 text-slate-300">Skills</a>
            <a href="http://localhost/recruiter.php" class="block py-2 text-slate-300">Recruiters</a>
            <a href="register.php" class="block py-2 text-slate-300">Sign Up</a>
            <a href="login.php" class="block py-2 text-blue-300 font-bold">Login</a>
        </div>
    </header>

    <div class="min-h-screen grid grid-cols-1 lg:grid-cols-2">

        <!-- Left Branding Panel -->
        <section class="hidden lg:flex bg-gradient-to-br from-slate-950 via-blue-950 to-slate-900 text-white p-12 flex-col justify-between">
            <div>
                <h1 class="text-5xl font-extrabold tracking-wide">SkillProof</h1>
                <p class="mt-4 text-blue-100 text-lg">
                    Evidence-Based Developer Assessment Platform
                </p>
            </div>

            <div class="max-w-xl">
                <span class="inline-block bg-white/10 border border-white/10 rounded-full px-4 py-2 text-sm text-blue-100 mb-6">
                    Developer Verification Portal
                </span>

                <h2 class="text-4xl font-extrabold leading-tight">
                    Verify skills with real evidence, not only CV claims.
                </h2>

                <p class="mt-6 text-slate-300 leading-7">
                    Developers can submit skill evidence, verification records, and GitHub-based proof.
                    Recruiters can review trusted skill profiles before making hiring decisions.
                </p>

                <div class="grid grid-cols-3 gap-4 mt-10">
                    <div class="bg-white/10 border border-white/10 rounded-2xl p-5">
                        <p class="text-3xl font-extrabold">1.2K+</p>
                        <p class="text-xs text-slate-300 mt-1">Developers</p>
                    </div>

                    <div class="bg-white/10 border border-white/10 rounded-2xl p-5">
                        <p class="text-3xl font-extrabold">342</p>
                        <p class="text-xs text-slate-300 mt-1">Verified Skills</p>
                    </div>

                    <div class="bg-white/10 border border-white/10 rounded-2xl p-5">
                        <p class="text-3xl font-extrabold">27</p>
                        <p class="text-xs text-slate-300 mt-1">Recruiters</p>
                    </div>
                </div>
            </div>

            <p class="text-xs text-slate-400">
                Developer access • Skill evidence • Recruiter trust
            </p>
        </section>

        <!-- Right Login Panel -->
        <section class="flex items-center justify-center px-5 py-10">
            <div class="w-full max-w-md">

                <div class="text-center lg:hidden mb-8">
                    <h1 class="text-4xl font-extrabold text-blue-950">SkillProof</h1>
                    <p class="text-sm text-slate-500 mt-2">
                        Evidence-Based Developer Assessment Platform
                    </p>
                </div>

                <div class="bg-white rounded-3xl shadow-2xl border border-slate-200 p-8">
                    <div class="text-center mb-7">
                        <h2 class="text-3xl font-extrabold text-blue-950">Welcome Back</h2>
                        <p class="text-sm text-slate-500 mt-2">
                            Sign in to access your dashboard.
                        </p>
                    </div>

                    <?php if (!empty($success_message)): ?>
                        <div class="bg-green-50 border border-green-300 text-green-700 px-4 py-3 rounded-xl mb-5 text-sm">
                            <?php echo safe_output($success_message); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error_message)): ?>
                        <div class="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded-xl mb-5 text-sm">
                            <span class="font-bold">Error:</span>
                            <?php echo safe_output($error_message); ?>
                        </div>
                    <?php endif; ?>

                    <form id="loginForm" action="login.php" method="POST" class="space-y-5" novalidate>
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
                                autocomplete="email"
                                class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:outline-none transition"
                                placeholder="Enter your email address"
                                value="<?php echo safe_output($email); ?>"
                            >

                            <p id="emailMessage" class="text-xs text-slate-400 mt-2">
                                Use a valid email format, for example student@university.edu
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
                                autocomplete="current-password"
                                class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:outline-none transition"
                                placeholder="Enter your password"
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
                                    <div
                                        id="strengthBar"
                                        class="h-2 bg-slate-300 rounded-full transition-all duration-300"
                                        style="width: 0%;"
                                    ></div>
                                </div>

                                <p id="strengthText" class="text-xs text-slate-500 mt-2">
                                    Use at least 8 characters with uppercase, number, and special character.
                                </p>
                            </div>
                        </div>

                        <button
                            type="submit"
                            class="w-full bg-blue-900 hover:bg-blue-800 text-white font-bold py-3 rounded-xl transition duration-200 shadow-lg shadow-blue-900/20"
                        >
                            Login
                        </button>
                    </form>

                    <div class="mt-6 grid grid-cols-2 gap-3 text-xs">
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                            <p class="font-bold text-slate-700">Developer Access</p>
                            <p class="text-slate-500 mt-1">Verified portal entry</p>
                        </div>

                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                            <p class="font-bold text-slate-700">Skill Evidence</p>
                            <p class="text-slate-500 mt-1">Proof-based profile</p>
                        </div>

                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                            <p class="font-bold text-slate-700">Recruiter Ready</p>
                            <p class="text-slate-500 mt-1">Trusted skill view</p>
                        </div>

                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                            <p class="font-bold text-slate-700">Session Control</p>
                            <p class="text-slate-500 mt-1">Protected workspace</p>
                        </div>
                    </div>
                </div>

                <p class="text-center text-xs text-slate-400 mt-5">
                    SkillProof Developer Verification Portal
                </p>
            </div>
        </section>
    </div>

    <script>
        const menuButton = document.getElementById("menuButton");
        const mobileMenu = document.getElementById("mobileMenu");

        menuButton.addEventListener("click", function () {
            mobileMenu.classList.toggle("hidden");
        });

        const loginForm = document.getElementById("loginForm");
        const emailInput = document.getElementById("email");
        const emailMessage = document.getElementById("emailMessage");

        const passwordInput = document.getElementById("password");
        const showPassword = document.getElementById("showPassword");
        const strengthBar = document.getElementById("strengthBar");
        const strengthText = document.getElementById("strengthText");
        const strengthLabel = document.getElementById("strengthLabel");

        function isValidEmail(email) {
          return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        function setEmailState() {
            const email = emailInput.value.trim();

            if (email.length === 0) {
                emailInput.className = "w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:outline-none transition";
                emailMessage.textContent = "Use a valid email format, for example student@university.edu";
                emailMessage.className = "text-xs text-slate-400 mt-2";
                return false;
            }

            if (!isValidEmail(email)) {
                emailInput.className = "w-full px-4 py-3 border border-red-400 bg-red-50 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 focus:outline-none transition";
                emailMessage.textContent = "Invalid email format. Please include @ and a domain name.";
                emailMessage.className = "text-xs text-red-600 mt-2 font-semibold";
                return false;
            }

            emailInput.className = "w-full px-4 py-3 border border-green-400 bg-green-50 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 focus:outline-none transition";
            emailMessage.textContent = "Valid email format.";
            emailMessage.className = "text-xs text-green-600 mt-2 font-semibold";
            return true;
        }

        emailInput.addEventListener("input", setEmailState);
        emailInput.addEventListener("blur", setEmailState);

        loginForm.addEventListener("submit", function (event) {
            const validEmail = setEmailState();

            if (!validEmail) {
                event.preventDefault();
                emailInput.focus();
            }
        });

        showPassword.addEventListener("change", function () {
            passwordInput.type = this.checked ? "text" : "password";
        });

        passwordInput.addEventListener("input", function () {
            const password = passwordInput.value;
            let score = 0;

            if (password.length >= 8) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;

            if (password.length === 0) {
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
    </script>

</body>
</html>