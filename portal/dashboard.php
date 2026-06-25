<?php
// portal/dashboard.php - Mitgliederportal Dashboard
$portal_page_title = 'MSV Wilen';

// Auth + DB laden
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
requireLogin();
$db = getDB();

// Vorname des Mitglieds holen
$mitglied_id = $_SESSION['mitglied_id'] ?? null;
$vorname = '';
if ($mitglied_id) {
    $stmt = $db->prepare("SELECT Vorname FROM mitglieder WHERE ID = ?");
    $stmt->execute([$mitglied_id]);
    $row = $stmt->fetch();
    $vorname = $row['Vorname'] ?? '';
}
if (empty($vorname)) {
    // Fallback: full_name oder username
    $vorname = explode(' ', $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'Mitglied')[0];
}

// Naechster Termin aus JMDefinition
$current_year = date('Y');
$next_event = null;
$stmt = $db->prepare("
    SELECT Bezeichnung, Schiesstage, Adresse, Info
    FROM JMDefinition
    WHERE year = ? AND hidden = 0 AND LENGTH(Schiesstage) > 0
    ORDER BY Reihenfolge ASC
");
$stmt->execute([$current_year]);
$events = $stmt->fetchAll();

// Versuche den naechsten zukuenftigen Termin zu finden
$today = date('Y-m-d');
$months_de = ['Januar'=>1,'Februar'=>2,'März'=>3,'April'=>4,'Mai'=>5,'Juni'=>6,
              'Juli'=>7,'August'=>8,'September'=>9,'Oktober'=>10,'November'=>11,'Dezember'=>12];
foreach ($events as $ev) {
    // Alle Datumsvorkommen aus Schiesstage extrahieren und das späteste nehmen
    // Format: "06. März 2026" oder "06. März" (ohne Jahr)
    $latest_date = null;
    foreach ($months_de as $name => $num) {
        // Mit Jahr: "06. März 2026"
        if (preg_match_all('/(\d{1,2})\.\s+' . preg_quote($name, '/') . '\s+(\d{4})/iu', $ev['Schiesstage'], $m)) {
            foreach ($m[1] as $i => $day) {
                $d = $m[2][$i] . '-' . str_pad($num, 2, '0', STR_PAD_LEFT) . '-' . str_pad((int)$day, 2, '0', STR_PAD_LEFT);
                if ($latest_date === null || $d > $latest_date) $latest_date = $d;
            }
        }
        // Ohne Jahr: "06. März"
        if (preg_match_all('/(\d{1,2})\.\s+' . preg_quote($name, '/') . '(?!\s+\d{4})/iu', $ev['Schiesstage'], $m)) {
            foreach ($m[1] as $day) {
                $d = $current_year . '-' . str_pad($num, 2, '0', STR_PAD_LEFT) . '-' . str_pad((int)$day, 2, '0', STR_PAD_LEFT);
                if ($latest_date === null || $d > $latest_date) $latest_date = $d;
            }
        }
    }
    if ($latest_date !== null && $latest_date >= $today) {
        $next_event = $ev;
        break;
    }
}

// Offene Umfragen zaehlen
$offene_umfragen = 0;
if ($mitglied_id) {
    try {
        $role = $_SESSION['user_role'] ?? 'mitglied';
        $stmtU = $db->prepare("
            SELECT COUNT(DISTINCT u.id) FROM umfragen u
            WHERE u.status = 'aktiv'
              AND (u.zielgruppe = 'alle' OR (u.zielgruppe = 'vorstand' AND ? IN ('admin','vorstand')))
              AND (u.gueltig_bis IS NULL OR u.gueltig_bis >= CURDATE())
              AND u.id NOT IN (
                  SELECT DISTINCT ua.umfrage_id FROM umfragen_antworten ua WHERE ua.mitglied_id = ?
              )
        ");
        $stmtU->execute([$role, $mitglied_id]);
        $offene_umfragen = (int)$stmtU->fetchColumn();
    } catch (Exception $e) {
        // Tabelle existiert evtl. noch nicht
        $offene_umfragen = 0;
    }
}

// Naechster Arbeitseinsatz aus einsatz_zuweisungen
$next_einsatz = null;
if ($mitglied_id) {
    try {
        $stmt = $db->prepare("
            SELECT bezeichnung, event_datum, event_zeit, funktion, typ
            FROM einsatz_zuweisungen
            WHERE mitglied_id = ? AND event_datum >= CURDATE()
            ORDER BY event_datum ASC
            LIMIT 1
        ");
        $stmt->execute([$mitglied_id]);
        $next_einsatz = $stmt->fetch();
    } catch (Exception $e) {
        // Tabelle existiert evtl. noch nicht
        $next_einsatz = null;
    }
}

// Offene Einsatz-Tausch-Anfragen an mich (Migration 034 evtl. noch nicht vorhanden)
$offene_tausch = 0;
if ($mitglied_id) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM einsatz_tausch WHERE an_mitglied_id = ? AND status = 'offen'");
        $stmt->execute([$mitglied_id]);
        $offene_tausch = (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        $offene_tausch = 0;
    }
}


// Push-Benachrichtigungen: eingeschaltet, aber kein Geraet registriert?
// -> Dashboard-Hinweis, sonst wuerde der User trotz aktiver Themen nichts erhalten.
$push_setup_noetig = false;
try {
    $uid = (int) ($_SESSION['user_id'] ?? 0);
    if ($uid) {
        $pstmt = $db->prepare("SELECT push_aktiv, einsaetze, jm, umfragen, termine FROM benachrichtigung_prefs WHERE user_id = ?");
        $pstmt->execute([$uid]);
        $prefs = $pstmt->fetch();
        // Fehlende Zeile = alles an
        $push_aktiv = $prefs ? ((int) $prefs['push_aktiv'] === 1) : true;
        $themen_an  = $prefs
            ? ((int) $prefs['einsaetze'] || (int) $prefs['jm'] || (int) $prefs['umfragen'] || (int) $prefs['termine'])
            : true;

        $dstmt = $db->prepare("SELECT COUNT(*) FROM push_abos WHERE benutzer_id = ?");
        $dstmt->execute([$uid]);
        $geraete = (int) $dstmt->fetchColumn();

        $push_setup_noetig = ($push_aktiv && $themen_an && $geraete === 0);
    }
} catch (Exception $e) {
    // Tabellen (Migration 021) evtl. noch nicht vorhanden
    $push_setup_noetig = false;
}

// Naechste Vereinstermine (wichtige_termine) — kompakte Vorschau, Vollansicht: termine.php
$next_termine = [];
try {
    $stmt = $db->query("SELECT name, `date`, `time` FROM wichtige_termine WHERE `date` >= CURDATE() ORDER BY `date` ASC, `time` ASC LIMIT 3");
    $next_termine = $stmt->fetchAll();
} catch (Exception $e) {
    $next_termine = [];
}

include 'portal_header.php';
?>
<script src="../inc/js/ssv-barcode.js?v=<?php echo @filemtime(__DIR__ . '/../inc/js/ssv-barcode.js'); ?>"></script>

<style>
/* Begruessung — bewusster Marken-Akzent (Gradient), nur kompakter + Tokens */
.dashboard-greeting {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: var(--p-radius);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: var(--p-3) var(--p-4);
    margin-bottom: var(--p-3);
}
.dashboard-greeting h1 {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--p-text);
    margin-bottom: var(--p-1);
}
.dashboard-greeting .date {
    color: var(--p-text-muted);
    font-size: 0.8rem;
}
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: var(--p-2);
    margin-bottom: var(--p-4);
}
/* Kachel: Icon links, Titel + Beschreibung rechts gestapelt — kompakt & aufgeraeumt.
   Eigene Kartenflaeche (kein .p-card, da eigenes Grid-Layout). */
