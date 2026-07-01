<?php
include 'inc/config.php';
if (!function_exists('getDB')) {
    require_once 'inc/dbconnect.inc.php';
}
require_once 'inc/remember_me.inc.php';
// reCAPTCHA-Keys aus zentraler Konfiguration (msvjm_config.php via inc/config.php)
$recaptcha_site_key   = $config['recaptcha']['site_key'] ?? '';
$recaptcha_secret_key = $config['recaptcha']['secret_key'] ?? '';

// Zentrale Session-Konfiguration (inkl. Cross-Subdomain Cookie-Domain)
require_once __DIR__ . '/inc/session_config.inc.php';

// Bei Logout-Parameter alte Session komplett erneuern
if (isset($_GET['logout']) || isset($_GET['timeout'])) {
    session_regenerate_id(true);
    $_SESSION = array(); // Session-Daten löschen
}

// Hilfsfunktionen fuer Login mit Rollen/Status
function checkUserStatus($status) {
    switch ($status) {
        case 'pending':  return "Dein Account wird noch geprüft. Du wirst benachrichtigt sobald er freigeschaltet wurde.";
        case 'rejected': return "Deine Registrierung wurde abgelehnt.";
        case 'disabled': return "Dein Account wurde deaktiviert. Bitte kontaktiere den Administrator.";
        case 'approved': return true;
        default:         return true; // Bestehende Admins ohne Status
    }
}

function setLoginSession($id, $username, $full_name, $role, $status, $mitglied_id, $jungschuetze_id = null) {
    $_SESSION['user_id'] = $id;
    $_SESSION['username'] = $username;
    $_SESSION['user_name'] = $full_name;
    $_SESSION['user_role'] = $role ?? 'admin';
    $_SESSION['user_status'] = $status ?: 'approved'; // '' und NULL → 'approved' (Legacy-Admins)
    $_SESSION['mitglied_id'] = $mitglied_id;
    $_SESSION['jungschuetze_id'] = $jungschuetze_id;
    $_SESSION['last_activity'] = time();
    $_SESSION['regenerated'] = time();
}

function getLoginRedirect($role) {
    $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    $host = $_SERVER['HTTP_HOST'] ?? '';
    // Admin-Domains: admin., jm., jahresmeisterschaft. → Admin-Bereich (nur für Admins/Vorstand)
    $isAdminDomain = (bool)preg_match('/^(admin|jm|jahresmeisterschaft)\./i', $host);
    if ($role == 'admin' && $isAdminDomain) return $basePath . '/index.php';
    // Jungschuetzen haben ein eigenes, eingeschraenktes Portal-Dashboard
    if ($role == 'jungschuetze') return $basePath . '/portal/jsk_dashboard.php';
    return $basePath . '/portal/dashboard.php';
}

