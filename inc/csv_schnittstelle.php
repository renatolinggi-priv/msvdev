<?php
// csv_schnittstelle.php - Einstellungen CSV-Schnittstelle (Schiessanlage)
require_once 'config.php';
require_once __DIR__ . '/../auth.php';

requireLogin();

// Nur Admin + Vorstand
if (!in_array($_SESSION['user_role'] ?? '', ['admin', 'vorstand'])) {
    header('Location: home.php');
    exit();
}

$page_specific_css = '
    .data-card { max-width: 720px; }
    @media (max-width: 991.98px) { .data-card { max-width: 100%; } }

    .csv-status-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 10px;
    }
    .csv-stat-box {
        text-align: center; padding: 12px 8px;
        background: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef;
    }
    .csv-stat-box .stat-value { font-size: 1.5rem; font-weight: 700; line-height: 1.2; }
    .csv-stat-box .stat-label { font-size: 0.72rem; color: #6c757d; text-transform: uppercase; letter-spacing: 0.04em; font-weight: 600; }
    .csv-stat-box.mitglied .stat-value { color: #0d6efd; }
    .csv-stat-box.gast .stat-value { color: #f59e0b; }
    .csv-stat-box.js .stat-value { color: #22c55e; }
    .csv-stat-box.total .stat-value { color: #212529; }

    .csv-file-info { font-size: 0.8rem; color: #6c757d; }
    .csv-file-info i { font-size: 0.7rem; }
';

include 'header.inc.php';
?>

<div class="container-fluid mt-3">
    <h4 class="mb-3"><i class="bi bi-arrow-left-right me-2"></i>CSV-Schnittstelle (Schiessanlage)</h4>

    <!-- Einstellungen -->
    <div class="card data-card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-gear me-1"></i>Einstellungen</span>
            <button class="btn btn-primary btn-sm" onclick="CsvSettings.save()">
                <i class="bi bi-save me-1"></i>Speichern
            </button>
        </div>
        <div class="card-body">
            <!-- Aktiv-Switch -->
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="csvExportAktiv">
                <label class="form-check-label" for="csvExportAktiv">Export aktiv</label>
            </div>

            <!-- Dateipfad -->
            <div class="mb-3">
                <label class="form-label small fw-semibold">Pfad shooters.csv:</label>
                <input type="text" id="csvPfadShooters" class="form-control form-control-sm"
                       placeholder="z.B. C:\Schiessanlage\shooters.csv">
                <div class="form-text">Absoluter Pfad wo die Datei geschrieben wird. Das Verzeichnis muss existieren.</div>
            </div>

            <!-- Jahr -->
            <div class="mb-0">
                <span class="small text-muted">Aktuelles Jahr: <strong><?= date('Y') ?></strong></span>
            </div>
        </div>
    </div>

    <!-- Status & Export -->
    <div class="card data-card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-file-earmark-spreadsheet me-1"></i>Export-Status</span>
            <button class="btn btn-success btn-sm" onclick="CsvSettings.exportNow()" id="btnExport">
                <i class="bi bi-download me-1"></i>Jetzt exportieren
            </button>
        </div>
        <div class="card-body">
            <!-- Statistik-Boxen -->
            <div class="csv-status-grid mb-3" id="csvStatusGrid">
                <div class="csv-stat-box mitglied">
                    <div class="stat-value" id="statMitglieder">–</div>
                    <div class="stat-label">Mitglieder</div>
                </div>
                <div class="csv-stat-box gast">
                    <div class="stat-value" id="statGaeste">–</div>
                    <div class="stat-label">Gaeste</div>
                </div>
                <div class="csv-stat-box js">
                    <div class="stat-value" id="statJs">–</div>
                    <div class="stat-label">Jungschuetzen</div>
                </div>
                <div class="csv-stat-box total">
                    <div class="stat-value" id="statTotal">–</div>
                    <div class="stat-label">Total</div>
                </div>
            </div>

            <!-- Datei-Info -->
            <div class="csv-file-info" id="csvFileInfo" style="display:none;">
                <i class="bi bi-file-earmark-check text-success me-1"></i>
                <span id="csvFileInfoText"></span>
            </div>
            <div class="csv-file-info text-muted" id="csvFileNone">
                <i class="bi bi-file-earmark-x me-1"></i>Noch keine Datei geschrieben
            </div>
        </div>
    </div>

    <!-- Info -->
    <div class="card data-card mb-3">
        <div class="card-body small text-muted">
            <p class="mb-2"><strong>Wie funktioniert es?</strong></p>
            <ul class="mb-0">
                <li>Exportiert werden alle Personen (Mitglieder, Gaeste, Jungschuetzen) die mindestens einen Stich geloest haben.</li>
                <li>Mitglieder behalten ihre bestehende ID. Gaeste und Jungschuetzen erhalten eine synthetische Nummer ab 999000.</li>
                <li>Format: <code>Mitgliedernr;Name;;;Jahrgang</code> (Semikolon-getrennt, ISO-8859-1)</li>
                <li>Die Datei wird nur neu geschrieben wenn sich der Inhalt geaendert hat.</li>
            </ul>
        </div>
    </div>
</div>

<script>
const CsvSettings = {
    apiBase: 'csv_schnittstelle/csv_export_api.php',

    init() {
        this.loadSettings();
        this.loadStatus();
    },

    loadSettings() {
        $.getJSON(this.apiBase + '?action=get_settings', (res) => {
            if (!res.success) return;
            const d = res.data;
            $('#csvExportAktiv').prop('checked', (d.csv_export_aktiv || '0') === '1');
            $('#csvPfadShooters').val(d.csv_pfad_shooters || '');
        });
    },

    loadStatus() {
        $.getJSON(this.apiBase + '?action=get_status', (res) => {
            if (!res.success) return;
            const d = res.data;
            $('#statMitglieder').text(d.mitglieder);
            $('#statGaeste').text(d.gaeste);
            $('#statJs').text(d.js);
            $('#statTotal').text(d.total);

            if (d.file_exists) {
                $('#csvFileInfo').show();
                $('#csvFileNone').hide();
                $('#csvFileInfoText').text('Letzte Datei: ' + d.file_mtime);
            } else {
                $('#csvFileInfo').hide();
                $('#csvFileNone').show();
            }
        });
    },

    save() {
        const data = {
            action: 'save_settings',
            csv_export_aktiv: $('#csvExportAktiv').is(':checked') ? '1' : '0',
            csv_pfad_shooters: $('#csvPfadShooters').val().trim(),
        };
        $.ajax({
            url: this.apiBase,
            method: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-Token': window._csrfToken || '' },
            data: JSON.stringify(data),
            dataType: 'json',
            success: (res) => {
                if (res.success) {
                    msvToast('Einstellungen gespeichert', 'success');
                } else {
                    msvToast(res.message || 'Fehler', 'error');
                }
            },
            error: () => msvToast('Fehler beim Speichern', 'error'),
        });
    },

    exportNow() {
        const $btn = $('#btnExport');
        $btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>Exportiere...');

        $.ajax({
            url: this.apiBase,
            method: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-Token': window._csrfToken || '' },
            data: JSON.stringify({ action: 'export_shooters' }),
            dataType: 'json',
            success: (res) => {
                if (res.success) {
                    msvToast(res.message, 'success');
                    if (res.counts) {
                        $('#statMitglieder').text(res.counts.mitglied);
                        $('#statGaeste').text(res.counts.gast);
                        $('#statJs').text(res.counts.js);
                        $('#statTotal').text(res.total);
                    }
                    this.loadStatus();
                } else {
                    msvToast(res.message || 'Export fehlgeschlagen', 'error');
                }
            },
            error: () => msvToast('Export fehlgeschlagen', 'error'),
            complete: () => {
                $btn.prop('disabled', false).html('<i class="bi bi-download me-1"></i>Jetzt exportieren');
            },
        });
    },
};

$(document).ready(() => CsvSettings.init());
</script>

<?php include 'footer.inc.php'; ?>
