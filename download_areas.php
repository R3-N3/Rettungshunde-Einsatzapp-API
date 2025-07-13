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

    // Areas abrufen
    $areaStmt = $pdo->prepare("
        SELECT id, title, description, color FROM " . DB_PREFIX . "areas
    ");
    $areaStmt->execute();
    $areas = $areaStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($areas as &$area) {
        $coordStmt = $pdo->prepare("
            SELECT latitude as lat, longitude as lon, order_index
            FROM " . DB_PREFIX . "area_coordinates
            WHERE area_id = :area_id
            ORDER BY order_index ASC
        ");
        $coordStmt->bindParam(":area_id", $area['id']);
        $coordStmt->execute();
        $area['points'] = $coordStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    sendResponse("success", "Flächen erfolgreich abgerufen.", $areas);

} catch (PDOException $e) {
    sendResponse("error", "Datenbankfehler: " . $e->getMessage());
}