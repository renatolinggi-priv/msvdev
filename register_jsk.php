<?php
// register_jsk.php - Selbstregistrierung fuer Jungschuetzen (Login per Mailadresse)
include 'inc/config.php';
// getDB()/$dbConf im globalen Scope sicherstellen (fuer jskFeatureAktiv())
require_once __DIR__ . '/inc/dbconnect.inc.php';

// Zentrale Session-Konfiguration (inkl. Cross-Subdomain Cookie-Domain)
require_once __DIR__ . '/inc/session_config.inc.php';
require_once __DIR__ . '/auth.php';

// CSRF-Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$featureAktiv = jskFeatureAktiv();
$errors = [];
$form_data = ['vorname' => '', 'nachname' => '', 'email' => '', 'username' => ''];

if ($featureAktiv && $_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = "Ungültiges Formular. Bitte versuche es erneut.";
    } else {
        $vorname  = trim($_POST['vorname'] ?? '');
        $nachname = trim($_POST['nachname'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        $form_data = compact('vorname', 'nachname', 'email', 'username');

        if (empty($vorname))  $errors[] = "Vorname ist erforderlich.";
        if (empty($nachname)) $errors[] = "Nachname ist erforderlich.";
        if (empty($email))    $errors[] = "E-Mail ist erforderlich.";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Ungültige E-Mail-Adresse.";
        if (empty($username)) $errors[] = "Benutzername ist erforderlich.";
        if (strlen($username) < 3) $errors[] = "Benutzername muss mindestens 3 Zeichen lang sein.";
        if (strlen($password) < 8) $errors[] = "Passwort muss mindestens 8 Zeichen lang sein.";
        if ($password !== $password_confirm) $errors[] = "Passwörter stimmen nicht überein.";

        $jungschuetze = null;
        if (empty($errors)) {
            // 1. Jungschuetze per E-Mail finden
            $stmt = $conn->prepare("SELECT id, Vorname, Name, Email FROM jungschuetzen WHERE Email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $jungschuetze = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$jungschuetze) {
                $errors[] = "Mit dieser E-Mail-Adresse ist kein Jungschütze hinterlegt. Bitte wende dich an den Jungschützenleiter.";
            } else {
                // 2. Name muss uebereinstimmen (case-insensitive)
                if (mb_strtolower($vorname) != mb_strtolower($jungschuetze['Vorname']) ||
                    mb_strtolower($nachname) != mb_strtolower($jungschuetze['Name'])) {
                    $errors[] = "Vor- und Nachname stimmen nicht mit der hinterlegten E-Mail überein.";
                }
                // 3. Pruefen ob fuer diesen Jungschuetzen bereits ein Konto existiert
                $stmt = $conn->prepare("SELECT id FROM users WHERE jungschuetze_id = ?");
                $stmt->bind_param("i", $jungschuetze['id']);
                $stmt->execute(); $stmt->store_result();
                if ($stmt->num_rows > 0) $errors[] = "Für diesen Jungschützen existiert bereits ein Konto.";
                $stmt->close();
                // 4. E-Mail / Username eindeutig in users
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute(); $stmt->store_result();
                if ($stmt->num_rows > 0) $errors[] = "Diese E-Mail-Adresse wird bereits verwendet.";
                $stmt->close();
                $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->bind_param("s", $username);
                $stmt->execute(); $stmt->store_result();
                if ($stmt->num_rows > 0) $errors[] = "Dieser Benutzername ist bereits vergeben.";
                $stmt->close();
            }
        }

        if (empty($errors) && $jungschuetze) {
            // Immer pending: Vorstand/Admin schaltet frei
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $full_name = $vorname . ' ' . $nachname;
            $jsId = (int) $jungschuetze['id'];

            $stmt = $conn->prepare("INSERT INTO users (username, full_name, email, password_hash, jungschuetze_id, role, status, created_at) VALUES (?, ?, ?, ?, ?, 'jungschuetze', 'pending', NOW())");
            $stmt->bind_param("ssssi", $username, $full_name, $email, $password_hash, $jsId);

            if ($stmt->execute()) {
                header("Location: login.php?registered=1");
                exit();
            } else {
                if ($conn->errno == 1062) {
                    $errors[] = "Benutzername oder E-Mail existiert bereits.";
                } else {
                    $errors[] = "Ein Fehler ist aufgetreten. Bitte versuche es später erneut.";
                    error_log("JSK registration error: " . $conn->error);
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
    <title>Jungschützen-Registrierung - MSV Wilen</title>
    <link rel="icon" type="image/x-icon" href="icons/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --primary-color:#3b5998; --secondary-color:#2d4373; --success-color:#28a745; --danger-color:#dc3545;
            --border-radius:0.375rem; --transition-speed:0.3s; --box-shadow-hover:0 0.5rem 1rem rgba(0,0,0,0.15); }
        body { background:white; min-height:100vh; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; }
        .register-container { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:2rem 1rem; }
        .register-card { background:white; border-radius:20px; box-shadow:0 20px 40px rgba(0,0,0,0.1); overflow:hidden;
            width:100%; max-width:480px; border:1px solid #e9ecef; }
        .register-header { background:linear-gradient(135deg,#3b5998,#2d4373); color:#fff; padding:2rem; text-align:center; }
        .logo { max-height:80px; max-width:200px; height:auto; width:auto; }
        .register-header h1 { font-size:1.6rem; font-weight:700; margin-bottom:0.5rem; }
        .register-header p { opacity:0.9; margin:0; font-size:0.9rem; }
        .register-body { padding:2rem; }
        .form-floating { margin-bottom:1rem; }
        .form-floating .form-control { border:2px solid #e9ecef; border-radius:var(--border-radius); transition:all var(--transition-speed) ease; }
        .form-floating .form-control:focus { border-color:var(--primary-color); box-shadow:0 0 0 3px rgba(59,89,152,0.15); }
        .btn-register { background:linear-gradient(135deg,#3b5998,#2d4373); border:none; border-radius:var(--border-radius);
            color:#fff; font-weight:600; padding:0.75rem 2rem; width:100%; margin-top:0.5rem; transition:all var(--transition-speed) ease; }
        .btn-register:hover { transform:translateY(-2px); box-shadow:var(--box-shadow-hover); color:#fff; background:linear-gradient(135deg,#34528c,#23355c); }
        .alert { border:none; border-radius:var(--border-radius); font-weight:500; margin-bottom:1rem; }
        .alert-danger { background:linear-gradient(135deg,#f8d7da,#f5c6cb); color:#721c24; border-left:4px solid var(--danger-color); }
        .alert-warning { background:linear-gradient(135deg,#fff3cd,#ffe69c); color:#664d03; border-left:4px solid #ffc107; }
        .info-hint { font-size:0.8rem; color:var(--secondary-color); margin-top:-0.5rem; margin-bottom:1rem; }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <div class="mb-3"><img src="images/MSVWilen_Logo.jpg" alt="MSV Wilen Logo" class="logo"></div>
                <h1><i class="bi bi-person-plus me-2"></i>Jungschützen-Registrierung</h1>
                <p>Erstelle deinen Zugang als Jungschütze</p>
            </div>
            <div class="register-body">

                <?php if (!$featureAktiv): ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="bi bi-info-circle me-2"></i>Die Jungschützen-Registrierung ist derzeit nicht verfügbar.
                        Bitte wende dich an den Jungschützenleiter.
                    </div>
                    <div class="text-center mt-3">
                        <a href="login.php" style="color:#3b5998; text-decoration:none; font-weight:500;">
                            <i class="bi bi-arrow-left me-1"></i>Zurück zur Anmeldung
                        </a>
                    </div>
                <?php else: ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-circle me-2"></i>
                        <?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="registerForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="row">
                        <div class="col-6"><div class="form-floating">
                            <input type="text" class="form-control" id="vorname" name="vorname" placeholder="Vorname" required
                                   value="<?php echo htmlspecialchars($form_data['vorname']); ?>">
                            <label for="vorname">Vorname</label>
                        </div></div>
                        <div class="col-6"><div class="form-floating">
                            <input type="text" class="form-control" id="nachname" name="nachname" placeholder="Nachname" required
                                   value="<?php echo htmlspecialchars($form_data['nachname']); ?>">
                            <label for="nachname">Nachname</label>
                        </div></div>
                    </div>
                    <p class="info-hint"><i class="bi bi-info-circle me-1"></i>Muss mit den im Kurs hinterlegten Daten übereinstimmen</p>

                    <div class="form-floating">
                        <input type="email" class="form-control" id="reg_email" name="email" placeholder="E-Mail" required
                               value="<?php echo htmlspecialchars($form_data['email']); ?>">
                        <label for="reg_email"><i class="bi bi-envelope me-1"></i>E-Mail-Adresse</label>
                    </div>
                    <p class="info-hint"><i class="bi bi-info-circle me-1"></i>Die beim Jungschützenkurs hinterlegte E-Mail-Adresse</p>

                    <div class="form-floating">
                        <input type="text" class="form-control" id="reg_username" name="username" placeholder="Benutzername" required minlength="3"
                               value="<?php echo htmlspecialchars($form_data['username']); ?>">
                        <label for="reg_username"><i class="bi bi-person me-1"></i>Benutzername</label>
                    </div>

                    <div class="form-floating">
                        <input type="password" class="form-control" id="reg_password" name="password" placeholder="Passwort" required minlength="8">
                        <label for="reg_password"><i class="bi bi-key me-1"></i>Passwort (min. 8 Zeichen)</label>
                    </div>
                    <div class="form-floating">
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" placeholder="Passwort bestätigen" required>
                        <label for="password_confirm"><i class="bi bi-key me-1"></i>Passwort bestätigen</label>
                    </div>

                    <div class="alert alert-warning py-2 small mb-3">
                        <i class="bi bi-shield-check me-1"></i>Dein Konto wird nach der Registrierung vom Jungschützenleiter freigeschaltet.
                    </div>

                    <button type="submit" class="btn btn-register" id="registerBtn">
                        <i class="bi bi-person-check me-2"></i>Registrieren
                    </button>
                </form>

                <div class="text-center mt-3" style="border-top:1px solid #e9ecef; padding-top:1rem;">
                    <a href="login.php" style="color:#3b5998; text-decoration:none; font-weight:500; font-size:0.9rem;">
                        <i class="bi bi-arrow-left me-1"></i>Zurück zur Anmeldung
                    </a>
                </div>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="inc/js/msv-toast.js"></script>
    <script>
    $(document).ready(function() {
        $('#registerForm').on('submit', function(e) {
            var pw = $('#reg_password').val(), pwc = $('#password_confirm').val();
            if (pw.length < 8) { e.preventDefault(); msvToast('Passwort muss mindestens 8 Zeichen lang sein.', 'error'); return false; }
            if (pw !== pwc) { e.preventDefault(); msvToast('Passwörter stimmen nicht überein.', 'error'); return false; }
            $('#registerBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Registriere...');
        });
    });
    </script>
</body>
</html>
