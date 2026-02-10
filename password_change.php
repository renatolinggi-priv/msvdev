<?php
// Header einbinden (startet Session und prüft Login)
include 'inc/header.inc.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $new_password_confirm = $_POST['new_password_confirm'];
    
    // Aktuelles Passwort aus DB holen
    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($current_password_hash);
    $stmt->fetch();
    $stmt->close();
    
    // Altes Passwort verifizieren
    if (!password_verify($old_password, $current_password_hash)) {
        $error = "Das aktuelle Passwort ist nicht korrekt.";
    } else if ($new_password !== $new_password_confirm) {
        $error = "Die neuen Passwörter stimmen nicht überein.";
    } else {
        // Neues Passwort validieren
        $password_errors = validate_password($new_password, $username);
        
        if (empty($password_errors)) {
            // Neues Passwort hashen und speichern
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            
            $update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $update_stmt->bind_param("si", $new_password_hash, $user_id);
            
            if ($update_stmt->execute()) {
                $success = "Ihr Passwort wurde erfolgreich geändert.";
            } else {
                $error = "Fehler beim Ändern des Passworts. Bitte versuchen Sie es später erneut.";
            }
            $update_stmt->close();
        } else {
            $error = implode('<br>', $password_errors);
        }
    }
}

// Funktion zur Passwortvalidierung (gleich wie in reset_password.php)
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
    <title>Passwort ändern - MSV Wilen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- MSV Styles einbinden -->
    <link rel="stylesheet" href="css/msv-styles.css">
    
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
            background: #f8f9fa;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .container {
            padding-top: 2rem;
            padding-bottom: 2rem;
        }

        .change-password-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
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

        .card-header {
            background: linear-gradient(135deg, #dee2e6, #adb5bd);
            color: #343a40;
            padding: 2rem;
            text-align: center;
        }

        .card-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .card-header p {
            opacity: 0.9;
            margin: 0;
            font-size: 0.95rem;
        }

        .card-body {
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

        .btn-change {
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

        .btn-change:hover {
            transform: translateY(-2px);
            box-shadow: var(--box-shadow-hover);
            color: #343a40;
            background: linear-gradient(135deg, #ced4da, #95a2ab);
        }

        .btn-change:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-cancel {
            background: transparent;
            border: 2px solid #e9ecef;
            border-radius: var(--border-radius);
            color: var(--secondary-color);
            font-weight: 600;
            padding: 0.75rem 2rem;
            font-size: 1rem;
            transition: all var(--transition-speed) ease;
            width: 100%;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-cancel:hover {
            background: #f8f9fa;
            border-color: var(--secondary-color);
            color: var(--dark-color);
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

        /* Responsive Design */
        @media (max-width: 576px) {
            .change-password-card {
                margin: 1rem;
                border-radius: 15px;
            }
            
            .card-header, .card-body {
                padding: 1.5rem;
            }
            
            .card-header h1 {
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

    </style>
</head>

<body>
    <!-- Navigation wird von header.inc.php eingefügt -->
    
    <div class="container" style="margin-top: 80px;"> <!-- Platz für fixed navbar -->
        <div class="change-password-card">
            <div class="card-header">
                <h1><i class="bi bi-key me-2"></i>Passwort ändern</h1>
                <p>Ändern Sie Ihr Passwort für mehr Sicherheit</p>
            </div>

    <!-- Footer einbinden -->
    <?php include 'inc/footer.inc.php'; ?>
            
            <div class="card-body">
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

                <form method="post" action="" id="changePasswordForm">
                    <div class="form-floating">
                        <input type="password" class="form-control" id="old_password" name="old_password" placeholder="Aktuelles Passwort" required>
                        <label for="old_password">
                            <i class="bi bi-lock me-2"></i>Aktuelles Passwort
                        </label>
                    </div>
                    
                    <div class="form-floating">
                        <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Neues Passwort" required>
                        <label for="new_password">
                            <i class="bi bi-key me-2"></i>Neues Passwort
                        </label>
                        <div class="password-strength-indicator">
                            <div class="password-strength-bar" id="strengthBar"></div>
                        </div>
                    </div>
                    
                    <div class="form-floating">
                        <input type="password" class="form-control" id="new_password_confirm" name="new_password_confirm" placeholder="Neues Passwort bestätigen" required>
                        <label for="new_password_confirm">
                            <i class="bi bi-shield-check me-2"></i>Neues Passwort bestätigen
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-change" id="changeBtn">
                        <i class="bi bi-shield-lock me-2"></i>
                        Passwort ändern
                    </button>
                    
                    <a href="home.php" class="btn btn-cancel">
                        <i class="bi bi-x-circle me-2"></i>
                        Abbrechen
                    </a>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="inc/js/msv-toast.js"></script>

    <script>
        $(document).ready(function() {
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
            $('#new_password').on('input', function() {
                updatePasswordStrength($(this).val());
            });

            // Formular-Validierung
            $('#changePasswordForm').on('submit', function(e) {
                e.preventDefault();
                
                const $changeBtn = $('#changeBtn');
                const originalText = $changeBtn.html();
                
                const oldPassword = $('#old_password').val();
                const newPassword = $('#new_password').val();
                const newPasswordConfirm = $('#new_password_confirm').val();
                let errors = [];

                // Clientseitige Validierung
                if (newPassword.length < 10) {
                    errors.push('Das Passwort muss mindestens 10 Zeichen lang sein.');
                }

                let categories = 0;
                if (/[A-Z]/.test(newPassword)) categories++;
                if (/[a-z]/.test(newPassword)) categories++;
                if (/[0-9]/.test(newPassword)) categories++;
                if (/[^A-Za-z0-9]/.test(newPassword)) categories++;
                
                if (categories < 3) {
                    errors.push('Das Passwort muss mindestens 3 der folgenden Kategorien enthalten: Großbuchstaben, Kleinbuchstaben, Ziffern, Sonderzeichen.');
                }

                if (newPassword !== newPasswordConfirm) {
                    errors.push('Die neuen Passwörter stimmen nicht überein.');
                }

                if (oldPassword === newPassword) {
                    errors.push('Das neue Passwort darf nicht mit dem alten Passwort identisch sein.');
                }

                if (errors.length > 0) {
                    msvToast(errors.join('<br>'), 'error');
                    return;
                }

                // Formular absenden
                $changeBtn.prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm me-2"></span>Passwort wird geändert...');

                // Echte Formular-Übermittlung
                this.submit();
            });

            // Passwort-Bestätigung Live-Validierung
            $('#new_password_confirm').on('input', function() {
                const password = $('#new_password').val();
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