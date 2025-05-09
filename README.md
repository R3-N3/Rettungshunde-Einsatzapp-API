# 📦 Rettungshunde-Einsatzapp-API

Backend-API für die mobile App **Rettungshunde-Einsatzapp** (Android, iOS, Web).  
Dieses Repository enthält die PHP-Skripte zur Datenverarbeitung, Benutzerauthentifizierung, Datenbankkommunikation und E-Mail-Versand.

---

## 🚀 Inhalt

- `api/` – Endpunkte (z. B. `login.php`, `user.php`, `data.php`)
- `config/` – Konfiguration inkl. `.env`, `config.php`

---

## 🛠️ Installation

```bash
git clone https://github.com/R3-N3/Rettungshunde-Einsatzapp-API.git
cd Rettungshunde-Einsatzapp-API
composer install
cp .env.example .env
```

> ⚠️ Hinweis: Das `vendor/`-Verzeichnis ist nicht im Repository enthalten.  
> Es wird durch `composer install` automatisch aus `composer.lock` generiert.

---

## 📋 Voraussetzungen

- PHP ≥ 7.4
- [Composer](https://getcomposer.org/)
- [`vlucas/phpdotenv`](https://github.com/vlucas/phpdotenv) – zum Laden von `.env`-Dateien
- [`PHPMailer`](https://github.com/PHPMailer/PHPMailer) – für SMTP-E-Mail-Versand

Diese Bibliotheken werden automatisch über Composer installiert. Alternativ können die Datein lokal auf dem PC erstellt und via FTP auf den Server geladen werden. zur lokaen installation, z.B. auf dem PC, könne die Fefehle verwnedte werden, sobald composer installiert ist:  

```bash
composer require vlucas/phpdotenv phpmailer/phpmailer
zip -r vendor.zip vendor/

```
---

## 📄 .env-Konfiguration

Die Datei `.env` enthält sensible Zugangsdaten und Konfigurationen.  
Sie darf **niemals** ins Repository eingecheckt werden. Eine Beispiel-Datei `.env.example` ist enthalten.

```dotenv
# Datenbank
DB_HOST=localhost
DB_USER=dbuser
DB_PASS=dbpass
DB_NAME=mydatabase
DB_PREFIX=app_

# E-Mail
EMAIL_NO_REPLY=no-reply@example.com
EMAIL_HOST=smtp.example.com
EMAIL_USERNAME=mailer@example.com
EMAIL_PASSWORD=secret
EMAIL_PORT=587
EMAIL_REPORTADRESS=reports@example.com

# App-URL
URL=https://myapp.example.com/api/
```

---

## 🧪 Beispiel-API-Endpunkte

| Endpoint           | Methode | Beschreibung                  |
|--------------------|---------|-------------------------------|
| `/api/login.php`   | POST    | Login mit E-Mail & Passwort   |
| `/api/user.php`    | GET     | Nutzerinformationen abrufen   |
| `/api/data.php`    | GET     | Einsatzdaten abrufen          |

---

## 🔐 Sicherheit

- Verwendung von `.env` zur Trennung von Konfiguration und Code
- Passwort-Hashing mit `password_hash()`
- Token-basierte Authentifizierung (optional)
- Keine sensiblen Daten im Repository

---

## 👤 Autor

Rene Nettekoven  
[https://rettungshunde-einsatzapp.de](https://rettungshunde-einsatzapp.de)
