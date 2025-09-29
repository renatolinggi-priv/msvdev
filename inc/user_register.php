<?php
// user_register.php - Benutzerregistrierung im Stil von backup_restore.php
include 'dbconnect.inc.php';

// Verarbeitung der Registrierung
$success = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8');
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    // Validierung
    if ($password !== $password_confirm) {
        $error = "Die Passwörter stimmen nicht überein.";
    } elseif (strlen($password) < 8) {
        $error = "Das Passwort muss mindestens 8 Zeichen lang sein.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Bitte gib eine gültige E-Mail-Adresse ein.";
    } else {
        // Passwort-Hashing
        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        // Prüfen ob Benutzer bereits existiert
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Benutzername oder E-Mail-Adresse ist bereits vergeben.";
        } else {
            // Neuen Benutzer anlegen
            $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $email, $password_hash);

            if ($stmt->execute()) {
                $success = "Registrierung erfolgreich! Du kannst dich jetzt anmelden.";
                // Formular zurücksetzen
                $username = '';
                $email = '';
            } else {
                $error = "Ein Fehler ist aufgetreten. Bitte versuche es später erneut.";
            }
        }
        $stmt->close();
    }
}

// Seitenspezifische Styles definieren
$page_specific_css = "
/* Registrierung spezifische Styles */
.main-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    padding: 2rem;
    margin-bottom: 2rem;
}

.sidebar-card,
.registration-card,
.info-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    padding: 1.5rem;
    margin-bottom: 1.25rem;
}

.sidebar-card { border-left: 4px solid var(--info-color); }
.registration-card { border-left: 4px solid var(--primary-color); }
.info-card { border-left: 4px solid var(--success-color); }

.card-title {
    color: var(--secondary-color);
    font-weight: 600;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: .5rem;
}

.form-label {
    font-weight: 500;
    color: var(--secondary-color);
    margin-bottom: .5rem;
}

.form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(var(--primary-rgb), 0.25);
}

.password-strength {
    height: 4px;
    border-radius: 2px;
    margin-top: .5rem;
    background: #e9ecef;
    overflow: hidden;
}

.password-strength-bar {
    height: 100%;
    transition: width .3s ease, background-color .3s ease;
}

.password-requirements {
    font-size: 0.875rem;
    color: #6c757d;
}

.password-requirements li {
    margin-bottom: .25rem;
}

.password-requirements .met {
    color: var(--success-color);
}

.password-requirements .met::before {
    content: '✓ ';
    font-weight: bold;
}

/* Animation */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

.sidebar-card, .registration-card, .info-card {
    animation: fadeIn .3s ease-out;
}

.alert {
    animation: fadeIn .3s ease-out;
}
";

// Header einbinden
include 'header.inc.php';
?>

