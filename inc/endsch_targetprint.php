<?php
// endsch_targetprint.php - Zielscheiben-Ausdruck (Trefferbilder) aus einer Imetron-CSV
require_once 'config.php';
require_once 'csrf.inc.php';

$page_specific_css = '
    /* Upload-Fläche (.upload-area) und Lade-Overlay kommen aus css/msv-ui.css */
    .upload-area { margin-bottom: 2rem; }
    .loading-spinner .spinner-border { width: 3rem; height: 3rem; }
    /* Vorschau je Stich: flache Karte wie die Erfassungskarten (kein Anheben, kein Schatten) */
    .stich-preview-card { margin-bottom: 12px; padding: 12px 14px; background: var(--ui-flaeche); border: 1px solid var(--ui-rand); border-radius: 10px; }
    .stich-preview-card h5 { margin-bottom: 0.5rem; font-size: 0.95rem; font-weight: 600; color: var(--ui-text); }
    .stich-preview-card .badge { margin-right: 0.5rem; }
    @media (max-width: 768px) {
        .stich-preview-card { font-size: 0.9rem; }
    }
';
include 'header.inc.php';

// Stich-Definitionen: Programmnummer => Stichname + Resultattabelle (kunst/glueck/schwini/...)
$stichDefinitionen = [];
$programmNummerMapping = [];

try {
    $result = $conn->query('SELECT stich, restable, nummer1, nummer2, nummer3 FROM interne_stichdefinition ORDER BY stich');
    while ($result && ($row = $result->fetch_assoc())) {
        $nummern = [];
        foreach (['nummer1', 'nummer2', 'nummer3'] as $col) {
            $nr = trim((string) ($row[$col] ?? ''));
            if ($nr === '') {
                continue;
            }
            $nummern[] = $nr;
            $programmNummerMapping[$nr] = ['stich' => $row['stich'], 'restable' => $row['restable']];
        }
        $stichDefinitionen[] = ['name' => $row['stich'], 'nummern' => $nummern];
    }
} catch (Exception $e) {
    error_log('[TARGETPRINT] Fehler beim Laden der Stich-Definitionen: ' . $e->getMessage());
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12 ps-0">
            <div class="main-content-wrapper content-width-default">
                <?php $page_title = 'Partner Scheiben Ausdruck'; $page_actions = '<button type="button" class="btn-help" data-help="endsch_targetprint.uebersicht" aria-label="Hilfe"></button>'; include 'partials/page_header.inc.php'; ?>

                <div class="content-background">

                    <!-- Phase 1: Upload -->
                    <div id="phase1" class="workflow-phase">
                        <div class="alert alert-info mb-3">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Unterstützte Stiche:</strong>
                            <?php
                            $stichInfo = [];
                            foreach ($stichDefinitionen as $def) {
                                $stichInfo[] = htmlspecialchars($def['name'] . ' (' . implode(', ', $def['nummern']) . ')', ENT_QUOTES, 'UTF-8');
                            }
                            echo $stichInfo ? implode(' • ', $stichInfo) : 'Keine Imetron-Stichnummern definiert';
                            ?>
                        </div>

                        <div class="upload-area" id="uploadArea">
                            <i class="bi bi-cloud-upload" aria-hidden="true"></i>
                            <h4 class="mt-3">CSV-Datei hier ablegen oder klicken zum Auswählen</h4>
                            <p class="text-muted mb-0">Unterstützte Formate: .csv</p>
                        </div>
                        <input type="file" id="fileInput" accept=".csv" style="display: none;">
                    </div>

                    <!-- Phase 2: Vorschau & PDF -->
                    <div id="phase2" class="workflow-phase" style="display: none;">
                        <div id="fileInfo" class="alert alert-success mb-3" style="display: none;"></div>

                        <div class="card mb-3">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="bi bi-person me-2"></i>Schützen-Informationen</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="schuetzenName" class="form-label"><strong>Name des Schützen:</strong></label>
                                        <input type="text" class="form-control" id="schuetzenName" maxlength="100" placeholder="z.B. Max Mustermann">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="jahrSelect" class="form-label"><strong>Jahr:</strong></label>
                                        <select id="jahrSelect" class="form-select" data-msv-jahr>
                                            <?php
                                            $currentYear = (int) date('Y');
                                            require_once __DIR__ . '/jahr.inc.php';
                                            $jahrStandard = msvJahrStandard(range(2024, $currentYear + 1));
                                            for ($year = 2024; $year <= $currentYear + 1; $year++) {
                                                echo '<option value="' . $year . '"' . ($year === $jahrStandard ? ' selected' : '') . '>' . $year . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted"><i class="bi bi-lightbulb me-1"></i>Der Name wird auf dem PDF angezeigt. Optional.</small>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-eye me-2"></i>
                                    Gefundene Stiche <button type="button" class="btn-help" data-help="endsch_targetprint.stiche" aria-label="Hilfe"></button>
                                </h5>
                            </div>
                            <div class="card-body" id="stichePreviewContainer"></div>
                        </div>

                        <div class="text-center mt-4">
                            <button type="button" class="btn btn-outline-secondary btn-sm me-2" onclick="resetUpload()">
                                <i class="bi bi-arrow-left me-2"></i>Zurück
                            </button>
                            <div class="btn-group btn-group-sm" role="group" aria-label="Zielscheiben">
                                <button type="button" class="btn btn-outline-info" id="generatePdfBtn" onclick="generatePDF()" disabled>
                                    <i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i><span>Zielscheiben</span>
                                </button>
                                <button type="button" class="btn btn-outline-info msv-druck" id="printPdfBtn" data-druck-blocked="1" data-druck-doctype="endsch_targetprint" data-druck-label="Endschiessen Zielscheiben" aria-label="Zielscheiben direkt drucken">
                                    <i class="bi bi-printer" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = <?php echo json_encode(csrf_token()); ?>;
// Programmnummer => {stich, restable}; restable steuert Sonderfälle (kunst/glueck ohne Umrechnung, schwini = Keiler)
const PROGRAMM_NUMMER_MAPPING = <?php echo json_encode($programmNummerMapping); ?>;

let parsedData = null;

$(function () {
    $('#uploadArea').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $('#fileInput').trigger('click');
    });

    $('#fileInput').on('change', function (e) {
        const file = e.target.files[0];
        if (file) handleFileUpload(file);
    });

    $('#uploadArea')
        .on('dragover', function (e) { e.preventDefault(); e.stopPropagation(); $(this).addClass('dragover'); })
        .on('dragleave', function (e) { e.preventDefault(); e.stopPropagation(); $(this).removeClass('dragover'); })
        .on('drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
            const files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) handleFileUpload(files[0]);
        });
});

