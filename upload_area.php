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

// JSON-Daten parsen
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

    // Token verlängern (180 Tage)
    $newExpiry = date('Y-m-d H:i:s', time() + 15552000);
    $updateStmt = $pdo->prepare("
        UPDATE " . DB_PREFIX . "tokens SET expires_at = :newExpiry WHERE token = :token
    ");
    $updateStmt->bindParam(":newExpiry", $newExpiry);
    $updateStmt->bindParam(":token", $token);
    $updateStmt->execute();

    // Bereiche empfangen
    $areas = $data["areas"] ?? [];

    if (!is_array($areas) || count($areas) === 0) {
        sendResponse("error", "Keine Flächen zum Hochladen übergeben.");
    }

    // Tabelle: uploaded_areas muss existieren!
    // Beispiel-Schema:
    // id INT AUTO_INCREMENT PRIMARY KEY
    // name VARCHAR(255)
    // latitude DOUBLE
    // longitude DOUBLE
    // color VARCHAR(16)
    // timestamp BIGINT

    $stmt = $pdo->prepare("
        INSERT INTO " . DB_PREFIX . "uploaded_areas (name, latitude, longitude, color, timestamp)
        VALUES (:name, :latitude, :longitude, :color, :timestamp)
    ");

    $countInserted = 0;

    foreach ($areas as $area) {
        $name = $area["name"] ?? "Unbenannt";
        $color = $area["color"] ?? "#FF0000";
        $timestamp = $area["timestamp"] ?? time();
        $points = $area["points"] ?? [];

        if (!is_array($points) || count($points) === 0) continue;

        foreach ($points as $point) {
            $lat = $point["lat"] ?? null;
            $lon = $point["lon"] ?? null;

            if ($lat === null || $lon === null) continue;

            $stmt->bindParam(":name", $name);
            $stmt->bindParam(":latitude", $lat);
            $stmt->bindParam(":longitude", $lon);
            $stmt->bindParam(":color", $color);
            $stmt->bindParam(":timestamp", $timestamp);

            if ($stmt->execute()) {
                $countInserted++;
            }
        }
    }

    sendResponse("success", "$countInserted Punkte erfolgreich gespeichert.");

} catch (PDOException $e) {
    sendResponse("error", "Datenbankfehler: " . $e->getMessage());
}