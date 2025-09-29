<?php
// jmresultate.php
include 'dbconnect.inc.php';

// Spezifische Styles für JM-Resultate mit Z-Index Fix
$page_specific_css = "
/* ===== Seite: nur der Tabellenbereich scrollt ===== */
html, body { height:100%; overflow:hidden; }

.container-fluid, .row, [class*='col-'],
.main-content-wrapper, .content-background,
.table-wrapper, .table-responsive { min-height:0; }

:root { --vh:1vh; --app-header:0px; --app-footer:0px; }

/* Höhe vom Viewport ableiten (Header/Footer via JS gesetzt) */
.main-content-wrapper{
  display:flex; flex-direction:column;
  height: calc(var(--vh) * 100 - var(--app-header) - var(--app-footer));
}

.content-background{ display:flex; flex-direction:column; flex:1; overflow:hidden; }
.table-wrapper{ display:flex; flex-direction:column; flex:1; overflow:hidden; }

/* Scrollbox: nur hier scrollen */
.table-responsive{
  flex:1;
  overflow:auto;
  margin-bottom:0;
  scroll-padding-bottom:28px;
}

/* Titelzeile dieses Screens ausblenden (Seite-spezifisch) */
.table-wrapper .table-title{ display:none !important; }

/* =========================================
   JM: THEAD-Layout (unten ausrichten, sticky)
   ========================================= */
:root { --jm-head-h: 280px; } /* bei Bedarf 160/180/200px */

#jmresultateTabelle thead tr{
  height: var(--jm-head-h) !important;      /* Zeilenhöhe steuert Gesamthöhe */
}

#jmresultateTabelle thead th{
  display: table-cell !important;           /* echte Table-Cells */
  vertical-align: bottom !important;        /* „valign bottom“ */
  /* keine max-height hier! -> Zelle darf wachsen */
  min-height: var(--jm-head-h) !important;  /* Mindesthöhe */
  position: sticky;
  top: var(--top-hscroll, 0px);             /* berücksichtigt Top-Scrollbar */
  padding: .5rem .5rem .35rem !important;
  line-height: 1.1 !important;
  overflow: visible !important;
}

/* Erste Header-Spalte linksbündig + korrekter Stack */
#jmresultateTabelle thead th:first-child{
  text-align:left !important;
  z-index:110 !important;
}

/* =========================================
   Sticky erste Spalte (Header + Body)
   ========================================= */
#jmresultateTabelle th:first-child{
  position: sticky; left: 0;
  top: var(--top-hscroll, 0px);             /* Offset der Top-Scrollbar */
  background:#f8fafc;
  border-right: 2px solid #dee2e6;
  width:200px; min-width:200px; max-width:200px;
}
#jmresultateTabelle td:first-child{
  position: sticky; left: 0;
  z-index:10 !important;                    /* unter THEAD */
  background:#fff;
  border-right: 2px solid #dee2e6;
}

/* =========================================
   Letzte Spalte: Header sticky, Body NICHT sticky
   ========================================= */
#jmresultateTabelle thead th:last-child{
  position: sticky !important;
  top: var(--top-hscroll, 0px) !important;
  z-index:100 !important;
}
#jmresultateTabelle tbody td:last-child{
  position: static !important;
  left:auto !important; right:auto !important;
  z-index:auto !important;
  box-shadow:none !important;
}

/* Sicherheit: nur die ERSTE Body-Spalte bleibt sticky */
#jmresultateTabelle tbody td:not(:first-child){ position: static !important; }

/* =========================================
   Spaltenbreiten & horizontales Scrollen
   ========================================= */
#jmresultateTabelle{
  table-layout: fixed;
  width: auto;
  min-width: 1800px;                        /* ggf. 2000/2200 erhöhen */
  border-collapse: separate;
  border-spacing: 0;
  --col:80px;
}
#jmresultateTabelle th:not(:first-child),
#jmresultateTabelle td:not(:first-child){
  width: var(--col); min-width: var(--col); max-width: var(--col);
  text-align:center;
}