function handleFileUpload(file) {
    if (!/\.csv$/i.test(file.name)) {
        msvToast('Bitte nur CSV-Dateien hochladen', 'error');
        return;
    }
    const reader = new FileReader();
    reader.onload = function (e) { parseCSV(e.target.result, file.name); };
    reader.onerror = function () { msvToast('Fehler beim Lesen der Datei', 'error'); };
    reader.readAsText(file);
}

// Header-Zeile der Imetron-Datei: "522;Endstich;01.10.2025-17:47:52;Linie: 7;Total: 92"
const HEADER_RE = /^(\d+);([^;]*);(\d{2}\.\d{2}\.\d{4}-\d{2}:\d{2}:\d{2})/;

// Schuss-Zeile: "Nr;Wettkampfschuss;Passe;Wertung;100er Wertung;...;X;Y" (Spalten 0..8)
function parseSchussZeile(teile) {
    if (teile.length < 9 || !/^\d+$/.test(teile[0].trim())) return null;
    const x = parseFloat(teile[7]) || 0;
    const y = parseFloat(teile[8]) || 0;
    const wertung = parseInt(teile[3], 10) || 0;
    if (x === 0 && y === 0 && wertung <= 0) return null;
    return {
        wettkampf: parseInt(teile[1], 10) === 1,
        passe: parseInt(teile[2], 10) || 0,
        wertung: wertung,
        hunderter: parseInt(teile[4], 10) || 0,
        x: x,
        y: y
    };
}

