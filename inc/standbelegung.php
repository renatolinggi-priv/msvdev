<?php
// standbelegung.php - Import, Übersicht und Export Standbelegungsplan
require_once 'config.php';

$page_specific_css = '';
include 'header.inc.php';

// Session-Kontrolle
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Aktuelles Jahr
$currentYear = date('Y');

// Bestehende Einträge laden
$existingEntries = [];
$stats = [];

$sql = "SELECT ID, Datum, Wochentag, Bezeichnung, StartZeit, EndZeit, Kategorie, InKalender, Jahr 
        FROM Standbelegung 
        ORDER BY Jahr DESC, Datum ASC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $existingEntries[] = $row;
        $jahr = $row['Jahr'];
        $kat = $row['Kategorie'];
        if (!isset($stats[$jahr])) {
            $stats[$jahr] = ['300m' => 0, '50m' => 0, '25m' => 0, '10m' => 0, 'Sonstiges' => 0, 'total' => 0, 'inKalender' => 0];
        }
        if (isset($stats[$jahr][$kat])) {
            $stats[$jahr][$kat]++;
        }
        $stats[$jahr]['total']++;
        if ($row['InKalender']) {
            $stats[$jahr]['inKalender']++;
        }
    }
}

// Verfügbare Jahre ermitteln
$years = array_keys($stats);
if (!in_array($currentYear, $years)) {
    $years[] = $currentYear;
}
if (!in_array($currentYear + 1, $years)) {
    $years[] = $currentYear + 1;
}
sort($years);

// Art-Keywords laden
$artKeywords = [];
$sqlKw = "SELECT ID, Keyword, Art FROM Standbelegung_ArtKeywords ORDER BY Art, Keyword";
$resultKw = $conn->query($sqlKw);
if ($resultKw) {
    while ($row = $resultKw->fetch_assoc()) {
        $artKeywords[] = $row;
    }
}

// Art-Codes Definition
$artCodes = [
    'SF' => 'Schützenfest',
    'FS' => 'Feldschiessen',
    'OP' => 'Obligatorisches Programm',
    'WK' => 'Wettkampf/Match',
    'JSK' => 'Jungschützenkurs',
    'TR' => 'Training',
    'VS' => 'Versammlung',
    'AND' => 'Anderes'
];
?>
<style>

/* =========================================================
   Upload Area
   ========================================================= */
.upload-area {
    border: 2px dashed #dee2e6;
    border-radius: 0.75rem;
    padding: 3rem;
    text-align: center;
    background-color: #f8f9fa;
    cursor: pointer;
    margin-bottom: 1.5rem;
    transition: all 0.25s ease-in-out;
}

.upload-area:hover {
    border-color: #6c757d;
    background-color: #e9ecef;
}

.upload-area.dragover {
    border-color: #0d6efd;
    background-color: #e7f1ff;
}


/* =========================================================
   Loading Overlay
   ========================================================= */
.loading-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.7);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.loading-spinner {
    background: #fff;
    padding: 2rem;
    border-radius: 0.5rem;
    text-align: center;
    color: #333;
}

.spinner-border {
    width: 3rem;
    height: 3rem;
    border: 0.3rem solid #f3f3f3;
    border-top-color: #0d6efd;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}


/* =========================================================
   Toast Messages
   ========================================================= */
#toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
}

.toast-message {
    background: #fff;
    border-radius: 0.5rem;
    padding: 12px 20px;
    margin-bottom: 10px;
    min-width: 250px;
    border-left: 4px solid;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    opacity: 0;
    transform: translateX(100%);
    transition: all 0.25s ease-in-out;
}

.toast-message.show {
    opacity: 1;
    transform: translateX(0);
}

