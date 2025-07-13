<?php
header('Content-Type: application/json');

require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . "/db_config.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

register_shutdown_function(function () {
    if (!headers_sent()) {
    sendResponse("error", "Unerearteter Serverfehler ");
    }
});

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

$email = $_POST['email'] ?? '';

if (empty($email)) {
    sendResponse("error", "E-Mail Adresse fehlt.");
}

try {
    $pdo = new PDO(DSN, DB_USER, DB_PASS, $options);

    // Prüfen, ob Nutzer existiert
    $stmt = $pdo->prepare("SELECT ID FROM " . DB_PREFIX . "users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        sendResponse("success", "Wenn die E-Mail registriert ist, wird eine Nachricht gesendet.");
    }



    // Token zum zurücksetzen generieren
    $token = bin2hex(random_bytes(32));
    $expiry = date("Y-m-d H:i:s", strtotime("+1 hour"));


    // Token speichern
    $stmt = $pdo->prepare("UPDATE " . DB_PREFIX . "users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?");
    $stmt->execute([$token, $expiry, $email]);



    // Link erstellen
    
    $link = API_LINK."setnewpassword?token=$token";

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
    $mail->setFrom(EMAIL_NO_REPLY, 'NO REPLY'); // Absender
    $mail->addAddress($email);                  // Empfaenger
    $mail->isHTML(false); // false = Nur Text, true = HTML
    $mail->Subject = "Passwort zurücksetzen";
    $mail->Body    = "Hallo,\n\n" .
                    "für dein Benutzerkonto der Rettungshunde Einsatzapp wurde ein Zurücksetzen des Passwortes angefordert.\n\n" .
                    "Das Passwort kannst du unter folgendem Link zurücksetzen: $link \n\n" .
                    "Fall du das Passwort nicht selbst zurückgesetzt hast, ändere bitte umgehend dein Passwort des Benutzerkontos. Wenn du glaubst Dritte haben Zugriff auf dein Benutzerkonto, wende dich umgehend an einen Administrator deiner Organisation. \n\n" .
                    "Viele Grüße,\n" .
                    "Dein Rettungshunde Einsatzapp Team";
    $mail->send();

    sendResponse("success", "Wenn die E-Mail registriert ist, wird eine Nachricht gesendet.");

} catch (PHPMailer\PHPMailer\Exception $e) {
    sendResponse("error", "Mailer-Fehler: " . $e->getMessage());
} catch (PDOException $e) {
    sendResponse("error", "Verbindungsfehler: " . $e->getMessage());
} catch (Throwable $e) {
    sendResponse("error", "Unbekannter Fehler: " . $e->getMessage());
}