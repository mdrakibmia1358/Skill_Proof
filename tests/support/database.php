<?php
// Only the runner's fresh private socket is accepted. Never include application config.
$socket = getenv("SKILLPROOF_TEST_DB_SOCKET");
if (!$socket || strpos($socket, "/skillproof-integration-") === false || !is_file(dirname($socket) . "/isolated-test-marker")) {
    throw new RuntimeException("Refusing a non-isolated database socket");
}
$pdo = new PDO("mysql:unix_socket=" . $socket . ";charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$request = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
if ($request["operation"] === "initialize") {
    $pdo->exec("CREATE DATABASE skillproof_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE skillproof_test");
    $pdo->exec($request["ddl"]);
    echo "[]";
} else {
    $pdo->exec("USE skillproof_test");
    $stmt = $pdo->prepare($request["sql"]);
    $stmt->execute($request["params"] ?? []);
    echo json_encode($stmt->columnCount() ? $stmt->fetchAll() : [], JSON_THROW_ON_ERROR);
}
