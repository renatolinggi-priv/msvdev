<?php
/** Tab «Import» – erwartet $currentYear, $stats, $pdfInfos (aus load_page_data.inc.php) */
?>
<div class="tab-pane fade show active" id="import" role="tabpanel">

    <?php if (!empty($stats)): ?>
    <div class="alert alert-info mb-3">
        <i class="bi bi-database me-2"></i><strong>Bestehende Daten:</strong>
        <?php
        $infoTexts = [];
        foreach ($stats as $jahr => $data) {
            $infoTexts[] = "{$jahr}: {$data['total']} Einträge ({$data['inKalender']} im Kalender)";
        }
        echo implode(' | ', $infoTexts);
        ?>
        <br><small class="text-muted">Beim Import werden bestehende Einträge mit gleichem Datum, gleicher Bezeichnung und Startzeit aktualisiert.</small>
    </div>
    <?php endif; ?>

    <div class="row mb-3">
        <div class="col-md-4">
            <label for="importYear" class="form-label fw-bold"><i class="bi bi-calendar3 me-1"></i>Jahr für Import</label>
            <select id="importYear" class="form-select form-select-sm">
                <?php for ($y = $currentYear - 1; $y <= $currentYear + 1; $y++): ?>
                <option value="<?= $y ?>" <?= $y === $currentYear ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
    </div>

    <!-- Upload-Bereiche (zentrale .import-area) -->
    <div class="row g-3">
        <div class="col-md-6">
            <div class="import-area sb-upload" id="uploadArea" role="button" tabindex="0" aria-label="Excel-Datei wählen">
                <i class="bi bi-file-earmark-excel sb-upload-icon text-success"></i>
                <h5 class="mt-3">Excel-Datei</h5>
                <p class="text-muted mb-0">Für Daten-Import (.xlsx)</p>
            </div>
            <input type="file" id="fileInput" accept=".xlsx,.xls" hidden>
        </div>
        <div class="col-md-6">
            <div class="import-area sb-upload" id="pdfUploadArea" role="button" tabindex="0" aria-label="PDF-Datei wählen">
                <i class="bi bi-file-earmark-pdf sb-upload-icon text-danger"></i>
                <h5 class="mt-3">PDF-Datei</h5>
                <p class="text-muted mb-0">Für Anzeige auf der Website (.pdf)</p>
                <span id="pdfFileName" class="badge bg-success mt-2" hidden></span>
            </div>
            <input type="file" id="pdfFileInput" accept=".pdf" hidden>
        </div>
    </div>

    <?php if ($pdfInfos): ?>
    <div class="alert alert-success mt-3">
        <i class="bi bi-file-pdf me-2"></i><strong>Gespeicherte PDFs:</strong>
        <?php foreach ($pdfInfos as $pdfYear => $info): ?>
        <span class="ms-3"><a href="standbelegung/pdf/standbelegung_<?= $pdfYear ?>.pdf" target="_blank" rel="noopener"><?= $pdfYear ?> (<?= $info['size'] ?> KB, <?= $info['date'] ?>)</a></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Import-Vorschau -->
    <div id="importPreview" hidden>
        <hr>
        <h5><i class="bi bi-eye me-2"></i>Vorschau</h5>
        <div class="row mb-3 g-2" id="importStatsRow"></div>

        <div class="d-flex flex-wrap align-items-center gap-1 mb-2">
            <span class="me-2 fw-bold small">Filter:</span>
            <?php foreach (SB_KATEGORIEN as $kat): ?>
            <button type="button" class="filter-chip filter-chip-<?= strtolower($kat) ?>" data-kategorie="<?= $kat ?>" data-scope="import" aria-pressed="true"><?= $kat ?> <span class="existing-count"></span></button>
            <?php endforeach; ?>
            <span class="ms-auto d-flex gap-1">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-action="import-select-all"><i class="bi bi-check-all"></i> Alle sichtbaren</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-action="import-select-none"><i class="bi bi-x-lg"></i> Keine</button>
            </span>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-1 mb-3">
            <span class="me-2 fw-bold small">Kalender-Anzeige für sichtbare:</span>
            <button type="button" class="btn btn-outline-success btn-sm" data-action="import-kalender-on"><i class="bi bi-calendar-check"></i> Alle im Kalender</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-action="import-kalender-off"><i class="bi bi-calendar-x"></i> Keine im Kalender</button>
        </div>

        <div class="preview-table-wrapper">
            <table class="table table-hover table-sm preview-table mb-0">
                <thead>
                    <tr>
                        <th style="width:40px;"><input type="checkbox" class="form-check-input" id="importSelectAll" aria-label="Alle auswählen"></th>
                        <th style="width:50px;" data-tooltip="Im Kalender anzeigen"><i class="bi bi-calendar-check"></i></th>
                        <th>Datum</th><th>Tag</th><th>Bezeichnung</th><th>Von</th><th>Bis</th><th>Kategorie</th>
                    </tr>
                </thead>
                <tbody id="importTableBody"></tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-action="import-reset"><i class="bi bi-arrow-left me-1"></i> Zurück</button>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-success btn-sm" id="saveImportBtn" data-action="import-save">
                    <i class="bi bi-database-add me-1"></i> <span id="saveImportCount">0</span> Einträge importieren
                </button>
                <button type="button" class="btn btn-outline-info btn-sm" data-action="export-from-import">
                    <i class="bi bi-file-earmark-excel me-1"></i> Direkt exportieren
                </button>
            </div>
        </div>
    </div>
</div>
