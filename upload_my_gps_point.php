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

$latitude = isset($_POST['latitude']) ? (float)$_POST['latitude'] : 0;
$longitude = isset($_POST['longitude']) ? (float)$_POST['longitude'] : 0;
$accuracy = isset($_POST['accuracy']) ? (int)$_POST['accuracy'] : 0;
$timestamp = $_POST['timestamp'] ?? null;
$token = $_POST['token'] ?? '';

if (empty($latitude) || empty($longitude) || empty($token)) {
    sendResponse("error", "Missing parameters.");
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

    $user_id = (int)$row['user_id'];

    if ($timestamp) {
        $insertStmt = $pdo->prepare("
            INSERT INTO " . DB_PREFIX . "locations (user_id, latitude, longitude, accuracy, timestamp)
            VALUES (:user_id, :latitude, :longitude, :accuracy, :timestamp)
        ");
        $insertStmt->bindParam(":timestamp", $timestamp);
    } else {
        $insertStmt = $pdo->prepare("
            INSERT INTO " . DB_PREFIX . "locations (user_id, latitude, longitude, accuracy)
            VALUES (:user_id, :latitude, :longitude, :accuracy)
        ");
    }

$insertStmt->bindParam(":user_id", $user_id);
$insertStmt->bindParam(":latitude", $latitude);
$insertStmt->bindParam(":longitude", $longitude);
$insertStmt->bindParam(":accuracy", $accuracy);
$insertStmt->execute();


/*
    $insertStmt = $pdo->prepare("
        INSERT INTO " . DB_PREFIX . "locations (user_id, latitude, longitude, accuracy)
        VALUES (:user_id, :latitude, :longitude, :accuracy)
    ");
    $insertStmt->bindParam(":user_id", $user_id);
    $insertStmt->bindParam(":latitude", $latitude);
    $insertStmt->bindParam(":longitude", $longitude);
    $insertStmt->bindParam(":accuracy", $accuracy);
    $insertStmt->execute();
*/








    sendResponse("success", "GPS data saved");

} catch (PDOException $e) {
    sendResponse("error", "Database error: " . $e->getMessage());
}