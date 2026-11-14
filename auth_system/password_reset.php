<?php
declare(strict_types=1);

session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/password_reset_helpers.php';

$error = '';
$message = '';
$stage = 'request';
$email = '';
$token = sp_csrf_token();
$config = sp_reset_config();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedStage = $_POST['stage'] ?? '';
    $stage = in_array($submittedStage, ['request', 'confirm'], true) ? $submittedStage : 'request';
    $emailInput = $_POST['email'] ?? '';
    $email = is_string($emailInput) ? strtolower(trim($emailInput)) : '';

    if (!sp_csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Your form session expired. Refresh this page and try again.';
    } elseif ($config === null) {
        $error = 'Password recovery is temporarily unavailable. Please contact support.';
    } elseif (strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } else {
        try {
            require_once __DIR__ . '/db_connect.php';
            $pdo = skillproof_db();
            if ($stage === 'request') {
                sp_reset_request_code($pdo, $email, $config);
                $message = 'If the address belongs to a SkillProof account, a verification code has been sent. Please check inbox or spam.';
                $stage = 'confirm';
            } else {
                $code = $_POST['code'] ?? '';
                $password = $_POST['password'] ?? '';
                $confirm = $_POST['confirm_password'] ?? '';
                if (!is_string($code) || !preg_match('/^[0-9]{6}$/D', $code)) {
                    $error = 'Enter the six-digit verification code.';
                } elseif (!is_string($password) || strlen($password) < 12 || strlen($password) > 1024) {
                    $error = 'Use a password of at least 12 characters.';
                } elseif (!is_string($confirm) || !hash_equals($password, $confirm)) {
                    $error = 'Passwords do not match.';
                } elseif (sp_reset_apply_code($pdo, $email, $code, $password, $config)) {
                    $_SESSION = [];
                    session_regenerate_id(true);
                    $_SESSION['login_flash'] = 'Your password has been reset. Sign in using your new password.';
                    header('Location: login.php', true, 303);
                    exit();
                } else {
                    $error = 'Invalid, expired, or already used verification code. Request a new code if needed.';
                }
            }
        } catch (Throwable $e) {
            error_log('SkillProof password reset error: ' . get_class($e));
            $error = 'Password recovery is temporarily unavailable. Please try again later.';
        }
    }
}

function sp_reset_escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - SkillProof</title>
    <link rel="stylesheet" href="../assets/css/tailwind.css?v=20261110">
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen flex items-center justify-center p-4">
<main class="w-full max-w-md bg-white shadow-lg border border-slate-200 rounded-2xl p-6 sm:p-8">
    <a href="../home.php" class="text-blue-700 font-bold">SkillProof</a>
    <h1 class="text-2xl font-extrabold mt-4 mb-2">Reset your password</h1>
    <p class="text-sm text-slate-600 mb-6">Receive a one-time verification code at your registered email. Codes expire in ten minutes.</p>
    <?php if ($message !== ''): ?>
        <div class="p-3 bg-green-50 text-green-900 border border-green-200 rounded-lg mb-4" role="status"><?php echo sp_reset_escape($message); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="p-3 bg-red-50 text-red-900 border border-red-200 rounded-lg mb-4" role="alert"><?php echo sp_reset_escape($error); ?></div>
    <?php endif; ?>
    <form action="password_reset.php" method="post" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?php echo sp_reset_escape($token); ?>">
        <input type="hidden" name="stage" value="<?php echo sp_reset_escape($stage); ?>">
        <div>
            <label for="email" class="block text-sm font-bold mb-1">Registered email</label>
            <input id="email" name="email" type="email" autocomplete="email" required maxlength="150"
                   value="<?php echo sp_reset_escape($email); ?>"
                   class="w-full border border-slate-300 rounded-lg px-3 py-3">
        </div>
        <?php if ($stage === 'confirm'): ?>
        <div>
            <label for="code" class="block text-sm font-bold mb-1">Six-digit email code</label>
            <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required
                   class="w-full border border-slate-300 rounded-lg px-3 py-3">
        </div>
        <div>
            <label for="password" class="block text-sm font-bold mb-1">New password (12+ characters)</label>
            <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required
                   class="w-full border border-slate-300 rounded-lg px-3 py-3">
        </div>
        <div>
            <label for="confirm_password" class="block text-sm font-bold mb-1">Confirm new password</label>
            <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" minlength="12" required
                   class="w-full border border-slate-300 rounded-lg px-3 py-3">
        </div>
        <?php endif; ?>
        <button type="submit" class="w-full bg-blue-700 text-white font-bold py-3 rounded-lg hover:bg-blue-800">
            <?php echo $stage === 'request' ? 'Send verification code' : 'Verify and reset password'; ?>
        </button>
    </form>
    <?php if ($stage === 'confirm'): ?>
        <p class="mt-4 text-sm">Didn't receive a code? <a href="password_reset.php" class="text-blue-700 underline">Request another code</a> (one per minute, maximum three per hour).</p>
    <?php endif; ?>
    <a href="login.php" class="block mt-5 text-center text-blue-700 underline text-sm">Back to login</a>
</main>
</body>
</html>
