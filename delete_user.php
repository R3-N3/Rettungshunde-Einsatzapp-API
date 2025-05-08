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
$delete_username = $_POST['username'] ?? '';

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

    if ((int)$row["securitylevel"] !== 3) {
        sendResponse("false", "Keine Berechtigung.");
    }

    // Benutzer löschen
    $deleteUserStmt = $pdo->prepare("
        DELETE FROM " . DB_PREFIX . "users WHERE username = :username
    ");
    $deleteUserStmt->bindParam(":username", $delete_username);
    $deleteUserStmt->execute();

    if ($deleteUserStmt->rowCount() > 0) {
        sendResponse("success", "Benutzer '$delete_username' wurde gelöscht.");
    } else {
        sendResponse("false", "Benutzer '$delete_username' konnte nicht gefunden oder gelöscht werden.");
    }

} catch (PDOException $e) {
    sendResponse("error", "Verbindungsfehler: " . $e->getMessage());
}