<?php
// portal/meine_heim.php - Heimmeisterschaft (8 Passen)
$portal_page_title = 'Heimmeisterschaft';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
requireLogin();
$db = getDB();

$mitglied_id = $_SESSION['mitglied_id'] ?? null;
$selected_year = intval($_GET['year'] ?? date('Y'));

// Verfuegbare Jahre
$years_stmt = $db->query("SELECT DISTINCT Jahr FROM heimresultate ORDER BY Jahr DESC");
$available_years = $years_stmt->fetchAll(PDO::FETCH_COLUMN);
if (empty($available_years)) $available_years = [date('Y')];
$current_year_str = date('Y');
if (!in_array($current_year_str, $available_years)) {
    array_unshift($available_years, $current_year_str);
}

// Resultate laden
$passen = [];
$total = 0;
$geschossen_count = 0;
$best_passe = 0;
if ($mitglied_id) {
    $stmt = $db->prepare("SELECT Passe1, Passe2, Passe3, Passe4, Passe5, Passe6, Passe7, Passe8 FROM heimresultate WHERE MitgliedID = ? AND Jahr = ?");
    $stmt->execute([$mitglied_id, $selected_year]);
    $row = $stmt->fetch();

    if ($row) {
        for ($i = 1; $i <= 8; $i++) {
            $val = $row['Passe' . $i];
            $p = ($val !== null && $val != 0) ? intval($val) : null;
            $passen[] = $p;
            if ($p !== null) {
                $total += $p;
                $geschossen_count++;
                if ($p > $best_passe) $best_passe = $p;
            }
        }
    } else {
        $passen = array_fill(0, 8, null);
    }
}

// Hinweis "nächste Erfassungs-Moeglichkeit": ZSMM-Termin dynamisch suchen.
// Schreibweise kann variieren -> LIKE '%ZSMM%'; naechster Termin ab heute = naechste Runde.
// Nur zeigen, wenn noch nicht alle Passen erfasst sind.
$zsmm_hint = null;
if ($mitglied_id && $geschossen_count < 8) {
    try {
        $zstmt = $db->prepare("
            SELECT name, date, time
            FROM wichtige_termine
            WHERE year = ? AND name LIKE '%ZSMM%' AND date >= CURDATE()
            ORDER BY date ASC, time ASC
            LIMIT 1
        ");
        $zstmt->execute([$selected_year]);
        $z = $zstmt->fetch();
        if ($z) {
            $zsmm_hint = $z;
        }
    } catch (Exception $e) {
        $zsmm_hint = null;  // Tabelle evtl. nicht vorhanden
    }
}

// Deutsches Datum fuer den Hinweis ("Sa, 12. Oktober 2026")
function heimFormatDatum(string $date): string {
    $wd  = ['So','Mo','Di','Mi','Do','Fr','Sa'];
    $mon = ['','Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
    $dt = new DateTime($date);
    return $wd[(int)$dt->format('w')] . ', ' . (int)$dt->format('j') . '. ' . $mon[(int)$dt->format('n')] . ' ' . $dt->format('Y');
}

include 'portal_header.php';
?>

<style>
.heim-hint {
    display: flex;
    align-items: flex-start;
    gap: var(--p-2);
    background: linear-gradient(135deg, #fff8e1, #ffecb3);
    border: 1px solid #ffe082;
    border-radius: var(--p-radius);
    padding: var(--p-2) var(--p-3);
    margin-bottom: var(--p-3);
    font-size: .85rem;
    color: #5d4200;
}
.heim-hint i { font-size: 1.1rem; color: #e65100; flex-shrink: 0; margin-top: 1px; }
.heim-hint strong { color: #4e3700; }
</style>

<div class="portal-page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h1><i class="bi bi-house me-2"></i>Heimmeisterschaft</h1>
        <p class="subtitle mb-0"><?php echo $selected_year; ?> &mdash; 8 Passen</p>
    </div>
    <form method="get" class="d-flex align-items-center gap-2">
        <select name="year" class="form-select form-select-sm" style="max-width:140px;" onchange="this.form.submit()">
            <?php foreach ($available_years as $y): ?>
            <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>><?php echo $y; ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if (!$mitglied_id): ?>
<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Dein Account ist noch nicht mit einem Mitglied verknüpft.</div>
<?php else: ?>

<?php if ($zsmm_hint):
    $z_time = substr((string)$zsmm_hint['time'], 0, 5);
?>
<div class="heim-hint">
    <i class="bi bi-clock-history"></i>
    <div>
        Noch <strong><?php echo 8 - $geschossen_count; ?> von 8 Passen</strong> offen — nächste Möglichkeit:
        <strong><?php echo htmlspecialchars($zsmm_hint['name']); ?></strong>,
        <?php echo heimFormatDatum($zsmm_hint['date']); ?><?php if ($z_time && $z_time !== '00:00'): ?> ab <?php echo htmlspecialchars($z_time); ?> Uhr<?php endif; ?>.
    </div>
</div>
<?php endif; ?>

<div class="result-summary">
    <div class="rs-item total">
        <span class="rs-num"><?php echo $total; ?></span>
        <span class="rs-lbl">Total</span>
    </div>
    <div class="rs-item">
        <span class="rs-num"><?php echo $geschossen_count; ?> / 8</span>
        <span class="rs-lbl">geschossen</span>
    </div>
    <div class="rs-item">
        <span class="rs-num"><?php echo $geschossen_count > 0 ? round($total / $geschossen_count, 1) : '–'; ?></span>
        <span class="rs-lbl">&empty; Schnitt</span>
    </div>
</div>

<div class="passe-list">
    <?php for ($i = 0; $i < 8; $i++):
        $val = $passen[$i];
        $shot = ($val !== null);
        $row_class = $shot ? 'passe-row' : 'passe-row not-shot';
    ?>
    <div class="<?php echo $row_class; ?>">
        <span class="passe-label">Passe <?php echo $i + 1; ?></span>
        <span class="passe-score"><?php echo $shot ? $val : '–'; ?></span>
        <span class="passe-status"><?php if (!$shot) echo 'nicht geschossen'; ?></span>
    </div>
    <?php endfor; ?>
</div>

<?php endif; ?>

<?php include 'portal_footer.php'; ?>
