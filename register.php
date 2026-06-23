<?php
// register.php - Selbstregistrierung fuer Vereinsmitglieder
include 'inc/config.php';

// Zentrale Session-Konfiguration (inkl. Cross-Subdomain Cookie-Domain)
require_once __DIR__ . '/inc/session_config.inc.php';

// CSRF-Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = false;
$auto_approved = false;
$form_data = ['mitglied_nr' => '', 'vorname' => '', 'nachname' => '', 'email' => '', 'username' => ''];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF pruefen
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = "Ungültiges Formular. Bitte versuche es erneut.";
    } else {
        $mitglied_nr = intval($_POST['mitglied_nr'] ?? 0);
        $vorname = trim($_POST['vorname'] ?? '');
        $nachname = trim($_POST['nachname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        $form_data = compact('mitglied_nr', 'vorname', 'nachname', 'email', 'username');

        // Validierung
        if (empty($mitglied_nr)) $errors[] = "Mitgliedernummer ist erforderlich.";
        if (empty($vorname)) $errors[] = "Vorname ist erforderlich.";
        if (empty($nachname)) $errors[] = "Nachname ist erforderlich.";
        if (empty($email)) $errors[] = "E-Mail ist erforderlich.";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Ungültige E-Mail-Adresse.";
        if (empty($username)) $errors[] = "Benutzername ist erforderlich.";
        if (strlen($username) < 3) $errors[] = "Benutzername muss mindestens 3 Zeichen lang sein.";
        if (strlen($password) < 8) $errors[] = "Passwort muss mindestens 8 Zeichen lang sein.";
        if ($password !== $password_confirm) $errors[] = "Passwörter stimmen nicht überein.";

        if (empty($errors)) {
            // 1. Mitgliedernummer pruefen
            $stmt = $conn->prepare("SELECT ID, Vorname, Name, Email, Status FROM mitglieder WHERE ID = ?");
            $stmt->bind_param("i", $mitglied_nr);
            $stmt->execute();
            $result = $stmt->get_result();
            $mitglied = $result->fetch_assoc();
            $stmt->close();

            if (!$mitglied) {
                $errors[] = "Diese Mitgliedernummer ist nicht bekannt.";
            } else {
                // 2. Vorname + Nachname pruefen (case-insensitive)
                if (mb_strtolower($vorname) != mb_strtolower($mitglied['Vorname']) ||
                    mb_strtolower($nachname) != mb_strtolower($mitglied['Name'])) {
                    $errors[] = "Vorname und Nachname stimmen nicht mit der Mitgliedernummer überein.";
                }

                // 3. Pruefen ob Mitgliedernummer bereits einem User zugeordnet ist
                $stmt = $conn->prepare("SELECT id FROM users WHERE mitglied_id = ?");
                $stmt->bind_param("i", $mitglied_nr);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $errors[] = "Für diese Mitgliedernummer existiert bereits ein Account.";
                }
                $stmt->close();

                // 4. E-Mail in users pruefen
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $errors[] = "Diese E-Mail-Adresse wird bereits verwendet.";
                }
                $stmt->close();

                // 5. Username in users pruefen
                $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $errors[] = "Dieser Benutzername ist bereits vergeben.";
                }
                $stmt->close();
            }
        }

        if (empty($errors)) {
            // E-Mail-Match pruefen: automatische Freischaltung wenn E-Mail beim Mitglied hinterlegt
            $mitglied_email = trim($mitglied['Email'] ?? '');
            if (!empty($mitglied_email) && mb_strtolower($email) == mb_strtolower($mitglied_email)) {
                // Auto-Approve: E-Mail stimmt mit Mitglied-Datenbank ueberein
                $user_status = 'approved';
                $auto_approved = true;
            } else {
                // Pending: Admin muss freischalten
                $user_status = 'pending';
            }

            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $full_name = $vorname . ' ' . $nachname;

            $stmt = $conn->prepare("INSERT INTO users (username, full_name, email, password_hash, mitglied_id, role, status, created_at) VALUES (?, ?, ?, ?, ?, 'mitglied', ?, NOW())");
            $stmt->bind_param("ssssis", $username, $full_name, $email, $password_hash, $mitglied_nr, $user_status);

            if ($stmt->execute()) {
                $success = true;
                $form_data = ['mitglied_nr' => '', 'vorname' => '', 'nachname' => '', 'email' => '', 'username' => ''];

                if ($auto_approved) {
                    header("Location: login.php?auto_approved=1");
                    exit();
                } else {
                    header("Location: login.php?registered=1");
                    exit();
                }
            } else {
                if ($conn->errno == 1062) {
                    $errors[] = "Benutzername oder E-Mail existiert bereits.";
                } else {
                    $errors[] = "Ein Fehler ist aufgetreten. Bitte versuche es später erneut.";
                    error_log("Registration error: " . $conn->error);
                }
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrierung - MSV Wilen</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="icons/favicon.ico">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #adb5bd;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
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
        .register-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .register-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 480px;
            animation: fadeInUp 0.6s ease-out;
            border: 1px solid #e9ecef;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .register-header {
            background: linear-gradient(135deg, #dee2e6, #adb5bd);
            color: #343a40;
            padding: 2rem;
            text-align: center;
        }
        .logo {
            max-height: 80px;
            max-width: 200px;
            height: auto;
            width: auto;
        }
        .register-header h1 {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .register-header p {
            opacity: 0.9;
            margin: 0;
            font-size: 0.9rem;
        }
        .register-body {
            padding: 2rem;
        }
        .form-floating {
            margin-bottom: 1rem;
        }
        .form-floating .form-control {
            border: 2px solid #e9ecef;
            border-radius: var(--border-radius);
            transition: all var(--transition-speed) ease;
            font-size: 1rem;
        }
        .form-floating .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(173, 181, 189, 0.1);
        }
        .form-floating label {
            color: var(--secondary-color);
            font-weight: 500;
        }
        .btn-register {
            background: linear-gradient(135deg, #dee2e6, #adb5bd);
            border: none;
            border-radius: var(--border-radius);
            color: #343a40;
            font-weight: 600;
            padding: 0.75rem 2rem;
            font-size: 1rem;
            transition: all var(--transition-speed) ease;
            width: 100%;
            margin-top: 0.5rem;
        }
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: var(--box-shadow-hover);
            color: #343a40;
            background: linear-gradient(135deg, #ced4da, #95a2ab);
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
        .info-hint {
            font-size: 0.8rem;
            color: var(--secondary-color);
            margin-top: -0.5rem;
            margin-bottom: 1rem;
        }
        @media (max-width: 576px) {
            .register-card { margin: 0.5rem; border-radius: 15px; }
            .register-header, .register-body { padding: 1.5rem; }
            .register-header h1 { font-size: 1.4rem; }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <div class="mb-3">
                    <img src="images/MSVWilen_Logo.jpg" alt="MSV Wilen Logo" class="logo">
                </div>
                <h1><i class="bi bi-person-plus me-2"></i>Registrierung</h1>
                <p>Erstelle deinen Zugang zum Mitgliederportal</p>
            </div>
            <div class="register-body">

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-circle me-2"></i>
                        <?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="registerForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="form-floating">
                        <input type="number" class="form-control" id="mitglied_nr" name="mitglied_nr"
                               placeholder="Mitgliedernummer" required
                               value="<?php echo htmlspecialchars($form_data['mitglied_nr']); ?>">
                        <label for="mitglied_nr">
                            <i class="bi bi-hash me-1"></i>Mitgliedernummer
                        </label>
                    </div>
                    <p class="info-hint"><i class="bi bi-info-circle me-1"></i>Deine Vereins-Mitgliedernummer (z.B. 112101)</p>

                    <div class="row">
                        <div class="col-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="vorname" name="vorname"
                                       placeholder="Vorname" required
                                       value="<?php echo htmlspecialchars($form_data['vorname']); ?>">
                                <label for="vorname">Vorname</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="nachname" name="nachname"
                                       placeholder="Nachname" required
                                       value="<?php echo htmlspecialchars($form_data['nachname']); ?>">
                                <label for="nachname">Nachname</label>
                            </div>
                        </div>
                    </div>
                    <p class="info-hint"><i class="bi bi-info-circle me-1"></i>Muss mit den Vereinsdaten übereinstimmen</p>

                    <div class="form-floating">
                        <input type="text" class="form-control" id="reg_username" name="username"
                               placeholder="Benutzername" required minlength="3"
                               value="<?php echo htmlspecialchars($form_data['username']); ?>">
                        <label for="reg_username">
                            <i class="bi bi-person me-1"></i>Benutzername
                        </label>
                    </div>

                    <div class="form-floating">
                        <input type="email" class="form-control" id="reg_email" name="email"
                               placeholder="E-Mail" required
                               value="<?php echo htmlspecialchars($form_data['email']); ?>">
                        <label for="reg_email">
                            <i class="bi bi-envelope me-1"></i>E-Mail-Adresse
                        </label>
                    </div>
                    <p class="info-hint"><i class="bi bi-info-circle me-1"></i>Wenn deine E-Mail beim Verein hinterlegt ist, wirst du automatisch freigeschaltet</p>

                    <div class="form-floating">
                        <input type="password" class="form-control" id="reg_password" name="password"
                               placeholder="Passwort" required minlength="8">
                        <label for="reg_password">
                            <i class="bi bi-key me-1"></i>Passwort (min. 8 Zeichen)
                        </label>
                    </div>

                    <div class="form-floating">
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm"
                               placeholder="Passwort bestätigen" required>
                        <label for="password_confirm">
                            <i class="bi bi-key me-1"></i>Passwort bestätigen
                        </label>
                    </div>

                    <button type="submit" class="btn btn-register" id="registerBtn">
                        <i class="bi bi-person-check me-2"></i>Registrieren
                    </button>
                </form>

                <div class="text-center mt-3" style="border-top: 1px solid #e9ecef; padding-top: 1rem;">
                    <a href="login.php" style="color: #3b5998; text-decoration: none; font-weight: 500; font-size: 0.9rem;">
                        <i class="bi bi-arrow-left me-1"></i>Zurück zur Anmeldung
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="inc/js/msv-toast.js"></script>

    <script>
    $(document).ready(function() {
        // Client-side Passwort-Validierung
        $('#registerForm').on('submit', function(e) {
            var pw = $('#reg_password').val();
            var pwConfirm = $('#password_confirm').val();

            if (pw.length < 8) {
                e.preventDefault();
                msvToast('Passwort muss mindestens 8 Zeichen lang sein.', 'error');
                return false;
            }
            if (pw !== pwConfirm) {
                e.preventDefault();
                msvToast('Passwörter stimmen nicht überein.', 'error');
                return false;
            }

            // Submit-Button deaktivieren
            $('#registerBtn').prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm me-2"></span>Registriere...');
        });
    });
    </script>
</body>
</html>