.dash-card {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    grid-template-areas:
        "icon title"
        "icon desc";
    align-items: center;
    column-gap: var(--p-3);
    padding: var(--p-3);
    background: #fff;
    border: 1px solid var(--p-border);
    border-radius: var(--p-radius);
    box-shadow: var(--p-shadow);
    text-decoration: none;
    color: inherit;
    transition: box-shadow var(--transition-speed) ease, transform var(--transition-speed) ease;
}
.dash-card:hover {
    box-shadow: var(--p-shadow-hover);
    transform: translateY(-2px);
}
/* Gradient-Icon = bewusster Marken-Akzent (seitenspezifisch), kompakter */
.dash-card-icon {
    grid-area: icon;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
}
.dash-card-title {
    grid-area: title;
    align-self: end;
    min-width: 0;
    overflow-wrap: break-word;
    -webkit-hyphens: auto;
    hyphens: auto;
    font-weight: 600;
    font-size: 0.92rem;
    line-height: 1.2;
    color: var(--p-text);
}
.dash-card-desc {
    grid-area: desc;
    align-self: start;
    color: var(--p-text-muted);
    font-size: 0.78rem;
    line-height: 1.25;
}
/* Event-/Einsatz-Banner — farbige Marken-Akzente, kompakt */
.next-event-card,
.next-einsatz-card {
    border-radius: var(--p-radius);
    padding: var(--p-2) var(--p-3);
    margin-bottom: var(--p-3);
}
.next-event-card { background: linear-gradient(135deg, #e8f4fd, #d1ecf9); border: 1px solid #bee5eb; }
.next-einsatz-card { background: linear-gradient(135deg, #fff8e1, #ffecb3); border: 1px solid #ffe082; }
.next-event-card h5,
.next-einsatz-card h5 {
    font-weight: 600;
    font-size: 0.82rem;
    margin-bottom: 2px;
}
.next-event-card h5 { color: #0c5460; }
.next-einsatz-card h5 { color: #e65100; }
.next-event-card .event-name { font-weight: 700; font-size: 0.88rem; color: #155724; }
.next-einsatz-card .einsatz-name { font-weight: 700; font-size: 0.88rem; color: #bf360c; }
.next-event-card small,
.next-einsatz-card small {
    font-size: 0.76rem;
    line-height: 1.3;
}
/* Kombinierte Termin-Karte: "Nächste Schiessanlässe" (oben) + "Nächste Termine" (Liste) */
.dash-events-card {
    background: linear-gradient(135deg, #e8f4fd, #d1ecf9);
    border: 1px solid #bee5eb;
    border-radius: var(--p-radius);
    padding: var(--p-2) var(--p-3);
    margin-bottom: var(--p-3);
}
.dash-events-section { display: block; color: inherit; text-decoration: none; }
.dash-events-link { border-radius: var(--p-radius-sm); transition: opacity var(--transition-speed) ease; }
.dash-events-link:hover { opacity: 0.82; }
.dash-events-head {
    display: flex;
    align-items: center;
    font-weight: 600;
    font-size: 0.82rem;
    color: #0c5460;
    margin-bottom: 3px;
}
.dash-event-name { font-weight: 700; font-size: 0.88rem; color: #155724; }
.dash-event-meta { font-size: 0.76rem; color: #155724; line-height: 1.3; opacity: 0.92; }
.dash-events-divider { margin: 0.6rem 0; border: 0; border-top: 1px solid rgba(12,84,96,0.18); }
.dash-termine-list { margin-top: 4px; }
.dash-termine-row {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
    font-size: 0.8rem;
    padding: 2px 0;
}
.dash-termine-row + .dash-termine-row { border-top: 1px solid rgba(12,84,96,0.15); }
.dash-termine-date { flex-shrink: 0; font-weight: 700; color: #0c5460; min-width: 64px; }
.dash-termine-name { flex: 1; min-width: 0; color: #155724; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dash-termine-time { flex-shrink: 0; color: #0c5460; font-size: 0.74rem; opacity: 0.85; }
@media (max-width: 767.98px) {
    .dashboard-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--p-2);
        margin-bottom: var(--p-3);
    }
    .dashboard-greeting {
        padding: var(--p-2) var(--p-3);
        margin-bottom: var(--p-2);
    }
    .dashboard-greeting h1 { font-size: 1rem; }
    /* Banner kompakter */
    .next-event-card, .next-einsatz-card {
        padding: var(--p-2) var(--p-3);
        margin-bottom: var(--p-3);
    }
    .next-event-card h5, .next-einsatz-card h5 { font-size: 0.78rem; }
    .next-event-card .event-name, .next-einsatz-card .einsatz-name { font-size: 0.84rem; }
    /* Kacheln: 2 Spalten, kompakt — nur Icon + Titel */
    .dash-card {
        grid-template-areas: "icon title";
        align-items: center;
        column-gap: var(--p-2);
        padding: var(--p-2);
    }
    .dash-card-icon {
        width: 30px;
        height: 30px;
        font-size: 0.9rem;
        border-radius: 8px;
    }
    .dash-card-title {
        align-self: center;
        font-size: 0.75rem;
        line-height: 1.15;
    }
    .dash-card-desc { display: none; }
}

/* Barcode Icon Button */
.barcode-icon-btn {
    background: none;
    border: none;
    color: var(--secondary-color);
    font-size: 1.4rem;
    padding: var(--p-1) 0.4rem;
    border-radius: var(--p-radius-sm);
    cursor: pointer;
    transition: all 0.2s;
    flex-shrink: 0;
}
.barcode-icon-btn:hover {
    color: var(--primary-color);
    background: rgba(59, 89, 152, 0.08);
}

/* Barcode Modal */
.barcode-modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.85);
    z-index: 9000;
    justify-content: center;
    align-items: center;
}
.barcode-modal-overlay.show {
    display: flex;
}
.barcode-modal-close {
    position: absolute;
    top: var(--p-3);
    right: var(--p-3);
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: white;
    border: none;
    color: #333;
    font-size: 1.2rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: var(--p-shadow-hover);
    transition: transform 0.2s;
}
.barcode-modal-close:hover {
    transform: scale(1.1);
}
.barcode-modal-overlay.show { background: #ffffff; }
.barcode-modal-card {
    background: white;
    border-radius: var(--p-radius);
    padding: var(--p-5) var(--p-3);
    max-width: 540px;
    width: 96%;
    text-align: center;
}
.barcode-modal-card .lizenznummer {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--p-text);
    margin-bottom: var(--p-3);
    letter-spacing: 1px;
}
.barcode-modal-card .barcode-label {
    font-size: 0.75rem;
    color: var(--secondary-color);
    margin-bottom: var(--p-1);
}
.barcode-canvas-wrap {
    position: relative;
    margin: 0 auto;
    overflow: visible;
}
.barcode-modal-card canvas {
    display: block;
    background: #ffffff;
}
.barcode-canvas-wrap.rotated canvas {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) rotate(90deg);
    transform-origin: center center;
}
.barcode-modal-card .barcode-nummer {
    font-size: 0.85rem;
    color: var(--p-text);
    margin-top: var(--p-2);
    letter-spacing: 2px;
    font-family: monospace;
    font-weight: 600;
}
.barcode-modal-card .barcode-hint {
    font-size: 0.7rem;
    color: var(--secondary-color);
    margin-top: var(--p-3);
}
.barcode-rotate-btn {
    position: absolute;
    top: var(--p-3);
    left: var(--p-3);
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: white;
    border: 1px solid #dee2e6;
    color: #333;
    font-size: 1.1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: var(--p-shadow);
}
</style>

<!-- Begruessung -->
<div class="dashboard-greeting">
    <div>
        <h1>Hallo <?php echo htmlspecialchars($vorname); ?>!</h1>
        <p class="date mb-0"><?php
            $weekdays = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
            $months = ['','Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
            echo $weekdays[date('w')] . ', ' . date('j') . '. ' . $months[date('n')] . ' ' . date('Y');
            ?>
        </p>
    </div>
    <?php if ($mitglied_id): ?>
    <button class="barcode-icon-btn" id="barcodeBtn" title="SSV Lizenz-Barcode"><i class="bi bi-upc-scan"></i></button>
    <?php endif; ?>
</div>

<?php if ($mitglied_id): ?>
<!-- Barcode Modal -->
<div class="barcode-modal-overlay" id="barcodeModal" role="dialog" aria-modal="true" aria-label="SSV Lizenz-Barcode">
    <button class="barcode-rotate-btn" id="barcodeRotate" title="Drehen" aria-label="Barcode drehen"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>
    <button class="barcode-modal-close" id="barcodeClose" aria-label="Schliessen"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    <div class="barcode-modal-card">
        <div class="barcode-label">SSV Lizenznummer</div>
        <div class="lizenznummer"><?php echo htmlspecialchars($mitglied_id); ?></div>
        <div class="barcode-canvas-wrap" id="barcodeWrap"><canvas id="barcodeCanvas"></canvas></div>
        <div class="barcode-nummer" id="barcodeNummer"></div>
        <div class="barcode-hint">Tipp: Helligkeit hoch, Handy flach halten. Button oben links = Querformat.</div>
    </div>
</div>
<script>
(function() {
    var btn = document.getElementById('barcodeBtn');
    var modal = document.getElementById('barcodeModal');
    var canvas = document.getElementById('barcodeCanvas');
    var nummerEl = document.getElementById('barcodeNummer');
    var rotateBtn = document.getElementById('barcodeRotate');
    var lnr = <?php echo json_encode((string)$mitglied_id); ?>;
    var bnr = ssvBarcodeNummer(lnr);
    var rotated = false;
    var wakeLock = null;

    var wrap = document.getElementById('barcodeWrap');

    function render() {
        var vpW = window.innerWidth;
        var vpH = window.innerHeight;
        var cssW, cssH;
        if (rotated) {
            // Querformat: Barcode nutzt die lange Bildschirmseite
            cssW = Math.min(vpH * 0.85, 600);
            cssH = Math.min(vpW * 0.35, 160);
            wrap.classList.add('rotated');
            wrap.style.width = cssH + 'px';
            wrap.style.height = cssW + 'px';
        } else {
            cssW = Math.min(vpW * 0.92, 520);
            cssH = Math.min(vpH * 0.22, 140);
            wrap.classList.remove('rotated');
            wrap.style.width = cssW + 'px';
            wrap.style.height = cssH + 'px';
        }
        var ctx = prepareBarcodeCanvas(canvas, cssW, cssH);
        drawItfBarcode(ctx, bnr, 0, 8, cssW, cssH - 16);
        nummerEl.textContent = bnr;
    }

    async function requestWakeLock() {
        try {
            if ('wakeLock' in navigator) {
                wakeLock = await navigator.wakeLock.request('screen');
            }
        } catch (e) { /* ignoriert — nicht unterstützt */ }
    }

    function releaseWakeLock() {
        if (wakeLock) { wakeLock.release().catch(function(){}); wakeLock = null; }
    }

    function open() {
        modal.classList.add('show');
        render();
        requestWakeLock();
    }

    function close() {
        modal.classList.remove('show');
        releaseWakeLock();
    }

    btn.addEventListener('click', open);
    document.getElementById('barcodeClose').addEventListener('click', close);
    rotateBtn.addEventListener('click', function() { rotated = !rotated; render(); });

    modal.addEventListener('click', function(e) {
        if (e.target === modal) close();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('show')) close();
    });

    window.addEventListener('resize', function() {
        if (modal.classList.contains('show')) render();
    });

    // Wake Lock nach Tab-Wechsel neu anfordern
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible' && modal.classList.contains('show')) {
            requestWakeLock();
        }
    });
})();
</script>
<?php endif; ?>

<!-- Naechste Schiessanlaesse + Vereinstermine (kombinierte Karte) -->
<?php if ($next_event || $next_termine): ?>
<div class="dash-events-card">
    <?php if ($next_event): ?>
    <div class="dash-events-section">
        <div class="dash-events-head"><i class="bi bi-bullseye me-2"></i>Nächste Schiessanlässe</div>
        <div class="dash-event-name"><?php echo htmlspecialchars($next_event['Bezeichnung']); ?></div>
        <div class="dash-event-meta">
            <?php echo nl2br(htmlspecialchars($next_event['Schiesstage'])); ?>
            <?php if (!empty($next_event['Adresse'])): ?>
                <br><i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($next_event['Adresse']); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($next_event && $next_termine): ?><hr class="dash-events-divider"><?php endif; ?>

    <?php if ($next_termine): ?>
    <a href="termine.php" class="dash-events-section dash-events-link">
        <div class="dash-events-head"><i class="bi bi-calendar3 me-2"></i>Nächste Termine<i class="bi bi-arrow-right-short ms-auto"></i></div>
        <div class="dash-termine-list">
            <?php foreach ($next_termine as $nt): $nts = strtotime($nt['date']); ?>
            <div class="dash-termine-row">
                <span class="dash-termine-date"><?php echo substr($weekdays[date('w', $nts)], 0, 2); ?> <?php echo date('d.m.', $nts); ?></span>
                <span class="dash-termine-name"><?php echo htmlspecialchars($nt['name']); ?></span>
                <?php if (!empty($nt['time'])): ?><span class="dash-termine-time"><?php echo htmlspecialchars(substr($nt['time'], 0, 5)); ?></span><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </a>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Naechster Arbeitseinsatz -->
<?php if ($next_einsatz): ?>
<a href="meine_einsaetze.php" class="next-einsatz-card d-block text-decoration-none">
    <h5><i class="bi bi-person-badge me-2"></i>Dein nächster Einsatz <i class="bi bi-arrow-right-short float-end"></i></h5>
    <div class="einsatz-name"><?php echo htmlspecialchars($next_einsatz['bezeichnung']); ?></div>
    <small class="text-muted">
        <?php
        $einsatz_datum = new DateTime($next_einsatz['event_datum']);
        $wt = $weekdays[$einsatz_datum->format('w')];
        $tag = $einsatz_datum->format('j');
        $monat = $months[$einsatz_datum->format('n')];
        echo $wt . ', ' . $tag . '. ' . $monat . ' ' . $einsatz_datum->format('Y');
        ?>
        <?php if (!empty($next_einsatz['event_zeit'])): ?>
            &middot; <?php echo htmlspecialchars($next_einsatz['event_zeit']); ?>
        <?php endif; ?>
        <br><i class="bi bi-wrench me-1"></i><?php echo htmlspecialchars($next_einsatz['funktion']); ?>
    </small>
</a>
<?php endif; ?>

<!-- Offene Einsatz-Tausch-Anfragen -->
<?php if ($offene_tausch > 0): ?>
<a href="meine_einsaetze.php" class="next-einsatz-card d-block text-decoration-none" style="background: linear-gradient(135deg, #e0f2f1, #b2dfdb); border-color: #80cbc4;">
    <h5 style="color: #00695c;"><i class="bi bi-arrow-left-right me-2"></i><?php echo $offene_tausch; ?> offene Tausch-Anfrage<?php echo $offene_tausch > 1 ? 'n' : ''; ?> <i class="bi bi-arrow-right-short float-end"></i></h5>
    <small class="text-muted"><?php echo $offene_tausch > 1 ? 'warten auf deine Antwort' : 'wartet auf deine Antwort'; ?></small>
</a>
<?php endif; ?>

<!-- Offene Umfragen Banner -->
<?php if ($offene_umfragen > 0): ?>
<a href="mein_fragebogen.php" class="next-event-card d-block text-decoration-none" style="background: linear-gradient(135deg, #e8f4fd, #d1ecf9);">
    <h5 style="color: #0c5460;"><i class="bi bi-clipboard2-check me-2"></i><?= $offene_umfragen ?> offene Umfrage<?= $offene_umfragen > 1 ? 'n' : '' ?> <i class="bi bi-arrow-right-short float-end"></i></h5>
    <small class="text-muted"><?= $offene_umfragen > 1 ? 'warten auf deine Antworten' : 'wartet auf deine Antwort' ?></small>
</a>
<?php endif; ?>

<!-- Push-Benachrichtigungen einrichten (Themen an, aber kein Geraet) -->
<?php if (!empty($push_setup_noetig)): ?>
<a href="benachrichtigungen.php" class="next-einsatz-card d-block text-decoration-none" style="background: linear-gradient(135deg, #ede7f6, #d1c4e9); border-color: #b39ddb;">
    <h5 style="color: #4527a0;"><i class="bi bi-bell me-2"></i>Benachrichtigungen aktivieren <i class="bi bi-arrow-right-short float-end"></i></h5>
    <small class="text-muted">Du hast Benachrichtigungen eingeschaltet, aber noch kein Gerät aktiviert — so erhältst du keine. Jetzt auf diesem Gerät aktivieren.</small>
</a>
<?php endif; ?>

<!-- Dashboard-Kacheln -->
<div class="dashboard-grid">
    <a href="meine_jm.php" class="dash-card">
        <div class="dash-card-icon" style="background: linear-gradient(135deg, #e8f5e9, #c8e6c9); color: #28a745;">
            <i class="bi bi-bullseye"></i>
        </div>
        <div class="dash-card-title">JM</div>
        <div class="dash-card-desc">Alle JM-Schiessen mit Streicher</div>
    </a>

    <a href="meine_heim.php" class="dash-card">
        <div class="dash-card-icon" style="background: linear-gradient(135deg, #fff3e0, #ffe0b2); color: #f57c00;">
            <i class="bi bi-house"></i>
        </div>
        <div class="dash-card-title">Heimmeisterschaft</div>
        <div class="dash-card-desc">8 Passen im Überblick</div>
    </a>

    <a href="meine_kanti.php" class="dash-card">
        <div class="dash-card-icon" style="background: linear-gradient(135deg, #fce4ec, #f8bbd0); color: #c62828;">
            <i class="bi bi-geo-alt"></i>
        </div>
        <div class="dash-card-title">Kantonalstich</div>
        <div class="dash-card-desc">5 Passen + Kranzlimite</div>
    </a>


    <a href="mein_fragebogen.php" class="dash-card">
        <div class="dash-card-icon" style="background: linear-gradient(135deg, #e8f4fd, #cce7ff); color: #0c5460;">
            <i class="bi bi-clipboard-check"></i>
        </div>
        <div class="dash-card-title">Umfragen</div>
        <div class="dash-card-desc">Fragebogen &amp; Umfragen beantworten</div>
    </a>
    <a href="meine_wanderpreise.php" class="dash-card">
        <div class="dash-card-icon" style="background: linear-gradient(135deg, #fff3e0, #ffe0b2); color: #e65100;">
            <i class="bi bi-award"></i>
        </div>
        <div class="dash-card-title">Wanderpreise</div>
        <div class="dash-card-desc">Deine Wanderpreise &amp; Rückgaben</div>
    </a>

    <a href="kalender_abo.php" class="dash-card">
        <div class="dash-card-icon" style="background: linear-gradient(135deg, #e8f5e9, #b2dfdb); color: #00796b;">
            <i class="bi bi-calendar-plus"></i>
        </div>
        <div class="dash-card-title">Kalender-Abo</div>
        <div class="dash-card-desc">Alle Termine im Handy-Kalender</div>
    </a>

    <?php if (isVorstand()): ?>
    <a href="einsatzplaene.php" class="dash-card">
        <div class="dash-card-icon" style="background: linear-gradient(135deg, #e3f2fd, #bbdefb); color: #1565c0;">
            <i class="bi bi-calendar-check"></i>
        </div>
        <div class="dash-card-title">Einsatzpläne</div>
        <div class="dash-card-desc">Einsatzpläne verwalten</div>
    </a>

    <a href="protokolle.php" class="dash-card">
        <div class="dash-card-icon" style="background: linear-gradient(135deg, #f3e5f5, #ce93d8); color: #7b1fa2;">
            <i class="bi bi-file-text"></i>
        </div>
        <div class="dash-card-title">Protokolle</div>
        <div class="dash-card-desc">Sitzungsprotokolle verwalten</div>
    </a>
    <?php else: ?>
    <a href="einsatzplaene.php" class="dash-card">
        <div class="dash-card-icon" style="background: linear-gradient(135deg, #e3f2fd, #bbdefb); color: #1565c0;">
            <i class="bi bi-calendar-check"></i>
        </div>
        <div class="dash-card-title">Einsatzpläne</div>
        <div class="dash-card-desc">Aktuelle Einsatzpläne ansehen</div>
    </a>

    <a href="protokolle.php" class="dash-card">
        <div class="dash-card-icon" style="background: linear-gradient(135deg, #f3e5f5, #ce93d8); color: #7b1fa2;">
            <i class="bi bi-file-text"></i>
        </div>
        <div class="dash-card-title">Protokolle</div>
        <div class="dash-card-desc">GV-Protokolle ansehen</div>
    </a>
    <?php endif; ?>
</div>

<?php include 'portal_footer.php'; ?>
