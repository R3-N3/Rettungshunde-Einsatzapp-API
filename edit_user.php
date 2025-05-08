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
$edit_username = $_POST['username'] ?? '';
$edit_email = $_POST['email'] ?? '';
$edit_phoneNumber = $_POST['phoneNumber'] ?? '';
$edit_callSign = $_POST['callSign'] ?? '';
$edit_selectedSecurityLevelSend = $_POST['selectedSecurityLevelSend'] ?? '';
$edit_selectedHex = $_POST['selectedHex'] ?? '';
$edit_userID = (int)($_POST['userID'] ?? 0);

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

    $editUserStmt = $pdo->prepare("
        UPDATE " . DB_PREFIX . "users
        SET
            username = :username,
            email = :email,
            phonenumber = :phone,
            securitylevel = :securitylevel,
            radiocallname = :callname,
            track_color = :color
        WHERE
            ID = :id
    ");

    $editUserStmt->execute([
        ':username' => $edit_username,
        ':email' => $edit_email,
        ':phone' => $edit_phoneNumber,
        ':securitylevel' => $edit_selectedSecurityLevelSend,
        ':callname' => $edit_callSign,
        ':color' => $edit_selectedHex,
        ':id' => $edit_userID
    ]);

    if ($editUserStmt->rowCount() > 0) {
        sendResponse("success", "Benutzerdaten erfolgreich aktualisiert.");
    } else {
        sendResponse("false", "Keine Änderungen vorgenommen.");
    }

} catch (PDOException $e) {
    sendResponse("error", "Verbindungsfehler: " . $e->getMessage());
}