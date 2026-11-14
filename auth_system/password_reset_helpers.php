<?php
declare(strict_types=1);

// Load only from password_reset.php. Do not put SMTP credentials in this file.
function sp_reset_config(): ?array {
    $file = __DIR__ . '/password_reset_config.local.php';
    if (!is_file($file)) return null;
    $config = require $file;
    if (!is_array($config)) return null;
    foreach (['smtp_host', 'smtp_user', 'smtp_password', 'smtp_from', 'code_pepper'] as $key) {
        if (!isset($config[$key]) || !is_string($config[$key]) || trim($config[$key]) === '') return null;
    }
    if (!preg_match('/^[a-f0-9]{64}$/iD', $config['code_pepper'])) return null;
    if (!filter_var($config['smtp_from'], FILTER_VALIDATE_EMAIL)) return null;
    if ((int)($config['smtp_port'] ?? 0) !== 587) return null;
    return $config;
}

function sp_reset_digest(string $code, array $config): string {
    return hash_hmac('sha256', $code, hex2bin($config['code_pepper']));
}

function sp_reset_utc_now(): DateTimeImmutable {
    return new DateTimeImmutable('now', new DateTimeZone('UTC'));
}

function sp_reset_datetime(DateTimeImmutable $date): string {
    return $date->format('Y-m-d H:i:s');
}

// Delivery is deliberately SMTP-only. InfinityFree does not reliably support PHP mail().
function sp_reset_send_email(string $to, string $code, array $config): void {
    $autoload = __DIR__ . '/vendor_mailer/autoload.php';
    if (!is_file($autoload)) throw new RuntimeException('PHPMailer is not installed');
    require_once $autoload;

    $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = $config['smtp_host'];
    $mailer->SMTPAuth = true;
    $mailer->Username = $config['smtp_user'];
    $mailer->Password = $config['smtp_password'];
    $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mailer->Port = 587;
    $mailer->Timeout = 15;
    $mailer->CharSet = 'UTF-8';
    $mailer->setFrom($config['smtp_from'], $config['smtp_from_name'] ?? 'SkillProof');
    $mailer->addAddress($to);
    $mailer->isHTML(false);
    $mailer->Subject = 'SkillProof password reset verification code';
    $mailer->Body = "Your SkillProof password reset code is: {$code}\n\nThis code expires in 10 minutes and can be used once. If you didn't request it, ignore this message. Never share this code.";
    $mailer->send();
}

// Account-specific cooldown (60 seconds) and hourly cap (3 emails).
// Always return the same public response, whether or not the email exists.
function sp_reset_request_code(PDO $pdo, string $email, array $config): void {
    $now = sp_reset_utc_now();
    $code = (string)random_int(100000, 999999);
    $digest = sp_reset_digest($code, $config);

    $pdo->beginTransaction();
    try {
        $userStmt = $pdo->prepare('SELECT user_id, email FROM users WHERE email = ? LIMIT 1 FOR UPDATE');
        $userStmt->execute([$email]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            $pdo->commit();
            return;
        }
        $userId = (int)$user['user_id'];

        $get = $pdo->prepare('SELECT * FROM password_reset_codes WHERE user_id = ? FOR UPDATE');
        $get->execute([$userId]);
        $existing = $get->fetch(PDO::FETCH_ASSOC);

        $windowStart = $now;
        $windowCount = 1;
        if ($existing) {
            $lastSent = new DateTimeImmutable($existing['created_at'], new DateTimeZone('UTC'));
            if ($lastSent > $now->modify('-60 seconds')) {
                $pdo->commit();
                return;
            }
            $oldWindow = new DateTimeImmutable($existing['window_started_at'], new DateTimeZone('UTC'));
            if ($oldWindow > $now->modify('-1 hour')) {
                $windowStart = $oldWindow;
                $windowCount = (int)$existing['requests_in_window'] + 1;
                if ($windowCount > 3) {
                    $pdo->commit();
                    return;
                }
            }
        }

        $save = $pdo->prepare(
            'INSERT INTO password_reset_codes
               (user_id, code_digest, expires_at, created_at, attempts, requests_in_window, window_started_at)
             VALUES (?, ?, ?, ?, 0, ?, ?)
             ON DUPLICATE KEY UPDATE
               code_digest = VALUES(code_digest),
               expires_at = VALUES(expires_at),
               created_at = VALUES(created_at),
               attempts = 0,
               requests_in_window = VALUES(requests_in_window),
               window_started_at = VALUES(window_started_at)'
        );
        $save->execute([
            $userId, $digest, sp_reset_datetime($now->modify('+10 minutes')),
            sp_reset_datetime($now), $windowCount, sp_reset_datetime($windowStart)
        ]);
        $pdo->commit();

        try {
            sp_reset_send_email($user['email'], $code, $config);
        } catch (Throwable $e) {
            // Invalidate a code that was never delivered; do not delete newer concurrent codes.
            $delete = $pdo->prepare('DELETE FROM password_reset_codes WHERE user_id = ? AND code_digest = ?');
            $delete->execute([$userId, $digest]);
            error_log('SkillProof reset email delivery failed: ' . get_class($e));
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

// False for any invalid/missing/expired/used code. Never reveals account existence.
function sp_reset_apply_code(PDO $pdo, string $email, string $code, string $newPassword, array $config): bool {
    $now = sp_reset_utc_now();
    $pdo->beginTransaction();
    try {
        $find = $pdo->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1 FOR UPDATE');
        $find->execute([$email]);
        $user = $find->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            $pdo->commit();
            return false;
        }

        $userId = (int)$user['user_id'];
        $get = $pdo->prepare('SELECT * FROM password_reset_codes WHERE user_id = ? FOR UPDATE');
        $get->execute([$userId]);
        $row = $get->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $pdo->commit();
            return false;
        }

        $expiry = new DateTimeImmutable($row['expires_at'], new DateTimeZone('UTC'));
        if ($expiry <= $now || (int)$row['attempts'] >= 5) {
            $pdo->prepare('DELETE FROM password_reset_codes WHERE user_id = ?')->execute([$userId]);
            $pdo->commit();
            return false;
        }

        $valid = hash_equals($row['code_digest'], sp_reset_digest($code, $config));
        if (!$valid) {
            $pdo->prepare('UPDATE password_reset_codes SET attempts = attempts + 1 WHERE user_id = ?')
                ->execute([$userId]);
            $pdo->commit();
            return false;
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        if ($hash === false) throw new RuntimeException('Password hashing failed');
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')->execute([$hash, $userId]);
        $pdo->prepare('DELETE FROM password_reset_codes WHERE user_id = ?')->execute([$userId]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
