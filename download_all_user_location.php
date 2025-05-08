<?php
header('Content-Type: application/json');
require "db_config.php";

function sendResponse($status, $message, $data = []) {
    echo json_encode([
        "status" => $status,
        "message" => $message,
        "data" => $data
    ]);
    exit;
}

// Nur POST zulassen
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    sendResponse("error", "Invalid request method.");
}

// Token prüfen
$token = $_POST['token'] ?? '';

if (empty($token)) {
    sendResponse("error", "Token is missing.");
}

try {
    $pdo = new PDO(DSN, DB_USER, DB_PASS, $options);

    // Token validieren und User-Sicherheitslevel abrufen
    $stmt = $pdo->prepare("
        SELECT t.user_id, t.expires_at, u.securitylevel
        FROM " . DB_PREFIX . "tokens t
        JOIN " . DB_PREFIX . "users u ON t.user_id = u.id
        WHERE t.token = :token
    ");
    $stmt->bindParam(":token", $token);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        sendResponse("error", "Token not found.");
    }

    if (strtotime($row['expires_at']) <= time()) {
        sendResponse("error", "Token expired.");
    }

    // Token verlängern
    $newExpiry = date('Y-m-d H:i:s', time() + 15552000);
    $updateStmt = $pdo->prepare("
        UPDATE " . DB_PREFIX . "tokens SET expires_at = :newExpiry WHERE token = :token
    ");
    $updateStmt->bindParam(":newExpiry", $newExpiry);
    $updateStmt->bindParam(":token", $token);
    $updateStmt->execute();

    // GPS-Daten abrufen (alle bis auf eigene GPS Daten ab)
    $locationStmt = $pdo->prepare("
        SELECT id, user_id, latitude, longitude, timestamp, accuracy
        FROM " . DB_PREFIX . "locations
        WHERE user_id != :user_id
        ORDER BY timestamp DESC
    ");
    $locationStmt->bindParam(":user_id", $row['user_id']);
    $locationStmt->execute();
    $locations = $locationStmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse("success", "Locations fetched", $locations);

} catch (PDOException $e) {
    sendResponse("error", "Database error.");
}