<?php
// portal/einsatzplaene.php - Einsatzpläne ansehen
// Gesamte Verwaltung (Upload, Bearbeiten, Löschen, Einsatz-Import, Zuweisungen, Tausch-Log)
// läuft zentral im Admin-Bereich (inc/dokumente_verwaltung.php).
// Das Portal zeigt Einsatzpläne und Einsatzplan-Dokumente nur an – für alle Rollen.
$portal_page_title = 'Einsatzpläne';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/inc_dokumente.php';
requireLogin();
$db = getDB();

$user_role = $_SESSION['user_role'] ?? 'mitglied';
$selected_year = intval($_GET['year'] ?? date('Y'));

// Dokumente laden (Berechtigungspruefung)
if ($user_role === 'admin') {
    // Admin: alle Einsatzplaene sehen (inkl. admin-only)
    $stmt = $db->prepare("
        SELECT d.*
        FROM vorstand_dokumente d
        WHERE d.typ = 'einsatzplan' AND (d.jahr = ? OR d.jahr IS NULL)
        ORDER BY d.datum DESC, d.hochgeladen_am DESC
    ");
} elseif ($user_role === 'vorstand') {
    // Vorstand: nur vorstand + alle_mitglieder (nicht admin-only)
    $stmt = $db->prepare("
        SELECT d.*
        FROM vorstand_dokumente d
        WHERE d.typ = 'einsatzplan' AND d.sichtbar_fuer IN ('vorstand', 'alle_mitglieder')
          AND (d.jahr = ? OR d.jahr IS NULL)
        ORDER BY d.datum DESC, d.hochgeladen_am DESC
    ");
} else {
    // Mitglied: nur fuer alle sichtbare
    $stmt = $db->prepare("
        SELECT d.*
        FROM vorstand_dokumente d
        WHERE d.typ = 'einsatzplan' AND d.sichtbar_fuer = 'alle_mitglieder'
          AND (d.jahr = ? OR d.jahr IS NULL)
        ORDER BY d.datum DESC, d.hochgeladen_am DESC
    ");
}
$stmt->execute([$selected_year]);
$dokumente = $stmt->fetchAll();

// Strukturierte Einsatzpläne aus der Einsatzplanung (Migration 050; Tabelle evtl. noch nicht vorhanden).
// Mitglieder sehen freigegebene/finale Pläne, Vorstand/Admin auch Entwürfe.
$plaene_struktur = [];
try {
    $pq = $db->prepare("SELECT p.id, p.titel, p.typ, p.status, p.layout,
                               (SELECT MIN(t.datum) FROM einsatz_plan_termine t WHERE t.plan_id = p.id) AS erster_termin,
                               (SELECT COUNT(*) FROM einsatz_plan_termine t WHERE t.plan_id = p.id) AS anz_termine
                          FROM einsatz_plaene p
                         WHERE p.jahr = ? " . (in_array($user_role, ['admin', 'vorstand'], true) ? '' : "AND p.status <> 'entwurf' ") . "
                         ORDER BY erster_termin, p.titel");
    $pq->execute([$selected_year]);
    $plaene_struktur = $pq->fetchAll();
} catch (Throwable $e) { $plaene_struktur = []; }

// Verfuegbare Jahre
$years_stmt = $db->query("SELECT DISTINCT jahr FROM vorstand_dokumente WHERE typ='einsatzplan' AND jahr IS NOT NULL ORDER BY jahr DESC");
$available_years = $years_stmt->fetchAll(PDO::FETCH_COLUMN);
if (!in_array(date('Y'), $available_years)) array_unshift($available_years, date('Y'));
if (empty($available_years)) $available_years = [date('Y')];

include 'portal_header.php';
?>

<div class="portal-page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h1><i class="bi bi-calendar-check me-2"></i>Einsatzpläne</h1>
        <p class="subtitle mb-0"><?php echo $selected_year; ?></p>
    </div>
    <form method="get" class="d-flex align-items-center gap-2">
        <?php if (isAdmin() || isVorstand()): ?><a href="einsatz_anwesenheit.php" class="btn btn-sm btn-outline-primary" title="Anwesenheit bei Einsätzen erfassen"><i class="bi bi-person-check me-1"></i>Anwesenheit</a><?php endif; ?>
        <select name="year" class="form-select form-select-sm" style="max-width:140px;" aria-label="Jahr" onchange="this.form.submit()">
            <?php foreach ($available_years as $y): ?>
            <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>><?php echo $y; ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if (!empty($plaene_struktur)): ?>
<!-- Strukturierte Einsatzpläne (Einsatzplanung) -->
<div class="p-eyebrow">Einsatzpläne als Tabelle</div>
<div class="p-list mb-4">
    <?php foreach ($plaene_struktur as $pl):
        $plTyp = ['obligatorisch' => 'Obligatorisch', 'feldschiessen' => 'Feldschiessen', 'chilbi' => 'Wiler Chilbi'][$pl['typ']] ?? 'Einsatzplan';
        $plStatus = ['entwurf' => 'Entwurf', 'freigegeben' => 'Freigegeben', 'final' => 'Definitiv'][$pl['status']] ?? $pl['status'];
    ?>
    <a class="p-list-row is-clickable text-decoration-none" href="einsatzplan.php?id=<?php echo (int) $pl['id']; ?>">
        <div class="p-chip lg orange"><i class="bi bi-person-lines-fill"></i></div>
        <div class="p-list-body">
            <div class="p-list-title"><?php echo htmlspecialchars($pl['titel']); ?></div>
            <div class="p-list-meta"><?php echo htmlspecialchars($plTyp); ?> · <?php echo (int) $pl['anz_termine']; ?> Termine
                <?php if ($pl['erster_termin']): ?> · ab <?php echo date('d.m.Y', strtotime($pl['erster_termin'])); ?><?php endif; ?>
                <?php if ($pl['status'] !== 'final'): ?> · <span class="<?php echo $pl['status'] === 'entwurf' ? 'text-danger' : 'text-primary'; ?>"><?php echo htmlspecialchars($plStatus); ?></span><?php endif; ?>
            </div>
        </div>
        <div class="p-list-actions"><i class="bi bi-chevron-right text-muted"></i></div>
    </a>
    <?php endforeach; ?>
</div>
<div class="p-eyebrow">Dokumente</div>
<?php endif; ?>

<!-- Dokumentenliste -->
<?php if (empty($dokumente)): ?>
<div class="text-center py-5">
    <i class="bi bi-folder2-open" style="font-size: 3rem; color: #dee2e6;"></i>
    <p class="text-muted mt-3">Keine Einsatzpläne für <?php echo $selected_year; ?> vorhanden</p>
</div>
<?php else: ?>
<?php portalDokumentListe($dokumente); ?>
<?php endif; ?>

<?php include 'portal_footer.php'; ?>