<div class="container-fluid">
  <div class="row">
    <div class="col-xl-8 col-lg-11 col-12 ps-0">
      <!-- Außen-Container -->
      <div class="main-content-wrapper">
        <!-- Header-Zeile -->
        <div class="row mb-4">
          <div class="col-md-12">
            <h2 class="h4 mb-0" style="color: var(--secondary-color);">
              <i class="bi bi-person-plus me-2"></i> Benutzerregistrierung
            </h2>
            <p class="text-muted mb-0">Erstelle einen neuen Account für das Jahresmeisterschaft-System</p>
          </div>
        </div>

        <!-- Weißer Hintergrund-Container -->
        <div class="content-background">
          <div class="row g-3">
            <!-- Linke Spalte: Registrierungsformular -->
            <div class="col-lg-6">
              <div class="registration-card">
                <h5 class="card-title">
                  <i class="bi bi-card-text"></i>
                  Account-Informationen
                </h5>
                
                <?php if ($success): ?>
                  <div class="alert alert-success d-flex align-items-center" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <div><?php echo htmlspecialchars($success); ?></div>
                  </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                  <div class="alert alert-danger d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <div><?php echo htmlspecialchars($error); ?></div>
                  </div>
                <?php endif; ?>

                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="registrationForm" autocomplete="off">
                  <div class="mb-3">
                    <label for="username" class="form-label">
                      <i class="bi bi-person me-1"></i>Benutzername
                    </label>
                    <input type="text" 
                           class="form-control" 
                           id="username" 
                           name="username" 
                           value=""
                           placeholder="Wähle einen eindeutigen Benutzernamen"
                           required>
                    <small class="form-text text-muted">Mindestens 3 Zeichen, nur Buchstaben, Zahlen und Unterstriche</small>
                  </div>

                  <div class="mb-3">
                    <label for="email" class="form-label">
                      <i class="bi bi-envelope me-1"></i>E-Mail-Adresse
                    </label>
                    <input type="email" 
                           class="form-control" 
                           id="email" 
                           name="email" 
                           value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>"
                           placeholder="deine.email@beispiel.ch"
                           required>
                    <small class="form-text text-muted">Wird für Passwort-Wiederherstellung benötigt</small>
                  </div>

                  <div class="mb-3">
                    <label for="password" class="form-label">
                      <i class="bi bi-lock me-1"></i>Passwort
                    </label>
                    <input type="password" 
                           class="form-control" 
                           id="password" 
                           name="password" 
                           placeholder="Wähle ein sicheres Passwort"
                           required>
                    <div class="password-strength">
                      <div class="password-strength-bar" id="strengthBar"></div>
                    </div>
                  </div>

                  <div class="mb-4">
                    <label for="password_confirm" class="form-label">
                      <i class="bi bi-lock-fill me-1"></i>Passwort bestätigen
                    </label>
                    <input type="password" 
                           class="form-control" 
                           id="password_confirm" 
                           name="password_confirm" 
                           placeholder="Passwort wiederholen"
                           required>
                  </div>

                  <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                      <i class="bi bi-person-plus me-2"></i>Account erstellen
                    </button>
                    <a href="login.php" class="btn btn-outline-secondary">
                      <i class="bi bi-box-arrow-in-right me-2"></i>Bereits registriert? Zur Anmeldung
                    </a>
                  </div>
                </form>
              </div>
            </div>

            <!-- Rechte Spalte: Informationen -->
            <div class="col-lg-6">
              <!-- Passwort-Anforderungen -->
              <div class="info-card">
                <h5 class="card-title">
                  <i class="bi bi-shield-check"></i>
                  Passwort-Anforderungen
                </h5>
                <ul class="password-requirements list-unstyled" id="requirements">
                  <li id="req-length">Mindestens 8 Zeichen</li>
                  <li id="req-uppercase">Mindestens ein Großbuchstabe</li>
                  <li id="req-lowercase">Mindestens ein Kleinbuchstabe</li>
                  <li id="req-number">Mindestens eine Zahl</li>
                  <li id="req-special">Mindestens ein Sonderzeichen (!@#$%^&*)</li>
                </ul>
              </div>

              <!-- Hinweise -->
              <div class="sidebar-card">
                <h5 class="card-title">
                  <i class="bi bi-info-circle"></i>
                  Wichtige Hinweise
                </h5>
                <ul class="mb-0">
                  <li>Deine Daten werden sicher verschlüsselt gespeichert</li>
                  <li>Die E-Mail-Adresse wird nur für systembezogene Nachrichten verwendet</li>
                  <li>Der Benutzername kann nach der Registrierung nicht mehr geändert werden</li>
                  <li>Bei Problemen wende dich an den Administrator</li>
                </ul>
              </div>

              <!-- Datenschutz -->
              <div class="sidebar-card">
                <h5 class="card-title">
                  <i class="bi bi-lock"></i>
                  Datenschutz
                </h5>
                <p class="text-muted mb-0">
                  Mit der Registrierung akzeptierst du unsere Datenschutzbestimmungen. 
                  Deine persönlichen Daten werden ausschließlich für die Verwaltung 
                  deines Accounts und die Teilnahme an der Jahresmeisterschaft verwendet.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('password_confirm');
    const strengthBar = document.getElementById('strengthBar');
    const form = document.getElementById('registrationForm');
    
    // Passwort-Stärke und Anforderungen prüfen
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        
        // Anforderungen prüfen
        const requirements = {
            'req-length': password.length >= 8,
            'req-uppercase': /[A-Z]/.test(password),
            'req-lowercase': /[a-z]/.test(password),
            'req-number': /[0-9]/.test(password),
            'req-special': /[!@#$%^&*(),.?":{}|<>]/.test(password)
        };
        
        // Anforderungen visuell updaten
        for (const [id, met] of Object.entries(requirements)) {
            const element = document.getElementById(id);
            if (met) {
                element.classList.add('met');
                strength++;
            } else {
                element.classList.remove('met');
            }
        }
        
        // Stärke-Balken updaten
        const percentage = (strength / 5) * 100;
        strengthBar.style.width = percentage + '%';
        
        if (strength <= 2) {
            strengthBar.style.backgroundColor = '#dc3545'; // rot
        } else if (strength <= 3) {
            strengthBar.style.backgroundColor = '#ffc107'; // gelb
        } else if (strength <= 4) {
            strengthBar.style.backgroundColor = '#17a2b8'; // blau
        } else {
            strengthBar.style.backgroundColor = '#28a745'; // grün
        }
    });
    
    // Passwort-Bestätigung prüfen
    confirmInput.addEventListener('input', function() {
        if (this.value !== passwordInput.value) {
            this.setCustomValidity('Die Passwörter stimmen nicht überein');
        } else {
            this.setCustomValidity('');
        }
    });
    
    // Username-Validierung
    const usernameInput = document.getElementById('username');
    usernameInput.addEventListener('input', function() {
        const username = this.value;
        if (username.length < 3) {
            this.setCustomValidity('Der Benutzername muss mindestens 3 Zeichen lang sein');
        } else if (!/^[a-zA-Z0-9_]+$/.test(username)) {
            this.setCustomValidity('Nur Buchstaben, Zahlen und Unterstriche erlaubt');
        } else {
            this.setCustomValidity('');
        }
    });
    
    // Form-Validierung vor dem Absenden
    form.addEventListener('submit', function(e) {
        const password = passwordInput.value;
        const confirmPassword = confirmInput.value;
        
        if (password !== confirmPassword) {
            e.preventDefault();
            confirmInput.setCustomValidity('Die Passwörter stimmen nicht überein');
            confirmInput.reportValidity();
            return false;
        }
        
        if (password.length < 8) {
            e.preventDefault();
            passwordInput.setCustomValidity('Das Passwort muss mindestens 8 Zeichen lang sein');
            passwordInput.reportValidity();
            return false;
        }
    });
});
</script>

<?php include 'footer.inc.php'; ?>
