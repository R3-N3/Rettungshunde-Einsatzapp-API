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

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    sendResponse("error", "Invalid request method.");
}

$token = $_POST['token'] ?? '';

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

    $securitylevel = (int)$row['securitylevel'];

    // Je nach Sicherheitslevel andere Felder abrufen
    if ($securitylevel === 1) {
        // EK – eingeschränkter Zugriff
        $getUserDataStmt = $pdo->prepare("
            SELECT ID, username, phonenumber, radiocallname FROM " . DB_PREFIX . "users
        ");
    } elseif ($securitylevel === 2 || $securitylevel === 3) {
        // ZF oder GF – volle Daten
        $getUserDataStmt = $pdo->prepare("
            SELECT ID, username, email, phonenumber, securitylevel, radiocallname, track_color FROM " . DB_PREFIX . "users
        ");
    } else {
        sendResponse("error", "Unknown security level.");
    }

    $getUserDataStmt->execute();
    $users = $getUserDataStmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse("success", "User data fetched successfully", $users);

} catch (PDOException $e) {
    sendResponse("error", "Database connection error.");
}

?>