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

$input = file_get_contents("php://input");
$data = json_decode($input, true);
$token = $data["token"] ?? '';

if (empty($token)) {
    sendResponse("error", "Token is missing.");
}

try {
    $pdo = new PDO(DSN, DB_USER, DB_PASS, $options);

    // Token validieren
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

    // ➡️ Alle Areas + Koordinaten löschen
    $pdo->beginTransaction();

    $pdo->exec("DELETE FROM " . DB_PREFIX . "area_coordinates");
    $pdo->exec("DELETE FROM " . DB_PREFIX . "areas");

    $pdo->commit();

    sendResponse("success", "Alle Flächen wurden erfolgreich gelöscht.");

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sendResponse("error", "Datenbankfehler: " . $e->getMessage());
}