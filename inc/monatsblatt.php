<?php
// monatsblatt.php – Monatsblatt (Schiesstage eines Monatsbereichs) als PDF exportieren
include 'dbconnect.inc.php';

// Nur rohes CSS: header.inc.php wrappt selbst in <style>
$page_specific_css = <<<'CSS'
@media (max-width: 767.98px) {
    .main-content-wrapper textarea.form-control { min-height: 120px; font-size: 16px; }
}
CSS;

include 'header.inc.php';

$monate = [1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
$aktMonat = (int)date('n');
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12 ps-0">
            <div class="main-content-wrapper content-width-default">
                <?php $page_title = 'Monatsblatt'; include 'partials/page_header.inc.php'; ?>

                <div class="content-background">
                    <form id="pdfExportForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                        <!-- Jahr/Zeitraum + Dokument erstellen (Export-Toolbar wie auf den Ranglisten-Seiten) -->
                        <div class="export-toolbar mb-3">
                            <div class="export-toolbar-head flex-wrap">
                                <label for="exportYear" class="export-year-label mb-0">
                                    <i class="bi bi-calendar3 me-1"></i>Jahr
                                </label>
                                <select id="exportYear" name="year" class="form-select form-select-sm export-year-select"></select>
                                <span class="export-toolbar-divider" aria-hidden="true"></span>
                                <label for="exportStartMonth" class="export-year-label mb-0">Von</label>
                                <select id="exportStartMonth" name="start_month" class="form-select form-select-sm export-year-select">
                                    <?php foreach ($monate as $nr => $name): ?>
                                        <option value="<?= sprintf('%02d', $nr) ?>" <?= $nr === $aktMonat ? 'selected' : '' ?>><?= $name ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="exportEndMonth" class="export-year-label mb-0">Bis</label>
                                <select id="exportEndMonth" name="end_month" class="form-select form-select-sm export-year-select">
                                    <?php foreach ($monate as $nr => $name): ?>
                                        <option value="<?= sprintf('%02d', $nr) ?>" <?= $nr === $aktMonat ? 'selected' : '' ?>><?= $name ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="export-group-btns">
                                <button type="submit" class="btn btn-outline-info btn-sm" id="btnExportPdf">
                                    <i class="bi bi-file-earmark-pdf me-1"></i><span>Monatsblatt PDF</span>
                                </button>
                                <button type="button" class="btn btn-outline-info btn-sm msv-druck"
                                        data-druck-doctype="monatsblatt" data-druck-label="Monatsblatt"
                                        aria-label="Monatsblatt direkt drucken"><i class="bi bi-printer"></i></button>
                            </div>
                        </div>

                        <!-- Bemerkungsfeld -->
                        <div class="mb-3">
                            <label for="bemerkung" class="form-label fw-bold mb-1">
                                <i class="bi bi-chat-text me-1"></i>Bemerkungen
                            </label>
                            <textarea id="bemerkung" name="bemerkung" rows="4" class="form-control form-control-sm"
                                      placeholder="Optional: Zusätzliche Bemerkungen für das Monatsblatt..."></textarea>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'partials/direktdruck_scripts.inc.php'; ?>

<script>
$(function() {
    // Jahr-Dropdown: aktuelles Jahr + 1 bis -3 (wie JM-Definition)
    (function() {
        const $y = $('#exportYear').empty();
        const cur = new Date().getFullYear();
        for (let y = cur + 1; y >= cur - 3; y--) {
            $y.append($('<option></option>').val(y).text(y).prop('selected', y === cur));
        }
    })();

    // Von-Monat darf nicht nach dem Bis-Monat liegen: sanft mitziehen
    $('#exportStartMonth').on('change', function() {
        if (this.value > $('#exportEndMonth').val()) $('#exportEndMonth').val(this.value);
    });
    $('#exportEndMonth').on('change', function() {
        if (this.value < $('#exportStartMonth').val()) $('#exportStartMonth').val(this.value);
    });

    function exportParams() {
        const year = $('#exportYear').val(), start = $('#exportStartMonth').val(), end = $('#exportEndMonth').val();
        if (!year || !start || !end) { msvToast('Bitte Jahr und Zeitraum wählen', 'warning'); return null; }
        if (start > end) { msvToast('Der Von-Monat liegt nach dem Bis-Monat', 'warning'); return null; }
        return {
            year, start, end,
            body: new URLSearchParams({
                year, start_month: start, end_month: end,
                bemerkung: $('#bemerkung').val(),
                csrf_token: $('input[name="csrf_token"]').val()
            }).toString()
        };
    }

    // PDF generieren und herunterladen (fetch -> Blob -> Download)
    $('#pdfExportForm').on('submit', async function(e) {
        e.preventDefault();
        const p = exportParams();
        if (!p) return;
        const $btn = $('#btnExportPdf'), orig = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Generiere PDF...');
        try {
            const response = await fetch('monatsblatt/export_monatsblatt.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: p.body
            });
            if (!response.ok) throw new Error((await response.text()) || 'Fehler beim Generieren');
            const blob = await response.blob();
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `Monatsblatt_${p.year}_${p.start}-${p.end}.pdf`;
            document.body.appendChild(a); a.click(); a.remove();
            URL.revokeObjectURL(a.href);
            msvToast('PDF heruntergeladen', 'success');
        } catch (err) {
            console.error('PDF Export Error:', err);
            msvToast('Fehler beim Generieren des PDFs: ' + err.message, 'error');
        } finally {
            $btn.prop('disabled', false).html(orig);
        }
    });

    // Direktdruck: gleicher POST, Ergebnis geht an QZ Tray (Profil «monatsblatt» in der Drucksteuerung)
    if (typeof MsvDruck !== 'undefined') {
        MsvDruck.resolve('monatsblatt', () => {
            const p = exportParams();
            if (!p) return null;
            return {
                url: 'monatsblatt/export_monatsblatt.php',
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: p.body,
                jobName: `Monatsblatt ${p.year} ${p.start}-${p.end}`
            };
        });
    }
});
</script>

<?php include 'footer.inc.php'; ?>
