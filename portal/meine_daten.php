<?php
// portal/meine_daten.php - Kontaktdaten bearbeiten
$portal_page_title = 'Meine Daten';

$portal_page_css = '
/* Karten/Header/Info/Felder kommen aus css/portal.css:
   .p-narrow .p-section .p-section-header .p-chip .p-info-row .p-field */

/* PLZ + Ort nebeneinander (Desktop) */
.md-row-plz-ort {
    display: flex;
    gap: 0.75rem;
}
.md-row-plz-ort .md-field-plz { flex: 0 0 120px; }
.md-row-plz-ort .md-field-ort { flex: 1; }

/* Speichern-Button: .p-btn.primary.block (css/portal.css) */

/* Mobile */
@media (max-width: 767.98px) {
    .md-row-plz-ort {
        flex-direction: column;
        gap: 0;
    }
    .md-row-plz-ort .md-field-plz { flex: none; }
}

/* Login-Sektion */
.md-login-display {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.4rem 0;
    font-size: 0.85rem;
}
.md-login-display .p-info-value {
    font-family: monospace;
    letter-spacing: 0.3px;
}
.md-change-link {
    background: none;
    border: none;
    color: var(--primary-color);
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    transition: background 0.15s;
    white-space: nowrap;
}
.md-change-link:hover {
    background: rgba(59, 89, 152, 0.08);
}
.md-change-form {
    display: none;
    margin-top: 0.5rem;
    padding-top: 0.5rem;
    border-top: 1px solid #f0f0f0;
}
.md-change-form.show { display: block; }
.md-change-form .md-btn-row {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.5rem;
}
.md-btn-sm {
    padding: 0.45rem 1rem;
    border-radius: 6px;
    font-size: 0.82rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.15s;
}
.md-btn-sm.primary { background: var(--primary-color); color: white; }
.md-btn-sm.primary:hover { background: #2d4373; }
.md-btn-sm.secondary { background: #f0f0f0; color: #495057; }
.md-btn-sm.secondary:hover { background: #e2e8f0; }
.md-btn-sm:disabled { opacity: 0.6; cursor: not-allowed; }
.md-pw-toggle {
    position: relative;
}
.md-pw-toggle input { padding-right: 2.5rem; }
.md-pw-toggle button {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #718096;
    font-size: 1rem;
    cursor: pointer;
    padding: 0.2rem;
}

/* Skeleton Loading */
.md-skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: mdShimmer 1.5s infinite;
    border-radius: 4px;
    height: 1em;
    display: inline-block;
}
@keyframes mdShimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
';

include 'portal_header.php';

$csrf_token = ensureCsrfToken();
?>

<div class="p-narrow">

    <!-- Persönliche Angaben (readonly) -->
    <div class="p-section">
        <div class="p-section-header">
            <div class="p-chip blue"><i class="bi bi-person"></i></div>
            <div class="p-section-title">Persönliche Angaben</div>
        </div>
        <div id="mdInfoBlock">
            <div class="p-info-row"><span class="p-info-label">Vorname</span><span class="p-info-value" id="mdVorname"><span class="md-skeleton" style="width:100px;">&nbsp;</span></span></div>
            <div class="p-info-row"><span class="p-info-label">Name</span><span class="p-info-value" id="mdName"><span class="md-skeleton" style="width:80px;">&nbsp;</span></span></div>
            <div class="p-info-row"><span class="p-info-label">Geboren</span><span class="p-info-value" id="mdGeburtsdatum"><span class="md-skeleton" style="width:80px;">&nbsp;</span></span></div>
            <div class="p-info-row"><span class="p-info-label">Waffe</span><span class="p-info-value" id="mdWaffe"><span class="md-skeleton" style="width:100px;">&nbsp;</span></span></div>
            <div class="p-info-row"><span class="p-info-label">Mitgl.-Nr.</span><span class="p-info-value" id="mdMitgliedNr"><span class="md-skeleton" style="width:60px;">&nbsp;</span></span></div>
        </div>
    </div>

    <!-- Adresse (editierbar) -->
    <div class="p-section">
        <div class="p-section-header">
            <div class="p-chip green"><i class="bi bi-house"></i></div>
            <div class="p-section-title">Adresse</div>
        </div>
        <div class="p-field">
            <label for="mdStrasse">Strasse & Nr.</label>
            <input type="text" id="mdStrasse" maxlength="255" autocomplete="street-address">
        </div>
        <div class="md-row-plz-ort">
            <div class="p-field md-field-plz">
                <label for="mdPLZ">PLZ</label>
                <input type="text" id="mdPLZ" maxlength="4" inputmode="numeric" autocomplete="postal-code">
            </div>
            <div class="p-field md-field-ort">
                <label for="mdOrt">Ort</label>
                <input type="text" id="mdOrt" maxlength="100" autocomplete="address-level2">
            </div>
        </div>
    </div>

    <!-- Kontakt (editierbar) -->
    <div class="p-section">
        <div class="p-section-header">
            <div class="p-chip orange"><i class="bi bi-envelope"></i></div>
            <div class="p-section-title">Kontakt</div>
        </div>
        <div class="p-field">
            <label for="mdEmail">E-Mail</label>
            <input type="email" id="mdEmail" maxlength="255" inputmode="email" autocomplete="email">
        </div>
        <div class="p-field">
            <label for="mdTelefon">Telefon</label>
            <input type="tel" id="mdTelefon" maxlength="50" inputmode="tel" autocomplete="tel" placeholder="+41 79 123 45 67">
        </div>
        <div class="p-field">
            <label for="mdMobile">Mobile</label>
            <input type="tel" id="mdMobile" maxlength="50" inputmode="tel" autocomplete="tel" placeholder="+41 79 123 45 67">
        </div>
    </div>

    <!-- Speichern -->
    <button type="button" class="p-btn primary block" id="mdSaveBtn" onclick="saveMeineDaten()">
        <i class="bi bi-check-lg"></i> Änderungen speichern
    </button>

    <!-- Login-Daten -->
    <div class="p-section" style="margin-top: 1rem;">
        <div class="p-section-header">
            <div class="p-chip red"><i class="bi bi-shield-lock"></i></div>
            <div class="p-section-title">Login-Daten</div>
        </div>

        <!-- Benutzername -->
        <div class="md-login-display">
            <div>
                <span class="p-info-label">Benutzer</span>
                <span class="p-info-value" id="mdUsername"><span class="md-skeleton" style="width:100px;">&nbsp;</span></span>
            </div>
            <button type="button" class="md-change-link" onclick="toggleForm('username')">Ändern</button>
        </div>
        <div class="md-change-form" id="mdUsernameForm">
            <div class="p-field">
                <label for="mdNewUsername">Neuer Benutzername</label>
                <input type="text" id="mdNewUsername" maxlength="50" autocomplete="username">
            </div>
            <div class="p-field md-pw-toggle">
                <label for="mdUsernamePw">Aktuelles Passwort</label>
                <input type="password" id="mdUsernamePw" autocomplete="current-password">
                <button type="button" onclick="togglePw(this)" tabindex="-1"><i class="bi bi-eye"></i></button>
            </div>
            <div class="md-btn-row">
                <button type="button" class="md-btn-sm primary" id="mdSaveUsername" onclick="changeUsername()">Speichern</button>
                <button type="button" class="md-btn-sm secondary" onclick="toggleForm('username')">Abbrechen</button>
            </div>
        </div>

        <!-- Passwort -->
        <div class="md-login-display" style="border-top: 1px solid #f0f0f0; margin-top: 0.3rem; padding-top: 0.5rem;">
            <div>
                <span class="p-info-label">Passwort</span>
                <span class="p-info-value" style="color:#718096;">********</span>
            </div>
            <button type="button" class="md-change-link" onclick="toggleForm('password')">Ändern</button>
        </div>
        <div class="md-change-form" id="mdPasswordForm">
            <div class="p-field md-pw-toggle">
                <label for="mdCurrentPw">Aktuelles Passwort</label>
                <input type="password" id="mdCurrentPw" autocomplete="current-password">
                <button type="button" onclick="togglePw(this)" tabindex="-1"><i class="bi bi-eye"></i></button>
            </div>
            <div class="p-field md-pw-toggle">
                <label for="mdNewPw">Neues Passwort</label>
                <input type="password" id="mdNewPw" autocomplete="new-password">
                <button type="button" onclick="togglePw(this)" tabindex="-1"><i class="bi bi-eye"></i></button>
            </div>
            <div class="p-field md-pw-toggle">
                <label for="mdConfirmPw">Neues Passwort bestätigen</label>
                <input type="password" id="mdConfirmPw" autocomplete="new-password">
                <button type="button" onclick="togglePw(this)" tabindex="-1"><i class="bi bi-eye"></i></button>
            </div>
            <div class="md-btn-row">
                <button type="button" class="md-btn-sm primary" id="mdSavePw" onclick="changePassword()">Speichern</button>
                <button type="button" class="md-btn-sm secondary" onclick="toggleForm('password')">Abbrechen</button>
            </div>
        </div>
    </div>
</div>

<script src="../inc/js/msv-phone.js"></script>
<script>
(function() {
    var csrfToken = <?php echo json_encode($csrf_token); ?>;

    // ================================================================
    // Daten laden
    // ================================================================
    function clearSkeletons() {
        ['mdVorname','mdName','mdGeburtsdatum','mdWaffe','mdMitgliedNr','mdUsername'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el && el.querySelector('.md-skeleton')) el.textContent = '–';
        });
    }

    function loadData() {
        fetch('../api/meine_daten_api.php')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) {
                    msvToast(data.message || 'Fehler beim Laden', 'error');
                    clearSkeletons();
                    return;
                }
                var d = data.data;
                document.getElementById('mdVorname').textContent      = d.vorname;
                document.getElementById('mdName').textContent         = d.name;
                document.getElementById('mdGeburtsdatum').textContent = d.geburtsdatum;
                document.getElementById('mdWaffe').textContent        = d.waffe;
                document.getElementById('mdMitgliedNr').textContent   = d.mitglied_nr;

                document.getElementById('mdStrasse').value = d.strasse;
                document.getElementById('mdPLZ').value     = d.plz;
                document.getElementById('mdOrt').value     = d.ort;
                document.getElementById('mdEmail').value   = d.email;
                document.getElementById('mdTelefon').value = d.telefon;
                document.getElementById('mdMobile').value  = d.mobile;
                document.getElementById('mdUsername').textContent = d.username;
            })
            .catch(function() {
                msvToast('Verbindungsfehler beim Laden der Daten.', 'error');
                clearSkeletons();
            });
    }

    // ================================================================
    // Client-Validierung
    // ================================================================
    function validate() {
        var errors = {};
        var plz = document.getElementById('mdPLZ').value.trim();
        var email = document.getElementById('mdEmail').value.trim();

        if (plz && !/^\d{4}$/.test(plz)) {
            errors.plz = 'Bitte eine gültige 4-stellige PLZ eingeben.';
        }
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            errors.email = 'Bitte eine gültige E-Mail-Adresse eingeben.';
        }

        var telefon = document.getElementById('mdTelefon').value.trim();
        var mobile  = document.getElementById('mdMobile').value.trim();
        if (telefon && !isValidSwissPhone(telefon)) {
            errors.telefon = 'Bitte Format +41 79 123 45 67 verwenden.';
        }
        if (mobile && !isValidSwissPhone(mobile)) {
            errors.mobile = 'Bitte Format +41 79 123 45 67 verwenden.';
        }

        // Fehler anzeigen / entfernen
        ['mdPLZ', 'mdEmail', 'mdTelefon', 'mdMobile'].forEach(function(id) {
            var el = document.getElementById(id);
            var keyMap = { mdPLZ: 'plz', mdEmail: 'email', mdTelefon: 'telefon', mdMobile: 'mobile' };
            var key = keyMap[id];
            var fb = el.parentElement.querySelector('.invalid-feedback');
            if (errors[key]) {
                el.classList.add('is-invalid');
                if (!fb) {
                    fb = document.createElement('div');
                    fb.className = 'invalid-feedback';
                    el.parentElement.appendChild(fb);
                }
                fb.textContent = errors[key];
            } else {
                el.classList.remove('is-invalid');
                if (fb) fb.remove();
            }
        });

        return Object.keys(errors).length === 0;
    }

    // ================================================================
    // Speichern
    // ================================================================
    window.saveMeineDaten = function() {
        if (!validate()) return;

        var btn = document.getElementById('mdSaveBtn');
        var origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Speichern...';

        var payload = {
            csrf_token: csrfToken,
            strasse:    document.getElementById('mdStrasse').value.trim(),
            plz:        document.getElementById('mdPLZ').value.trim(),
            ort:        document.getElementById('mdOrt').value.trim(),
            email:      document.getElementById('mdEmail').value.trim(),
            telefon:    document.getElementById('mdTelefon').value.trim(),
            mobile:     document.getElementById('mdMobile').value.trim()
        };

        fetch('../api/meine_daten_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                msvToast(data.message, 'success');
            } else {
                msvToast(data.message || 'Fehler beim Speichern', 'error');
                // Feld-Fehler markieren
                if (data.errors) {
                    var fieldMap = { strasse: 'mdStrasse', plz: 'mdPLZ', ort: 'mdOrt', email: 'mdEmail', telefon: 'mdTelefon', mobile: 'mdMobile' };
                    Object.keys(data.errors).forEach(function(key) {
                        var el = document.getElementById(fieldMap[key]);
                        if (el) {
                            el.classList.add('is-invalid');
                            var fb = el.parentElement.querySelector('.invalid-feedback');
                            if (!fb) {
                                fb = document.createElement('div');
                                fb.className = 'invalid-feedback';
                                el.parentElement.appendChild(fb);
                            }
                            fb.textContent = data.errors[key];
                        }
                    });
                }
            }
        })
        .catch(function() {
            msvToast('Verbindungsfehler. Bitte versuche es erneut.', 'error');
        })
        .finally(function() {
            btn.disabled = false;
            btn.innerHTML = origHtml;
        });
    };

    // ================================================================
    // Input-Fehler bei Eingabe entfernen
    // ================================================================
    document.querySelectorAll('.md-field input').forEach(function(input) {
        input.addEventListener('input', function() {
            this.classList.remove('is-invalid');
            var fb = this.parentElement.querySelector('.invalid-feedback');
            if (fb) fb.remove();
        });
    });

    // ================================================================
    // Telefon-Felder: Auto-Format bei blur
    // ================================================================
    ['mdTelefon', 'mdMobile'].forEach(function(id) {
        document.getElementById(id).addEventListener('blur', function() {
            var v = this.value.trim();
            if (v) this.value = formatSwissPhone(v);
        });
    });

    // ================================================================
    // Login: Toggle Formulare
    // ================================================================
    window.toggleForm = function(type) {
        var form = document.getElementById(type === 'username' ? 'mdUsernameForm' : 'mdPasswordForm');
        form.classList.toggle('show');
        if (!form.classList.contains('show')) {
            // Felder leeren beim Schliessen
            form.querySelectorAll('input').forEach(function(i) { i.value = ''; i.classList.remove('is-invalid'); });
            form.querySelectorAll('.invalid-feedback').forEach(function(f) { f.remove(); });
        }
    };

    // ================================================================
    // Login: Passwort-Sichtbarkeit togglen
    // ================================================================
    window.togglePw = function(btn) {
        var input = btn.parentElement.querySelector('input');
        var icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'bi bi-eye';
        }
    };

    // ================================================================
    // Login: Benutzername ändern
    // ================================================================
    window.changeUsername = function() {
        var newUsername = document.getElementById('mdNewUsername').value.trim();
        var currentPw  = document.getElementById('mdUsernamePw').value;

        if (!newUsername) { msvToast('Bitte einen Benutzernamen eingeben.', 'error'); return; }
        if (newUsername.length < 3) { msvToast('Benutzername muss mindestens 3 Zeichen lang sein.', 'error'); return; }
        if (!currentPw) { msvToast('Bitte aktuelles Passwort eingeben.', 'error'); return; }

        var btn = document.getElementById('mdSaveUsername');
        btn.disabled = true;

        fetch('../api/meine_daten_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_token: csrfToken, action: 'change_username', new_username: newUsername, current_password: currentPw })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                msvToast(data.message, 'success');
                if (data.new_username) document.getElementById('mdUsername').textContent = data.new_username;
                toggleForm('username');
            } else {
                msvToast(data.message, 'error');
            }
        })
        .catch(function() { msvToast('Verbindungsfehler.', 'error'); })
        .finally(function() { btn.disabled = false; });
    };

    // ================================================================
    // Login: Passwort ändern
    // ================================================================
    window.changePassword = function() {
        var currentPw = document.getElementById('mdCurrentPw').value;
        var newPw     = document.getElementById('mdNewPw').value;
        var confirmPw = document.getElementById('mdConfirmPw').value;

        if (!currentPw || !newPw || !confirmPw) { msvToast('Bitte alle Passwort-Felder ausfüllen.', 'error'); return; }
        if (newPw.length < 8) { msvToast('Neues Passwort muss mindestens 8 Zeichen lang sein.', 'error'); return; }
        if (newPw !== confirmPw) { msvToast('Die neuen Passwörter stimmen nicht überein.', 'error'); return; }

        var btn = document.getElementById('mdSavePw');
        btn.disabled = true;

        fetch('../api/meine_daten_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_token: csrfToken, action: 'change_password', current_password: currentPw, new_password: newPw, confirm_password: confirmPw })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                msvToast(data.message, 'success');
                toggleForm('password');
            } else {
                msvToast(data.message, 'error');
            }
        })
        .catch(function() { msvToast('Verbindungsfehler.', 'error'); })
        .finally(function() { btn.disabled = false; });
    };

    // Init
    loadData();
})();
</script>

<?php include 'portal_footer.php'; ?>
