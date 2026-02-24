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

include 'portal_header.php';
?>

<style>
.heim-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
    gap: 0.5rem;
    margin-bottom: 1rem;
}
.heim-stat {
    background: white;
    border-radius: 0.75rem;
    padding: 0.6rem;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.heim-stat .number { font-size: 1.15rem; font-weight: 700; color: #2d3748; }
.heim-stat .label { font-size: 0.7rem; color: #718096; }
.heim-stat .label i { color: #28a745; margin-right: 0.2rem; }
.heim-stat.total { border-top: 3px solid #28a745; background: #f0faf3; }
.heim-stat.total .number { color: #1a8c35; }
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
.passe-card .passe-nr { font-size: 0.7rem; color: #718096; font-weight: 600; }
.passe-card .passe-nr .bi-star-fill { color: #28a745; font-size: 0.65rem; }
.passe-card .passe-value { font-size: 1.3rem; font-weight: 700; }
.passe-card .passe-value.shot { color: #28a745; }
.passe-card .passe-value.not-shot { color: #999; }
@media (max-width: 767.98px) {
    .passe-grid { grid-template-columns: repeat(2, 1fr); }
    .heim-summary { grid-template-columns: repeat(2, 1fr); }
}
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

<div class="heim-summary">
    <div class="heim-stat total">
        <div class="number"><?php echo $total; ?></div>
        <div class="label"><i class="bi bi-bullseye"></i>Total</div>
    </div>
    <div class="heim-stat">
        <div class="number"><?php echo $geschossen_count; ?> / 8</div>
        <div class="label"><i class="bi bi-check2-all"></i>Passen geschossen</div>
    </div>
    <div class="heim-stat">
        <div class="number"><?php echo $geschossen_count > 0 ? round($total / $geschossen_count, 1) : '-'; ?></div>
        <div class="label"><i class="bi bi-graph-up"></i>Durchschnitt</div>
    </div>
</div>

<div class="passe-grid">
    <?php for ($i = 0; $i < 8; $i++):
        $val = $passen[$i];
        $shot = ($val !== null);
        $is_best = ($shot && $val == $best_passe && $best_passe > 0);

        $card_class = 'passe-card';
        if ($is_best) $card_class .= ' best';
        elseif ($shot) $card_class .= ' shot';
        else $card_class .= ' not-shot';
    ?>
    <div class="<?php echo $card_class; ?>">
        <div class="passe-nr"><?php if ($is_best): ?><i class="bi bi-star-fill me-1"></i><?php endif; ?>Passe <?php echo $i + 1; ?></div>
        <div class="passe-value <?php echo $shot ? 'shot' : 'not-shot'; ?>">
            <?php echo $shot ? $val : '-'; ?>
        </div>
        <?php if (!$shot): ?>
        <small class="text-muted">Nicht geschossen</small>
        <?php endif; ?>
    </div>
    <?php endfor; ?>
</div>

<?php endif; ?>

<?php include 'portal_footer.php'; ?>
