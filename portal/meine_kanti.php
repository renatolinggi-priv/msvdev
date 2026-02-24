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
.kanti-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
    gap: 0.5rem;
    margin-bottom: 1rem;
}
.kanti-stat {
    background: white;
    border-radius: 0.75rem;
    padding: 0.6rem;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.kanti-stat .number { font-size: 1.15rem; font-weight: 700; color: #2d3748; }
.kanti-stat .label { font-size: 0.7rem; color: #718096; }
.kanti-stat .label i { color: #28a745; margin-right: 0.2rem; }
.kanti-stat.total { border-top: 3px solid #28a745; background: #f0faf3; }
.kanti-stat.total .number { color: #1a8c35; }
.passe-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 0.5rem;
}
.passe-card {
    background: white;
    border-radius: 0.75rem;
    padding: 0.6rem;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    border-left: 4px solid #dee2e6;
}
.passe-card.shot { border-left-color: #28a745; }
.passe-card.not-shot { border-left-color: #dee2e6; }
.passe-card.best { border-left-color: #28a745; border-left-width: 4px; background: #f0faf3; }
.passe-card.kranz { border-left-color: #ffc107; background: linear-gradient(135deg, #fff9e6, #fff3cd); }
.passe-card .passe-nr { font-size: 0.7rem; color: #718096; font-weight: 600; }
.passe-card .passe-nr .bi-star-fill { color: #28a745; font-size: 0.65rem; }
.passe-card .passe-value { font-size: 1.3rem; font-weight: 700; }
.passe-card .passe-value.shot { color: #28a745; }
.passe-card .passe-value.not-shot { color: #999; }
.kranz-badge {
    display: inline-block;
    background: linear-gradient(135deg, #ffc107, #ff9800);
    color: #343a40;
    padding: 0.15rem 0.5rem;
    border-radius: 4px;
    font-size: 0.65rem;
    font-weight: 700;
    margin-top: 0.2rem;
}
.kranz-info {
    background: #f8f9fa;
    border-radius: 0.75rem;
    padding: 0.6rem;
    margin-bottom: 1rem;
    font-size: 0.8rem;
}
@media (max-width: 767.98px) {
    .passe-grid { grid-template-columns: repeat(2, 1fr); }
    .kanti-summary { grid-template-columns: repeat(2, 1fr); }
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

<div class="kanti-summary">
    <div class="kanti-stat total">
        <div class="number"><?php echo $total; ?></div>
        <div class="label"><i class="bi bi-bullseye"></i>Total</div>
    </div>
    <div class="kanti-stat">
        <div class="number"><?php echo $geschossen_count; ?> / 5</div>
        <div class="label"><i class="bi bi-check2-all"></i>Passen geschossen</div>
    </div>
    <div class="kanti-stat">
        <div class="number"><?php echo $best_passe; ?></div>
        <div class="label"><i class="bi bi-star"></i>Beste Passe</div>
    </div>
    <?php if ($kranzlimite !== null):
        $kranz_count = 0;
        foreach ($passen as $p) { if ($p !== null && $p >= $kranzlimite) $kranz_count++; }
    ?>
    <div class="kanti-stat">
        <div class="number"><?php echo $kranz_count; ?></div>
        <div class="label"><i class="bi bi-award"></i>Kränze</div>
    </div>
    <?php endif; ?>
</div>

<div class="passe-grid">
    <?php for ($i = 0; $i < 5; $i++):
        $val = $passen[$i];
        $shot = ($val !== null);
        $is_kranz = ($shot && $kranzlimite !== null && $val >= $kranzlimite);
        $is_best = ($shot && $val == $best_passe && $best_passe > 0);

        $card_class = 'passe-card';
        if ($is_kranz) $card_class .= ' kranz';
        elseif ($is_best) $card_class .= ' best';
        elseif ($shot) $card_class .= ' shot';
        else $card_class .= ' not-shot';
    ?>
    <div class="<?php echo $card_class; ?>">
        <div class="passe-nr"><?php if ($is_best && !$is_kranz): ?><i class="bi bi-star-fill me-1"></i><?php endif; ?>Passe <?php echo $i + 1; ?></div>
        <div class="passe-value <?php echo $shot ? 'shot' : 'not-shot'; ?>">
            <?php echo $shot ? $val : '-'; ?>
        </div>
        <?php if ($is_kranz): ?>
            <div class="kranz-badge"><i class="bi bi-award me-1"></i>Kranz</div>
        <?php elseif ($shot && $kranzlimite !== null): ?>
            <small class="text-muted">Kein Kranz (<?php echo $kranzlimite - $val; ?> fehlen)</small>
        <?php elseif (!$shot): ?>
            <small class="text-muted">Nicht geschossen</small>
        <?php endif; ?>
    </div>
    <?php endfor; ?>
</div>

<?php endif; ?>

<?php include 'portal_footer.php'; ?>
