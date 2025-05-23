
<?php
// Fehlermeldungen deaktivieren (nur in Produktion)
//error_reporting(0);
//ini_set('display_errors', 0);

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Datenbank-Konstanten
define('DB_HOST', $_ENV['DB_HOST']);
define('DB_USER', $_ENV['DB_USER']);
define('DB_PASS', $_ENV['DB_PASS']);
define('DB_NAME', $_ENV['DB_NAME']);
define('DB_PREFIX', $_ENV['DB_PREFIX']);

// E-Mail-Konstanten
define('EMAIL_NO_REPLY', $_ENV['EMAIL_NO_REPLY']);
define('EMAIL_HOST', $_ENV['EMAIL_HOST']);
define('EMAIL_USERNAME', $_ENV['EMAIL_USERNAME']);
define('EMAIL_PASSWORD', $_ENV['EMAIL_PASSWORD']);
define('EMAIL_PORT', $_ENV['EMAIL_PORT']);
define('EMAIL_REPORTADRESS', $_ENV['EMAIL_REPORTADRESS']);

// URL-Konstante
define('API_URL', $_ENV['API_URL']);

// Datenbankverbindungsdaten (Ändere diese Werte!)
define ("DSN", "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4");


$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

?>


