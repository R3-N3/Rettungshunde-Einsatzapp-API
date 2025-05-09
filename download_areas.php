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

    $stmt = $pdo->prepare("
        SELECT t.user_id, t.expires_at
        FROM " . DB_PREFIX . "tokens t
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

    // Flächen abrufen (nach name + gruppierten Punkten)
    $stmt = $pdo->prepare("
        SELECT name, color, timestamp, latitude, longitude
        FROM " . DB_PREFIX . "uploaded_areas
        ORDER BY name, timestamp
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Zu Flächen gruppieren
    $areas = [];
    foreach ($rows as $row) {
        $key = $row['name'] . '|' . $row['timestamp'] . '|' . $row['color'];
        if (!isset($areas[$key])) {
            $areas[$key] = [
                'name' => $row['name'],
                'color' => $row['color'],
                'timestamp' => (int)$row['timestamp'],
                'points' => []
            ];
        }
        $areas[$key]['points'][] = [
            'lat' => (float)$row['latitude'],
            'lon' => (float)$row['longitude']
        ];
    }

    sendResponse("success", "Areas loaded successful", array_values($areas));

} catch (PDOException $e) {
    sendResponse("error", "Database error: " . $e->getMessage());
}