// Hunderterwertung auf die Zehnerskala (91-100 = 10, 81-90 = 9, ...), nicht bei Kunst/Glück
function normalisiereWertung(roh, restable) {
    let wertung = roh.wertung;
    let hunderter = roh.hunderter;
    const ohneUmrechnung = restable === 'kunst' || restable === 'glueck';
    if (!ohneUmrechnung && wertung > 10) {
        hunderter = wertung;
        wertung = wertung >= 91 ? 10 : Math.max(1, Math.floor((wertung - 1) / 10) + 1);
    }
    return { wertung, hunderter };
}

// Stich abschliessen: Wettkampfschüsse bevorzugen (Probeschüsse fallen weg), sonst alle; fortlaufend nummerieren
function stichAbschliessen(stich, alleStiche) {
    if (!stich || stich.roh.length === 0) return;
    const wettkampf = stich.roh.filter(s => s.wettkampf);
    const relevante = wettkampf.length > 0 ? wettkampf : stich.roh;
    const passe = relevante.find(s => s.passe > 0);

    alleStiche.push({
        programmNummer: stich.programmNummer,
        stichName: stich.stichName,
        stichNameCSV: stich.stichNameCSV,
        datum: stich.datum,
        passe: passe ? passe.passe : null,
        schuesse: relevante.map((s, i) => {
            const n = normalisiereWertung(s, stich.restable);
            return { schuss_nr: i + 1, wert: n.wertung, hunderter: n.hunderter, x: s.x, y: s.y, wettkampf: s.wettkampf };
        })
    });
}

function parseCSV(content, filename) {
    try {
        const zeilen = content.split(/\r?\n/);
        const alleStiche = [];
        let aktuellerStich = null;

        for (const rohZeile of zeilen) {
            const zeile = rohZeile.trim();
            if (!zeile) continue;

            const header = zeile.match(HEADER_RE);
            if (header) {
                stichAbschliessen(aktuellerStich, alleStiche);
                const programmNummer = header[1];
                const nameCSV = header[2].trim();
                const meta = PROGRAMM_NUMMER_MAPPING[programmNummer] || null;
                if (!meta) {
                    console.warn('[TARGETPRINT] Programmnummer ' + programmNummer + ' nicht in Imetron-Stichnummern definiert');
                }
                aktuellerStich = {
                    programmNummer: programmNummer,
                    stichName: meta ? meta.stich : (nameCSV || 'Programm ' + programmNummer),
                    stichNameCSV: nameCSV,
                    restable: meta ? meta.restable : null,
                    datum: header[3],
                    roh: []
                };
                continue;
            }

            if (!aktuellerStich) continue;
            const schuss = parseSchussZeile(zeile.split(';'));
            if (schuss) aktuellerStich.roh.push(schuss);
        }
        stichAbschliessen(aktuellerStich, alleStiche);

        if (alleStiche.length === 0) {
            msvToast('Keine Stiche mit Schüssen gefunden', 'warning');
            return;
        }

        parsedData = { filename: filename, alleStiche: alleStiche };
        showPreview();
    } catch (error) {
        console.error('[TARGETPRINT] Parse error:', error);
        // Technische Details nur in die Konsole; der Vorstand braucht den nächsten Schritt
        msvToast('Die Datei konnte nicht gelesen werden. Bitte den CSV-Export des Endschiessens aus Imetron wählen (Trennzeichen Semikolon).', 'error');
    }
}

