<?php
// Copy to password_reset_config.local.php on hosting; never commit the local file.
// Use a dedicated sending mailbox; for Gmail use 2-Step Verification + an App Password.
// Generate the pepper once: php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
return [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_user' => 'your-sending-mailbox@gmail.com',
    'smtp_password' => 'GMAIL_APP_PASSWORD_REPLACE_ME',
    'smtp_from' => 'your-sending-mailbox@gmail.com',
    'smtp_from_name' => 'SkillProof',
    'code_pepper' => 'REPLACE_WITH_64_HEX_RANDOM_CHARACTERS',
];