// Auto-Redirect: Session noch gültig oder Remember-Cookie vorhanden → kein Login nötig.
// Wichtig: localStorage['msv_rt'] wird dadurch NICHT gelöscht (passiert nur bei echtem Login-Form).
if (!isset($_GET['logout']) && !isset($_GET['timeout'])) {
    if (isset($_SESSION['user_id'])) {
        header('Location: ' . getLoginRedirect($_SESSION['user_role'] ?? 'admin'));
        exit;
    }
    if (restoreSessionFromToken()) {
        header('Location: ' . getLoginRedirect($_SESSION['user_role'] ?? 'mitglied'));
        exit;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username_input = htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8');
    $password = $_POST['password'];
    // reCAPTCHA v3 Überprüfung (optional, nicht blockierend)
    $recaptcha_secret = $recaptcha_secret_key;
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
    $recaptcha_valid = true; // Standard: gültig
    if (!empty($recaptcha_response) && !empty($recaptcha_secret)) {
        $response = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=$recaptcha_secret&response=$recaptcha_response");
        $responseKeys = json_decode($response, true);
        if (intval($responseKeys["success"]) !== 1 || ($responseKeys["score"] ?? 1) < 0.3) {
            // Nur loggen, nicht blockieren - Passwort-Auth ist die eigentliche Sicherheit
            error_log("reCAPTCHA fehlgeschlagen: " . json_encode($responseKeys));
        }
    }
    if ($recaptcha_valid) {
        if ($conn->connect_error) {
            error_log("DB connection failed: " . $conn->connect_error);
            die("Datenbankverbindung fehlgeschlagen. Bitte später erneut versuchen.");
        }

        // Prepared Statement verwenden (erweitert fuer Mitgliederportal)
        $stmt = $conn->prepare("SELECT id, username, password_hash, full_name, role, status, mitglied_id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username_input);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->bind_result($id, $username_db, $password_hash, $full_name, $user_role, $user_status, $mitglied_id);
            $stmt->fetch();

            // jungschuetze_id defensiv nachladen (Spalte existiert erst ab Migration 028).
            // Nur fuer Rolle jungschuetze noetig; try/catch verhindert Login-Lockout,
            // falls die Migration noch nicht eingespielt wurde.
            $jungschuetze_id = null;
            if (($user_role ?? '') === 'jungschuetze') {
                try {
                    $jsStmt = $conn->prepare("SELECT jungschuetze_id FROM users WHERE id = ?");
                    $jsStmt->bind_param("i", $id);
                    $jsStmt->execute();
                    $jsStmt->bind_result($jungschuetze_id);
                    $jsStmt->fetch();
                    $jsStmt->close();
                } catch (\Throwable $e) {
                    $jungschuetze_id = null;
                }
            }

            // Prüfen, ob der Passwort-Hash ein MD5-Hash ist (32 Zeichen)
            if (strlen($password_hash) == 32) {
                if (md5($password) == $password_hash) {

                    // Passwort neu hashen und Datenbank aktualisieren
                    $new_hash = password_hash($password, PASSWORD_DEFAULT);
                    $update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $update_stmt->bind_param("si", $new_hash, $id);
                    $update_stmt->execute();
                    $update_stmt->close();

                    // Status pruefen (Mitgliederportal)
                    $status_check = checkUserStatus($user_status ?? 'approved');
                    if ($status_check !== true) {
                        $error = $status_check;
                    } else {
                        // Erfolgreiche Anmeldung
                        session_regenerate_id(true);
                        setLoginSession($id, $username_db, $full_name, $user_role, $user_status, $mitglied_id, $jungschuetze_id);
                        setRememberToken($id); // iOS PWA: persistenter Token

                        header("Location: " . getLoginRedirect($user_role ?? 'admin'));
                        exit();
                    }
                } else {
                    $error = "Ungültige Anmeldedaten";
                }
            } else {

                // Verwendung von password_verify für bcrypt-Hashes
                if (password_verify($password, $password_hash)) {

                    // Status pruefen (Mitgliederportal)
                    $status_check = checkUserStatus($user_status ?? 'approved');
                    if ($status_check !== true) {
                        $error = $status_check;
                    } else {
                        // Erfolgreiche Anmeldung
                        session_regenerate_id(true);
                        setLoginSession($id, $username_db, $full_name, $user_role, $user_status, $mitglied_id, $jungschuetze_id);
                        setRememberToken($id); // iOS PWA: persistenter Token

                        header("Location: " . getLoginRedirect($user_role ?? 'admin'));
                        exit();
                    }
                } else {
                    $error = "Ungültige Anmeldedaten";
                }
            }
        } else {
            $error = "Ungültiger Benutzername";
        }
        $stmt->close();
        $conn->close();
    }
}

// Meldungen für Logout und Timeout
if (isset($_GET['logout'])) {
    $success = "Sie wurden erfolgreich abgemeldet.";
}

if (isset($_GET['timeout'])) {
    $error = "Ihre Sitzung ist abgelaufen. Bitte melden Sie sich erneut an.";
}

if (isset($_GET['registered'])) {
    $success = "Deine Registrierung war erfolgreich! Du wirst benachrichtigt sobald dein Account freigeschaltet wurde.";
}

if (isset($_GET['auto_approved'])) {
    $success = "Deine Registrierung war erfolgreich! Du kannst dich jetzt anmelden.";
}

if (isset($_GET['error']) && $_GET['error'] == 'not_approved') {
    $error = "Dein Account ist nicht freigeschaltet. Bitte kontaktiere den Administrator.";
}

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MSV Wilen</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="icons/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="icons/icon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="icons/icon-16x16.png">

    <!-- PWA -->
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" sizes="180x180" href="icons/apple-touch-icon.png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="MSV Wilen">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">

  <style>
        :root {
            --primary-color: #adb5bd;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #adb5bd;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --border-radius: 0.375rem;
            --transition-speed: 0.3s;
            --box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --box-shadow-hover: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        body {
            background: white;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .login-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 360px;
            animation: fadeInUp 0.6s ease-out;
            border: 1px solid #e9ecef;
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .login-header {
            background: linear-gradient(135deg, #dee2e6, #adb5bd);
            color: #343a40;
            padding: 1.5rem;
            text-align: center;
        }
        .logo-container {
            margin-bottom: 0.75rem;
        }
        .logo {
            max-height: 60px;
            max-width: 160px;
            height: auto;
            width: auto;
        }
        .login-header h1 {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        .login-header p {
            opacity: 0.9;
            margin: 0;
            font-size: 0.9rem;
        }
        .login-body {
            padding: 1.5rem;
        }
        .form-floating {
            margin-bottom: 1rem;
        }
        .form-floating .form-control {
            border: 2px solid #e9ecef;
            border-radius: var(--border-radius);
            transition: all var(--transition-speed) ease;
            font-size: 1rem;
            padding: 1rem 0.75rem;
        }
        .form-floating .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(173, 181, 189, 0.1);
        }
        .form-floating .form-control:hover {
            border-color: #ced4da;
            background-color: #f8f9fa;
        }
        .form-floating label {
            color: var(--secondary-color);
            font-weight: 500;
        }
        .btn-login {
            background: linear-gradient(135deg, #dee2e6, #adb5bd);
            border: none;
            border-radius: var(--border-radius);
            color: #343a40;
            font-weight: 600;
            padding: 0.6rem 2rem;
            font-size: 1rem;
            transition: all var(--transition-speed) ease;
            width: 100%;
            margin-bottom: 0.75rem;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: var(--box-shadow-hover);
            color: #343a40;
            background: linear-gradient(135deg, #ced4da, #95a2ab);
        }
        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .forgot-password {
            text-align: center;
            margin-top: 1rem;
        }
        .forgot-password a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color var(--transition-speed) ease;
        }
        .forgot-password a:hover {
            color: #6c757d;
            text-decoration: underline;
        }
        .alert {
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            box-shadow: var(--box-shadow);
            margin-bottom: 1rem;
        }
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-left: 4px solid var(--danger-color);
        }
        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-left: 4px solid var(--success-color);
        }

        /* Modal Verbesserungen */
        .modal-content {
            border: none;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }
        .modal-header {
            background: linear-gradient(135deg, #dee2e6, #adb5bd);
            color: #343a40;
            border-bottom: none;
            padding: 1.5rem 2rem;
        }
        .modal-title {
            font-weight: 700;
            font-size: 1.3rem;
        }
        .modal-body {
            padding: 2rem;
        }
        .btn-close {
            filter: none;
            opacity: 0.6;
        }
        .btn-close:hover {
            opacity: 1;
        }
        .recaptcha-info {
            font-size: 0.8rem;
            color: var(--secondary-color);
            text-align: center;
            margin-top: 1rem;
        }

        /* Responsive Design */
        @media (max-width: 576px) {
            .login-card {
                margin: 1rem;
                border-radius: 15px;
            }
            .login-header, .login-body {
                padding: 1.5rem;
            }
            .login-header h1 {
                font-size: 1.5rem;
            }
        }

        /* Loading Animation */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
        }

        /* Passwort-Reset Modal Button */
        .btn-primary {
            background: linear-gradient(135deg, #dee2e6, #adb5bd);
            border: none;
            color: #343a40;
            transition: all var(--transition-speed) ease;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #ced4da, #95a2ab);
            color: #343a40;
            transform: translateY(-1px);
        }
    /* PWA Install Banner */
    #pwa-banner {
        position: fixed;
        bottom: 0; left: 0; right: 0;
        background: #fff;
        border-radius: 20px 20px 0 0;
        box-shadow: 0 -6px 30px rgba(0,0,0,0.18);
        padding: 1.25rem 1.25rem 2rem;
        z-index: 2000;
        display: none;
        animation: slideUp 0.3s cubic-bezier(.34,1.3,.64,1);
    }
    #pwa-banner.show { display: block; }
    @keyframes slideUp {
        from { transform: translateY(110%); }
        to   { transform: translateY(0); }
    }
    .pwa-banner-inner { max-width: 420px; margin: 0 auto; }
    .pwa-banner-header {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        margin-bottom: 1rem;
    }
    .pwa-banner-icon {
        width: 52px; height: 52px;
        border-radius: 12px;
        flex-shrink: 0;
        box-shadow: 0 3px 10px rgba(0,0,0,0.18);
    }
    .pwa-banner-title { flex: 1; }
    .pwa-banner-title strong {
        display: block;
        font-size: 1.05rem;
        font-weight: 700;
        color: #1a202c;
    }
    .pwa-banner-title span {
        font-size: 0.83rem;
        color: #718096;
    }
    .pwa-banner-close {
        background: #f0f2f4;
        border: none;
        border-radius: 50%;
        width: 30px; height: 30px;
        display: flex; align-items: center; justify-content: center;
        color: #6c757d;
        font-size: 0.85rem;
        flex-shrink: 0;
        cursor: pointer;
    }
    .pwa-steps-list {
        list-style: none;
        padding: 0; margin: 0;
        display: flex;
        flex-direction: column;
        gap: 0.6rem;
    }
    .pwa-steps-list li {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 0.9rem;
        color: #4a5568;
    }
    .pwa-step-num {
        background: #3b5998;
        color: #fff;
        width: 24px; height: 24px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.75rem;
        font-weight: 700;
        flex-shrink: 0;
    }
    .pwa-share-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #007aff;
        color: #fff;
        border-radius: 6px;
        width: 22px; height: 22px;
        font-size: 0.8rem;
        vertical-align: middle;
        margin: 0 1px;
    }
    .pwa-install-btn {
        width: 100%;
        background: #007aff;
        color: #fff;
        border: none;
        border-radius: 12px;
        padding: 0.75rem;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        letter-spacing: 0.01em;
    }
    /* Trigger-Link unterhalb der Login-Card */
    #pwa-install-trigger {
        display: none;
        text-align: center;
        padding: 0.75rem 1rem 0.25rem;
    }
    #pwa-trigger-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        color: #495057;
        font-size: 0.9rem;
        font-weight: 500;
        text-decoration: none;
        background: #f0f2f4;
        border: 1px solid #dee2e6;
        border-radius: 20px;
        padding: 0.45rem 1.1rem;
        transition: background 0.15s, color 0.15s;
    }
    #pwa-trigger-btn:hover {
        background: #e2e6ea;
        color: #343a40;
        text-decoration: none;
    }
    </style>

