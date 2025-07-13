<?php
// Überschreibe ggf. vorher gesetzten Content-Type
header('Content-Type: text/html; charset=utf-8');
?>
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require "db_config.php";

$token = $_GET['token'] ?? '';
$showForm = false;
$message = '';

// 1. Token prüfen
if (!empty($token)) {
    $pdo = new PDO(DSN, DB_USER, DB_PASS, $options);
    $stmt = $pdo->prepare("SELECT ID, reset_token_expiry FROM " . DB_PREFIX . "users WHERE reset_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if ($user) {
        $expiry = strtotime($user['reset_token_expiry']);
        if ($expiry >= time()) {
            $showForm = true;
        } else {
            $message = "Dieser Link ist abgelaufen.";
        }
    } else {
        $message = "Ungültiger Reset-Link.";
    }
} else {
    $message = "Kein Token übergeben.";
}

// 2. Passwort ändern (wenn Formular abgeschickt)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['new_password']) && isset($_POST['token'])) {
    $newPassword = $_POST['new_password'];
    $token = $_POST['token'];

    if (strlen($newPassword) < 8 ||
    !preg_match('/[A-Z]/', $newPassword) ||
    !preg_match('/[a-z]/', $newPassword) ||
    !preg_match('/[0-9]/', $newPassword) ||
    !preg_match('/[\W_]/', $newPassword)) {
    
        $message = "Passwort muss mindestens 8 Zeichen lang sein und Großbuchstaben, Kleinbuchstaben, Zahl und Sonderzeichen enthalten.";
        $showForm = true;
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $pdo = new PDO(DSN, DB_USER, DB_PASS, $options);
        $stmt = $pdo->prepare("SELECT ID FROM " . DB_PREFIX . "users WHERE reset_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            // Passwort setzen
            $stmt = $pdo->prepare("UPDATE " . DB_PREFIX . "users SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE reset_token = ?");
            $stmt->execute([$hashedPassword, $token]);

            // Tokens löschen
            $stmt = $pdo->prepare("DELETE FROM " . DB_PREFIX . "tokens WHERE user_id = ?");
            $stmt->execute([$user['ID']]);

            $message = "Passwort erfolgreich geändert. Du wurdest von allen Geräten abgemeldet. Du kannst dich jetzt mit deinem neuen Passwort anmelden.";
            $showForm = false;
        } else {
            $message = "Fehler: Benutzer nicht gefunden.";
        }
        $showForm = false;
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Passwort zurücksetzen</title>
</head>
<body>
    <h2>Passwort zurücksetzen</h2>

    <?php if (!empty($message)): ?>
        <p><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <?php if ($showForm): ?>
        <form method="post" action="?route=debug/setnewpassword&token=<?= htmlspecialchars($token) ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            <label for="new_password">Neues Passwort:</label><br>
            <input type="password" name="new_password" id="new_password" required><br><br>
            <button type="submit">Passwort setzen</button>
        </form>
    <?php endif; ?>
</body>
</html>