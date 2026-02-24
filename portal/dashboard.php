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
    // Versuche Monat aus Schiesstage zu extrahieren
    $month_num = 0;
    foreach ($months_de as $name => $num) {
        if (stripos($ev['Schiesstage'], $name) !== false) {
            $month_num = $num;
            break;
        }
    }
    if ($month_num > 0) {
        // Erstelle approx Datum (letzter Tag des Monats)
        $approx = $current_year . '-' . str_pad($month_num, 2, '0', STR_PAD_LEFT) . '-28';
        if ($approx >= $today) {
            $next_event = $ev;
            break;
        }
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

include 'portal_header.php';
?>

<style>
.dashboard-greeting {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 0.75rem;
    padding: 0.875rem 1rem;
    margin-bottom: 1rem;
}
.dashboard-greeting h1 {
    font-size: 1.1rem;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 0.15rem;
}
.dashboard-greeting .date {
    color: #718096;
    font-size: 0.8rem;
}
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.dash-card {
    background: white;
    border-radius: 0.75rem;
    padding: 1.25rem;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    border: 1px solid #f0f0f0;
    transition: all 0.3s ease;
    text-decoration: none;
    color: inherit;
    display: block;
}
.dash-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
    color: inherit;
}
.dash-card-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    margin-bottom: 0.75rem;
}
.dash-card-title {
    font-weight: 600;
    font-size: 1rem;
    color: #2d3748;
    margin-bottom: 0.25rem;
}
.dash-card-desc {
    color: #718096;
    font-size: 0.85rem;
}
.next-event-card {
    background: linear-gradient(135deg, #e8f4fd, #d1ecf9);
    border: 1px solid #bee5eb;
    border-radius: 0.75rem;
    padding: 0.75rem 1rem;
    margin-bottom: 0.75rem;
}
.next-event-card h5 {
    color: #0c5460;
    font-weight: 600;
    font-size: 0.85rem;
    margin-bottom: 0.25rem;
}
.next-event-card .event-name {
    font-weight: 700;
    font-size: 0.9rem;
    color: #155724;
}
.next-einsatz-card {
    background: linear-gradient(135deg, #fff8e1, #ffecb3);
    border: 1px solid #ffe082;
    border-radius: 0.75rem;
    padding: 0.75rem 1rem;
    margin-bottom: 0.75rem;
}
.next-einsatz-card h5 {
    color: #e65100;
    font-weight: 600;
    font-size: 0.85rem;
    margin-bottom: 0.25rem;
}
.next-einsatz-card .einsatz-name {
    font-weight: 700;
    font-size: 0.9rem;
    color: #bf360c;
}
@media (max-width: 767.98px) {
    .dashboard-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.65rem;
        margin-bottom: 1rem;
    }
    .dashboard-greeting {
        padding: 0.75rem 0.875rem;
        margin-bottom: 0.75rem;
        border-radius: 0.625rem;
    }
    .dashboard-greeting h1 { font-size: 1rem; }
    .next-event-card, .next-einsatz-card {
        padding: 0.625rem 0.875rem;
        margin-bottom: 0.625rem;
    }
    .dash-card {
        padding: 0.875rem;
    }
    .dash-card-icon {
        width: 38px;
        height: 38px;
        font-size: 1.1rem;
        margin-bottom: 0.5rem;
        border-radius: 10px;
    }
    .dash-card-title {
        font-size: 0.875rem;
        margin-bottom: 0.15rem;
    }
    .dash-card-desc {
        font-size: 0.76rem;
    }
}
</style>

<!-- Begruessung -->
<div class="dashboard-greeting">
    <h1>Hallo <?php echo htmlspecialchars($vorname); ?>!</h1>
    <p class="date mb-0"><?php
        $weekdays = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
        $months = ['','Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
        echo $weekdays[date('w')] . ', ' . date('j') . '. ' . $months[date('n')] . ' ' . date('Y');
        ?>
    </p>
</div>

<!-- Naechster Termin -->
<?php if ($next_event): ?>
<div class="next-event-card">
    <h5><i class="bi bi-calendar-event me-2"></i>Nächster Termin</h5>
    <div class="event-name"><?php echo htmlspecialchars($next_event['Bezeichnung']); ?></div>
    <small class="text-muted">
        <?php echo nl2br(htmlspecialchars($next_event['Schiesstage'])); ?>
        <?php if (!empty($next_event['Adresse'])): ?>
            <br><i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($next_event['Adresse']); ?>
        <?php endif; ?>
    </small>
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

<!-- Offene Umfragen Banner -->
<?php if ($offene_umfragen > 0): ?>
<a href="mein_fragebogen.php" class="next-event-card d-block text-decoration-none" style="background: linear-gradient(135deg, #e8f4fd, #d1ecf9);">
    <h5 style="color: #0c5460;"><i class="bi bi-clipboard2-check me-2"></i><?= $offene_umfragen ?> offene Umfrage<?= $offene_umfragen > 1 ? 'n' : '' ?> <i class="bi bi-arrow-right-short float-end"></i></h5>
    <small class="text-muted"><?= $offene_umfragen > 1 ? 'warten auf deine Antworten' : 'wartet auf deine Antwort' ?></small>
</a>
<?php endif; ?>

<!-- Dashboard-Kacheln -->
<div class="dashboard-grid">
    <a href="meine_jm.php" class="dash-card">
        <div class="dash-card-icon" style="background: linear-gradient(135deg, #e8f5e9, #c8e6c9); color: #28a745;">
            <i class="bi bi-bullseye"></i>
        </div>
        <div class="dash-card-title">Jahresmeisterschaft</div>
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
