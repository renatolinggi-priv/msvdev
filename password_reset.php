<?php
include 'inc/config.php';

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Überprüfen, ob der Token existiert und gültig ist
    $stmt = $conn->prepare("SELECT user_id, expires FROM password_resets WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($user_id, $expires);
        $stmt->fetch();

        // Überprüfen, ob der Token abgelaufen ist
        if ($expires >= date("U")) {
            $show_form = true;

            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                $password = $_POST['password'];
                $password_confirm = $_POST['password_confirm'];

                if ($password === $password_confirm) {
                    // Benutzernamen abrufen
                    $user_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                    $user_stmt->bind_param("i", $user_id);
                    $user_stmt->execute();
                    $user_stmt->bind_result($username_db);
                    $user_stmt->fetch();
                    $user_stmt->close();

                    // Passwort gemäß Richtlinien validieren
                    $password_errors = validate_password($password, $username_db);

                    if (empty($password_errors)) {
                        // Passwort hashen
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);

                        // Passwort aktualisieren
                        $update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                        $update_stmt->bind_param("si", $password_hash, $user_id);
                        $update_stmt->execute();
                        $update_stmt->close();

                        // Token löschen
                        $delete_stmt = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
                        $delete_stmt->bind_param("i", $user_id);
                        $delete_stmt->execute();
                        $delete_stmt->close();

                        $success = "Ihr Passwort wurde erfolgreich zurückgesetzt.";
                        $show_form = false;
                    } else {
                        // Fehler anzeigen
                        $error = implode('<br>', $password_errors);
                    }
                } else {
                    $error = "Die Passwörter stimmen nicht überein.";
                }
            }
        } else {
            $error = "Der Link ist abgelaufen. Bitte fordern Sie einen neuen Link an.";
        }
    } else {
        $error = "Ungültiger Link. Bitte fordern Sie einen neuen Link an.";
    }

    $stmt->close();
    $conn->close();
} else {
    $error = "Kein Token angegeben. Bitte verwenden Sie den Link aus der E-Mail.";
}