function showPreview() {
    if (!parsedData) return;

    $('#fileInfo').html(
        '<i class="bi bi-file-earmark-check me-2"></i><strong>Datei:</strong> ' + msvEsc(parsedData.filename) +
        ' <span class="badge bg-success">' + parsedData.alleStiche.length + ' Stiche gefunden</span>'
    ).show();

    let previewHtml = '';
    parsedData.alleStiche.forEach(stich => {
        const passeTxt = stich.passe ? ' - ' + stich.passe + '. Passe' : '';
        let total = 0;
        let max100er = 0;
        stich.schuesse.forEach(s => { total += s.wert; if (s.hunderter > max100er) max100er = s.hunderter; });
        const ersteDrei = stich.schuesse.slice(0, 3).map(s => s.wert).join(', ') + (stich.schuesse.length > 3 ? '...' : '');
        const unbekannt = PROGRAMM_NUMMER_MAPPING[stich.programmNummer]
            ? ''
            : ' <span class="badge bg-warning text-dark" data-tooltip="Programmnummer nicht in den Imetron-Stichnummern definiert">nicht definiert</span>';

        previewHtml +=
            '<div class="stich-preview-card">' +
                '<h5>' + msvEsc(stich.stichName) + msvEsc(passeTxt) +
                    ' <span class="badge bg-primary">' + msvEsc(stich.programmNummer) + '</span>' +
                    '<span class="badge bg-info">' + stich.schuesse.length + ' Schüsse</span>' + unbekannt +
                '</h5>' +
                '<div class="row">' +
                    '<div class="col-md-6">' +
                        '<p class="mb-1"><strong>Total:</strong> ' + total + ' Punkte</p>' +
                        '<p class="mb-0"><strong>100er:</strong> ' + max100er + '</p>' +
                    '</div>' +
                    '<div class="col-md-6"><small class="text-muted">Erste 3 Schüsse: ' + msvEsc(ersteDrei) + '</small></div>' +
                '</div>' +
            '</div>';
    });

    $('#stichePreviewContainer').html(previewHtml);
    $('#phase1').hide();
    $('#phase2').show();
    zielscheibenBereit(true);
    msvToast('CSV erfolgreich geladen', 'success');
}

// Daten für Generator und Direktdruck (gleicher POST)
function zielscheibenDaten() {
    return {
        alleStiche: parsedData.alleStiche,
        schuetzenName: $('#schuetzenName').val().trim(),
        jahr: $('#jahrSelect').val()
    };
}

// PDF und Drucker gibt es erst, wenn eine CSV geladen ist
function zielscheibenBereit(an) {
    $('#generatePdfBtn').prop('disabled', !an);
    $('#printPdfBtn').attr('data-druck-blocked', an ? '0' : '1');
    if (window.MsvDruck) MsvDruck.refresh();
}

// Ausgabe-Baustein msvAusgabe: sperren, Spinner, Download, Toast (kein Overlay, kein Erfolgsdialog mehr)
function generatePDF() {
    if (!parsedData) {
        msvToast('Keine Daten zum Generieren', 'error');
        return;
    }
    const d = zielscheibenDaten();
    msvAusgabe(document.getElementById('generatePdfBtn'), {
        url: 'endsch_targetprint/generate_pdf.php',
        json: d,
        csrf: CSRF_TOKEN,
        titel: 'Zielscheiben' + (d.schuetzenName ? ' ' + d.schuetzenName : '') + ' ' + d.jahr,
        name: 'Zielscheiben_' + (d.schuetzenName ? d.schuetzenName + '_' : '') + d.jahr,
        fehler: 'Das PDF konnte nicht erstellt werden. Bitte nochmals versuchen.'
    });
}

function resetUpload() {
    $('#fileInput').val('');
    parsedData = null;
    $('#phase2').hide();
    $('#phase1').show();
    $('#schuetzenName').val('');
    zielscheibenBereit(false);
}
</script>

<?php include 'partials/direktdruck_scripts.inc.php'; ?>
<script>
// Direktdruck (QZ Tray), Profil «Endschiessen Zielscheiben»: gleicher JSON-POST wie der PDF-Knopf
MsvDruck.resolve('endsch_targetprint', () => {
    if (!parsedData) { msvToast('Zuerst eine CSV laden', 'warning'); return null; }
    const d = zielscheibenDaten();
    return {
        url: 'endsch_targetprint/generate_pdf.php',
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
        body: JSON.stringify(d),
        jobName: 'Zielscheiben' + (d.schuetzenName ? ' ' + d.schuetzenName : '') + ' ' + d.jahr
    };
});
</script>
<?php include 'footer.inc.php'; ?>
