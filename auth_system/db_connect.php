<?php
// ============================================================
// SkillProof Database Connection
// Week 5 Lab Task - PDO connection using prepared statements
// ============================================================

// For shared hosting, create db_config.local.php from db_config.example.php.
// The local config is ignored by Git and blocked from public HTTP access.
// Environment variables take precedence when available.
$localConfigFile = __DIR__ . "/db_config.local.php";
$dbConfig = is_file($localConfigFile) ? require $localConfigFile : [];
if (!is_array($dbConfig)) {
    http_response_code(500);
    exit("Invalid database configuration.");
}
define("DB_HOST", getenv("SKILLPROOF_DB_HOST") ?: ($dbConfig["host"] ?? "localhost"));
define("DB_NAME", getenv("SKILLPROOF_DB_NAME") ?: ($dbConfig["name"] ?? "skillproof_db"));
define("DB_USER", getenv("SKILLPROOF_DB_USER") ?: ($dbConfig["user"] ?? "root"));
define("DB_PASS", getenv("SKILLPROOF_DB_PASS") ?: ($dbConfig["password"] ?? ""));

function skillproof_db(): PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        die("Database connection failed. Please verify MySQL is running and schema.sql has been imported.");
    }

    return $pdo;
}