/* Inputs kompakt */
#jmresultateTabelle .small-input{
  width:65px; min-width:65px; max-width:65px;
  text-align:center; font-size:.85rem;
  padding:.25rem .15rem; font-weight:500;
}

/* unsichtbarer Spacer unten gegen Clipping */
#jmresultateTabelle tbody::after{ content:\\\"\\\"; display:block; height:12px; }

/* Vertikale Zusatz-Header (falls vorhanden) */
#jmresultateTabelle .vertical-header{
  position: sticky; top: var(--top-hscroll, 0px);
  z-index:105 !important; /* zwischen normalem THEAD (100) und erster Spalte (110) */
  background: linear-gradient(180deg,#f8fafc 0%,#eef2f7 100%);
}

/* =========================================
   Responsive Feintuning
   ========================================= */
@media (max-width: 768px){
  #jmresultateTabelle{ min-width:1100px; }
  #jmresultateTabelle th:not(:first-child),
  #jmresultateTabelle td:not(:first-child){ --col:60px; }
  #jmresultateTabelle th:first-child, #jmresultateTabelle td:first-child{
    width:150px; min-width:150px; max-width:150px;
  }
  #jmresultateTabelle .small-input{
    width:50px; min-width:50px; max-width:50px;
    font-size:.75rem; padding:.2rem .1rem;
  }
}

/* Formular in die Flex-Kette einhängen (wichtig!) */
#jmresultateForm {
  display:flex;
  flex-direction:column;
  flex:1 1 auto;
  min-height:0 !important;
}
";






include 'header.inc.php';

// CSRF Token generieren
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<link rel="stylesheet" href="../css/fixes/resultate-unified.css">
<link rel="stylesheet" href="../css/fixes/table-title-and-firstcol-override.css">
<div class="container-fluid">
    <div class="row">
        <div class="col-xl-12 col-lg-12 col-12 ps-0">
            <!-- Äußerer weißer Container -->
            <div class="main-content-wrapper">
                <!-- Header außerhalb des inneren Containers -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                            <i class="bi bi-trophy me-2"></i>
                            Erfassung Jahresmeisterschaft
                        </h2>
                    </div>
                </div>

                <!-- Weißer Hintergrund-Container -->
                <div class="content-background">
                    <form id="jmresultateForm">
                        <input type="hidden" name="csrf_token"
                            value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                        <!-- Jahr-Auswahl in eigener Card -->
                        <div class="year-selection-card">
                            <div class="row align-items-center">
                                <div class="col-md-5">
                                    <label for="yearSelect" class="form-label fw-bold">
                                        <i class="bi bi-calendar3 me-1"></i>Jahr auswählen:
                                    </label>
                                    <select id="yearSelect" class="form-select">
                                        <!-- Optionen werden per JavaScript eingefügt -->
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Button Toolbar -->
                        <div class="button-toolbar">
                            <div class="button-group">
                                <button id="redirect-btn" type="button"
                                    class="btn btn-compact-standard btn-outline-success">
                                    <i class="bi bi-trophy me-2"></i>
                                    Rangliste
                                </button>
                                <button type="submit" class="btn btn-compact-standard btn-outline-primary">
                                    <i class="bi bi-save me-2"></i>
                                    Ergebnisse speichern
                                </button>
                                <button id="delete-btn" type="button"
                                    class="btn btn-compact-standard btn-outline-danger">
                                    <i class="bi bi-trash me-2"></i>
                                    Aktuelle Resultate löschen
                                </button>
                            </div>
                        </div>

                        <!-- Nachrichten Container -->
                        <div id="message"></div>

                        <!-- Tabelle -->
                        <div class="table-wrapper">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="jmresultateTabelle">
                                    <!-- Tabelleninhalte werden dynamisch geladen -->
                                </table>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal zur Bestätigung für das Löschen aller Daten -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmModalLabel">
                    <i class="bi bi-exclamation-triangle text-warning me-2"></i>
                    Bestätigung erforderlich
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                Möchten Sie wirklich ALLE Resultate des aktuellen Jahres löschen? Diese Aktion kann nicht rückgängig
                gemacht werden!
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Abbrechen
                </button>
                <button type="button" class="btn btn-outline-danger" id="confirmAction">
                    <i class="bi bi-check-circle me-1"></i>Bestätigen
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Unsaved Changes Modal -->
<div class="modal fade" id="unsavedChangesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-exclamation-triangle me-2"></i> Ungespeicherte Änderungen
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
      </div>
      <div class="modal-body">
        Du hast Änderungen, die noch nicht gespeichert sind. Was möchtest du tun?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" id="btn-cancel-leave" data-bs-dismiss="modal">Abbrechen</button>
        <button type="button" class="btn btn-outline-danger" id="btn-leave-without-save">Ohne Speichern verlassen</button>
        <button type="button" class="btn btn-success" id="btn-save-and-leave">Speichern & verlassen</button>
      </div>
    </div>
  </div>