.toast-success { border-left-color: #28a745; }
.toast-warning { border-left-color: #ffc107; }
.toast-error   { border-left-color: #dc3545; }
.toast-info    { border-left-color: #17a2b8; }


/* =========================================================
   Preview Table
   ========================================================= */
.preview-table {
    font-size: 0.85rem;
}

.preview-table th {
    background-color: #f8f9fa;
    font-weight: 600;
    position: sticky;
    top: 0;
    z-index: 10;
}

.preview-table-wrapper {
    max-height: 500px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 0.5rem;
}


/* =========================================================
   Stat Cards
   ========================================================= */
.stat-card {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 0.5rem;
    padding: 1rem;
    text-align: center;
}

.stat-card .stat-number {
    font-size: 1.75rem;
    font-weight: 700;
}

.stat-card .stat-label {
    font-size: 0.8rem;
    color: #6c757d;
}


/* =========================================================
   Tabs
   ========================================================= */
.nav-tabs .nav-link {
    font-weight: 500;
}

.nav-tabs .nav-link.active {
    background-color: #f8f9fa;
    border-bottom-color: #f8f9fa;
}


/* =========================================================
   Filter Chips (Bootstrap Outline Style)
   ========================================================= */
.filter-chip {
    display: inline-flex;
    align-items: center;
    padding: 0.35rem 0.85rem;
    margin: 0.2rem;
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    background-color: transparent;
    border: 1px solid currentColor;
    transition: all 0.15s ease-in-out;
}

.filter-chip:hover,
.filter-chip.active {
    color: #fff;
}

.filter-chip.active {
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

/* Farbvarianten */
.filter-chip-300m { color: #dc3545; }
.filter-chip-300m:hover,
.filter-chip-300m.active { background-color: #dc3545; }

.filter-chip-50m { color: #28a745; }
.filter-chip-50m:hover,
.filter-chip-50m.active { background-color: #28a745; }

.filter-chip-25m { color: #007bff; }
.filter-chip-25m:hover,
.filter-chip-25m.active { background-color: #007bff; }

.filter-chip-10m { color: #6f42c1; }
.filter-chip-10m:hover,
.filter-chip-10m.active { background-color: #6f42c1; }

.filter-chip-sonstiges { color: #6c757d; }
.filter-chip-sonstiges:hover,
.filter-chip-sonstiges.active { background-color: #6c757d; }

.existing-count {
    font-size: 0.75rem;
    opacity: 0.8;
}


/* =========================================================
   Keyword Tags
   ========================================================= */
.keyword-tag {
    display: inline-flex;
    align-items: center;
    background: #e9ecef;
    padding: 0.3rem 0.6rem;
    border-radius: 0.25rem;
    margin: 0.2rem;
    font-size: 0.85rem;
}

.keyword-tag .badge {
    margin-right: 0.4rem;
    font-size: 0.7rem;
}

.keyword-tag .btn-remove {
    background: none;
    border: none;
    color: #dc3545;
    padding: 0 0.3rem;
    margin-left: 0.3rem;
    cursor: pointer;
    font-size: 1rem;
    line-height: 1;
}

.keyword-tag .btn-remove:hover {
    color: #a71d2a;
}


/* =========================================================
   Badge Farben (Art)
   ========================================================= */
.badge-300m { background-color: #dc3545; }
.badge-50m  { background-color: #28a745; }
.badge-25m  { background-color: #007bff; }
.badge-10m  { background-color: #6f42c1; }
.badge-sonstiges { background-color: #6c757d; }

.badge-SF  { background-color: #fd7e14; }
.badge-FS  { background-color: #20c997; }
.badge-OP  { background-color: #198754; }
.badge-WK  { background-color: #6610f2; }
.badge-JSK { background-color: #e83e8c; }
.badge-TR  { background-color: #17a2b8; }
.badge-VS  { background-color: #6c757d; }
.badge-AND { background-color: #343a40; }


/* =========================================================
   Art Select
   ========================================================= */
.art-select {
    width: 80px;
    font-size: 0.8rem;
    padding: 0.2rem 0.4rem;
}


/* =========================================================
   Kalender Toggle
   ========================================================= */
.kalender-toggle {
    cursor: pointer;
    font-size: 1.2rem;
    transition: opacity 0.15s;
}

.kalender-toggle.active {
    color: #28a745;
}

.kalender-toggle:not(.active) {
    color: #dee2e6;
}

.kalender-toggle:hover {
    opacity: 0.7;
}


/* =========================================================
   Import Kalender
   ========================================================= */
.import-kalender-cell {
    text-align: center;
}

</style>


<div class="container-fluid">
    <div class="row">
        <div class="col-xl-11 col-lg-12 col-md-12 col-12">
            <div class="main-content-wrapper">
                <!-- Header -->
                <div class="row mb-3">
                    <div class="col-md-12">
                        <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                            <i class="bi bi-calendar-week me-2"></i>
                            Standbelegungsplan
                        </h2>
                        <p class="text-muted mt-1">Import, Übersicht und Export der Standbelegungen</p>
                    </div>
                </div>
                
                <div class="content-background">
                    <!-- Tabs -->
                    <ul class="nav nav-tabs mb-3" id="mainTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="import-tab" data-bs-toggle="tab" data-bs-target="#import" type="button">
                                <i class="bi bi-cloud-upload me-1"></i> Import
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button">
                                <i class="bi bi-table me-1"></i> Übersicht & Export
                                <span class="badge bg-secondary ms-1"><?= count($existingEntries) ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button">
                                <i class="bi bi-gear me-1"></i> Art-Erkennung
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="mainTabsContent">
                        <!-- ==================== TAB: IMPORT ==================== -->
                        <div class="tab-pane fade show active" id="import" role="tabpanel">
                            
                            <!-- Bestehende Daten Info -->
                            <?php if (!empty($stats)): ?>
                            <div class="alert alert-info mb-3">
                                <i class="bi bi-database me-2"></i>
                                <strong>Bestehende Daten:</strong>
                                <?php 
                                $infoTexts = [];
                                foreach ($stats as $jahr => $data) {
                                    if (is_array($data) && isset($data['total'])) {
                                        $infoTexts[] = "{$jahr}: {$data['total']} Einträge ({$data['inKalender']} im Kalender)";
                                    }
                                }
                                echo implode(' | ', $infoTexts);
                                ?>
                                <br><small class="text-muted">Beim Import werden bestehende Einträge (gleiches Datum + Bezeichnung) aktualisiert.</small>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Jahr Auswahl -->
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="importYear" class="form-label fw-bold">
                                        <i class="bi bi-calendar3 me-1"></i> Jahr für Import:
                                    </label>
                                    <select id="importYear" class="form-select">
                                        <?php for ($y = $currentYear - 1; $y <= $currentYear + 1; $y++): ?>
                                        <option value="<?= $y ?>" <?= ($y == $currentYear) ? 'selected' : '' ?>><?= $y ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Upload Areas -->
                            <div class="row">
                                <!-- Excel Upload -->
                                <div class="col-md-6">
                                    <div class="upload-area" id="uploadArea">
                                        <i class="bi bi-file-earmark-excel" style="font-size: 3rem; color: #28a745;"></i>
                                        <h5 class="mt-3">Excel-Datei</h5>
                                        <p class="text-muted mb-0">Für Daten-Import (.xlsx)</p>
                                    </div>
                                    <input type="file" id="fileInput" accept=".xlsx,.xls" style="display: none;">
                                </div>
                                
                                <!-- PDF Upload -->
                                <div class="col-md-6">
                                    <div class="upload-area" id="pdfUploadArea">
                                        <i class="bi bi-file-earmark-pdf" style="font-size: 3rem; color: #dc3545;"></i>
                                        <h5 class="mt-3">PDF-Datei</h5>
                                        <p class="text-muted mb-0">Für Anzeige auf Website (.pdf)</p>
                                        <span id="pdfFileName" class="badge bg-success mt-2" style="display: none;"></span>
                                    </div>
                                    <input type="file" id="pdfFileInput" accept=".pdf" style="display: none;">
                                </div>
                            </div>
                            
                            <!-- Aktuelles PDF Info -->
                            <?php
                            $currentPdfInfo = null;
                            foreach ($years as $checkYear) {
                                $pdfPath = __DIR__ . '/standbelegung/pdf/standbelegung_' . $checkYear . '.pdf';
                                if (file_exists($pdfPath)) {
                                    $currentPdfInfo[$checkYear] = [
                                        'size' => round(filesize($pdfPath) / 1024, 1),
                                        'date' => date('d.m.Y H:i', filemtime($pdfPath))
                                    ];
                                }
                            }
                            if ($currentPdfInfo): ?>
                            <div class="alert alert-success mt-3">
                                <i class="bi bi-file-pdf me-2"></i>
                                <strong>Gespeicherte PDFs:</strong>
                                <?php foreach ($currentPdfInfo as $pdfYear => $info): ?>
                                <span class="ms-3">
                                    <a href="standbelegung/pdf/standbelegung_<?= $pdfYear ?>.pdf" target="_blank">
                                        <?= $pdfYear ?> (<?= $info['size'] ?> KB, <?= $info['date'] ?>)
                                    </a>
                                </span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Import Preview (hidden initially) -->
                            <div id="importPreview" style="display: none;">
                                <hr>
                                <h5><i class="bi bi-eye me-2"></i>Vorschau</h5>
                                
                                <!-- Stats Row -->
                                <div class="row mb-3" id="importStatsRow"></div>
                                
                                <!-- Filter für Import -->
                                <div class="card mb-3">
                                    <div class="card-body py-2">
                                        <div class="d-flex flex-wrap align-items-center">
                                            <span class="me-2 fw-bold">Filter:</span>
                                            <span class="filter-chip filter-chip-300m" data-kategorie="300m">300m <span class="existing-count"></span></span>
                                            <span class="filter-chip filter-chip-50m" data-kategorie="50m">50m <span class="existing-count"></span></span>
                                            <span class="filter-chip filter-chip-25m" data-kategorie="25m">25m <span class="existing-count"></span></span>
                                            <span class="filter-chip filter-chip-10m" data-kategorie="10m">10m <span class="existing-count"></span></span>
                                            <span class="filter-chip filter-chip-sonstiges" data-kategorie="Sonstiges">Sonstiges <span class="existing-count"></span></span>
                                            <span class="ms-auto">
                                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="selectAllVisible()">
                                                    <i class="bi bi-check-all"></i> Alle sichtbaren
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="deselectAllVisible()">
                                                    <i class="bi bi-x-lg"></i> Keine
                                                </button>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Kalender-Auswahl Buttons -->
                                <div class="mb-3">
                                    <span class="me-2 fw-bold">Kalender-Anzeige für sichtbare:</span>
                                    <button type="button" class="btn btn-outline-success btn-sm" onclick="setKalenderVisible(true)">
                                        <i class="bi bi-calendar-check"></i> Alle im Kalender
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setKalenderVisible(false)">
                                        <i class="bi bi-calendar-x"></i> Keine im Kalender
                                    </button>
                                </div>
                                
                                <!-- Import Table -->
                                <div class="preview-table-wrapper">
                                    <table class="table table-hover table-sm preview-table mb-0">
                                        <thead>
                                            <tr>
                                                <th style="width: 40px;"><input type="checkbox" class="form-check-input" id="importSelectAll"></th>
                                                <th style="width: 50px;" title="Im Kalender anzeigen"><i class="bi bi-calendar-check"></i></th>
                                                <th>Datum</th>
                                                <th>Tag</th>
                                                <th>Bezeichnung</th>
                                                <th>Von</th>
                                                <th>Bis</th>
                                                <th>Kategorie</th>
                                            </tr>
                                        </thead>
                                        <tbody id="importTableBody"></tbody>
                                    </table>
                                </div>
                                
                                <div class="d-flex justify-content-between mt-3">
                                    <button type="button" class="btn btn-outline-secondary" onclick="resetImport()">
                                        <i class="bi bi-arrow-left me-1"></i> Zurück
                                    </button>
                                    <div>
                                        <button type="button" class="btn btn-success me-2" onclick="saveImport()">
                                            <i class="bi bi-database-add me-1"></i> <span id="saveImportCount">0</span> Einträge importieren
                                        </button>
                                        <button type="button" class="btn btn-primary" onclick="showExportPreviewFromImport()">
                                            <i class="bi bi-file-earmark-excel me-1"></i> Direkt exportieren
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                        </div>
                        
                        <!-- ==================== TAB: ÜBERSICHT & EXPORT ==================== -->
                        <div class="tab-pane fade" id="overview" role="tabpanel">
                            
                            <!-- PDF Link -->
                            <?php
                            $overviewYear = $currentYear;
                            $pdfPath = __DIR__ . '/standbelegung/pdf/standbelegung_' . $overviewYear . '.pdf';
                            $pdfExists = file_exists($pdfPath);
                            ?>
                            <?php if ($pdfExists): ?>
                            <div class="alert alert-light border mb-3 d-flex align-items-center">
                                <i class="bi bi-file-pdf text-danger fs-4 me-3"></i>
                                <div>
                                    <strong>Standbelegung <?= $overviewYear ?></strong>
                                    <span class="text-muted ms-2">(<?= round(filesize($pdfPath) / 1024, 1) ?> KB)</span>
                                </div>
                                <div class="ms-auto">
                                    <a href="standbelegung/pdf/standbelegung_<?= $overviewYear ?>.pdf" target="_blank" class="btn btn-outline-primary btn-sm me-2">
                                        <i class="bi bi-eye me-1"></i>Anzeigen
                                    </a>
                                    <a href="standbelegung/pdf/standbelegung_<?= $overviewYear ?>.pdf" download class="btn btn-outline-success btn-sm">
                                        <i class="bi bi-download me-1"></i>Download
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Jahr Filter -->
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label for="overviewYear" class="form-label fw-bold">Jahr:</label>
                                    <select id="overviewYear" class="form-select">
                                        <option value="">Alle Jahre</option>
                                        <?php foreach ($years as $y): ?>
                                        <option value="<?= $y ?>" <?= ($y == $currentYear) ? 'selected' : '' ?>><?= $y ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Kategorien:</label>
                                    <div>
                                        <span class="filter-chip filter-chip-300m active" data-kategorie="300m" data-target="overview">300m</span>
                                        <span class="filter-chip filter-chip-50m" data-kategorie="50m" data-target="overview">50m</span>
                                        <span class="filter-chip filter-chip-25m" data-kategorie="25m" data-target="overview">25m</span>
                                        <span class="filter-chip filter-chip-10m" data-kategorie="10m" data-target="overview">10m</span>
                                        <span class="filter-chip filter-chip-sonstiges" data-kategorie="Sonstiges" data-target="overview">Sonstiges</span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">Suche:</label>
                                    <input type="text" class="form-control" id="overviewSearch" placeholder="Bezeichnung...">
                                </div>
                            </div>
                            
                            <!-- Stats -->
                            <div class="row mb-3" id="overviewStatsRow">
                                <!-- Wird dynamisch gefüllt -->
                            </div>
                            
                            <!-- Overview Table -->
                            <div class="card mb-3">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-table me-2"></i>Einträge (<span id="overviewCount">0</span> ausgewählt)</span>
                                    <div>
                                        <button type="button" class="btn btn-outline-success btn-sm" onclick="selectAllOverview()">
                                            <i class="bi bi-check-all"></i> Alle
                                        </button>
                                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="deselectAllOverview()">
                                            <i class="bi bi-x-lg"></i> Keine
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div class="preview-table-wrapper">
                                        <table class="table table-hover table-sm preview-table mb-0">
                                            <thead>
                                                <tr>
                                                    <th style="width: 40px;"><input type="checkbox" class="form-check-input" id="overviewSelectAll"></th>
                                                    <th style="width: 50px;" title="Im Kalender anzeigen"><i class="bi bi-calendar-check"></i></th>
                                                    <th>Datum</th>
                                                    <th>Tag</th>
                                                    <th>Bezeichnung</th>
                                                    <th>Von</th>
                                                    <th>Bis</th>
                                                    <th>Kategorie</th>
                                                    <th style="width: 90px;">Aktionen</th>
                                                </tr>
                                            </thead>
                                            <tbody id="overviewTableBody">
                                                <!-- Wird dynamisch gefüllt -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Export Buttons -->
                            <div class="d-flex justify-content-between">
                                <div>
                                    <button type="button" class="btn btn-outline-danger" onclick="deleteSelected()">
                                        <i class="bi bi-trash me-1"></i> Ausgewählte löschen
                                    </button>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-outline-primary me-2" onclick="openAddModal()">
                                        <i class="bi bi-plus-lg me-1"></i> Neuer Eintrag
                                    </button>
                                    <button type="button" class="btn btn-outline-success" onclick="showExportPreview()">
                                        <i class="bi bi-file-earmark-excel me-1"></i> Schiesstagemeldung exportieren
                                    </button>
                                </div>
                            </div>
                            
                        </div>
                        
                        <!-- ==================== TAB: ART-ERKENNUNG ==================== -->
                        <div class="tab-pane fade" id="settings" role="tabpanel">
                            
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="card">
                                        <div class="card-header">
                                            <i class="bi bi-tags me-2"></i>Art-Keywords verwalten
                                        </div>
                                        <div class="card-body">
                                            <p class="text-muted">
                                                Hier kannst du Begriffe definieren, die beim Export automatisch einer bestimmten Art zugeordnet werden.
                                                Enthält eine Bezeichnung einen dieser Begriffe, wird die entsprechende Art vorgeschlagen.
                                            </p>
                                            
                                            <!-- Keyword hinzufügen -->
                                            <div class="row mb-4">
                                                <div class="col-md-5">
                                                    <input type="text" class="form-control" id="newKeyword" placeholder="Neues Keyword (z.B. Schlossturmschiessen)">
                                                </div>
                                                <div class="col-md-3">
                                                    <select class="form-select" id="newKeywordArt">
                                                        <?php foreach ($artCodes as $code => $label): ?>
                                                        <option value="<?= $code ?>"><?= $code ?> - <?= $label ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <button type="button" class="btn btn-primary w-100" onclick="addKeyword()">
                                                        <i class="bi bi-plus-lg"></i> Hinzufügen
                                                    </button>
                                                </div>
                                            </div>
                                            
                                            <!-- Keywords Liste -->
                                            <div id="keywordsList">
                                                <?php 
                                                $groupedKeywords = [];
                                                foreach ($artKeywords as $kw) {
                                                    $groupedKeywords[$kw['Art']][] = $kw;
                                                }
                                                foreach ($artCodes as $code => $label): 
                                                    $keywords = $groupedKeywords[$code] ?? [];
                                                ?>
                                                <div class="mb-3">
                                                    <h6>
                                                        <span class="badge badge-<?= $code ?>"><?= $code ?></span>
                                                        <?= $label ?>
                                                        <small class="text-muted">(<?= count($keywords) ?>)</small>
                                                    </h6>
                                                    <div class="keywords-container" data-art="<?= $code ?>">
                                                        <?php if (empty($keywords)): ?>
                                                        <span class="text-muted fst-italic">Keine Keywords definiert</span>
                                                        <?php else: ?>
                                                        <?php foreach ($keywords as $kw): ?>
                                                        <span class="keyword-tag" data-id="<?= $kw['ID'] ?>">
                                                            <?= htmlspecialchars($kw['Keyword']) ?>
                                                            <button type="button" class="btn-remove" onclick="deleteKeyword(<?= $kw['ID'] ?>)">&times;</button>
                                                        </span>
                                                        <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-header">
                                            <i class="bi bi-info-circle me-2"></i>Art-Codes Übersicht
                                        </div>
                                        <div class="card-body">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Code</th>
                                                        <th>Bedeutung</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($artCodes as $code => $label): ?>
                                                    <tr>
                                                        <td><span class="badge badge-<?= $code ?>"><?= $code ?></span></td>
                                                        <td><?= $label ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                            
                                            <hr>
                                            <h6>Standard-Erkennung</h6>
                                            <small class="text-muted">
                                                Ohne Keyword wird automatisch erkannt:
                                                <ul class="mb-0 mt-1">
                                                    <li><strong>FS:</strong> Feldschiessen</li>
                                                    <li><strong>OP:</strong> Bundesprogramm, Obligatorisch</li>
                                                    <li><strong>JSK:</strong> Jungschützen</li>
                                                    <li><strong>TR:</strong> Training</li>
                                                    <li><strong>VS:</strong> Versammlung, GV, Absenden</li>
                                                </ul>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay" style="display: none;">
    <div class="loading-spinner">
        <div class="spinner-border" role="status"></div>
        <h4 class="mt-3" id="loadingText">Wird verarbeitet...</h4>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container"></div>

<!-- Edit/Add Entry Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalTitle"><i class="bi bi-pencil me-2"></i>Eintrag bearbeiten</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editId">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="editDatum" class="form-label">Datum *</label>
                        <input type="date" class="form-control" id="editDatum" required>
                    </div>
                    <div class="col-md-6">
                        <label for="editKategorie" class="form-label">Kategorie *</label>
                        <select class="form-select" id="editKategorie" required>
                            <option value="300m">300m</option>
                            <option value="50m">50m</option>
                            <option value="25m">25m</option>
                            <option value="10m">10m</option>
                            <option value="Sonstiges">Sonstiges</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="editBezeichnung" class="form-label">Bezeichnung *</label>
                    <input type="text" class="form-control" id="editBezeichnung" required>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="editStartZeit" class="form-label">Von</label>
                        <input type="time" class="form-control" id="editStartZeit">
                    </div>
                    <div class="col-md-6">
                        <label for="editEndZeit" class="form-label">Bis</label>
                        <input type="time" class="form-control" id="editEndZeit">
                    </div>
                </div>
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="editInKalender">
                        <label class="form-check-label" for="editInKalender">
                            <i class="bi bi-calendar-check me-1"></i> Im Kalender anzeigen
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-primary" onclick="saveEntry()">
                    <i class="bi bi-check-lg me-1"></i> Speichern
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Löschen bestätigen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Möchtest du <strong id="deleteCount">0</strong> Einträge wirklich löschen?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Löschen</button>
            </div>
        </div>
    </div>
</div>

<!-- Export Preview Modal -->
<div class="modal fade" id="exportModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-file-earmark-excel text-success me-2"></i>Export Vorschau - Schiesstagemeldung</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">
                    Überprüfe und passe die <strong>Art</strong> für jeden Eintrag an, bevor du exportierst.
                </p>
                
                <div class="preview-table-wrapper" style="max-height: 400px;">
                    <table class="table table-hover table-sm preview-table mb-0">
                        <thead>
                            <tr>
                                <th>Disziplin</th>
                                <th>Datum</th>
                                <th>Von</th>
                                <th>Bis</th>
                                <th style="width: 100px;">Art</th>
                                <th>Anlass</th>
                            </tr>
                        </thead>
                        <tbody id="exportPreviewBody">
                            <!-- Dynamisch gefüllt -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-success" onclick="executeExport()">
                    <i class="bi bi-download me-1"></i> Excel herunterladen
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';
const ART_CODES = <?php echo json_encode($artCodes); ?>;
let importData = [];
let overviewData = <?php echo json_encode($existingEntries); ?>;
let artKeywords = <?php echo json_encode($artKeywords); ?>;
let exportData = [];
let exportSource = 'overview'; // 'overview' oder 'import'

// ==================== INITIALIZATION ====================
$(document).ready(function() {
    initUploadHandlers();
    initFilterHandlers();
    initOverviewTable();
    
    // Tab change handler
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
        if (e.target.id === 'overview-tab') {
            applyOverviewFilters();
        }
    });
});

// ==================== UPLOAD HANDLERS ====================
function initUploadHandlers() {
    // Excel Upload
    $('#uploadArea').on('click', function(e) {
        e.preventDefault();
        $('#fileInput').trigger('click');
    });
    
    $('#fileInput').on('change', function(e) {
        if (e.target.files[0]) handleFileUpload(e.target.files[0]);
    });
    
    $('#uploadArea')
        .on('dragover', function(e) { e.preventDefault(); $(this).addClass('dragover'); })
        .on('dragleave', function(e) { e.preventDefault(); $(this).removeClass('dragover'); })
        .on('drop', function(e) {
            e.preventDefault();
            $(this).removeClass('dragover');
            if (e.originalEvent.dataTransfer.files.length) {
                handleFileUpload(e.originalEvent.dataTransfer.files[0]);
            }
        });
    
    // PDF Upload
    $('#pdfUploadArea').on('click', function(e) {
        e.preventDefault();
        $('#pdfFileInput').trigger('click');
    });
    
    $('#pdfFileInput').on('change', function(e) {
        if (e.target.files[0]) handlePdfUpload(e.target.files[0]);
    });
    
    $('#pdfUploadArea')
        .on('dragover', function(e) { e.preventDefault(); $(this).addClass('dragover'); })
        .on('dragleave', function(e) { e.preventDefault(); $(this).removeClass('dragover'); })
        .on('drop', function(e) {
            e.preventDefault();
            $(this).removeClass('dragover');
            if (e.originalEvent.dataTransfer.files.length) {
                handlePdfUpload(e.originalEvent.dataTransfer.files[0]);
            }
        });
    
    $('#importSelectAll').on('change', function() {
        const checked = $(this).prop('checked');
        $('#importTableBody tr:visible .row-checkbox').prop('checked', checked);
        updateImportCount();
    });
}

function handleFileUpload(file) {
    if (!file.name.match(/\.xlsx?$/i)) {
        showToast('Bitte nur Excel-Dateien hochladen', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('file', file);
    formData.append('year', $('#importYear').val());
    formData.append('csrf_token', CSRF_TOKEN);
    
    showLoading('Excel wird analysiert...');
    
    $.ajax({
        url: 'standbelegung/parse_excel.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            hideLoading();
            if (response.success) {
                importData = response.data;
                showImportPreview(response.stats);
                showToast(`${importData.length} Termine gefunden`, 'success');
            } else {
                showToast('Fehler: ' + (response.error || 'Unbekannt'), 'error');
            }
        },
        error: function(xhr, status, error) {
            hideLoading();
            showToast('Fehler: ' + error, 'error');
        }
    });
}

function handlePdfUpload(file) {
    if (!file.name.match(/\.pdf$/i)) {
        showToast('Bitte nur PDF-Dateien hochladen', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('file', file);
    formData.append('year', $('#importYear').val());
    formData.append('csrf_token', CSRF_TOKEN);
    
    showLoading('PDF wird hochgeladen...');
    
    $.ajax({
        url: 'standbelegung/upload_pdf.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            hideLoading();
            if (response.success) {
                showToast('PDF für ' + response.year + ' gespeichert', 'success');
                $('#pdfFileName').text(response.filename).show();
                // Icon ändern auf Erfolg
                $('#pdfUploadArea i').removeClass('bi-file-earmark-pdf').addClass('bi-check-circle-fill').css('color', '#28a745');
            } else {
                showToast('Fehler: ' + (response.error || 'Unbekannt'), 'error');
            }
        },
        error: function(xhr, status, error) {
            hideLoading();
            showToast('Fehler: ' + error, 'error');
        }
    });
}

function showImportPreview(stats) {
    // Stats anzeigen
    let statsHtml = '';
    const categories = [
        {key: '300m', color: 'danger'},
        {key: '50m', color: 'success'},
        {key: '25m', color: 'primary'},
        {key: '10m', color: 'purple'},
        {key: 'Sonstiges', color: 'secondary'}
    ];
    
    categories.forEach(cat => {
        const count = stats[cat.key] || 0;
        if (count > 0) {
            statsHtml += `
                <div class="col-auto">
                    <div class="stat-card">
                        <div class="stat-number text-${cat.color}">${count}</div>
                        <div class="stat-label">${cat.key}</div>
                    </div>
                </div>`;
        }
        // Filter count aktualisieren
        $(`.filter-chip[data-kategorie="${cat.key}"] .existing-count`).text(`(${count})`);
    });
    $('#importStatsRow').html(statsHtml);
    
    // Tabelle füllen
    renderImportTable();
    
    // Alle Filter aktivieren
    $('#importPreview .filter-chip').addClass('active');
    
    $('#uploadArea').hide();
    $('#importPreview').show();
}

function renderImportTable() {
    let html = '';
    importData.forEach((item, index) => {
        const badgeClass = getBadgeClass(item.kategorie);
        // Standard: 300m immer im Kalender, andere nicht
        const inKalender = item.kategorie === '300m';
        html += `
            <tr data-index="${index}" data-kategorie="${item.kategorie}">
                <td><input type="checkbox" class="form-check-input row-checkbox" data-index="${index}" checked></td>
                <td class="import-kalender-cell">
                    <i class="bi bi-calendar-check kalender-toggle ${inKalender ? 'active' : ''}" 
                       data-index="${index}" 
                       onclick="toggleImportKalender(this)"></i>
                </td>
                <td>${item.datum || '-'}</td>
                <td>${item.wochentag || '-'}</td>
                <td>${item.bezeichnung || '-'}</td>
                <td>${item.start_zeit || '-'}</td>
                <td>${item.end_zeit || '-'}</td>
                <td><span class="badge ${badgeClass}">${item.kategorie}</span></td>
            </tr>`;
    });
    $('#importTableBody').html(html);
    
    $('#importTableBody .row-checkbox').on('change', updateImportCount);
    updateImportCount();
}

function toggleImportKalender(el) {
    $(el).toggleClass('active');
}

function setKalenderVisible(value) {
    $('#importTableBody tr:visible .kalender-toggle').each(function() {
        if (value) {
            $(this).addClass('active');
        } else {
            $(this).removeClass('active');
        }
    });
}

function updateImportCount() {
    const count = $('#importTableBody .row-checkbox:checked').length;
    $('#saveImportCount').text(count);
}

function resetImport() {
    importData = [];
    $('#fileInput').val('');
    $('#importPreview').hide();
    $('#uploadArea').show();
}

function saveImport() {
    const selectedData = [];
    $('#importTableBody .row-checkbox:checked').each(function() {
        const index = $(this).data('index');
        const inKalender = $(this).closest('tr').find('.kalender-toggle').hasClass('active') ? 1 : 0;
        selectedData.push({
            ...importData[index],
            in_kalender: inKalender
        });
    });
    
    if (selectedData.length === 0) {
        showToast('Bitte mindestens einen Eintrag auswählen', 'warning');
        return;
    }
    
    showLoading('Speichere...');
    
    $.ajax({
        url: 'standbelegung/save_standbelegung.php',
        method: 'POST',
        data: JSON.stringify({
            csrf_token: CSRF_TOKEN,
            year: $('#importYear').val(),
            termine: selectedData
        }),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            hideLoading();
            if (response.success) {
                showToast(`${response.inserted} neu, ${response.updated} aktualisiert`, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('Fehler: ' + (response.error || 'Unbekannt'), 'error');
            }
        },
        error: function() {
            hideLoading();
            showToast('Fehler beim Speichern', 'error');
        }
    });
}

// ==================== FILTER HANDLERS ====================
function initFilterHandlers() {
    // Import Filter (Klick togglet)
    $('#importPreview .filter-chip').on('click', function() {
        $(this).toggleClass('active');
        applyImportFilters();
    });
    
    // Overview Filter (Klick togglet)
    $('.filter-chip[data-target="overview"]').on('click', function() {
        $(this).toggleClass('active');
        applyOverviewFilters();
    });
    
    // Overview Jahr/Suche
    $('#overviewYear, #overviewSearch').on('change input', function() {
        applyOverviewFilters();
    });
    
    // Overview Select All
    $('#overviewSelectAll').on('change', function() {
        const checked = $(this).prop('checked');
        $('#overviewTableBody tr:visible .row-checkbox').prop('checked', checked);
        updateOverviewCount();
    });
}

function applyImportFilters() {
    const activeCategories = [];
    $('#importPreview .filter-chip.active').each(function() {
        activeCategories.push($(this).data('kategorie'));
    });
    
    $('#importTableBody tr').each(function() {
        const kategorie = $(this).data('kategorie');
        const show = activeCategories.length === 0 || activeCategories.includes(kategorie);
        $(this).toggle(show);
        if (!show) {
            $(this).find('.row-checkbox').prop('checked', false);
        }
    });
    
    updateImportCount();
}

function selectAllVisible() {
    $('#importTableBody tr:visible .row-checkbox').prop('checked', true);
    updateImportCount();
}

function deselectAllVisible() {
    $('#importTableBody tr:visible .row-checkbox').prop('checked', false);
    updateImportCount();
}

// ==================== OVERVIEW TABLE ====================
function initOverviewTable() {
    renderOverviewTable();
}

function renderOverviewTable() {
    let html = '';
    overviewData.forEach((item, index) => {
        const badgeClass = getBadgeClass(item.Kategorie);
        const datum = formatDate(item.Datum);
        const inKalender = parseInt(item.InKalender) === 1;
        html += `
            <tr data-id="${item.ID}" data-kategorie="${item.Kategorie}" data-jahr="${item.Jahr}" data-bezeichnung="${(item.Bezeichnung || '').toLowerCase()}">
                <td><input type="checkbox" class="form-check-input row-checkbox" data-id="${item.ID}"></td>
                <td class="text-center">
                    <i class="bi bi-calendar-check kalender-toggle ${inKalender ? 'active' : ''}" 
                       data-id="${item.ID}" 
                       onclick="toggleOverviewKalender(this, ${item.ID})"></i>
                </td>
                <td>${datum}</td>
                <td>${item.Wochentag || '-'}</td>
                <td>${item.Bezeichnung || '-'}</td>
                <td>${formatTime(item.StartZeit)}</td>
                <td>${formatTime(item.EndZeit)}</td>
                <td><span class="badge ${badgeClass}">${item.Kategorie}</span></td>
                <td class="text-nowrap">
                    <button class="btn btn-outline-primary btn-sm me-1" onclick="openEditModal(${item.ID})" title="Bearbeiten">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-outline-danger btn-sm" onclick="deleteSingle(${item.ID})" title="Löschen">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>`;
    });
    $('#overviewTableBody').html(html);
    
    $('#overviewTableBody .row-checkbox').on('change', updateOverviewCount);
    applyOverviewFilters();
}

function toggleOverviewKalender(el, id) {
    const newValue = !$(el).hasClass('active');
    
    $.ajax({
        url: 'standbelegung/update_kalender.php',
        method: 'POST',
        data: JSON.stringify({
            csrf_token: CSRF_TOKEN,
            id: id,
            in_kalender: newValue ? 1 : 0
        }),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $(el).toggleClass('active');
                // Lokale Daten aktualisieren
                const entry = overviewData.find(e => e.ID === id);
                if (entry) entry.InKalender = newValue ? 1 : 0;
            } else {
                showToast('Fehler beim Speichern', 'error');
            }
        },
        error: function() {
            showToast('Fehler beim Speichern', 'error');
        }
    });
}

function applyOverviewFilters() {
    const year = $('#overviewYear').val();
    const search = $('#overviewSearch').val().toLowerCase();
    const activeCategories = [];
    
    $('.filter-chip[data-target="overview"].active').each(function() {
        activeCategories.push($(this).data('kategorie'));
    });
    
    let visibleCount = 0;
    let stats = {'300m': 0, '50m': 0, '25m': 0, '10m': 0, 'Sonstiges': 0};
    
    $('#overviewTableBody tr').each(function() {
        const $row = $(this);
        const rowYear = $row.data('jahr').toString();
        const rowKat = $row.data('kategorie');
        const rowBez = $row.data('bezeichnung');
        
        const matchYear = !year || rowYear === year;
        const matchKat = activeCategories.length === 0 || activeCategories.includes(rowKat);
        const matchSearch = !search || rowBez.includes(search);
        
        const show = matchYear && matchKat && matchSearch;
        $row.toggle(show);
        
        if (show) {
            visibleCount++;
            if (stats[rowKat] !== undefined) stats[rowKat]++;
        }
        
        if (!show) {
            $row.find('.row-checkbox').prop('checked', false);
        }
    });
    
    // Stats aktualisieren
    let statsHtml = '';
    const categories = [
        {key: '300m', color: 'danger'},
        {key: '50m', color: 'success'},
        {key: '25m', color: 'primary'},
        {key: '10m', color: 'purple'},
        {key: 'Sonstiges', color: 'secondary'}
    ];
    categories.forEach(cat => {
        if (stats[cat.key] > 0) {
            statsHtml += `
                <div class="col-auto">
                    <div class="stat-card">
                        <div class="stat-number text-${cat.color}">${stats[cat.key]}</div>
                        <div class="stat-label">${cat.key}</div>
                    </div>
                </div>`;
        }
    });
    $('#overviewStatsRow').html(statsHtml);
    
    updateOverviewCount();
}

function updateOverviewCount() {
    const count = $('#overviewTableBody .row-checkbox:checked').length;
    $('#overviewCount').text(count);
}

function selectAllOverview() {
    $('#overviewTableBody tr:visible .row-checkbox').prop('checked', true);
    updateOverviewCount();
}

function deselectAllOverview() {
    $('#overviewTableBody tr:visible .row-checkbox').prop('checked', false);
    updateOverviewCount();
}

// ==================== DELETE ====================
function deleteSingle(id) {
    if (confirm('Diesen Eintrag wirklich löschen?')) {
        deleteEntries([id]);
    }
}

function deleteSelected() {
    const ids = [];
    $('#overviewTableBody .row-checkbox:checked').each(function() {
        ids.push($(this).data('id'));
    });
    
    if (ids.length === 0) {
        showToast('Bitte Einträge auswählen', 'warning');
        return;
    }
    
    $('#deleteCount').text(ids.length);
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
    
    $('#confirmDeleteBtn').off('click').on('click', function() {
        modal.hide();
        deleteEntries(ids);
    });
}

function deleteEntries(ids) {
    showLoading('Lösche...');
    
    $.ajax({
        url: 'standbelegung/delete_entries.php',
        method: 'POST',
        data: JSON.stringify({
            csrf_token: CSRF_TOKEN,
            ids: ids
        }),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            hideLoading();
            if (response.success) {
                showToast(`${response.deleted} Einträge gelöscht`, 'success');
                // Aus lokalen Daten entfernen
                overviewData = overviewData.filter(e => !ids.includes(e.ID));
                renderOverviewTable();
            } else {
                showToast('Fehler: ' + (response.error || 'Unbekannt'), 'error');
            }
        },
        error: function() {
            hideLoading();
            showToast('Fehler beim Löschen', 'error');
        }
    });
}

// ==================== EXPORT ====================
function showExportPreview() {
    exportSource = 'overview';
    const ids = [];
    $('#overviewTableBody .row-checkbox:checked').each(function() {
        ids.push($(this).data('id'));
    });
    
    if (ids.length === 0) {
        showToast('Bitte Einträge für Export auswählen', 'warning');
        return;
    }
    
    // Daten für Export sammeln
    exportData = overviewData.filter(e => ids.includes(e.ID));
    renderExportPreview();
}

function showExportPreviewFromImport() {
    exportSource = 'import';
    
    // Ausgewählte Import-Daten sammeln
    exportData = [];
    $('#importTableBody .row-checkbox:checked').each(function() {
        const index = $(this).data('index');
        exportData.push({
            Kategorie: importData[index].kategorie,
            Datum: importData[index].datum,
            StartZeit: importData[index].start_zeit,
            EndZeit: importData[index].end_zeit,
            Bezeichnung: importData[index].bezeichnung
        });
    });
    
    if (exportData.length === 0) {
        showToast('Bitte Einträge für Export auswählen', 'warning');
        return;
    }
    
    renderExportPreview();
}

function renderExportPreview() {
    let html = '';
    exportData.forEach((item, index) => {
        const disziplin = mapDisziplin(item.Kategorie);
        const datum = item.Datum.includes('.') ? item.Datum : formatDate(item.Datum);
        const startZeit = item.StartZeit ? (item.StartZeit.includes(':') ? item.StartZeit.substring(0,5) : item.StartZeit) : '-';
        const endZeit = item.EndZeit ? (item.EndZeit.includes(':') ? item.EndZeit.substring(0,5) : item.EndZeit) : '-';
        const art = detectArt(item.Bezeichnung);
        
        html += `
            <tr data-index="${index}">
                <td>${disziplin}</td>
                <td>${datum}</td>
                <td>${startZeit}</td>
                <td>${endZeit}</td>
                <td>
                    <select class="form-select art-select" data-index="${index}">
                        ${Object.keys(ART_CODES).map(code => 
                            `<option value="${code}" ${code === art ? 'selected' : ''}>${code}</option>`
                        ).join('')}
                    </select>
                </td>
                <td>${item.Bezeichnung || '-'}</td>
            </tr>`;
    });
    $('#exportPreviewBody').html(html);
    
    // Modal zeigen
    const modal = new bootstrap.Modal(document.getElementById('exportModal'));
    modal.show();
}

function detectArt(bezeichnung) {
    if (!bezeichnung) return 'AND';
    const bez = bezeichnung.toLowerCase();
    
    // Zuerst Keywords aus DB prüfen
    for (const kw of artKeywords) {
        if (bez.includes(kw.Keyword.toLowerCase())) {
            return kw.Art;
        }
    }
    
    // Standard-Erkennung
    if (bez.includes('feldschiessen')) {
        return 'FS';
    }
    if (bez.includes('bundesprogramm') || bez.includes('obligator')) {
        return 'OP';
    }
    if (bez.includes('jungschütz') || bez.includes('js-kurs') || bez.includes('jskurs')) {
        return 'JSK';
    }
    if (bez.includes('training')) {
        return 'TR';
    }
    if (bez.includes('versammlung') || bez.includes('absenden') || bez.match(/\bgv\b/)) {
        return 'VS';
    }
    
    // Default
    return 'AND';
}

function mapDisziplin(kategorie) {
    const mapping = {
        '300m': 'G300',
        '50m': 'KK50',
        '25m': 'P25',
        '10m': 'LG10'
    };
    return mapping[kategorie] || kategorie;
}

function executeExport() {
    // Art-Werte aus den Selects sammeln
    const exportEntries = [];
    $('#exportPreviewBody tr').each(function() {
        const index = $(this).find('.art-select').data('index');
        const art = $(this).find('.art-select').val();
        exportEntries.push({
            ...exportData[index],
            art: art
        });
    });
    
    showLoading('Erstelle Export...');
    
    $.ajax({
        url: 'standbelegung/export_schiesstagemeldung.php',
        method: 'POST',
        data: JSON.stringify({
            csrf_token: CSRF_TOKEN,
            entries: exportEntries,
            source: exportSource
        }),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            hideLoading();
            if (response.success && response.file) {
                showToast('Export erstellt', 'success');
                bootstrap.Modal.getInstance(document.getElementById('exportModal')).hide();
                window.location.href = response.file;
            } else {
                showToast('Fehler: ' + (response.error || 'Unbekannt'), 'error');
            }
        },
        error: function() {
            hideLoading();
            showToast('Fehler beim Export', 'error');
        }
    });
}

// ==================== KEYWORDS MANAGEMENT ====================
function addKeyword() {
    const keyword = $('#newKeyword').val().trim();
    const art = $('#newKeywordArt').val();
    
    if (!keyword) {
        showToast('Bitte ein Keyword eingeben', 'warning');
        return;
    }
    
    $.ajax({
        url: 'standbelegung/manage_keywords.php',
        method: 'POST',
        data: JSON.stringify({
            csrf_token: CSRF_TOKEN,
            action: 'add',
            keyword: keyword,
            art: art
        }),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showToast('Keyword hinzugefügt', 'success');
                // Lokale Liste aktualisieren
                artKeywords.push({ID: response.id, Keyword: keyword, Art: art});
                // UI aktualisieren
                const container = $(`.keywords-container[data-art="${art}"]`);
                if (container.find('.text-muted').length) {
                    container.empty();
                }
                container.append(`
                    <span class="keyword-tag" data-id="${response.id}">
                        ${keyword}
                        <button type="button" class="btn-remove" onclick="deleteKeyword(${response.id})">&times;</button>
                    </span>
                `);
                $('#newKeyword').val('');
            } else {
                showToast('Fehler: ' + (response.error || 'Unbekannt'), 'error');
            }
        },
        error: function() {
            showToast('Fehler beim Hinzufügen', 'error');
        }
    });
}

function deleteKeyword(id) {
    if (!confirm('Keyword wirklich löschen?')) return;
    
    $.ajax({
        url: 'standbelegung/manage_keywords.php',
        method: 'POST',
        data: JSON.stringify({
            csrf_token: CSRF_TOKEN,
            action: 'delete',
            id: id
        }),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showToast('Keyword gelöscht', 'success');
                // Aus lokaler Liste entfernen
                artKeywords = artKeywords.filter(kw => kw.ID !== id);
                // UI aktualisieren
                $(`.keyword-tag[data-id="${id}"]`).remove();
            } else {
                showToast('Fehler: ' + (response.error || 'Unbekannt'), 'error');
            }
        },
        error: function() {
            showToast('Fehler beim Löschen', 'error');
        }
    });
}

// ==================== EDIT/ADD ENTRY ====================
function openAddModal() {
    $('#editModalTitle').html('<i class="bi bi-plus-lg me-2"></i>Neuer Eintrag');
    $('#editId').val('');
    $('#editDatum').val('');
    $('#editBezeichnung').val('');
    $('#editStartZeit').val('');
    $('#editEndZeit').val('');
    $('#editKategorie').val('300m');
    $('#editInKalender').prop('checked', true);
    
    const modal = new bootstrap.Modal(document.getElementById('editModal'));
    modal.show();
}

function openEditModal(id) {
    const entry = overviewData.find(e => e.ID === id);
    if (!entry) {
        showToast('Eintrag nicht gefunden', 'error');
        return;
    }
    
    $('#editModalTitle').html('<i class="bi bi-pencil me-2"></i>Eintrag bearbeiten');
    $('#editId').val(entry.ID);
    $('#editDatum').val(entry.Datum);
    $('#editBezeichnung').val(entry.Bezeichnung);
    $('#editStartZeit').val(entry.StartZeit ? entry.StartZeit.substring(0, 5) : '');
    $('#editEndZeit').val(entry.EndZeit ? entry.EndZeit.substring(0, 5) : '');
    $('#editKategorie').val(entry.Kategorie);
    $('#editInKalender').prop('checked', parseInt(entry.InKalender) === 1);
    
    const modal = new bootstrap.Modal(document.getElementById('editModal'));
    modal.show();
}

function saveEntry() {
    const id = $('#editId').val();
    const datum = $('#editDatum').val();
    const bezeichnung = $('#editBezeichnung').val().trim();
    const startZeit = $('#editStartZeit').val();
    const endZeit = $('#editEndZeit').val();
    const kategorie = $('#editKategorie').val();
    const inKalender = $('#editInKalender').prop('checked') ? 1 : 0;
    
    // Validierung
    if (!datum) {
        showToast('Bitte Datum eingeben', 'warning');
        return;
    }
    if (!bezeichnung) {
        showToast('Bitte Bezeichnung eingeben', 'warning');
        return;
    }
    
    showLoading('Speichere...');
    
    $.ajax({
        url: 'standbelegung/save_entry.php',
        method: 'POST',
        data: JSON.stringify({
            csrf_token: CSRF_TOKEN,
            id: id || null,
            datum: datum,
            bezeichnung: bezeichnung,
            start_zeit: startZeit || null,
            end_zeit: endZeit || null,
            kategorie: kategorie,
            in_kalender: inKalender
        }),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            hideLoading();
            if (response.success) {
                showToast(id ? 'Eintrag aktualisiert' : 'Eintrag hinzugefügt', 'success');
                bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
                
                // Lokale Daten aktualisieren
                if (id) {
                    // Update
                    const idx = overviewData.findIndex(e => e.ID === parseInt(id));
                    if (idx !== -1) {
                        overviewData[idx] = {
                            ...overviewData[idx],
                            Datum: datum,
                            Bezeichnung: bezeichnung,
                            StartZeit: startZeit,
                            EndZeit: endZeit,
                            Kategorie: kategorie,
                            InKalender: inKalender,
                            Wochentag: response.wochentag || overviewData[idx].Wochentag
                        };
                    }
                } else {
                    // Neuer Eintrag - zur Liste hinzufügen
                    overviewData.push({
                        ID: response.id,
                        Datum: datum,
                        Bezeichnung: bezeichnung,
                        StartZeit: startZeit,
                        EndZeit: endZeit,
                        Kategorie: kategorie,
                        InKalender: inKalender,
                        Jahr: new Date(datum).getFullYear(),
                        Wochentag: response.wochentag || ''
                    });
                }
                
                renderOverviewTable();
            } else {
                showToast('Fehler: ' + (response.error || 'Unbekannt'), 'error');
            }
        },
        error: function() {
            hideLoading();
            showToast('Fehler beim Speichern', 'error');
        }
    });
}

// ==================== HELPERS ====================
function getBadgeClass(kategorie) {
    const classes = {
        '300m': 'badge-300m',
        '50m': 'badge-50m',
        '25m': 'badge-25m',
        '10m': 'badge-10m'
    };
    return classes[kategorie] || 'badge-sonstiges';
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('de-CH');
}

function formatTime(timeStr) {
    if (!timeStr) return '-';
    return timeStr.substring(0, 5);
}

function showLoading(text) {
    $('#loadingText').text(text || 'Wird verarbeitet...');
    $('#loadingOverlay').show();
}

function hideLoading() {
    $('#loadingOverlay').hide();
}

function showToast(message, type = 'info') {
    const toast = $(`<div class="toast-message toast-${type}">${message}</div>`);
    $('#toast-container').append(toast);
    setTimeout(() => toast.addClass('show'), 100);
    setTimeout(() => {
        toast.removeClass('show');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}
</script>

<?php include 'footer.inc.php'; ?>
