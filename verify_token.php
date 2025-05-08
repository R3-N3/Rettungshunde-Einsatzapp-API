<?php
header('Content-Type: application/json');
require "db_config.php";

function sendResponse($status, $message) {
    echo json_encode([
        "status" => $status,
        "message" => $message
    ]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    sendResponse("error", "Invalid request method.");
}

$token = $_POST['token'] ?? '';

if (empty($token)) {
    sendResponse("error", "Token is missing.");
}

try {
    $pdo = new PDO(DSN, DB_USER, DB_PASS, $options);

    $stmt = $pdo->prepare("
        SELECT user_id, expires_at
        FROM " . DB_PREFIX . "tokens
        WHERE token = :token
    ");
    $stmt->bindParam(":token", $token);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        sendResponse("false", "No token found.");
    }

    if (strtotime($row['expires_at']) <= time()) {
        sendResponse("false", "Token expired.");
    }

    // Token verlängern
    $newExpiry = date('Y-m-d H:i:s', time() + 15552000); // 180 Tage
    $updateStmt = $pdo->prepare("
        UPDATE " . DB_PREFIX . "tokens
        SET expires_at = :newExpiry
        WHERE token = :token
    ");
    $updateStmt->bindParam(":newExpiry", $newExpiry);
    $updateStmt->bindParam(":token", $token);
    $updateStmt->execute();

    sendResponse("success", "Token exists and is valid.");

} catch (PDOException $e) {
    sendResponse("error", "Connection error.");
}