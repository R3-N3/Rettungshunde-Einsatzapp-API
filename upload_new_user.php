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
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$phone = $_POST['phone'] ?? '';
$callSign = $_POST['callSign'] ?? '';
$securityLevel = $_POST['securelevel'] ?? '';
$color = $_POST['color'] ?? '';

if (empty($token)) {
    sendResponse("error", "Token fehlt.");
}

try {
    $pdo = new PDO(DSN, DB_USER, DB_PASS, $options);

    // Token & Admin prüfen
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

    // Pflichtfelder prüfen
    if (empty($username) || empty($email) || empty($password)) {
        sendResponse("error", "Fehlende Pflichtfelder.");
    }

    // Existiert der Benutzer bereits?
    $checkStmt = $pdo->prepare("
        SELECT id FROM " . DB_PREFIX . "users WHERE username = :username OR email = :email
    ");
    $checkStmt->execute([
        ':username' => $username,
        ':email' => $email
    ]);

    if ($checkStmt->fetch()) {
        sendResponse("error", "Benutzername oder E-Mail bereits vergeben.");
    }
    
    // Benutzer anlegen
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $insertStmt = $pdo->prepare("
        INSERT INTO " . DB_PREFIX . "users
        (username, email, password_hash, phonenumber, securitylevel, radiocallname, track_color)
        VALUES (:username, :email, :password_hash, :phone, :securitylevel, :callname, :color)
    ");
    
    $insertStmt->execute([
        ':username' => $username,
        ':email' => $email,
        ':password_hash' => $passwordHash,
        ':phone' => $phone,
        ':securitylevel' => $securityLevel,
        ':callname' => $callSign,
        ':color' => $color
    ]);
    

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
    $mail->Subject = "Zugangsdaten Rettungshunde Einsatzapp";
    $mail->Body    = "Hallo $username,\n\n" .
                    "dein Benutzerkonto für die Rettungshunde Einsatzapp wurde erfolgreich erstellt. Die Anmeldedaten sind wie folgt:\n\n" .
                    "Benutzername: $username\n" .
                    "Passwort: $password\n\n" .
                    "Bitte bewahre diese Informationen sicher auf und ändern Ihr Passwort nach der ersten Anmeldung. Die App können SIe im Google Playstore und Apple App Store herunterladen.\n\n" .
                    "Viele Grüße,\n" .
                    "Dein Rettungshunde Einsatzapp Team";    
    $mail->send();
    
    sendResponse("success", "Benutzer erfolgreich angelegt.");

} catch (PDOException $e) {
    sendResponse("error", "Verbindungsfehler: " . $e->getMessage());
} catch (PHPMailer\PHPMailer\Exception $e) {
    sendResponse("error", "Mailer-Fehler: " . $e->getMessage());
}