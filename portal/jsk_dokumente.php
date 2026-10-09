<?php
// portal/jsk_dokumente.php - Dokumente fuer Jungschuetzen ansehen
// Verwaltung (Hochladen/Löschen) läuft zentral im Admin-Bereich
// (inc/dokumente_verwaltung.php). Das Portal ist nur Ansicht.
$portal_page_title = 'JSK-Dokumente';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/inc_dokumente.php';
requireLogin();
$db = getDB();

$selected_year = intval($_GET['year'] ?? date('Y'));

// Dokumente laden (Vorstand/Admin sehen alle; alle anderen nur 'alle_mitglieder')
if (isVorstand()) {
    $stmt = $db->prepare("
        SELECT d.*
        FROM vorstand_dokumente d
        WHERE d.typ = 'jsk' AND (d.jahr = ? OR d.jahr IS NULL)
        ORDER BY d.datum DESC, d.hochgeladen_am DESC
    ");
} else {
    $stmt = $db->prepare("
        SELECT d.*
        FROM vorstand_dokumente d
        WHERE d.typ = 'jsk' AND d.sichtbar_fuer = 'alle_mitglieder' AND (d.jahr = ? OR d.jahr IS NULL)
        ORDER BY d.datum DESC, d.hochgeladen_am DESC
    ");
}
$stmt->execute([$selected_year]);
$dokumente = $stmt->fetchAll();

$years_stmt = $db->query("SELECT DISTINCT jahr FROM vorstand_dokumente WHERE typ='jsk' AND jahr IS NOT NULL ORDER BY jahr DESC");
$available_years = $years_stmt->fetchAll(PDO::FETCH_COLUMN);
if (!in_array(date('Y'), $available_years)) array_unshift($available_years, date('Y'));
if (empty($available_years)) $available_years = [date('Y')];

include 'portal_header.php';
?>

<div class="portal-page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h1><i class="bi bi-mortarboard me-2"></i>JSK-Dokumente</h1>
        <p class="subtitle mb-0"><?php echo $selected_year; ?></p>
    </div>
    <form method="get" class="d-flex align-items-center gap-2">
        <select name="year" class="form-select form-select-sm" style="max-width:140px;" aria-label="Jahr" onchange="this.form.submit()">
            <?php foreach ($available_years as $y): ?>
            <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>><?php echo $y; ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if (empty($dokumente)): ?>
<div class="text-center py-5">
    <i class="bi bi-folder2-open" style="font-size: 3rem; color: #dee2e6;"></i>
    <p class="text-muted mt-3">Keine JSK-Dokumente für <?php echo $selected_year; ?> vorhanden</p>
</div>
<?php else: ?>
<?php portalDokumentListe($dokumente); ?>
<?php endif; ?>

<?php include 'portal_footer.php'; ?>
