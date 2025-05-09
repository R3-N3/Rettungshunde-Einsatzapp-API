<?php
header('Content-Type: application/json');
require "db_config.php";

function sendResponse($status, $message, $token = "") {
    echo json_encode([
        "status" => $status,
        "message" => $message,
        "token" => $token
    ]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    sendResponse("error", "Invalid request method.");
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    sendResponse("error", "Username or password missing.");
}

try {
    $pdo = new PDO(DSN, DB_USER, DB_PASS, $options);

    $stmt = $pdo->prepare("
        SELECT password_hash, ID, email
        FROM " . DB_PREFIX . "users
        WHERE username = :username
    ");
    $stmt->bindParam(":username", $username);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || !password_verify($password, $row["password_hash"])) {
        sendResponse("false", "Wrong user parameter.");
    }

    // Token generieren und speichern
    $token = bin2hex(random_bytes(32));
    $user_id = (int)$row["ID"];
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 day'));

    $stmt = $pdo->prepare("
        INSERT INTO " . DB_PREFIX . "tokens (user_id, token, expires_at)
        VALUES (:user_id, :token, :expires_at)
    ");
    $stmt->bindParam(":user_id", $user_id);
    $stmt->bindParam(":token", $token);
    $stmt->bindParam(":expires_at", $expires_at);
    $stmt->execute();

    sendResponse("success", "User parameter true.", $token);

} catch (PDOException $e) {
    sendResponse("error", "Connection error.");
}