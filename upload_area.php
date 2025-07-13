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

    // Areas empfangen
    $areas = $data["areas"] ?? [];
    if (!is_array($areas) || count($areas) === 0) {
        sendResponse("error", "Keine Flächen zum Hochladen übergeben.");
    }

    $pdo->beginTransaction();

    $insertAreaStmt = $pdo->prepare("
        INSERT INTO " . DB_PREFIX . "areas (title, description, color, uploaded_to_server)
        VALUES (:title, :description, :color, 1)
    ");

    $insertCoordStmt = $pdo->prepare("
        INSERT INTO " . DB_PREFIX . "area_coordinates (area_id, latitude, longitude, order_index)
        VALUES (:area_id, :latitude, :longitude, :order_index)
    ");

    foreach ($areas as $area) {
        $title = $area["title"] ?? "Unbenannt";
        $description = $area["description"] ?? "";
        $color = $area["color"] ?? "#FF0000";
        $points = $area["points"] ?? [];

        if (!is_array($points) || count($points) === 0) continue;

        // Area einfügen
        $insertAreaStmt->bindParam(":title", $title);
        $insertAreaStmt->bindParam(":description", $description);
        $insertAreaStmt->bindParam(":color", $color);
        $insertAreaStmt->execute();
        $areaId = $pdo->lastInsertId();

        // Punkte einfügen
        foreach ($points as $index => $point) {
            $lat = $point["lat"] ?? null;
            $lon = $point["lon"] ?? null;
            if ($lat === null || $lon === null) continue;

            $insertCoordStmt->bindParam(":area_id", $areaId);
            $insertCoordStmt->bindParam(":latitude", $lat);
            $insertCoordStmt->bindParam(":longitude", $lon);
            $insertCoordStmt->bindParam(":order_index", $index);
            $insertCoordStmt->execute();
        }
    }

    $pdo->commit();
    sendResponse("success", "Flächen erfolgreich hochgeladen.");

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sendResponse("error", "Datenbankfehler: " . $e->getMessage());
}