</div>

<script>
    // Titelhöhe messen -> als CSS-Var, damit thead unter dem Titel sticky bleibt
   function setJMTitleOffset() {
  const resp  = document.querySelector('#jmresultateTabelle')?.closest('.table-responsive');
  const title = resp ? resp.querySelector('.table-title') : null; // Titel sitzt jetzt im resp
  if (!resp || !title) return;
  const h = Math.round(title.getBoundingClientRect().height);
  resp.style.setProperty('--jm-title-h', h + 'px');
}


    // Scrollbox dynamisch begrenzen
function sizeJMTable() {
  const resp = document.querySelector('#jmresultateTabelle')?.closest('.table-responsive');
  if (!resp) return;

  const footer = document.querySelector('footer, .site-footer');
  const footerH = footer ? Math.round(footer.getBoundingClientRect().height) : 0;

  // Höhe der Top-Scrollbar, falls vorhanden (liegt aktuell in der .table-responsive)
  const topScroll = resp.querySelector('.top-hscroll');
  const topScrollH = topScroll ? Math.round(topScroll.getBoundingClientRect().height) : 0;

  const top = Math.round(resp.getBoundingClientRect().top);
  const padding = 16, safety = 20;

  // WICHTIG: Top-Scrollbar-Höhe abziehen
  const maxH = Math.max(
    180,
    window.innerHeight - top - footerH - padding - safety - topScrollH
  );

  resp.style.maxHeight = maxH + 'px';
  resp.style.overflowY = 'auto';
}


    // EINZIGE zentrale Recalc-Funktion
    function afterJMRowsInserted() {
        //setJMTitleOffset();
        sizeJMTable();
    }

    $(function () {
        var currentYear = new Date().getFullYear();
        var startYear = 2024;
        var basePath = '';
        var $yearDD = $('#yearSelect');

        // Viewport-Variablen
        function applyViewportVars() {
            document.documentElement.style.setProperty('--vh', (window.innerHeight * 0.01) + 'px');
            const headerEl = document.querySelector('.navbar, header, .site-header');
            const footerEl = document.querySelector('footer, .site-footer');
            const headerH = headerEl ? Math.round(headerEl.getBoundingClientRect().height) : 0;
            const footerH = footerEl ? Math.round(footerEl.getBoundingClientRect().height) : 0;
            document.documentElement.style.setProperty('--app-header', headerH + 'px');
            document.documentElement.style.setProperty('--app-footer', footerH + 'px');
        }
        applyViewportVars();

        // Resize-Listener
        $(window).on('resize', debounce(function () {
            applyViewportVars();
            sizeJMTable();
        }, 120));
        window.addEventListener('resize', setJMTitleOffset);  // <— wichtig für den Titel-Offset

        // Scroll-Delegation (scrollen auch außerhalb der Tabelle)
        enableGlobalScrollToTable('jmresultateTabelle');

        // Toasts
        function showToast(message, type = 'info') {
            if ($('#toast-container').length === 0) {
                $('body').append('<div id="toast-container" style="position:fixed;top:70px;right:20px;z-index:9999;"></div>');
            }
            const icon = type === 'success' ? 'check-circle' : (type === 'error' ? 'exclamation-circle' : 'info-circle');
            const $t = $('<div>').addClass(`toast-message toast-${type}`).html(`<i class="bi bi-${icon} me-2"></i>${message}`);
            $('#toast-container').append($t);
            setTimeout(() => $t.addClass('show'), 40);
            setTimeout(() => { $t.removeClass('show'); setTimeout(() => $t.remove(), 300); }, 4000);
        }
        function showMessage(m, t) { const map = { danger: 'error', success: 'success', warning: 'warning', info: 'info' }; showToast(m, map[t] || 'info'); }

        // Jahr-Dropdown
        $yearDD.empty();
        for (let y = currentYear; y >= startYear; y--) $yearDD.append($('<option>', { value: y, text: y }));
        $yearDD.val(currentYear);

        // Daten laden
        function loadJMResultate(year) {
            $('#jmresultateTabelle').html(
                '<tr><td class="text-center py-4"><div class="spinner-border spinner-border-sm me-2" style="color:var(--secondary-color);"></div>Lade Daten...</td></tr>'
            );
            $.get(basePath + 'jmresultate/load_jmresultate_form.php', { year })
                .done(function (html) {
                    $('#jmresultateTabelle').html(html);
                    showToast('Daten erfolgreich geladen', 'success');

                    // Tooltips
                    $('#jmresultateTabelle input.small-input').each(function () {
                        const col = $(this).closest('td').index();
                        const headerText = $('#jmresultateTabelle thead th').eq(col).text().trim();
                        $(this).attr({ 'data-bs-toggle': 'tooltip', 'data-bs-placement': 'top', 'title': headerText });
                    });
                    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

                    // Spalten-Highlight
                    $(document).off('focus', '#jmresultateTabelle input')
                        .on('focus', '#jmresultateTabelle input', function () {
                            $('#jmresultateTabelle td, #jmresultateTabelle th').removeClass('active-column active-column-header');
                            const col = $(this).closest('td').index();
                            $('#jmresultateTabelle tr').each(function () {
                                $(this).find('td').eq(col).addClass('active-column');
                                $(this).find('th').eq(col).addClass('active-column-header');
                            });
                        })
                        .off('blur', '#jmresultateTabelle input')
                        .on('blur', '#jmresultateTabelle input', function () {
                            setTimeout(() => {
                                if (!$('#jmresultateTabelle input:focus').length) {
                                    $('#jmresultateTabelle td, #jmresultateTabelle th').removeClass('active-column active-column-header');
                                }
                            }, 100);
                        });

                    // <<< WICHTIG: nach dem Einfügen neu berechnen
                    afterJMRowsInserted();
                })
                .fail(function () {
                    $('#jmresultateTabelle').html(
                        '<tr><td class="text-center text-danger py-4"><i class="bi bi-exclamation-triangle me-2"></i>Fehler beim Laden der Daten</td></tr>'
                    );
                    showToast('Fehler beim Laden der Daten', 'error');
                });
        }
        loadJMResultate(currentYear);
        $yearDD.on('change', function () { loadJMResultate($(this).val()); });

        // Speichern - nach erfolgreichem Speichern isDirty zurücksetzen
        $('#jmresultateForm').on('submit.main', function (e) {
            e.preventDefault();
            e.stopImmediatePropagation(); // Verhindert, dass andere Handler ausgeführt werden
            
            const $btn = $(this).find('button[type="submit"]').prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');
            const formData = $(this).serialize() + '&year=' + $yearDD.val();
            
            $.post(basePath + 'jmresultate/save_jmresultate.php', formData)
                .done(() => { 
                    // WICHTIG: isDirty auf false setzen nach erfolgreichem Speichern
                    window.isDirtyFlag = false;
                    // Trigger custom event um anderen Code zu informieren
                    $(document).trigger('jmresultate:saved');
                    
                    showMessage('Ergebnisse erfolgreich gespeichert!', 'success'); 
                    setTimeout(() => {
                        loadJMResultate($yearDD.val());
                        window.isDirtyFlag = false; // Nochmal nach dem Laden zurücksetzen
                    }, 800); 
                })
                .fail(() => { 
                    showMessage('Fehler beim Speichern der Ergebnisse', 'danger'); 
                })
                .always(() => { 
                    $btn.prop('disabled', false).html('<i class="bi bi-save me-2"></i>Ergebnisse speichern'); 
                });
        });

        // Löschen
        $('#delete-btn').on('click', function (e) { e.preventDefault(); $('#confirmModal').modal('show'); });
        $('#confirmAction').on('click', function () {
            const $b = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Lösche...');
            $.post(basePath + 'jmresultate/delete_jmresultate.php', {
                year: $yearDD.val(),
                csrf_token: $('input[name="csrf_token"]').val()
            }).done(function () {
                showMessage('Alle aktuellen Resultate erfolgreich gelöscht', 'success');
                setTimeout(() => loadJMResultate($yearDD.val()), 600);
            }).fail(function () {
                showMessage('Fehler beim Löschen der aktuellen Resultate', 'danger');
            }).always(function () {
                $('#confirmModal').modal('hide');
                $b.prop('disabled', false).html('<i class="bi bi-check-circle me-1"></i>Bestätigen');
            });
        });

        // Hilfsfunktionen
       function enableGlobalScrollToTable(tableId) {
  const box = document.getElementById(tableId)?.closest('.table-responsive');
  if (!box) return;

  const vScroll = d => box.scrollTop  += d;
  const hScroll = d => box.scrollLeft += d;

  // Wheel/Trackpad: vertikal oder horizontal (Shift ODER Alt => horizontal)
  window.addEventListener('wheel', function (e) {
    if (getComputedStyle(document.body).overflow !== 'hidden') return;
    const horizontalMode = e.shiftKey || e.altKey || Math.abs(e.deltaX) > Math.abs(e.deltaY);
    if (horizontalMode) {
      hScroll(e.deltaX || e.deltaY);
    } else {
      vScroll(e.deltaY);
    }
    e.preventDefault();
  }, { passive: false });

  // Tastatur inkl. Links/Rechts
  window.addEventListener('keydown', function (e) {
    if (!['Space','ArrowDown','ArrowUp','ArrowLeft','ArrowRight','PageDown','PageUp','End','Home'].includes(e.code)) return;
    const stepV = 60, pageV = box.clientHeight - 40;
    const stepH = 80, pageH = Math.min(400, box.clientWidth);
    switch (e.code) {
      case 'Space':
      case 'PageDown':  vScroll(pageV);  break;
      case 'PageUp':    vScroll(-pageV); break;
      case 'ArrowDown': vScroll(stepV);  break;
      case 'ArrowUp':   vScroll(-stepV); break;
      case 'End':       box.scrollTop  = box.scrollHeight; break;
      case 'Home':      box.scrollTop  = 0; break;
      case 'ArrowRight':hScroll(stepH); break;
      case 'ArrowLeft': hScroll(-stepH);break;
      default: return;
    }
    e.preventDefault();
  });

  // Drag-to-Pan (linke Maustaste halten und ziehen)
  let dragging = false, startX = 0, startLeft = 0, startY = 0, startTop = 0;
  window.addEventListener('mousedown', (e) => {
    // Nur starten, wenn die Seite selbst nicht scrollt
    if (getComputedStyle(document.body).overflow !== 'hidden') return;
    // nicht starten auf Inputs/Buttons, damit die bedienbar bleiben
    const tag = (e.target.closest('input,textarea,button,select,a') || {}).tagName;
    if (tag) return;

    dragging  = true;
    startX    = e.clientX;
    startY    = e.clientY;
    startLeft = box.scrollLeft;
    startTop  = box.scrollTop;
    document.body.style.cursor = 'grabbing';
  });
  window.addEventListener('mousemove', (e) => {
    if (!dragging) return;
    const dx = e.clientX - startX;
    const dy = e.clientY - startY;
    box.scrollLeft = startLeft - dx;
    box.scrollTop  = startTop  - dy;
  });
  window.addEventListener('mouseup', () => {
    dragging = false;
    document.body.style.cursor = '';
  });
}


        function debounce(fn, wait) { let t; return function () { clearTimeout(t); t = setTimeout(() => fn.apply(this, arguments), wait); }; }
    });
