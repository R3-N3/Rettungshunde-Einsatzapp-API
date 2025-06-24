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
$edit_email = $_POST['email'] ?? '';
$edit_phoneNumber = $_POST['phoneNumber'] ?? '';

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

    if ((int)$row["securitylevel"] > 3 OR (int)$row["securitylevel"] < 1) {
        sendResponse("false", "Ungültige Berechtigung.");
    }

    $editUserStmt = $pdo->prepare("
        UPDATE " . DB_PREFIX . "users
        SET
            email = :email,
            phonenumber = :phone
        WHERE
            ID = :id
    ");

    $editUserStmt->execute([
        ':email' => $edit_email,
        ':phone' => $edit_phoneNumber,
        ':id' => $row['user_id']
    ]);

    if ($editUserStmt->rowCount() > 0) {
        sendResponse("success", "Benutzerdaten erfolgreich aktualisiert. " . $edit_email . $edit_phoneNumber);
    } else {
        sendResponse("false", "Keine Änderungen vorgenommen.");
    }

} catch (PDOException $e) {
    sendResponse("error", "Verbindungsfehler: " . $e->getMessage());
}