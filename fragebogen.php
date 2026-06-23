<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
// reCAPTCHA Site Key aus zentraler Konfiguration
$config = require __DIR__ . '/../msvjm_config.php';
$recaptcha_site_key = $config['recaptcha']['site_key'] ?? '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fragebogen - MSV Wilen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2c3e50;
            --primary-light: #34495e;
            --accent: #3498db;
            --accent-hover: #2980b9;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --text: #333;
            --text-muted: #6c757d;
            --bg: #f5f7fa;
            --white: #fff;
            --border: #e0e4e8;
            --radius: 8px;
            --shadow: 0 2px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 32px rgba(0,0,0,0.12);
            --transition: 0.3s ease;
        }

        * { box-sizing: border-box; }

        body {
            background: var(--bg);
            min-height: 100vh;
            padding: 1.5rem 1rem;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text);
        }

        .fragebogen-card {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            max-width: 640px;
            margin: 0 auto;
            overflow: hidden;
            animation: fadeInUp 0.5s ease-out;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card-header-custom {
            background: linear-gradient(135deg, #dee2e6, #adb5bd);
            color: #343a40;
            padding: 1.5rem 2rem;
            text-align: center;
        }
        .card-header-custom .logo {
            max-height: 70px;
            margin-bottom: 0.75rem;
        }
        .card-header-custom h1 {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        .card-header-custom p {
            margin: 0;
            opacity: 0.85;
            font-size: 0.95rem;
        }

        .card-body-custom {
            padding: 2rem;
        }

        /* --- Schritte (Wizard-Look) --- */
        .step { display: none; animation: fadeInUp 0.4s ease-out; }
        .step.active { display: block; }

        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .step-dot {
            width: 10px; height: 10px;
            border-radius: 50%;
            background: var(--border);
            transition: background var(--transition);
        }
        .step-dot.active { background: var(--accent); }
        .step-dot.done { background: var(--success); }

        /* --- Form Elements --- */
        .form-label {
            font-weight: 600;
            color: var(--primary);
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
        }
        .form-select, .form-control {
            border: 2px solid var(--border);
            border-radius: 6px;
            padding: 0.65rem 0.75rem;
            font-size: 0.95rem;
            transition: border-color var(--transition), box-shadow var(--transition);
        }
        .form-select:focus, .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(52,152,219,0.15);
        }

        /* --- Buttons --- */
        .btn-primary-custom {
            background: var(--accent);
            border: none;
            color: var(--white);
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            width: 100%;
            transition: background var(--transition), transform var(--transition);
        }
        .btn-primary-custom:hover:not(:disabled) {
            background: var(--accent-hover);
            transform: translateY(-1px);
            color: var(--white);
        }
        .btn-primary-custom:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }

        .btn-success-custom {
            background: var(--success);
            border: none;
            color: var(--white);
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            width: 100%;
            transition: background var(--transition), transform var(--transition);
        }
        .btn-success-custom:hover:not(:disabled) {
            background: #218838;
            transform: translateY(-1px);
            color: var(--white);
        }

        .btn-outline-custom {
            background: transparent;
            border: 2px solid var(--border);
            color: var(--text-muted);
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: all var(--transition);
        }
        .btn-outline-custom:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        /* --- Fragebogen-Felder --- */
        .question-group {
            background: var(--bg);
            border-radius: 6px;
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
            border-left: 3px solid var(--accent);
        }
        .question-group label {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--primary);
            margin-bottom: 0.4rem;
            display: block;
        }
        .question-group .form-select {
            font-size: 0.9rem;
        }

        /* Farbige Selects */
        .select-teil { background-color: #d4edda !important; border-color: var(--success) !important; color: #155724 !important; }
        .select-nicht { background-color: #f8d7da !important; border-color: var(--danger) !important; color: #721c24 !important; }
        .select-evtl { background-color: #fff3cd !important; border-color: var(--warning) !important; color: #856404 !important; }
        .select-ja { background-color: #d4edda !important; border-color: var(--success) !important; color: #155724 !important; }
        .select-nein { background-color: #f8d7da !important; border-color: var(--danger) !important; color: #721c24 !important; }

        /* --- Alert --- */
        .alert-custom {
            border: none;
            border-radius: 6px;
            padding: 0.75rem 1rem;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 1rem;
        }
        .alert-danger-custom {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-left: 3px solid var(--danger);
        }
        .alert-success-custom {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-left: 3px solid var(--success);
        }

        /* --- Erfolgs-Screen --- */
        .success-screen {
            text-align: center;
            padding: 2rem 1rem;
        }
        .success-icon {
            font-size: 4rem;
            color: var(--success);
            margin-bottom: 1rem;
        }

        /* --- Spinner --- */
        .spinner-sm {
            width: 1rem; height: 1rem;
            border: 0.15em solid currentColor;
            border-right-color: transparent;
            border-radius: 50%;
            animation: spin 0.75s linear infinite;
            display: inline-block;
            vertical-align: middle;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* --- Footer --- */
        .page-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        .page-footer a {
            color: var(--text-muted);
            text-decoration: none;
        }
        .page-footer a:hover { color: var(--primary); }

        /* Honeypot */
        .hp-field { position: absolute; left: -9999px; opacity: 0; height: 0; }

        /* reCAPTCHA Badge ausblenden */
        .grecaptcha-badge { visibility: hidden !important; }

        /* Responsive */
        @media (max-width: 576px) {
            .card-body-custom { padding: 1.25rem; }
            .card-header-custom { padding: 1.25rem; }
            .card-header-custom h1 { font-size: 1.2rem; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="fragebogen-card">
        <div class="card-header-custom">
            <img src="images/MSVWilen_Logo.jpg" alt="MSV Wilen Logo" class="logo">
            <h1><i class="bi bi-clipboard-check me-2"></i>Fragebogen</h1>
            <p>Jahresmeisterschaft &mdash; Teilnahme erfassen</p>
        </div>

        <div class="card-body-custom">
            <!-- Schritt-Indikatoren -->
            <div class="step-indicator">
                <div class="step-dot active" data-step="1"></div>
                <div class="step-dot" data-step="2"></div>
                <div class="step-dot" data-step="3"></div>
            </div>

            <!-- Fehlermeldung -->
            <div id="errorMsg" class="alert-custom alert-danger-custom" style="display:none;"></div>

            <!-- Honeypot -->
            <div class="hp-field">
                <label for="website">Website</label>
                <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
            </div>

            <!-- CSRF Token -->
            <input type="hidden" id="csrfToken" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <!-- ========== SCHRITT 1: Mitglied auswählen ========== -->
            <div class="step active" id="step1">
                <h5 class="mb-3"><i class="bi bi-person-circle me-2"></i>Mitgliedername auswählen</h5>
                <div class="mb-3">
                    <select id="memberSelect" class="form-select">
                        <option value="">Lade Mitglieder...</option>
                    </select>
                </div>
                <button type="button" class="btn btn-primary-custom" id="btnToStep2" disabled>
                    <i class="bi bi-arrow-right me-2"></i>Weiter
                </button>
            </div>

            <!-- ========== SCHRITT 2: Geburtsdatum verifizieren ========== -->
            <div class="step" id="step2">
                <h5 class="mb-3"><i class="bi bi-shield-check me-2"></i>Verifizierung</h5>
                <p class="text-muted mb-3">Bitte gib dein Geburtsdatum ein, um deine Identität zu bestätigen.</p>
                <div class="mb-3">
                    <label for="birthdate" class="form-label">Geburtsdatum</label>
                    <input type="date" class="form-control" id="birthdate" required>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-custom" id="btnBackToStep1">
                        <i class="bi bi-arrow-left me-1"></i>Zurück
                    </button>
                    <button type="button" class="btn btn-primary-custom flex-grow-1" id="btnVerify">
                        <i class="bi bi-check-circle me-2"></i>Verifizieren
                    </button>
                </div>
            </div>

            <!-- ========== SCHRITT 3: Fragebogen ========== -->
            <div class="step" id="step3">
                <h5 class="mb-1"><i class="bi bi-pencil-square me-2"></i>Fragebogen <span id="yearBadge" class="badge bg-secondary ms-1"></span></h5>
                <p class="text-muted mb-3" id="memberNameDisplay"></p>

                <form id="fragebogenForm">
                    <div id="fragebogenFields">
                        <!-- Dynamisch befüllt -->
                    </div>

                    <button type="submit" class="btn btn-success-custom mt-3" id="btnSave">
                        <i class="bi bi-save me-2"></i>Speichern
                    </button>
                </form>

            </div>

            <!-- ========== ERFOLGS-SCREEN ========== -->
            <div class="step" id="stepSuccess">
                <div class="success-screen">
                    <div class="success-icon"><i class="bi bi-check-circle-fill"></i></div>
                    <h4>Vielen Dank!</h4>
                    <p class="text-muted">Dein Fragebogen wurde erfolgreich gespeichert.</p>
                </div>
            </div>

        </div>
    </div>

    <div class="page-footer">
        <a href="index.php"><i class="bi bi-arrow-left me-1"></i>Zurück zur Startseite</a>
        <br>
        <small class="mt-1 d-block">
            <i class="bi bi-shield-check me-1"></i>Geschützt durch reCAPTCHA
        </small>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://www.google.com/recaptcha/api.js?render=<?php echo htmlspecialchars($recaptcha_site_key); ?>"></script>

<script>
$(function() {
    const RECAPTCHA_SITE_KEY = '<?php echo htmlspecialchars($recaptcha_site_key); ?>';
    const basePath = 'inc/fragebogen_public/';

    let verifyToken = '';
    let currentYear = 0;
    let selectedMemberId = 0;
    let selectedMemberName = '';

    // --- Hilfsfunktionen ---

    function showError(msg) {
        $('#errorMsg').html('<i class="bi bi-exclamation-circle me-2"></i>' + msg).show();
        setTimeout(() => $('#errorMsg').fadeOut(400), 6000);
    }

    function hideError() {
        $('#errorMsg').hide();
    }

    function goToStep(step) {
        hideError();
        $('.step').removeClass('active');
        $('#step' + step).addClass('active');
        // Dots aktualisieren
        $('.step-dot').each(function() {
            const s = $(this).data('step');
            $(this).removeClass('active done');
            if (s < step) $(this).addClass('done');
            if (s === step) $(this).addClass('active');
        });
    }

    function setButtonLoading($btn, loading) {
        if (loading) {
            $btn.data('original-html', $btn.html());
            $btn.prop('disabled', true).html('<span class="spinner-sm me-2"></span>Bitte warten...');
        } else {
            $btn.prop('disabled', false).html($btn.data('original-html'));
        }
    }

    function updateSelectColor(el) {
        const $el = $(el);
        const val = $el.val();
        $el.removeClass('select-teil select-nicht select-evtl select-ja select-nein');
        if (val === 'teil') $el.addClass('select-teil');
        else if (val === 'nicht') $el.addClass('select-nicht');
        else if (val === 'evtl') $el.addClass('select-evtl');
        else if (val === 'ja') $el.addClass('select-ja');
        else if (val === 'nein') $el.addClass('select-nein');
    }

    function getRecaptchaToken(action) {
        return new Promise((resolve) => {
            if (typeof grecaptcha !== 'undefined') {
                grecaptcha.ready(function() {
                    grecaptcha.execute(RECAPTCHA_SITE_KEY, { action: action })
                        .then(resolve)
                        .catch(() => resolve(''));
                });
            } else {
                resolve('');
            }
        });
    }

    // --- Schritt 1: Mitglieder laden ---

    $.getJSON(basePath + 'load_members.php')
        .done(function(resp) {
            if (resp.success && resp.members) {
                const $sel = $('#memberSelect').empty();
                $sel.append('<option value="">-- Bitte auswählen --</option>');
                resp.members.forEach(function(m) {
                    $sel.append($('<option>').val(m.id).text(m.name));
                });
            }
        })
        .fail(function() {
            $('#memberSelect').html('<option value="">Fehler beim Laden</option>');
        });

    $('#memberSelect').on('change', function() {
        const val = $(this).val();
        $('#btnToStep2').prop('disabled', !val);
    });

    $('#btnToStep2').on('click', function() {
        selectedMemberId = parseInt($('#memberSelect').val());
        selectedMemberName = $('#memberSelect option:selected').text();
        if (!selectedMemberId) return;
        goToStep(2);
        $('#birthdate').focus();
    });

    // --- Schritt 2: Verifizierung ---

    $('#btnBackToStep1').on('click', function() {
        goToStep(1);
    });

    $('#btnVerify').on('click', async function() {
        const birthdate = $('#birthdate').val();
        if (!birthdate) {
            showError('Bitte Geburtsdatum eingeben.');
            return;
        }

        const $btn = $(this);
        setButtonLoading($btn, true);

        const recaptchaToken = await getRecaptchaToken('verify');

        $.post(basePath + 'verify.php', {
            mitglied_id: selectedMemberId,
            geburtsdatum: birthdate,
            csrf_token: $('#csrfToken').val(),
            website: $('#website').val(),
            'g-recaptcha-response': recaptchaToken
        })
        .done(function(resp) {
            if (resp.success) {
                verifyToken = resp.token;
                currentYear = resp.year;
                buildForm(resp.waffen, resp.defs, resp.existing);
                goToStep(3);
            } else {
                showError(resp.message || 'Verifizierung fehlgeschlagen.');
            }
        })
        .fail(function(xhr) {
            let msg = 'Verbindungsfehler. Bitte versuche es erneut.';
            try {
                const resp = JSON.parse(xhr.responseText);
                if (resp.message) msg = resp.message;
            } catch(e) {}
            showError(msg);
        })
        .always(function() {
            setButtonLoading($btn, false);
        });
    });

    // Enter-Taste bei Geburtsdatum
    $('#birthdate').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#btnVerify').click();
        }
    });

    // --- Schritt 3: Fragebogen aufbauen ---

    function buildForm(waffen, defs, existing) {
        $('#yearBadge').text(currentYear);
        $('#memberNameDisplay').html('<i class="bi bi-person me-1"></i>' + escapeHtml(selectedMemberName));

        let html = '';

        // Waffe
        html += '<div class="question-group">';
        html += '<label><i class="bi bi-crosshair me-1"></i>Mit welcher Waffe nimmst du an der Jahresmeisterschaft teil?</label>';
        html += '<select name="waffenID" class="form-select">';
        html += '<option value="0"' + (existing.waffenID === 0 ? ' selected' : '') + '>Nehme nicht teil</option>';
        waffen.forEach(function(w) {
            const sel = (w.id === existing.waffenID) ? 'selected' : '';
            html += '<option value="' + w.id + '" ' + sel + '>' + escapeHtml(w.bezeichnung) + '</option>';
        });
        html += '</select></div>';

        // Mannschaftsmeisterschaft
        html += '<div class="question-group">';
        html += '<label><i class="bi bi-people me-1"></i>Zentralschweizer Mannschaftsmeisterschaft (ZSMM)</label>';
        html += buildParticipationSelect('mannschaft', existing.mannschaft || 'nicht');
        html += '</div>';

        // Gruppenmeisterschaft
        html += '<div class="question-group">';
        html += '<label><i class="bi bi-people-fill me-1"></i>Gruppenmeisterschaft (GM)</label>';
        html += buildParticipationSelect('gruppen', existing.gruppen || 'nicht');
        html += '</div>';

        // Erweiterte Fragen
        if (defs.length > 0) {
            html += '<hr class="my-3">';
            html += '<p class="text-muted mb-2" style="font-size:0.85rem;"><i class="bi bi-list-check me-1"></i>Nimmst du an folgenden Anlässen teil?</p>';
            defs.forEach(function(d) {
                const currentVal = existing.erweitert[d.id] || 'nein';
                html += '<div class="question-group">';
                html += '<label>' + escapeHtml(d.bezeichnung) + '</label>';
                html += '<select name="erweitert[' + d.id + ']" class="form-select erweitert-select">';
                html += '<option value="nein"' + (currentVal === 'nein' ? ' selected' : '') + '>Nein</option>';
                html += '<option value="ja"' + (currentVal === 'ja' ? ' selected' : '') + '>Ja</option>';
                html += '</select></div>';
            });
        }

        $('#fragebogenFields').html(html);

        // Farben setzen
        $('#fragebogenFields select').each(function() { updateSelectColor(this); });
    }

    function buildParticipationSelect(name, currentVal) {
        let html = '<select name="' + name + '" class="form-select participation-select">';
        html += '<option value="teil"' + (currentVal === 'teil' ? ' selected' : '') + '>Ich nehme teil</option>';
        html += '<option value="nicht"' + (currentVal === 'nicht' ? ' selected' : '') + '>Ich nehme nicht teil</option>';
        html += '<option value="evtl"' + (currentVal === 'evtl' ? ' selected' : '') + '>Nur wenn Gruppe füllt</option>';
        html += '</select>';
        return html;
    }

    // Farbänderung bei Auswahl
    $(document).on('change', '.participation-select, .erweitert-select', function() {
        updateSelectColor(this);
    });

    // --- Schritt 3: Speichern ---

    $('#fragebogenForm').on('submit', async function(e) {
        e.preventDefault();

        const $btn = $('#btnSave');
        setButtonLoading($btn, true);

        const recaptchaToken = await getRecaptchaToken('save');

        const formData = $(this).serialize()
            + '&verify_token=' + encodeURIComponent(verifyToken)
            + '&csrf_token=' + encodeURIComponent($('#csrfToken').val())
            + '&year=' + currentYear
            + '&website=' + encodeURIComponent($('#website').val())
            + '&g-recaptcha-response=' + encodeURIComponent(recaptchaToken);

        $.post(basePath + 'save.php', formData)
            .done(function(resp) {
                if (resp.success) {
                    goToStep('Success');
                    // Success-Screen Dots
                    $('.step-dot').addClass('done').removeClass('active');
                } else {
                    showError(resp.message || 'Fehler beim Speichern.');
                }
            })
            .fail(function(xhr) {
                let msg = 'Verbindungsfehler. Bitte versuche es erneut.';
                try {
                    const resp = JSON.parse(xhr.responseText);
                    if (resp.message) msg = resp.message;
                } catch(e) {}
                showError(msg);
            })
            .always(function() {
                setButtonLoading($btn, false);
            });
    });

    // --- Navigation ---


    // --- Hilfsfunktion ---

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // --- JSON als Default für AJAX ---
    $.ajaxSetup({
        dataType: 'json'
    });
});
</script>
</body>
</html>