</script>

<script>
/**
 * Erstellt eine obere horizontale Scrollbar für die Tabelle und
 * synchronisiert sie beidseitig mit der .table-responsive.
 * Außerdem: Shift + Mausrad => horizontales Scrollen.
 */
(function initTopScrollForJM(){
  const table = document.getElementById('jmresultateTabelle');
  if (!table) return;
  const resp = table.closest('.table-responsive');
  if (!resp) return;

  // 1) Top-Scrollbar einfügen (einmalig)
  let top = resp.querySelector('.top-hscroll');
  if (!top) {
    top = document.createElement('div');
    top.className = 'top-hscroll';
    const spacer = document.createElement('div');
    spacer.className = 'spacer';
    top.appendChild(spacer);
    // oben als erstes Kind der Scrollbox einsetzen
    resp.insertBefore(top, resp.firstChild);
  }
  const spacer = top.querySelector('.spacer');

  // 2) Höhe messen -> CSS-Var setzen, damit thead darunter sticky bleibt
  function setTopHOffset(){
    const h = Math.round(top.getBoundingClientRect().height);
    resp.style.setProperty('--top-hscroll', h + 'px');
  }

  // 3) Breite synchronisieren (Spacers breite = tatsächliche Tabellenbreite)
  function syncSpacerWidth(){
    // scrollWidth der Tabelle inkl. Sticky-Spalten
    spacer.style.width = table.scrollWidth + 'px';
  }

  // 4) Scroll-Sync (beidseitig)
  let syncing = false;
  top.addEventListener('scroll', () => {
    if (syncing) return;
    syncing = true;
    resp.scrollLeft = top.scrollLeft;
    syncing = false;
  });
  resp.addEventListener('scroll', () => {
    if (syncing) return;
    syncing = true;
    top.scrollLeft = resp.scrollLeft;
    syncing = false;
  });

  // 5) Shift + Mausrad => horizontal scrollen (auf Fenster-Ebene, delegiert an resp)
  window.addEventListener('wheel', function(e){
    if (!e.shiftKey) return;
    // nur umleiten, wenn die Seite selbst nicht scrollt
    if (getComputedStyle(document.body).overflow === 'hidden') {
      resp.scrollLeft += e.deltaY;
      e.preventDefault();
    }
  }, { passive: false });

  // 6) Reaktionsfähig halten (Größenänderungen / neue Daten)
  const ro = new ResizeObserver(() => {
    syncSpacerWidth();
    setTopHOffset();
  });
  ro.observe(table);
  ro.observe(top);

  window.addEventListener('resize', () => {
    syncSpacerWidth();
    setTopHOffset();
  });

  // Falls deine Seite nach AJAX neue Inhalte einsetzt, kannst du das auch manuell triggern:
  // (keine Pflicht – ResizeObserver deckt i.d.R. alles ab)
  window.jmUpdateTopScroller = function(){
    syncSpacerWidth();
    setTopHOffset();
  };

  // Initial
  syncSpacerWidth();
  setTopHOffset();
})();

