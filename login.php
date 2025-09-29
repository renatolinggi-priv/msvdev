<?php
include 'inc/config.php';

// Session-Konfiguration VOR session_start()
ini_set('session.cookie_httponly', 1);
// Auf Hosting meistens kein HTTPS-Check nötig, da oft über Proxy
// ini_set('session.cookie_secure', 1); // Deaktiviert für Hosting ohne HTTPS
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax'); // Lax statt Strict für bessere Kompatibilität

// Session starten - KEINE Cookie-Löschung hier, das macht Probleme!
session_start();

// Bei Logout-Parameter alte Session komplett erneuern
if (isset($_GET['logout']) || isset($_GET['timeout'])) {
    session_regenerate_id(true);
    $_SESSION = array(); // Session-Daten löschen
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username_input = htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8');
    $password = $_POST['password'];

    // reCAPTCHA v3 Überprüfung (optional)
    $recaptcha_secret = "6LflroMrAAAAAMj24jpSNlv8HLQXKSlqgMVkLaHW"; // GEHEIMER SCHLÜSSEL
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
    
    $recaptcha_valid = true; // Standard: gültig
    
    if (!empty($recaptcha_response)) {
        $response = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=$recaptcha_secret&response=$recaptcha_response");
        $responseKeys = json_decode($response, true);
        
        if (intval($responseKeys["success"]) !== 1 || $responseKeys["score"] < 0.3) {
            $recaptcha_valid = false;
            $error = "Sicherheitsprüfung fehlgeschlagen. Bitte versuchen Sie es erneut.";
        }
    }
    
    if ($recaptcha_valid) {
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        // Prepared Statement verwenden
        $stmt = $conn->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
        $stmt->bind_param("s", $username_input);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($id, $username_db, $password_hash);
            $stmt->fetch();

            // Prüfen, ob der Passwort-Hash ein MD5-Hash ist (32 Zeichen)
            if (strlen($password_hash) == 32) {
                if (md5($password) == $password_hash) {
                    // Passwort neu hashen und Datenbank aktualisieren
                    $new_hash = password_hash($password, PASSWORD_DEFAULT);
                    $update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $update_stmt->bind_param("si", $new_hash, $id);
                    $update_stmt->execute();
                    $update_stmt->close();

                    // Erfolgreiche Anmeldung
                    session_regenerate_id(true); // Neue Session-ID bei Login
                    $_SESSION['user_id'] = $id;
                    $_SESSION['username'] = $username_db;
                    $_SESSION['last_activity'] = time();
                    $_SESSION['regenerated'] = time();
                    header("Location: index.php");
                    exit();
                } else {
                    $error = "Ungültige Anmeldedaten";
                }
            } else {
                // Verwendung von password_verify für bcrypt-Hashes
                if (password_verify($password, $password_hash)) {
                    // Erfolgreiche Anmeldung
                    session_regenerate_id(true); // Neue Session-ID bei Login
                    $_SESSION['user_id'] = $id;
                    $_SESSION['username'] = $username_db;
                    $_SESSION['last_activity'] = time();
                    $_SESSION['regenerated'] = time();
                    header("Location: index.php");
                    exit();
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
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MSV Wilen</title>
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
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
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
            padding: 2rem;
            text-align: center;
        }

        .logo-container {
            margin-bottom: 1rem;
        }

        .logo {
            max-height: 80px;
            max-width: 200px;
            height: auto;
            width: auto;
        }

        .login-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .login-header p {
            opacity: 0.9;
            margin: 0;
        }

        .login-body {
            padding: 2rem;
        }

        .form-floating {
            margin-bottom: 1.5rem;
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
            padding: 0.75rem 2rem;
            font-size: 1rem;
            transition: all var(--transition-speed) ease;
            width: 100%;
            margin-bottom: 1rem;
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

        /* Toast Container */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }

        .toast {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow-hover);
            border: none;
        }

        .toast.show {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
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
    </style>    
</head>

<body>
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
                
                <div class="recaptcha-info">
                    <i class="bi bi-shield-check me-1"></i>
                    Diese Seite ist durch reCAPTCHA geschützt
                </div>
            </div>
        </div>
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

    <!-- Toast Container -->
    <div id="toast-container" class="toast-container"></div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- reCAPTCHA v3 -->
    <script src="https://www.google.com/recaptcha/api.js?render=6LflroMrAAAAAN0PFGysDuXuVoBIRo_yF6b7WhUA"></script>
    
    <script>
        $(document).ready(function() {
            // Cursor automatisch ins Benutzername-Feld setzen
            $('#username').focus();
            
            // Toast-Funktion
            function showToast(message, type = 'info') {
                const colors = {
                    'success': '#28a745',
                    'error': '#dc3545',
                    'warning': '#ffc107',
                    'info': '#17a2b8'
                };
                
                const icons = {
                    'success': 'bi-check-circle',
                    'error': 'bi-exclamation-circle',
                    'warning': 'bi-exclamation-triangle',
                    'info': 'bi-info-circle'
                };
                
                const toastId = 'toast_' + Date.now();
                const toast = $(`
                    <div class="toast align-items-center text-white border-0" role="alert" aria-live="assertive" aria-atomic="true" id="${toastId}">
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="bi ${icons[type]} me-2"></i>
                                ${message}
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                `).css('background-color', colors[type]);
                
                $('#toast-container').append(toast);
                
                const bsToast = new bootstrap.Toast(document.getElementById(toastId));
                bsToast.show();
                
                // Toast nach 5 Sekunden automatisch entfernen
                setTimeout(() => {
                    $('#' + toastId).remove();
                }, 5000);
            }

            // Login-Formular Submit
            $('#loginForm').on('submit', function(e) {
                e.preventDefault();
                
                const $loginBtn = $('#loginBtn');
                const originalText = $loginBtn.html();
                $loginBtn.prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm me-2"></span>Anmelden...');
                
                // Prüfe ob reCAPTCHA verfügbar ist
                if (typeof grecaptcha !== 'undefined') {
                    // reCAPTCHA v3 verwenden
                    grecaptcha.ready(function() {
                        grecaptcha.execute('6LflroMrAAAAAN0PFGysDuXuVoBIRo_yF6b7WhUA', {action: 'login'}).then(function(token) {
                            $('#g-recaptcha-response').val(token);
                            submitLogin();
                        }).catch(function(error) {
                            console.error('reCAPTCHA error:', error);
                            showToast('reCAPTCHA Fehler - versuche ohne...', 'warning');
                            submitLogin(); // Fallback ohne reCAPTCHA
                        });
                    });
                } else {
                    // Ohne reCAPTCHA fortfahren
                    console.log('reCAPTCHA nicht verfügbar - Login ohne reCAPTCHA');
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
                        showToast('E-Mail erfolgreich gesendet', 'success');
                    },
                    error: function(xhr, status, error) {
                        $('#passwordResetMessage').html(`
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.
                            </div>
                        `);
                        showToast('Fehler beim Senden der E-Mail', 'error');
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
</body>
</html>
