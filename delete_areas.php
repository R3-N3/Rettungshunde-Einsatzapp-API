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

    // Token prüfen
    $stmt = $pdo->prepare("
        SELECT t.user_id, t.expires_at
        FROM " . DB_PREFIX . "tokens t
        WHERE t.token = :token
    ");
    $stmt->bindParam(":token", $token);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) sendResponse("error", "Token not found.");
    if (strtotime($row['expires_at']) <= time()) sendResponse("error", "Token expired.");

    // ALLE AREAS LÖSCHEN
    $deleteStmt = $pdo->prepare("DELETE FROM " . DB_PREFIX . "uploaded_areas");
    $deleteStmt->execute();

    sendResponse("success", "Alle Flächen wurden vom Server gelöscht.");

} catch (PDOException $e) {
    sendResponse("error", "Database error: " . $e->getMessage());
}