document.getElementById("redirect-btn").addEventListener("click", function () {
    // Gewähltes Jahr holen (falls relevant)
    const selectedYear = document.getElementById("yearSelect")?.value || "";

    // Ziel-URL bauen
    let targetUrl = "https://jahresmeisterschaft.msvwilen.ch/inc/jmrang.php";
    if (selectedYear) {
        targetUrl += "?year=" + encodeURIComponent(selectedYear);
    }

    // Weiterleiten
    window.location.href = targetUrl;
});
</script>
<script>
(function(){
  const $doc = $(document);
  let isDirty = false;            // Flag: ungespeicherte Änderungen vorhanden
  let pendingNav = null;          // Ziel-URL, wenn User weg navigieren möchte
  // AUTOSAVE DEAKTIVIERT - const DEBOUNCE_MS = 800;
  
  // Listen for saved event from main form handler
  $doc.on('jmresultate:saved', function() {
    isDirty = false;
    window.isDirtyFlag = false;
  });

  // ---- Debounce Helper ----
  function debounce(fn, ms){
    let t; return function(...args){
      clearTimeout(t); t = setTimeout(()=>fn.apply(this,args), ms);
    };
  }

  // ---- Payload-Buffer für gebündelte Saves ----
  const pendingPayload = { punkte:{}, punkte_runde1:{}, punkte_runde2:{} };

  function addChangeToPayload(input){
    const name = input.name || '';
    const val  = input.value;
    // Erwartete Namen: punkte[123][456], punkte_runde1[123][456], punkte_runde2[123][456]
    const m = name.match(/^(punkte(?:_runde1|_runde2)?)\[(\d+)\]\[(\d+)\]$/);
    if(!m) return; // unbekanntes Feld, ignorieren
    const bucket = m[1], mid = m[2], did = m[3];
    if(!pendingPayload[bucket][mid]) pendingPayload[bucket][mid] = {};
    pendingPayload[bucket][mid][did] = val;
  }

  function hasPayload(){
    return Object.values(pendingPayload).some(group => Object.keys(group).length);
  }

  function flushPayload(){
    const copy = {
      punkte: JSON.parse(JSON.stringify(pendingPayload.punkte)),
      punkte_runde1: JSON.parse(JSON.stringify(pendingPayload.punkte_runde1)),
      punkte_runde2: JSON.parse(JSON.stringify(pendingPayload.punkte_runde2))
    };
    pendingPayload.punkte = {};
    pendingPayload.punkte_runde1 = {};
    pendingPayload.punkte_runde2 = {};
    return copy;
  }

  // ---- AUTOSAVE DEAKTIVIERT ----
  // Die automatische Speicherfunktion wurde entfernt.
  // Änderungen werden nur noch über den "Speichern"-Button gespeichert.
  /*
  const debouncedAutoSave = debounce(() => {
    if(!hasPayload()) return;
    const data = flushPayload();
    $.ajax({
      url: 'inc/jmresultate/save_jmresultate.php',
      method: 'POST',
      data,
      dataType: 'json'
    }).done(res=>{
      if(res && res.success){
        if(!hasPayload()) isDirty = false;
        showToast('Gespeichert.', 'success');
      } else {
        isDirty = true;
        showToast('Fehler beim Speichern: ' + (res?.message || 'Unbekannt'), 'error');
      }
    }).fail(xhr=>{
      isDirty = true;
      console.error('Autosave failed', xhr);
      showToast('Speichern fehlgeschlagen (HTTP ' + xhr.status + ')', 'error');
    });
  }, DEBOUNCE_MS);
  */

  // ---- Input-Listener: Nur noch Änderungen markieren (kein Autosave mehr) ----
  $doc.on('input change', '#jmresultateTabelle input, #heimresultateTabelle input, #kantiresultateTabelle input', function(){
    isDirty = true;
    window.isDirtyFlag = true; // Global verfügbar machen für andere Scripts
    addChangeToPayload(this);
    // debouncedAutoSave(); // AUTOSAVE DEAKTIVIERT
  });

  // ---- Browser-Refresh/Tab-Schliessen: nativer Hinweis (Modal geht hier nicht!) ----
  window.addEventListener('beforeunload', function(e){
    if(!isDirty) return;
    e.preventDefault();
    e.returnValue = ''; // zeigt Standard-Warnung an
  });

  // ---- Interne Navigation abfangen -> Modal ----
  function requestNavigation(href){
    if(!isDirty){ window.location.href = href; return; }
    pendingNav = href;
    const modal = new bootstrap.Modal(document.getElementById('unsavedChangesModal'));
    modal.show();
  }

  // Links (gleiche Origin), ohne target/_blank und ohne Anker
  $doc.on('click', 'a[href]:not([target]):not([href^="#"]):not([data-ignore-unsaved])', function(e){
    // Nur gleiche Domain abfangen (extern ggf. direkt durchlassen)
    if(this.origin !== window.location.origin) return;
    e.preventDefault();
    requestNavigation(this.href);
  });

  // Form-Submits komplett ignorieren - das Hauptformular hat seinen eigenen Handler
  // Keine Form-Submits abfangen, da das normale Speichern funktionieren soll

  // Spezieller Redirect-Button
  $doc.on('click', '#redirect-btn', function(e){
    e.preventDefault();
    const y = $('#yearSelect').val();
    const href = 'https://jahresmeisterschaft.msvwilen.ch/inc/jmrang.php' + (y ? ('?year='+encodeURIComponent(y)) : '');
    requestNavigation(href);
  });

  // ---- Modal-Buttons ----
  $('#btn-leave-without-save').on('click', function(){
    isDirty = false; // beforeunload nicht auslösen
    const href = pendingNav; pendingNav = null;
    bootstrap.Modal.getInstance(document.getElementById('unsavedChangesModal')).hide();
    if(href) window.location.href = href;
  });

  $('#btn-save-and-leave').on('click', function(){
    const $btn = $(this).prop('disabled', true);
    const modalEl = document.getElementById('unsavedChangesModal');

    // Sofort speichern (bypass Debounce) falls noch Änderungen im Buffer sind
    const proceed = () => {
      isDirty = false;
      bootstrap.Modal.getInstance(modalEl).hide();
      const href = pendingNav; pendingNav = null;
      if(href) window.location.href = href;
      $btn.prop('disabled', false);
    };

    if(hasPayload()){
      const dataNow = flushPayload();
      $.ajax({
        url: 'inc/jmresultate/save_jmresultate.php',
        method: 'POST',
        data: dataNow,
        dataType: 'json'
      }).done(res=>{
        if(res && res.success){
          proceed();
        } else {
          isDirty = true;
          $btn.prop('disabled', false);
          showToast('Speichern fehlgeschlagen: ' + (res?.message || ''), 'error');
        }
      }).fail(xhr=>{
        isDirty = true;
        $btn.prop('disabled', false);
        showToast('Speichern fehlgeschlagen (HTTP ' + xhr.status + ')', 'error');
      });
    } else {
      // Nichts im Buffer -> einfach verlassen
      proceed();
    }
  });

  $('#btn-cancel-leave').on('click', function(){
    pendingNav = null; // einfach Modal schliessen
  });

  // ---- Mini-Toast Helper (nutzt dein #message, falls vorhanden) ----
  function showToast(text, type){
    const $msg = $('#message');
    if($msg.length){
      $msg
        .stop(true, true)
        .removeClass()
        .addClass('alert ' + (type==='success'?'alert-success':type==='error'?'alert-danger':'alert-info'))
        .text(text)
        .fadeIn(120)
        .delay(1200)
        .fadeOut(180);
    } else {
      console.log(text);
    }
  }

})();
</script>


<?php
include 'footer.inc.php';
?>