</head>
<body>

<!-- PWA Install Banner -->
<div id="pwa-banner">
    <div class="pwa-banner-inner">
        <div class="pwa-banner-header">
            <img src="icons/icon-192x192.png" class="pwa-banner-icon" alt="MSV">
            <div class="pwa-banner-title">
                <strong>App installieren</strong>
                <span>Kein erneutes Einloggen mehr nötig</span>
            </div>
            <button class="pwa-banner-close" id="pwa-banner-close" aria-label="Schliessen">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="pwa-steps-ios" style="display:none">
            <ul class="pwa-steps-list">
                <li><span class="pwa-step-num">1</span> Tippe auf <span class="pwa-share-icon"><i class="bi bi-box-arrow-up"></i></span> <strong>Teilen</strong> in Safari</li>
                <li><span class="pwa-step-num">2</span> Wähle <strong>«Zum Home-Bildschirm»</strong></li>
                <li><span class="pwa-step-num">3</span> Tippe <strong>«Hinzufügen»</strong></li>
            </ul>
        </div>
        <div class="pwa-steps-android" style="display:none">
            <button class="pwa-install-btn" id="pwa-install-btn">
                <i class="bi bi-download me-2"></i>Jetzt installieren
            </button>
        </div>
    </div>
</div>

    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo-container mb-3">
                    <img src="images/MSVWilen_Logo.jpg" alt="MSV Wilen Logo" class="logo">
                </div>
                <h1><i class="bi bi-shield-lock me-2"></i>Anmeldung</h1>
                <p>Willkommen zurück</p>
            </div>
            <div class="login-body">

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-circle me-2"></i>

                        <?php echo $error; ?>
                    </div>

                <?php endif; ?>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="bi bi-check-circle me-2"></i>

                        <?php echo $success; ?>
                    </div>

                <?php endif; ?>
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="loginForm">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="username" name="username" placeholder="Benutzername" required>
                        <label for="username">
                            <i class="bi bi-person me-2"></i>Benutzername
                        </label>
                    </div>
                    <div class="form-floating">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Passwort" required>
                        <label for="password">
                            <i class="bi bi-key me-2"></i>Passwort
                        </label>
                    </div>
                    <!-- Hidden reCAPTCHA v3 Token -->
                    <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">
                    <button type="submit" class="btn btn-login" id="loginBtn">
                        <i class="bi bi-box-arrow-in-right me-2"></i>
                        Anmelden
                    </button>
                </form>
                <div class="forgot-password">
                    <a href="#" data-bs-toggle="modal" data-bs-target="#passwordResetModal">
                        <i class="bi bi-question-circle me-1"></i>
                        Passwort vergessen?
                    </a>
                </div>
                <div class="text-center mt-3" style="border-top: 1px solid #e9ecef; padding-top: 1rem;">
                    <span style="color: var(--secondary-color); font-size: 0.9rem;">Noch kein Konto?</span>
                    <a href="register.php" style="color: #3b5998; text-decoration: none; font-weight: 600; font-size: 0.9rem;">
                        <i class="bi bi-person-plus me-1"></i>Registrieren
                    </a>
                    <div class="mt-2">
                        <a href="register_jsk.php" style="color: #0d9488; text-decoration: none; font-weight: 500; font-size: 0.85rem;">
                            <i class="bi bi-person-bounding-box me-1"></i>Bist du Jungschütze? Hier registrieren
                        </a>
                    </div>
                </div>
                <?php if (!empty($recaptcha_site_key)): ?>
                <div class="recaptcha-info">
                    <i class="bi bi-shield-check me-1"></i>
                    Diese Seite ist durch reCAPTCHA geschützt
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- PWA Install Trigger (nur auf Mobile, via JS eingeblendet) -->
    <div id="pwa-install-trigger">
        <a href="#" id="pwa-trigger-btn">
            <i class="bi bi-phone me-1"></i>Als App installieren
        </a>
    </div>
    <!-- Passwort-Zurücksetzen-Modal -->
    <div class="modal fade" id="passwordResetModal" tabindex="-1" aria-labelledby="passwordResetModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="passwordResetModalLabel">
                        <i class="bi bi-arrow-clockwise me-2"></i>
                        Passwort zurücksetzen
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                </div>
                <div class="modal-body">
                    <form id="passwordResetForm">
                        <div class="form-floating mb-3">
                            <input type="email" class="form-control" id="reset_email" name="email" placeholder="E-Mail-Adresse" required>
                            <label for="reset_email">
                                <i class="bi bi-envelope me-2"></i>E-Mail-Adresse
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100" id="resetBtn">
                            <i class="bi bi-send me-2"></i>
                            Zurücksetzen-Link senden
                        </button>
                    </form>
                    <div id="passwordResetMessage" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>
    <!-- Scripts -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- reCAPTCHA v3 (nur laden wenn Key konfiguriert) -->
    <?php if (!empty($recaptcha_site_key)): ?>
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo htmlspecialchars($recaptcha_site_key); ?>"></script>
    <?php endif; ?>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="inc/js/msv-toast.js"></script>

    <script>
        // Hier angekommen bedeutet: kein gültiger Cookie/Session vorhanden (PHP hat bereits geprüft).
        // localStorage-Token löschen damit kein veralteter Token bleibt.
        try { localStorage.removeItem('msv_rt'); } catch(e) {}
    </script>
    <script>
        $(document).ready(function() {

            // Cursor automatisch ins Benutzername-Feld setzen
            $('#username').focus();

            // Login-Formular Submit
            $('#loginForm').on('submit', function(e) {
                e.preventDefault();
                const $loginBtn = $('#loginBtn');
                const originalText = $loginBtn.html();
                $loginBtn.prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm me-2"></span>Anmelden...');

                // Prüfe ob reCAPTCHA verfügbar und konfiguriert ist
                var recaptchaSiteKey = '<?php echo htmlspecialchars($recaptcha_site_key); ?>';
                if (recaptchaSiteKey && typeof grecaptcha !== 'undefined') {
                    // Timeout-Fallback: Falls grecaptcha.ready() nie feuert (z.B. Domain nicht autorisiert)
                    var recaptchaTimeout = setTimeout(function() {
                        console.warn('reCAPTCHA Timeout - Login ohne Token');
                        submitLogin();
                    }, 3000);

                    grecaptcha.ready(function() {
                        clearTimeout(recaptchaTimeout);
                        grecaptcha.execute(recaptchaSiteKey, {action: 'login'}).then(function(token) {
                            $('#g-recaptcha-response').val(token);
                            submitLogin();
                        }).catch(function(err) {
                            console.warn('reCAPTCHA Fehler:', err);
                            submitLogin(); // Fallback ohne reCAPTCHA
                        });
                    });
                } else {
                    // Ohne reCAPTCHA sofort einloggen
                    submitLogin();
                }

                function submitLogin() {

                    // Normale Formular-Übermittlung ohne AJAX
                    // Das PHP-Script behandelt Erfolg/Fehler und leitet entsprechend weiter
                    $('#loginForm')[0].submit();
                }
            });

            // Passwort-Zurücksetzen-Modal
            $('#passwordResetForm').on('submit', function(e) {
                e.preventDefault();
                const $resetBtn = $('#resetBtn');
                const originalText = $resetBtn.html();
                $resetBtn.prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm me-2"></span>Sende...');
                $.ajax({
                    url: 'password_reset_request.php',
                    type: 'POST',
                    dataType: 'json',
                    data: $(this).serialize(),
                    success: function(response) {
                        $('#passwordResetMessage').html(`
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i>
                                ${response.message}
                            </div>
                        `);
                        $('#passwordResetForm')[0].reset();
                        msvToast('E-Mail erfolgreich gesendet', 'success');
                    },
                    error: function(xhr, status, error) {
                        $('#passwordResetMessage').html(`
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.
                            </div>
                        `);
                        msvToast('Fehler beim Senden der E-Mail', 'error');
                    },
                    complete: function() {
                        $resetBtn.prop('disabled', false).html(originalText);
                    }
                });
            });

            // Modal Reset beim Schließen
            $('#passwordResetModal').on('hidden.bs.modal', function () {
                $('#passwordResetForm')[0].reset();
                $('#passwordResetMessage').empty();
            });
        });
    </script>

    <script>
    // PWA Install Banner – auf Mobilgeräten automatisch als Hinweis einblenden
    (function() {
        var isStandalone = navigator.standalone === true || window.matchMedia('(display-mode: standalone)').matches;
        // Cookie setzen damit PHP beim Login-Redirect den PWA-Modus erkennt
        var cookieDomain = <?php echo json_encode(msv_cookie_domain()); ?>;
        var domainStr = cookieDomain ? '; domain=' + cookieDomain : '';
        if (isStandalone) {
            document.cookie = 'pwa_mode=1; path=/' + domainStr + '; SameSite=Lax';
            return; // Bereits als PWA installiert – Banner nicht zeigen
        } else {
            document.cookie = 'pwa_mode=; path=/' + domainStr + '; max-age=0; SameSite=Lax';
        }

        var isIos     = /iphone|ipad|ipod/i.test(navigator.userAgent);
        var isAndroid = /android/i.test(navigator.userAgent);
        if (!isIos && !isAndroid) return; // Desktop: nicht anzeigen

        var banner      = document.getElementById('pwa-banner');
        var trigger     = document.getElementById('pwa-install-trigger');
        var triggerBtn  = document.getElementById('pwa-trigger-btn');
        var deferredPrompt = null;

        // Hinweis nur EINMAL automatisch zeigen – wer ihn wegklickt, wird nicht erneut belästigt.
        var DISMISS_KEY = 'msv_pwa_login_dismissed';
        function isDismissed(){ try { return localStorage.getItem(DISMISS_KEY) === '1'; } catch(e){ return false; } }
        function autoShow(){ if (banner && !isDismissed()) banner.classList.add('show'); }

        if (isIos) {
            // Nur in Safari möglich (nicht Chrome/Firefox auf iOS)
            var isSafari = /safari/i.test(navigator.userAgent) && !/crios|fxios|opios/i.test(navigator.userAgent);
            if (!isSafari) return;
            banner.querySelector('.pwa-steps-ios').style.display = 'block';
            if (trigger) trigger.style.display = 'block';
            autoShow();
        }

        if (isAndroid) {
            // Trigger-Button + Auto-Hinweis erst zeigen wenn der Browser die Installation anbietet
            window.addEventListener('beforeinstallprompt', function(e) {
                e.preventDefault();
                deferredPrompt = e;
                banner.querySelector('.pwa-steps-android').style.display = 'block';
                if (trigger) trigger.style.display = 'block';
                autoShow();
            });

            // Klick im Banner → nativer Install-Dialog
            var installBtn = document.getElementById('pwa-install-btn');
            if (installBtn) {
                installBtn.addEventListener('click', function() {
                    if (!deferredPrompt) return;
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then(function() {
                        deferredPrompt = null;
                        banner.classList.remove('show');
                        if (trigger) trigger.style.display = 'none';
                    });
                });
            }
        }

        // Trigger-Button Klick → Banner öffnen (oder direkt Android-Dialog)
        if (triggerBtn) {
            triggerBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (isAndroid && deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then(function() {
                        deferredPrompt = null;
                        if (trigger) trigger.style.display = 'none';
                    });
                } else if (banner) {
                    banner.classList.add('show');
                }
            });
        }

        // Schliessen-Button – merkt sich die Entscheidung (kein erneutes Auto-Einblenden)
        var closeBtn = document.getElementById('pwa-banner-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                banner.classList.remove('show');
                try { localStorage.setItem(DISMISS_KEY, '1'); } catch(e){}
            });
        }
    })();
    </script>

</body>
</html>
