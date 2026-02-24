<?php
// Erst dbconnect einbinden für die connect_db Funktion
include 'dbconnect.inc.php';

// Seitenspezifische CSS definieren (vor header.inc.php!)
$page_specific_css = '
    .password-requirements {
        background: #f8f9fa;
        border-radius: 0.375rem;
        padding: 0.75rem 1rem;
        border-left: 3px solid #adb5bd;
    }

    #password-requirements-list li {
        transition: color 0.3s ease;
    }

    #password-requirements-list li.fulfilled {
        color: #28a745;
    }

    #password-requirements-list li.fulfilled i {
        color: #28a745;
    }

    #password-requirements-list li i {
        transition: all 0.3s ease;
        font-size: 0.8rem;
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
        transition: all 0.3s ease;
        border-radius: 2px;
    }

    .strength-weak   { background: #dc3545; }
    .strength-medium { background: #ffc107; }
    .strength-strong { background: #28a745; }

    .password-strength-text {
        font-weight: 500;
        transition: all 0.3s ease;
    }

    @keyframes pulse {
        0%   { transform: scale(1);   opacity: 1; }
        50%  { transform: scale(1.1); opacity: 0.8; }
        100% { transform: scale(1);   opacity: 1; }
    }
    .pulse-animation { animation: pulse 2s ease-in-out infinite; }

    .spinner-border-sm { width: 1rem; height: 1rem; }
';

// Dann header einbinden (startet Session und prüft Login)
include 'header.inc.php';

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $old_password         = $_POST['old_password'];
    $new_password         = $_POST['new_password'];
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
    } elseif ($new_password !== $new_password_confirm) {
        $error = "Die neuen Passwörter stimmen nicht überein.";
    } else {
        $password_errors = validate_password($new_password, $username);

        if (empty($password_errors)) {
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

            $update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $update_stmt->bind_param("si", $new_password_hash, $user_id);

            if ($update_stmt->execute()) {
                $success         = "Ihr Passwort wurde erfolgreich geändert.";
                $password_changed = true;
            } else {
                $error = "Fehler beim Ändern des Passworts. Bitte versuchen Sie es später erneut.";
            }
            $update_stmt->close();
        } else {
            $error = implode('<br>', $password_errors);
        }
    }
}

function validate_password($password, $username) {
    $errors = [];
    if (strlen($password) < 10) {
        $errors[] = "Das Passwort muss mindestens 10 Zeichen lang sein.";
    }
    $categories = 0;
    if (preg_match('/[A-Z]/', $password)) $categories++;
    if (preg_match('/[a-z]/', $password)) $categories++;
    if (preg_match('/[0-9]/', $password)) $categories++;
    if (preg_match('/[^A-Za-z0-9]/', $password)) $categories++;
    if ($categories < 3) {
        $errors[] = "Das Passwort muss mindestens 3 der folgenden Kategorien enthalten: Großbuchstaben, Kleinbuchstaben, Ziffern, Sonderzeichen.";
    }
    if (stripos($password, $username) !== false) {
        $errors[] = "Das Passwort darf den Benutzernamen nicht enthalten.";
    }
    return $errors;
}
?>

<div class="row">
    <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5 mx-auto">

        <div class="row mb-3">
            <div class="col">
                <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                    <i class="bi bi-key me-2"></i>Passwort ändern
                </h2>
            </div>
        </div>

        <div class="card">
            <div class="card-body">

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-circle me-2"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
                    </div>
                    <div class="text-center py-3">
                        <i class="bi bi-check-circle-fill text-success pulse-animation" style="font-size: 3.5rem;"></i>
                        <p class="text-muted mt-3">Weiterleitung in <span id="countdown">3</span> Sekunden...</p>
                    </div>
                    <script>
                    $(document).ready(function() {
                        let seconds = 3;
                        const timer = setInterval(function() {
                            seconds--;
                            $('#countdown').text(seconds);
                            if (seconds <= 0) {
                                clearInterval(timer);
                                const ref = document.referrer;
                                window.location.href = (ref && !ref.includes('password_change')) ? ref : 'home.php';
                            }
                        }, 1000);
                    });
                    </script>
                <?php else: ?>

                    <div class="password-requirements mb-3">
                        <h6 class="mb-2 small fw-semibold text-muted">
                            <i class="bi bi-info-circle me-1"></i>Passwort-Anforderungen
                        </h6>
                        <ul class="list-unstyled mb-0 small" id="password-requirements-list">
                            <li data-requirement="length"><i class="bi bi-circle me-2"></i>Mindestens 10 Zeichen</li>
                            <li data-requirement="categories" class="mt-1"><i class="bi bi-circle me-2"></i>Mindestens 3 Kategorien:
                                <ul class="list-unstyled ps-3 mt-1">
                                    <li data-requirement="uppercase"><i class="bi bi-circle me-2"></i>Großbuchstaben (A-Z)</li>
                                    <li data-requirement="lowercase"><i class="bi bi-circle me-2"></i>Kleinbuchstaben (a-z)</li>
                                    <li data-requirement="numbers"><i class="bi bi-circle me-2"></i>Ziffern (0–9)</li>
                                    <li data-requirement="special"><i class="bi bi-circle me-2"></i>Sonderzeichen</li>
                                </ul>
                            </li>
                            <li data-requirement="username" class="mt-1"><i class="bi bi-circle me-2"></i>Kein Benutzername im Passwort</li>
                        </ul>
                    </div>

                    <form method="post" action="" id="changePasswordForm">
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control" id="old_password" name="old_password" placeholder="Aktuelles Passwort" required>
                            <label for="old_password"><i class="bi bi-lock me-2"></i>Aktuelles Passwort</label>
                        </div>

                        <div class="form-floating mb-1">
                            <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Neues Passwort" required>
                            <label for="new_password"><i class="bi bi-key me-2"></i>Neues Passwort</label>
                        </div>
                        <div class="password-strength-indicator mb-1">
                            <div class="password-strength-bar" id="strengthBar"></div>
                        </div>
                        <small class="password-strength-text text-muted d-block mb-3" id="strengthText"></small>

                        <div class="form-floating mb-3">
                            <input type="password" class="form-control" id="new_password_confirm" name="new_password_confirm" placeholder="Neues Passwort bestätigen" required>
                            <label for="new_password_confirm"><i class="bi bi-shield-check me-2"></i>Neues Passwort bestätigen</label>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-secondary" id="changeBtn">
                                <i class="bi bi-shield-lock me-2"></i>Passwort ändern
                            </button>
                            <a href="home.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-2"></i>Abbrechen
                            </a>
                        </div>
                    </form>

                <?php endif; ?>

            </div>
        </div>

    </div>
</div>

<script>
// HIBP k-Anonymity: SHA-1 via SubtleCrypto, nur erste 5 Zeichen werden übermittelt
async function sha1Hex(str) {
    const buf = await crypto.subtle.digest('SHA-1', new TextEncoder().encode(str));
    return Array.from(new Uint8Array(buf)).map(b => b.toString(16).padStart(2, '0')).join('').toUpperCase();
}

async function checkHibp(password) {
    const hash   = await sha1Hex(password);
    const prefix = hash.substring(0, 5);
    const suffix = hash.substring(5);
    const resp   = await fetch('https://api.pwnedpasswords.com/range/' + prefix, {
        headers: { 'Add-Padding': 'true' }
    });
    if (!resp.ok) throw new Error('HIBP API nicht erreichbar');
    const text = await resp.text();
    for (const line of text.split('\r\n')) {
        const [s, count] = line.split(':');
        if (s && s.trim() === suffix) return parseInt(count.trim(), 10);
    }
    return 0;
}

$(document).ready(function() {

    function updatePasswordStrength(password) {
        const strengthBar  = $('#strengthBar');
        const strengthText = $('#strengthText');
        const username     = '<?php echo addslashes($username); ?>'.toLowerCase();

        const hasLength   = password.length >= 10;
        const hasUpper    = /[A-Z]/.test(password);
        const hasLower    = /[a-z]/.test(password);
        const hasNumbers  = /[0-9]/.test(password);
        const hasSpecial  = /[^A-Za-z0-9]/.test(password);
        const noUsername  = !password.toLowerCase().includes(username);

        updateReq('length',     hasLength);
        updateReq('uppercase',  hasUpper);
        updateReq('lowercase',  hasLower);
        updateReq('numbers',    hasNumbers);
        updateReq('special',    hasSpecial);
        updateReq('username',   noUsername);

        const categories = [hasUpper, hasLower, hasNumbers, hasSpecial].filter(Boolean).length;
        updateReq('categories', categories >= 3);

        if (password.length === 0) {
            strengthBar.css('width', '0%').removeClass('strength-weak strength-medium strength-strong');
            strengthText.text('').removeClass('text-danger text-warning text-success').addClass('text-muted');
            return;
        }

        let strength = 0;
        if (hasLength)        strength++;
        if (categories >= 2)  strength++;
        if (categories >= 3)  strength++;
        if (categories === 4) strength++;
        if (noUsername)       strength++;

        const pct = (strength / 5) * 100;
        strengthBar.css('width', pct + '%');

        if (!hasLength || !noUsername || categories < 3) {
            strengthBar.removeClass('strength-medium strength-strong').addClass('strength-weak');
            strengthText.removeClass('text-warning text-success text-muted').addClass('text-danger')
                .text(!hasLength ? 'Zu kurz – noch ' + (10 - password.length) + ' Zeichen'
                    : !noUsername ? 'Passwort enthält Benutzernamen!'
                    : 'Schwach – mehr Zeichentypen verwenden');
        } else if (pct < 80) {
            strengthBar.removeClass('strength-weak strength-strong').addClass('strength-medium');
            strengthText.removeClass('text-danger text-success text-muted').addClass('text-warning').text('Mittel – kann noch besser werden');
        } else {
            strengthBar.removeClass('strength-weak strength-medium').addClass('strength-strong');
            strengthText.removeClass('text-danger text-warning text-muted').addClass('text-success').text('Stark – ausgezeichnetes Passwort!');
        }
    }

    function updateReq(requirement, fulfilled) {
        const el   = $('[data-requirement="' + requirement + '"]');
        const icon = el.children('i').first();
        const typing = $('#new_password').val().length > 0;
        if (fulfilled) {
            el.addClass('fulfilled');
            icon.removeClass('bi-circle bi-x-circle').addClass('bi-check-circle-fill');
        } else if (typing) {
            el.removeClass('fulfilled');
            icon.removeClass('bi-circle bi-check-circle-fill').addClass('bi-x-circle');
        } else {
            el.removeClass('fulfilled');
            icon.removeClass('bi-x-circle bi-check-circle-fill').addClass('bi-circle');
        }
    }

    $('#new_password').on('input', function() {
        updatePasswordStrength($(this).val());
        const confirm = $('#new_password_confirm').val();
        if (confirm) {
            $('#new_password_confirm').toggleClass('is-invalid', $(this).val() !== confirm);
        }
    });

    $('#new_password_confirm').on('input', function() {
        $(this).toggleClass('is-invalid', $(this).val() !== $('#new_password').val());
    });

    $('#changePasswordForm').on('submit', async function(e) {
        e.preventDefault();
        const oldPw  = $('#old_password').val();
        const newPw  = $('#new_password').val();
        const confPw = $('#new_password_confirm').val();
        const errors = [];

        if (newPw.length < 10) errors.push('Das Passwort muss mindestens 10 Zeichen lang sein.');

        let cats = 0;
        if (/[A-Z]/.test(newPw)) cats++;
        if (/[a-z]/.test(newPw)) cats++;
        if (/[0-9]/.test(newPw)) cats++;
        if (/[^A-Za-z0-9]/.test(newPw)) cats++;
        if (cats < 3) errors.push('Mindestens 3 Zeichenkategorien erforderlich.');
        if (newPw !== confPw) errors.push('Die neuen Passwörter stimmen nicht überein.');
        if (oldPw === newPw)  errors.push('Das neue Passwort darf nicht mit dem alten identisch sein.');

        if (errors.length > 0) { msvToast(errors.join('<br>'), 'error'); return; }

        const $btn = $('#changeBtn');
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Wird geprüft...');

        try {
            const count = await checkHibp(newPw);
            if (count > 0) {
                $btn.prop('disabled', false).html('<i class="bi bi-shield-lock me-2"></i>Passwort ändern');
                const confirmed = await msvConfirm(
                    'Dieses Passwort wurde in <strong>' + count.toLocaleString('de-CH') + '</strong> bekannten Datenlecks gefunden. Wir empfehlen, ein anderes Passwort zu wählen.<br><small class="text-muted">Quelle: HaveIBeenPwned.com</small>',
                    'Passwort kompromittiert!',
                    'Trotzdem verwenden'
                );
                if (!confirmed.isConfirmed) return;
                $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Wird geändert...');
            } else {
                $btn.html('<span class="spinner-border spinner-border-sm me-2"></span>Wird geändert...');
            }
        } catch (err) {
            msvToast('Datenleck-Prüfung nicht verfügbar – Passwort wird trotzdem gespeichert.', 'warning');
        }

        this.submit();
    });
});
</script>

<?php include 'footer.inc.php'; ?>
