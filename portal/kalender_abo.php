<?php
// portal/kalender_abo.php - Kalender-Abo im Portal-Stil
$portal_page_title = 'Kalender-Abo';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
requireLogin();

$calendarUrl = "https://jahresmeisterschaft.msvwilen.ch/termine";
$webcalUrl   = "webcal://jahresmeisterschaft.msvwilen.ch/termine";

// Aktuelles Einsatz-Token laden
$einsatz_token      = null;
$einsatz_feed_url   = null;
$einsatz_webcal_url = null;
$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id) {
    $db   = getDB();
    $stmt = $db->prepare("SELECT calendar_token FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    if ($row && !empty($row['calendar_token'])) {
        $einsatz_token      = $row['calendar_token'];
        $scheme             = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host               = $_SERVER['HTTP_HOST'];
        $einsatz_feed_url   = $scheme . '://' . $host . '/einsatz_feed.php?token=' . $einsatz_token;
        $einsatz_webcal_url = 'webcal://' . $host . '/einsatz_feed.php?token=' . $einsatz_token;
    }
}

$csrf_token = ensureCsrfToken();

include 'portal_header.php';
?>

<style>
/* Karten-Container für die zwei Abschnitte */
.abo-card {
    background: white;
    border-radius: 0.85rem;
    padding: 1.1rem 1.2rem 1rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
    border: 1px solid #f0f0f0;
    margin-bottom: 1.1rem;
}
.abo-card-header {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    margin-bottom: 0.85rem;
}
.abo-card-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}
.abo-card-icon.personal { background: #fff8e1; color: #e65100; }
.abo-card-icon.club     { background: #e8f0fe; color: #3b5998; }
.abo-card-icon.help     { background: #f0f4f8; color: #718096; }
.abo-card-title {
    font-size: 0.88rem;
    font-weight: 700;
    color: #2d3748;
}
.abo-card-subtitle {
    font-size: 0.75rem;
    color: #718096;
    margin-top: 0.05rem;
}

/* Primär-Subscribe-Button */
.abo-subscribe-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    width: 100%;
    background: var(--primary-color);
    color: white;
    border: none;
    border-radius: 0.65rem;
    padding: 0.8rem;
    font-size: 0.97rem;
    font-weight: 600;
    text-decoration: none;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    margin-bottom: 0.75rem;
}
.abo-subscribe-btn:hover {
    background: #2d4373;
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(59,89,152,0.3);
}

/* URL-Zeile */
.abo-url-row {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-bottom: 0.5rem;
}
.abo-url-row code {
    flex: 1;
    background: #f5f6fa;
    padding: 0.45rem 0.7rem;
    border-radius: 6px;
    font-size: 0.7rem;
    word-break: break-all;
    border: 1px solid #e0e4e8;
    color: #4a5568;
    min-width: 0;
}
.abo-copy-btn {
    background: #edf2f7;
    color: #4a5568;
    border: none;
    border-radius: 6px;
    padding: 0.45rem 0.65rem;
    font-size: 0.8rem;
    cursor: pointer;
    white-space: nowrap;
    transition: background 0.15s;
    flex-shrink: 0;
}
.abo-copy-btn:hover  { background: #e2e8f0; color: #2d3748; }
.abo-copy-btn.copied { background: #c6f6d5; color: #276749; }

/* Neu-generieren-Link */
.abo-regen-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    color: #a0aec0;
    margin-top: 0.5rem;
}
.abo-regen-row button {
    background: none;
    border: none;
    color: #a0aec0;
    font-size: 0.75rem;
    cursor: pointer;
    padding: 0;
    text-decoration: underline;
    text-underline-offset: 2px;
}
.abo-regen-row button:hover { color: #718096; }

/* Info-Chip */
.abo-info-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.73rem;
    color: #718096;
    background: #f7fafc;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    padding: 0.2rem 0.6rem;
    margin-bottom: 0.75rem;
}
</style>

<!-- ============================================================
     KARTE 1: VEREINSKALENDER — öffentlicher Feed
     ============================================================ -->
<div class="abo-card">
    <div class="abo-card-header">
        <div class="abo-card-icon club"><i class="bi bi-calendar-event"></i></div>
        <div>
            <div class="abo-card-title">Vereinskalender</div>
            <div class="abo-card-subtitle">Schiesstage, Wettkämpfe &amp; Vereinstermine</div>
        </div>
    </div>

    <!-- Primär: webcal-Button -->
    <a href="<?php echo $webcalUrl; ?>" class="abo-subscribe-btn">
        <i class="bi bi-calendar-plus"></i> Kalender abonnieren
    </a>

    <!-- URL für Android/Desktop -->
    <div class="abo-url-row">
        <code id="calUrl"><?php echo htmlspecialchars($calendarUrl); ?></code>
        <button class="abo-copy-btn" onclick="copyUrl(this)" type="button">
            <i class="bi bi-clipboard"></i>
        </button>
    </div>
</div>

<!-- ============================================================
     KARTE 2: MEINE EINSÄTZE — persönlicher Feed
     ============================================================ -->
<div class="abo-card">
    <div class="abo-card-header">
        <div class="abo-card-icon personal"><i class="bi bi-person-badge"></i></div>
        <div>
            <div class="abo-card-title">Meine Einsätze</div>
            <div class="abo-card-subtitle">Persönliche Arbeitseinsätze — nur für dich</div>
        </div>
    </div>

    <?php if ($einsatz_feed_url): ?>

        <!-- Primär: webcal-Button für iOS -->
        <a href="<?php echo htmlspecialchars($einsatz_webcal_url); ?>" id="einsatzWebcalBtn" class="abo-subscribe-btn">
            <i class="bi bi-calendar-plus"></i> Kalender abonnieren
        </a>

        <!-- Sekundär: URL für Android/Desktop -->
        <div class="abo-url-row">
            <code id="einsatzFeedUrl"><?php echo htmlspecialchars($einsatz_feed_url); ?></code>
            <button class="abo-copy-btn" onclick="copyEinsatzUrl(this)" type="button">
                <i class="bi bi-clipboard"></i>
            </button>
        </div>

        <div class="abo-regen-row">
            <i class="bi bi-shield-lock-fill"></i>
            <span>Persönlicher Link —</span>
            <button onclick="regenerateEinsatzToken()" type="button">neu generieren</button>
        </div>

    <?php else: ?>

        <!-- Noch kein Token: Generieren-Button -->
        <button class="abo-subscribe-btn" onclick="generateEinsatzToken()" type="button" id="einsatzGenerateBtn">
            <i class="bi bi-calendar-plus"></i> Abo-Link erstellen
        </button>

        <!-- Wird nach Generierung eingeblendet -->
        <div id="einsatzUrlSection" style="display:none;">
            <a href="#" id="einsatzWebcalBtn" class="abo-subscribe-btn">
                <i class="bi bi-calendar-plus"></i> Kalender abonnieren
            </a>
            <div class="abo-url-row">
                <code id="einsatzFeedUrl"></code>
                <button class="abo-copy-btn" onclick="copyEinsatzUrl(this)" type="button">
                    <i class="bi bi-clipboard"></i>
                </button>
            </div>
            <div class="abo-regen-row">
                <i class="bi bi-shield-lock-fill"></i>
                <span>Persönlicher Link —</span>
                <button onclick="regenerateEinsatzToken()" type="button">neu generieren</button>
            </div>
        </div>

    <?php endif; ?>
</div>

<!-- ============================================================
     KARTE 3: MANUELLE EINRICHTUNG — gilt für beide Kalender
     ============================================================ -->
<div class="abo-card">
    <div class="abo-card-header">
        <div class="abo-card-icon help"><i class="bi bi-question-circle"></i></div>
        <div>
            <div class="abo-card-title">Manuelle Einrichtung</div>
            <div class="abo-card-subtitle">Gilt für Einsätze- und Vereinskalender</div>
        </div>
    </div>

    <div class="accordion" id="aboAnleitungen">
        <div class="accordion-item" style="border:none; border-radius:0.65rem; overflow:hidden; margin-bottom:0.4rem; box-shadow:0 1px 4px rgba(0,0,0,0.06);">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#anleitungIos" style="font-size:0.82rem; font-weight:600; padding:0.6rem 0.9rem;">
                    <i class="bi bi-apple me-2"></i> iPhone / iPad — Schritt für Schritt
                </button>
            </h2>
            <div id="anleitungIos" class="accordion-collapse collapse" data-bs-parent="#aboAnleitungen">
                <div class="accordion-body" style="font-size:0.81rem; padding:0.65rem 0.9rem;">
                    <ol class="mb-0 ps-3">
                        <li>Den Button <strong>"Kalender abonnieren"</strong> antippen</li>
                        <li>Im Dialog auf <strong>"Abonnieren"</strong> tippen</li>
                        <li>Der Kalender erscheint automatisch in deiner Kalender-App</li>
                    </ol>
                </div>
            </div>
        </div>
        <div class="accordion-item" style="border:none; border-radius:0.65rem; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,0.06);">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#anleitungAndroid" style="font-size:0.82rem; font-weight:600; padding:0.6rem 0.9rem;">
                    <i class="bi bi-android2 me-2"></i> Android / Google Kalender
                </button>
            </h2>
            <div id="anleitungAndroid" class="accordion-collapse collapse" data-bs-parent="#aboAnleitungen">
                <div class="accordion-body" style="font-size:0.81rem; padding:0.65rem 0.9rem;">
                    <ol class="mb-0 ps-3">
                        <li>URL mit dem <strong>Kopieren</strong>-Button kopieren</li>
                        <li>Google Kalender App &rarr; <strong>Einstellungen</strong> &rarr; <strong>"Kalender hinzufügen"</strong> &rarr; <strong>"Über URL"</strong></li>
                        <li>URL einfügen und bestätigen</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyUrl(btn) {
    navigator.clipboard.writeText(document.getElementById('calUrl').textContent).then(function() {
        btn.innerHTML = '<i class="bi bi-check-lg"></i>';
        btn.classList.add('copied');
        setTimeout(function() {
            btn.innerHTML = '<i class="bi bi-clipboard"></i>';
            btn.classList.remove('copied');
        }, 2000);
    });
}

function copyEinsatzUrl(btn) {
    navigator.clipboard.writeText(document.getElementById('einsatzFeedUrl').textContent).then(function() {
        btn.innerHTML = '<i class="bi bi-check-lg"></i>';
        btn.classList.add('copied');
        setTimeout(function() {
            btn.innerHTML = '<i class="bi bi-clipboard"></i>';
            btn.classList.remove('copied');
        }, 2000);
    });
}

async function generateEinsatzToken() {
    await _doGenerateEinsatzToken();
}

async function regenerateEinsatzToken() {
    const r = await msvConfirm(
        'Neuen Link generieren?',
        'Der alte Abo-Link wird ungültig. Kalender-Apps mit dem alten Link erhalten keine Daten mehr.',
        'Neu generieren',
        'Abbrechen'
    );
    if (!r.isConfirmed) return;
    await _doGenerateEinsatzToken();
}

async function _doGenerateEinsatzToken() {
    const csrf = <?php echo json_encode($csrf_token); ?>;
    try {
        const resp = await fetch('../api/einsatz_feed_token.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=generate&csrf_token=' + encodeURIComponent(csrf)
        });
        const data = await resp.json();
        if (data.success) {
            const httpsUrl = data.url;
            const webcalUrl = httpsUrl.replace(/^https?:\/\//, 'webcal://');

            document.getElementById('einsatzFeedUrl').textContent = httpsUrl;

            const webcalBtn = document.getElementById('einsatzWebcalBtn');
            webcalBtn.href = webcalUrl;

            const section = document.getElementById('einsatzUrlSection');
            if (section) section.style.display = 'block';

            const generateBtn = document.getElementById('einsatzGenerateBtn');
            if (generateBtn) generateBtn.style.display = 'none';

            msvToast('Abo-Link erfolgreich erstellt!', 'success');
        } else {
            msvToast(data.message || 'Fehler beim Erstellen des Links', 'error');
            if (data.csrf_expired) location.reload();
        }
    } catch (e) {
        msvToast('Verbindungsfehler. Bitte versuche es erneut.', 'error');
    }
}
</script>

<?php include 'portal_footer.php'; ?>
