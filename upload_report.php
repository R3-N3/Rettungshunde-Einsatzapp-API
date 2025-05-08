<?php
header('Content-Type: application/json');

require __DIR__ . '/vendor/autoload.php';
require "db_config.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendResponse($status, $message) {
    echo json_encode([
        "status" => $status,
        "message" => $message
    ]);
    exit;
}



if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    sendResponse("error", "Ungültige Anfragemethode.");
}

$token = $_POST['token'] ?? '';
$username = $_POST['username'] ?? '';
$date = $_POST['date'] ?? '';
$text = $_POST['text'] ?? '';

if (empty($token)) {
    sendResponse("error", "Token fehlt.");
}

try {



    $pdo = new PDO(DSN, DB_USER, DB_PASS, $options);

    // Token prüfen
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

    if ((int)$row["securitylevel"] < 1) {
        sendResponse("false", "Keine Berechtigung.");
    }

    // Pflichtfelder prüfen
    if (empty($username) || empty($date) || empty($text)) {
        sendResponse("error", "Fehlende Pflichtfelder.");
    }




    // Send Mail
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = EMAIL_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = EMAIL_USERNAME;
    $mail->Password   = EMAIL_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = EMAIL_PORT;
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    // Absender & Empfänger
    $mail->setFrom(EMAIL_NO_REPLY, 'NO REPLY');
    $mail->addAddress(EMAIL_REPORTADRESS);

    // Inhalt
    $mail->isHTML(false); // false = Nur Text, true = HTML
    $mail->Subject = "Einsatzbericht über Rettungshunde Einsatzapp REA";
    $mail->Body    = "Dies ist ein Einsatzbericht, der über die Rettungshunde Einsatz App (REA) gesendet wurden ist,\n\n" .
                                 "\n\n" .
                                 "Benutzername: $username\n\n" .
                                 "Einsatzdatum: $date\n\n" .
                                 "Einsatzbericht: $text\n\n\n" .
                                 "Diese Informationen sind nur für den Empfänger geeignet und dürfen nicht weitergegeben werden.\n\n" .
                                 "Viele Grüße,\n" .
                                 "Dein Rettungshunde Einsatz-App Team";

    $mail->send();

    sendResponse("success", "Einsatzbericht versandt.");

} catch (PHPMailer\PHPMailer\Exception $e) {
    sendResponse("error", "Mailer-Fehler: " . $e->getMessage());
} catch (PDOException $e) {
    sendResponse("error", "Verbindungsfehler: " . $e->getMessage());
}
