<?php
// Content-Type setzen
header('Content-Type: text/html; charset=utf-8');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require "db_config.php";

$debugInfo = [];

$getToken = $_GET['token'] ?? '';
$token = $getToken;
$showForm = false;
$message = '';
$isSuccess = false;

// 1. Token prüfen (GET)
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

// 2. Passwort ändern (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['new_password']) && isset($_POST['token'])) {
    $newPassword = $_POST['new_password'];
    $token = $_POST['token'] ?? '';
    $debugInfo[] = "DEBUG POST-Token: $token";

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
            $stmt = $pdo->prepare("UPDATE " . DB_PREFIX . "users SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE reset_token = ?");
            $stmt->execute([$hashedPassword, $token]);

            $stmt = $pdo->prepare("DELETE FROM " . DB_PREFIX . "tokens WHERE user_id = ?");
            $stmt->execute([$user['ID']]);

            $message = "Passwort erfolgreich geändert. Du wurdest von allen Geräten abgemeldet. Du kannst dich jetzt mit deinem neuen Passwort anmelden.";
            $showForm = false;
            $isSuccess = true;
        } else {
            $message = "Fehler: Benutzer nicht gefunden.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passwort zurücksetzen</title>
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: sans-serif;
            background: #f2f2f2;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 400px;
        }

        h2 {
            margin-top: 0;
            text-align: center;
            color: #333;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
        }

        input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ccc;
            border-radius: 5px;
            margin-bottom: 1rem;
            font-size: 1rem;
        }

        .show-password {
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        button {
            width: 100%;
            padding: 0.75rem;
            background-color: #007bff;
            border: none;
            border-radius: 5px;
            color: white;
            font-size: 1rem;
            cursor: pointer;
        }

        button:hover {
            background-color: #0056b3;
        }

        p.message {
            text-align: center;
            color: <?= $isSuccess ? '#28a745' : '#d00' ?>;
            font-weight: bold;
        }

        .debug {
            margin-top: 2rem;
            font-size: 0.85rem;
            color: #444;
            background: #f9f9f9;
            padding: 1rem;
            border: 1px dashed #ccc;
        }

        @media (max-width: 400px) {
            .container {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Passwort zurücksetzen</h2>

        <?php if (!empty($message)): ?>
            <p class="message"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <?php if ($showForm): ?>
            <form method="post" action="?token=<?= htmlspecialchars($getToken) ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($getToken) ?>">
                <label for="new_password" aria-label="Neues Passwort eingeben">Neues Passwort:</label>
                <input type="password" name="new_password" id="new_password" required>
                <div class="show-password">
                    <input type="checkbox" id="toggle" onclick="togglePassword()">
                    <label for="toggle">Passwort anzeigen</label>
                </div>
                <button type="submit">Passwort setzen</button>
            </form>
        <?php endif; ?>

        <?php /* Debug optional sichtbar machen
        if (!empty($debugInfo)): ?>
            <div class="debug">
                <strong>Debug-Infos:</strong>
                <pre><?php foreach ($debugInfo as $line) echo htmlspecialchars($line) . "\n"; ?></pre>
            </div>
        <?php endif; */
        ?>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById("new_password");
            input.type = input.type === "password" ? "text" : "password";
        }
    </script>
</body>
</html>