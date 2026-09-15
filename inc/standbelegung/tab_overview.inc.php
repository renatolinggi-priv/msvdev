<?php
/** Tab «Übersicht & Export» – erwartet $currentYear, $years, $pdfInfos */
?>
<div class="tab-pane fade" id="overview" role="tabpanel">

    <?php if (isset($pdfInfos[$currentYear])): ?>
    <div class="alert alert-light border mb-3 d-flex align-items-center flex-wrap gap-2">
        <i class="bi bi-file-pdf text-danger fs-4 me-2"></i>
        <div><strong>Standbelegung <?= $currentYear ?></strong><span class="text-muted ms-2">(<?= $pdfInfos[$currentYear]['size'] ?> KB)</span></div>
        <div class="ms-auto d-flex gap-2">
            <a href="standbelegung/pdf/standbelegung_<?= $currentYear ?>.pdf" target="_blank" rel="noopener" class="btn btn-outline-info btn-sm"><i class="bi bi-eye me-1"></i>Anzeigen</a>
            <a href="standbelegung/pdf/standbelegung_<?= $currentYear ?>.pdf" download class="btn btn-outline-info btn-sm"><i class="bi bi-download me-1"></i>Download</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filter + Aktionen -->
    <div class="d-flex flex-wrap gap-3 align-items-start mb-3">
        <div class="d-flex align-items-center gap-2">
            <label for="overviewYear" class="form-label fw-bold mb-0 small text-nowrap">Jahr</label>
            <select id="overviewYear" class="form-select form-select-sm" style="width:auto;min-width:110px;">
                <option value="">Alle Jahre</option>
                <?php foreach ($years as $y): ?>
                <option value="<?= $y ?>" <?= $y === $currentYear ? 'selected' : '' ?>><?= $y ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="d-flex align-items-center gap-2 flex-grow-1" style="max-width:260px;">
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" id="overviewSearch" placeholder="Bezeichnung suchen..." aria-label="Bezeichnung suchen">
            </div>
        </div>
        <?php
        $ac_id = 'standbelegungActions';
        ob_start(); ?>
            <div class="row g-2 mb-2">
                <div class="col-6">
                    <button type="button" class="btn btn-outline-success btn-sm w-100" data-action="entry-add"><i class="bi bi-plus-lg me-1"></i>Hinzufügen</button>
                </div>
                <div class="col-6">
                    <button type="button" class="btn btn-outline-danger btn-sm w-100" data-action="delete-selected"><i class="bi bi-trash me-1"></i>Löschen</button>
                </div>
            </div>
            <div class="border-top pt-2">
                <small class="text-muted d-block mb-2"><i class="bi bi-download me-1"></i>Exporte</small>
                <div class="row g-2">
                    <div class="col-6">
                        <button type="button" class="btn btn-outline-info btn-sm w-100" data-action="export-jsk-pdf"><i class="bi bi-file-pdf me-1"></i>JSK PDF</button>
                    </div>
                    <div class="col-6">
                        <button type="button" class="btn btn-outline-info btn-sm w-100" data-action="export-preview"><i class="bi bi-file-earmark-excel me-1"></i>Schiesstage</button>
                    </div>
                </div>
            </div>
        <?php
        $ac_body = ob_get_clean();
        include __DIR__ . '/../partials/action_card.inc.php';
        ?>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-1 mb-3">
        <span class="me-2 fw-bold small">Kategorien:</span>
        <?php foreach (SB_KATEGORIEN as $kat): ?>
        <button type="button" class="filter-chip filter-chip-<?= strtolower($kat) ?><?= $kat === '300m' ? ' active' : '' ?>" data-kategorie="<?= $kat ?>" data-scope="overview" aria-pressed="<?= $kat === '300m' ? 'true' : 'false' ?>"><?= $kat ?></button>
        <?php endforeach; ?>
    </div>

    <div class="row mb-3 g-2" id="overviewStatsRow"></div>

    <!-- Tabelle -->
    <div class="table-wrapper">
        <h5 class="table-title">
            <span><i class="bi bi-table me-2"></i>Einträge <span class="badge bg-secondary ms-1" id="overviewVisibleCount">0</span></span>
            <span class="d-flex align-items-center gap-2 small text-muted">
                <span><span id="overviewCount">0</span> ausgewählt</span>
                <button type="button" class="btn btn-outline-secondary btn-sm py-0" data-action="overview-select-all"><i class="bi bi-check-all"></i> Alle</button>
                <button type="button" class="btn btn-outline-secondary btn-sm py-0" data-action="overview-select-none"><i class="bi bi-x-lg"></i> Keine</button>
            </span>
        </h5>
        <div class="desktop-table-container">
            <div class="preview-table-wrapper">
                <table class="table table-hover table-sm preview-table mb-0" id="overviewTable">
                    <thead>
                        <tr>
                            <th style="width:40px;"><input type="checkbox" class="form-check-input" id="overviewSelectAll" aria-label="Alle sichtbaren auswählen"></th>
                            <th style="width:50px;" data-tooltip="Im Kalender anzeigen"><i class="bi bi-calendar-check"></i></th>
                            <th>Datum</th><th>Tag</th><th>Bezeichnung</th><th>Von</th><th>Bis</th><th>Kategorie</th>
                            <th style="width:90px;">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody id="overviewTableBody"></tbody>
                </table>
            </div>
        </div>

        <!-- Mobile: Cards aus den gefilterten Daten -->
        <div class="mobile-cards-container" id="mobileCardsStandbelegung">
            <div class="mobile-cards-scroll"></div>
        </div>
    </div>
</div>
