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
    sendResponse("error", "Ungültige Anfragemethode.");
}

$token = $_POST['token'] ?? '';

if (empty($token)) {
    sendResponse("error", "Token fehlt.");
}

try {
    $pdo = new PDO(DSN, DB_USER, DB_PASS, $options);

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
        sendResponse("false", "Token nicht gefunden.");
    }

    if (strtotime($row["expires_at"]) <= time()) {
        sendResponse("false", "Token abgelaufen.");
    }

    if ((int)$row["securitylevel"] < 2) {
        sendResponse("false", "Keine Berechtigung.");
    }

    // Alle GPS-Daten löschen
    $deleteAllGPSData = $pdo->prepare("TRUNCATE TABLE " . DB_PREFIX . "locations");
    $deleteAllGPSData->execute();

    sendResponse("success", "Alle GPS-Daten wurden erfolgreich gelöscht.");

} catch (PDOException $e) {
    sendResponse("error", "Verbindungsfehler: " . $e->getMessage());
}