// Funktion zur Passwortvalidierung
function validate_password($password, $username) {
    $errors = array();

    // 1. Mindestlänge prüfen
    if (strlen($password) < 10) {
        $errors[] = "Das Passwort muss mindestens 10 Zeichen lang sein.";
    }

    // 2. Kategorien prüfen
    $categories = 0;
    if (preg_match('/[A-Z]/', $password)) {
        $categories++;
    }
    if (preg_match('/[a-z]/', $password)) {
        $categories++;
    }
    if (preg_match('/[0-9]/', $password)) {
        $categories++;
    }
    if (preg_match('/[^A-Za-z0-9]/', $password)) {
        // Sonderzeichen
        $categories++;
    }
    if ($categories < 3) {
        $errors[] = "Das Passwort muss mindestens 3 der folgenden Kategorien enthalten: Großbuchstaben, Kleinbuchstaben, Ziffern, Sonderzeichen.";
    }

    // 3. Benutzernamen prüfen
    if (stripos($password, $username) !== false) {
        $errors[] = "Das Passwort darf den Benutzernamen nicht enthalten.";
    }

    return $errors;
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passwort zurücksetzen - MSV Wilen</title>
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

        .reset-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .reset-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
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

        .reset-header {
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

        .reset-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .reset-header p {
            opacity: 0.9;
            margin: 0;
            font-size: 0.95rem;
        }

        .reset-body {
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

        .btn-reset {
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

        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: var(--box-shadow-hover);
            color: #343a40;
            background: linear-gradient(135deg, #ced4da, #95a2ab);
        }

        .btn-reset:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-login-link {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            border-radius: var(--border-radius);
            color: white;
            font-weight: 600;
            padding: 0.75rem 2rem;
            font-size: 1rem;
            transition: all var(--transition-speed) ease;
            width: 100%;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-login-link:hover {
            transform: translateY(-2px);
            box-shadow: var(--box-shadow-hover);
            color: white;
            background: linear-gradient(135deg, #218838, #1e7e34);
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

        .password-requirements {
            background: linear-gradient(135deg, #e2e3e5, #f8f9fa);
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--info-color);
        }

        .password-requirements h6 {
            color: var(--dark-color);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .password-requirements ul {
            margin: 0;
            padding-left: 1.2rem;
            color: var(--secondary-color);
        }

        .password-requirements li {
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .password-strength-indicator {
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all var(--transition-speed) ease;
            border-radius: 2px;
        }

        .strength-weak { background: var(--danger-color); }
        .strength-medium { background: var(--warning-color); }
        .strength-strong { background: var(--success-color); }

        .back-to-login {
            text-align: center;
            margin-top: 1rem;
        }

        .back-to-login a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color var(--transition-speed) ease;
        }

        .back-to-login a:hover {
            color: #6c757d;
            text-decoration: underline;
        }

        /* Responsive Design */
        @media (max-width: 576px) {
            .reset-card {
                margin: 1rem;
                border-radius: 15px;
            }
            
            .reset-header, .reset-body {
                padding: 1.5rem;
            }
            
            .reset-header h1 {
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
    </style>
</head>

<body>
    <div class="reset-container">
        <div class="reset-card">
            <div class="reset-header">
                <div class="logo-container mb-3">
                    <img src="images/MSVWilen_Logo.jpg" alt="MSV Wilen Logo" class="logo">
                </div>
                <h1><i class="bi bi-arrow-clockwise me-2"></i>Passwort zurücksetzen</h1>
                <p>Setzen Sie ein neues, sicheres Passwort</p>
            </div>
            
            <div class="reset-body">
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
                    <a href="login.php" class="btn-login-link">
                        <i class="bi bi-box-arrow-in-right me-2"></i>
                        Zum Login
                    </a>
                <?php endif; ?>

                <?php if (isset($show_form) && $show_form === true): ?>
                    <div class="password-requirements">
                        <h6><i class="bi bi-info-circle me-2"></i>Passwort-Anforderungen:</h6>
                        <ul>
                            <li>Mindestens 10 Zeichen lang</li>
                            <li>Mindestens 3 der folgenden Kategorien:
                                <ul>
                                    <li>Großbuchstaben (A-Z)</li>
                                    <li>Kleinbuchstaben (a-z)</li>
                                    <li>Ziffern (0-9)</li>
                                    <li>Sonderzeichen (!@#$%^&*)</li>
                                </ul>
                            </li>
                            <li>Darf den Benutzernamen nicht enthalten</li>
                        </ul>
                    </div>

                    <form method="post" action="" id="resetForm">
                        <div class="form-floating">
                            <input type="password" class="form-control" id="password" name="password" placeholder="Neues Passwort" required>
                            <label for="password">
                                <i class="bi bi-key me-2"></i>Neues Passwort
                            </label>
                            <div class="password-strength-indicator">
                                <div class="password-strength-bar" id="strengthBar"></div>
                            </div>
                        </div>
                        
                        <div class="form-floating">
                            <input type="password" class="form-control" id="password_confirm" name="password_confirm" placeholder="Passwort bestätigen" required>
                            <label for="password_confirm">
                                <i class="bi bi-shield-check me-2"></i>Passwort bestätigen
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-reset" id="resetBtn">
                            <i class="bi bi-shield-lock me-2"></i>
                            Passwort setzen
                        </button>
                    </form>
                <?php endif; ?>
                
                <div class="back-to-login">
                    <a href="login.php">
                        <i class="bi bi-arrow-left me-1"></i>
                        Zurück zum Login
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toast-container" class="toast-container"></div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Toast-Funktion
            function showToast(message, type = 'info') {
                const colors = {
                    'success': '#28a745',
                    'error': '#dc3545',
                    'warning': '#ffc107',
                    'info': '#adb5bd'
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
                
                setTimeout(() => {
                    $('#' + toastId).remove();
                }, 5000);
            }

            // Passwort-Stärke-Anzeige
            function updatePasswordStrength(password) {
                const strengthBar = $('#strengthBar');
                let strength = 0;
                let strengthClass = '';

                if (password.length >= 10) strength++;
                if (/[A-Z]/.test(password)) strength++;
                if (/[a-z]/.test(password)) strength++;
                if (/[0-9]/.test(password)) strength++;
                if (/[^A-Za-z0-9]/.test(password)) strength++;

                const percentage = (strength / 5) * 100;
                
                if (percentage < 40) {
                    strengthClass = 'strength-weak';
                } else if (percentage < 80) {
                    strengthClass = 'strength-medium';
                } else {
                    strengthClass = 'strength-strong';
                }

                strengthBar.removeClass('strength-weak strength-medium strength-strong')
                    .addClass(strengthClass)
                    .css('width', percentage + '%');
            }

            // Passwort-Input Event
            $('#password').on('input', function() {
                updatePasswordStrength($(this).val());
            });

            // Formular-Validierung
            $('#resetForm').on('submit', function(e) {
                e.preventDefault();
                
                const $resetBtn = $('#resetBtn');
                const originalText = $resetBtn.html();
                
                const password = $('#password').val();
                const passwordConfirm = $('#password_confirm').val();
                let errors = [];

                // Clientseitige Validierung
                if (password.length < 10) {
                    errors.push('Das Passwort muss mindestens 10 Zeichen lang sein.');
                }

                let categories = 0;
                if (/[A-Z]/.test(password)) categories++;
                if (/[a-z]/.test(password)) categories++;
                if (/[0-9]/.test(password)) categories++;
                if (/[^A-Za-z0-9]/.test(password)) categories++;
                
                if (categories < 3) {
                    errors.push('Das Passwort muss mindestens 3 der folgenden Kategorien enthalten: Großbuchstaben, Kleinbuchstaben, Ziffern, Sonderzeichen.');
                }

                if (password !== passwordConfirm) {
                    errors.push('Die Passwörter stimmen nicht überein.');
                }

                if (errors.length > 0) {
                    showToast(errors.join('<br>'), 'error');
                    return;
                }

                // Formular absenden
                $resetBtn.prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm me-2"></span>Passwort wird gesetzt...');

                // Simuliere Verarbeitung (in der echten Anwendung wird das Formular normal abgesendet)
                setTimeout(() => {
                    showToast('Passwort wurde erfolgreich zurückgesetzt!', 'success');
                    this.submit(); // Echte Formular-Übermittlung
                }, 1000);
            });

            // Passwort-Bestätigung Live-Validierung
            $('#password_confirm').on('input', function() {
                const password = $('#password').val();
                const confirmPassword = $(this).val();
                
                if (confirmPassword && password !== confirmPassword) {
                    $(this).addClass('is-invalid');
                } else {
                    $(this).removeClass('is-invalid');
                }
            });
        });
    </script>
</body>
</html>