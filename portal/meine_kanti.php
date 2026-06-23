<?php
// portal/meine_kanti.php - Kantonalstich (5 Passen)
$portal_page_title = 'Kantonalstich';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
requireLogin();
$db = getDB();

$mitglied_id = $_SESSION['mitglied_id'] ?? null;
$selected_year = intval($_GET['year'] ?? date('Y'));

// Verfuegbare Jahre
$years_stmt = $db->query("SELECT DISTINCT Jahr FROM kantiresultate ORDER BY Jahr DESC");
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
    $stmt = $db->prepare("SELECT Passe1, Passe2, Passe3, Passe4, Passe5 FROM kantiresultate WHERE MitgliedID = ? AND Jahr = ?");
    $stmt->execute([$mitglied_id, $selected_year]);
    $row = $stmt->fetch();

    if ($row) {
        for ($i = 1; $i <= 5; $i++) {
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
        $passen = array_fill(0, 5, null);
    }
}

// Kranzlimite laden (aus sKranzLimiten Tabelle, falls vorhanden)
$kranzlimite = null;
$has_kranzlimiten = false;
try {
    // Pruefen ob Tabelle existiert
    $check = $db->query("SHOW TABLES LIKE 'sKranzLimiten'");
    if ($check->rowCount() > 0 && $mitglied_id) {
        $has_kranzlimiten = true;
        // Mitglied-Daten fuer Kranzlimite (Waffe + Alter)
        $m_stmt = $db->prepare("SELECT WaffenID, Geburtsdatum FROM mitglieder WHERE ID = ?");
        $m_stmt->execute([$mitglied_id]);
        $mitglied_data = $m_stmt->fetch();

        if ($mitglied_data && $mitglied_data['WaffenID'] && $mitglied_data['Geburtsdatum']) {
            $waffen_id = $mitglied_data['WaffenID'];
            $geburtsdatum = $mitglied_data['Geburtsdatum'];
            $alter = date_diff(date_create($geburtsdatum), date_create())->y;

            // Kranzlimite basierend auf Waffe und Alter suchen
            $kl_stmt = $db->prepare("
                SELECT Limite FROM sKranzLimiten
                WHERE WaffenID = ? AND AlterVon <= ? AND AlterBis >= ?
                LIMIT 1
            ");
            $kl_stmt->execute([$waffen_id, $alter, $alter]);
            $kl_row = $kl_stmt->fetch();
            if ($kl_row) {
                $kranzlimite = intval($kl_row['Limite']);
            }
        }
    }
} catch (Exception $e) {
    // sKranzLimiten existiert nicht - ignorieren
}

include 'portal_header.php';
?>

<style>
/* Summary-Strip + Passen-Liste kommen aus css/portal.css. Hier nur die Kranzlimite-Info. */
.kranz-info {
    background: #f8f9fa;
    border-radius: var(--p-radius);
    padding: var(--p-2) var(--p-3);
    margin-bottom: var(--p-3);
    font-size: .8rem;
}
</style>

<div class="portal-page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h1><i class="bi bi-geo-alt me-2"></i>Kantonalstich</h1>
        <p class="subtitle mb-0"><?php echo $selected_year; ?> &mdash; 5 Passen</p>
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

<?php if ($kranzlimite !== null): ?>
<div class="kranz-info">
    <i class="bi bi-info-circle me-2"></i>
    <strong>Deine Kranzlimite:</strong> <?php echo $kranzlimite; ?> Punkte pro Passe
</div>
<?php endif; ?>

<div class="result-summary">
    <div class="rs-item total">
        <span class="rs-num"><?php echo $total; ?></span>
        <span class="rs-lbl">Total</span>
    </div>
    <div class="rs-item">
        <span class="rs-num"><?php echo $geschossen_count; ?> / 5</span>
        <span class="rs-lbl">geschossen</span>
    </div>
    <div class="rs-item">
        <span class="rs-num"><?php echo $best_passe ?: '–'; ?></span>
        <span class="rs-lbl">Beste</span>
    </div>
    <?php if ($kranzlimite !== null):
        $kranz_count = 0;
        foreach ($passen as $p) { if ($p !== null && $p >= $kranzlimite) $kranz_count++; }
    ?>
    <div class="rs-item">
        <span class="rs-num"><?php echo $kranz_count; ?></span>
        <span class="rs-lbl">Kränze</span>
    </div>
    <?php endif; ?>
</div>

<div class="passe-list">
    <?php for ($i = 0; $i < 5; $i++):
        $val = $passen[$i];
        $shot = ($val !== null);
        $is_kranz = ($shot && $kranzlimite !== null && $val >= $kranzlimite);
        $is_best = ($shot && $val == $best_passe && $best_passe > 0);

        $row_class = 'passe-row';
        if ($is_kranz) $row_class .= ' kranz';
        elseif ($is_best) $row_class .= ' best';
        elseif (!$shot) $row_class .= ' not-shot';
    ?>
    <div class="<?php echo $row_class; ?>">
        <span class="passe-label">Passe <?php echo $i + 1; ?></span>
        <span class="passe-score"><?php echo $shot ? $val : '–'; ?></span>
        <span class="passe-status"><?php
            if ($is_kranz) echo '<i class="bi bi-award-fill"></i> Kranz';
            elseif ($is_best) echo '<i class="bi bi-star-fill"></i> beste';
            elseif ($shot && $kranzlimite !== null) echo ($kranzlimite - $val) . ' fehlen';
            elseif (!$shot) echo 'nicht geschossen';
        ?></span>
    </div>
    <?php endfor; ?>
</div>

<?php endif; ?>

<?php include 'portal_footer.php'; ?>
