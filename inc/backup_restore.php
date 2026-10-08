<?php
// backup_restore.php – Vollseite im Stil von gruppenerfassung.php
include 'dbconnect.inc.php';
require_once __DIR__ . '/csrf.inc.php'; // csrf_token(), Session über session_config

// Seitenspezifische Styles definieren (gleicher Look wie in Deinem Beispiel)
$page_specific_css = "
/* Backup/Restore spezifische Styles – an Deine Variablen angelehnt */
.main-card {
    background: var(--ui-flaeche);
    border: 1px solid var(--ui-rand);
    border-radius: 10px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.sidebar-card,
.group-creation-card,
.existing-groups-card {
    background: var(--ui-flaeche);
    border: 1px solid var(--ui-rand);
    border-radius: 10px;
    padding: 1.5rem;
    margin-bottom: 1.25rem;
}


.card-title {
    color: var(--ui-text);
    font-weight: 600;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: .5rem;
}

/* Tabelle schlank */
#tblBackups .btn { padding: .25rem .5rem; }


/* Modal Styles */
.modal-backdrop.show {
    opacity: 0.5;
}

.modal.fade .modal-dialog {
    transition: transform .3s ease-out;
}

.modal-body .spinner-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 120px;
    gap: 1rem;
}

.progress {
    height: 4px;
    overflow: hidden;
    background-color: var(--ui-rand);
    border-radius: 3px;
}

.progress-bar-animated {
    background: var(--ui-akzent);
    background-size: 200% 100%;
    animation: progress-animation 1.5s ease-in-out infinite;
}

@keyframes progress-animation {
    0% { background-position: 100% 0; }
    100% { background-position: -100% 0; }
}

/* Mobile Optimierung für Backup & Restore */
@media (max-width: 767.98px) {
    .sidebar-card, .group-creation-card, .existing-groups-card {
        padding: 1rem !important;
    }

    .card-title {
        font-size: 1rem !important;
    }

    /* Stack Quick Action Buttons on Mobile */
    .d-flex.gap-2 {
        flex-direction: column !important;
        width: 100% !important;
    }

    .d-flex.gap-2 .btn {
        width: 100% !important;
    }

    /* Table Actions kompakter */
    #tblBackups .btn {
        min-width: 44px !important;
        min-height: 44px !important;
        padding: 0.5rem !important;
    }

    /* Modal Buttons volle Breite */
    .modal-footer .btn {
        flex: 1 1 auto !important;
    }

    .container-fluid {
        padding: 0.5rem !important;
    }
}
";

// Header binden (zieht globale Styles/Variablen/Bootstrap/Icons)
include 'header.inc.php';

// Nur Admin (nach header.inc.php, da dieser user_role setzt). Bisher war die Seite nur im Menü
// versteckt, aber für den Vorstand per Adresse erreichbar – ein Restore ersetzt die ganze Datenbank.
if (($_SESSION['user_role'] ?? '') !== 'admin' && (int)($_SESSION['user_id'] ?? 0) !== 1) {
    ob_end_clean();
    header('Location: home.php');
    exit();
}


// Pfad anpassen, falls nötig
$configFile = dirname(__DIR__, 1) . '/../msvjm_config.php';
$cfg = require $configFile;

// Falls Key gesetzt ist, nimm ihn – sonst leer
$BACKUP_API_KEY = $cfg['backup']['api_key'] ?? '';

?>

<div class="container-fluid">
  <div class="row">
    <div class="col-12 ps-0">
      <!-- Aussen-Container -->
      <div class="main-content-wrapper content-width-default">
        <!-- Header-Zeile -->
        <?php $page_title = 'Backup &amp; Restore'; $page_actions = '<button type="button" class="btn-help" data-help="backup_restore.uebersicht" aria-label="Hilfe"></button>'; include 'partials/page_header.inc.php'; ?>

        <!-- Weisser Hintergrund-Container -->
        <div class="content-background">
          <div class="row g-3">
            <!-- Info & Quick Actions -->
            <div class="col-lg-8">
              <div class="sidebar-card">
                <h5 class="card-title">
                  <i class="bi bi-shield-lock"></i>
                  Hinweise & Schnellaktionen <button type="button" class="btn-help" data-help="backup_restore.backup" aria-label="Hilfe"></button>
                </h5>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                  <div class="text-muted">
                    Backups werden ausserhalb des Webroots gespeichert (empfohlen). Zugriff nur mit gültigem API-Key.
                  </div>
                  <div class="d-flex gap-2">
                    <button id="btnBackupNow" class="btn btn-sm btn-outline-success">
                      <span class="spinner-border spinner-border-sm me-2 d-none" id="spinBackup"></span>
                      <i class="bi bi-cloud-arrow-down me-1"></i> Backup jetzt erstellen
                    </button>
                    <button id="btnReload" class="btn btn-sm btn-outline-secondary">
                      <i class="bi bi-arrow-clockwise me-1"></i> Aktualisieren
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Restore -->
            <div class="col-lg-4">
              <div class="group-creation-card">
                <h5 class="card-title">
                  <i class="bi bi-upload"></i>
                  Restore aus Datei <button type="button" class="btn-help" data-help="backup_restore.restore" aria-label="Hilfe"></button>
                </h5>
                <form id="formRestore" class="d-flex flex-column gap-2" enctype="multipart/form-data"
                  autocomplete="off">
                  <input class="form-control form-control-sm" type="file" id="restoreFile" name="file" accept=".sql,.gz"
                    required>
                  <button class="btn btn-sm btn-outline-danger" id="btnRestore" type="submit">
                    <span class="spinner-border spinner-border-sm me-2 d-none" id="spinRestore"></span>
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Wiederherstellen
                  </button>
                </form>
              </div>
            </div>
          </div>

          <!-- Backups-Liste -->
          <div class="existing-groups-card">
            <h5 class="card-title">
              <i class="bi bi-archive"></i>
              Verfügbare Backups <button type="button" class="btn-help" data-help="backup_restore.liste" aria-label="Hilfe"></button>
            </h5>
            <div class="table-responsive">
              <table class="table table-sm align-middle" id="tblBackups">
                <thead class="table-light">
                  <tr>
                    <th>Datei</th>
                    <th class="text-end">Grösse</th>
                    <th>Erstellt</th>
                    <th class="text-end">Aktionen</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td colspan="4" class="text-center text-muted py-4">Keine Daten geladen…</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Restore Confirmation Modal -->
<div class="modal fade" id="restoreModal" tabindex="-1" aria-labelledby="restoreModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="restoreModalLabel">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>Datenbank wiederherstellen
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Schliessen"></button>
      </div>
      <div class="modal-body">
        <div id="modalConfirmContent">
          <div class="alert alert-danger d-flex align-items-center" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
            <div>
              <strong>Achtung!</strong> Die aktuelle Datenbank wird vollständig überschrieben.
              Dieser Vorgang kann nicht rückgängig gemacht werden!
            </div>
          </div>
          <p class="mb-3">Möchtest du wirklich das Backup <strong id="backupFileName"></strong> wiederherstellen?</p>
          <div class="form-check bg-light p-3 ps-5 rounded mb-0">
            <input class="form-check-input" type="checkbox" id="restoreVorherSichern" checked>
            <label class="form-check-label small" for="restoreVorherSichern">
              Vorher eine Sicherung der aktuellen Datenbank erstellen
              <span class="d-block text-muted">Damit lässt sich der Restore selbst wieder rückgängig machen.</span>
            </label>
          </div>
        </div>
        <div id="modalProgressContent" class="d-none">
          <div class="spinner-container">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Wird wiederhergestellt...</span>
            </div>
            <div class="text-center">
              <h6 class="mb-2">Datenbank wird wiederhergestellt...</h6>
              <p class="text-muted mb-3">Bitte warten, dieser Vorgang kann einige Minuten dauern.</p>
              <div class="progress w-100">
                <div class="progress-bar progress-bar-animated" style="width: 100%"></div>
              </div>
            </div>
          </div>
        </div>
        <div id="modalErrorContent" class="d-none">
          <div class="alert alert-danger" role="alert">
            <h5 class="alert-heading"><i class="bi bi-x-circle-fill me-2"></i>Restore fehlgeschlagen</h5>
            <p id="errorMessage" class="mb-2"></p>
            <hr>
            <details class="small">
              <summary>Technische Details</summary>
              <pre id="errorDetails" class="mt-2 p-2 bg-light rounded" style="max-height: 200px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word;"></pre>
            </details>
          </div>
        </div>
      </div>
      <div class="modal-footer" id="modalFooter">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal" id="btnModalCancel">Abbrechen</button>
        <button type="button" class="btn btn-outline-danger btn-sm" id="btnConfirmRestore">
          <i class="bi bi-arrow-counterclockwise me-1"></i> Ja, wiederherstellen
        </button>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    const API = '../admin/backup_api.php';
    const API_KEY = <?php echo json_encode($BACKUP_API_KEY, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    // Bootstrap Modal Instanz
    let restoreModal;
    let currentRestoreName = '';
    let currentRestoreType = ''; // 'file' oder 'existing'
    let currentRestoreFile = null;

    // Toast: nutze Deine msvToast(), sonst Fallback
    function toast(msg, type = 'info') {
      msvToast(msg, type);
    }

    function fmtSize(bytes) {
      if (bytes == null) return '-';
      const u = ['B', 'KB', 'MB', 'GB', 'TB']; let i = 0, v = Number(bytes);
      while (v >= 1024 && i < u.length - 1) { v /= 1024; i++; }
      const fixed = v < 10 ? 2 : (v < 100 ? 1 : 0);
      return v.toFixed(fixed) + ' ' + u[i];
    }
    
    function fmtDate(ts) {
      try { return new Date(ts * 1000).toLocaleString(); } catch (e) { return '-'; }
    }

    async function apiJSON(url, opts = {}) {
      const u = new URL(url, window.location.href);
      if (!u.searchParams.get('key')) u.searchParams.set('key', API_KEY);

      const res = await fetch(u, opts);
      const ct = (res.headers.get('content-type') || '').toLowerCase();

      // Versuche JSON zu parsen, ansonsten Text
      let payload, isJSON = ct.includes('application/json');
      try {
        payload = isJSON ? await res.json() : await res.text();
      } catch {
        payload = null;
      }

      // Fehlerfall: wirf ein Objekt mit möglichst vielen Details
      if (!res.ok || (isJSON && payload && payload.success === false)) {
        throw {
          status: res.status,
          isJSON,
          payload,
          message: isJSON
            ? (payload && (payload.message || payload.error) || 'Die Aktion hat nicht geklappt (Serverfehler ' + res.status + '). Details stehen im PHP-Log.')
            : (typeof payload === 'string' && payload.trim() && !/<[a-z!]/i.test(payload) && payload.trim().length < 300 ? payload.trim() : 'Die Aktion hat nicht geklappt (Serverfehler ' + res.status + '). Details stehen im PHP-Log.'),
        };
      }

      return payload; // JSON-Objekt oder Text
    }

    async function loadList() {
      const tbody = document.querySelector('#tblBackups tbody');
      tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Lade…</td></tr>';
      try {
        const { files = [] } = await apiJSON(API + '?action=list');
        if (!files.length) {
          tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Keine Backups vorhanden</td></tr>';
          return;
        }
        tbody.innerHTML = files.map(f => {
          const href = `${API}?action=download&name=${encodeURIComponent(f.name)}&key=${encodeURIComponent(API_KEY)}`;
          return `
            <tr>
              <td><i class="bi bi-file-earmark-zip me-1"></i> ${f.name}</td>
              <td class="text-end">${fmtSize(f.size)}</td>
              <td>${fmtDate(f.mtime)}</td>
              <td class="text-end">
                <a class="btn btn-compact-standard btn-outline-info me-1" href="${href}" data-tooltip="Herunterladen">
                  <i class="bi bi-download"></i>
                </a>
                <button class="btn btn-compact-standard btn-outline-warning me-1 btnRestoreExisting" 
                        data-name="${f.name}" data-tooltip="Wiederherstellen">
                  <i class="bi bi-arrow-counterclockwise"></i>
                </button>
                <button class="btn btn-compact-standard btn-outline-danger btnDel" 
                        data-name="${f.name}" data-tooltip="Löschen">
                  <i class="bi bi-trash"></i>
                </button>
              </td>
            </tr>
          `;
        }).join('');
      } catch (e) {
        console.error(e);
        toast((e?.message) || 'Fehler beim Laden der Backups', 'error');
      }
    }

    async function createBackup() {
      const btn = document.getElementById('btnBackupNow');
      const spin = document.getElementById('spinBackup');
      btn.disabled = true; spin.classList.remove('d-none');
      try {
        const data = await apiJSON(API, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ action: 'backup', key: API_KEY })
        });
        toast('Backup erstellt: ' + (data.file || ''), 'success');
        loadList();
      } catch (e) {
        toast((e?.message) || 'Backup fehlgeschlagen', 'error');
      } finally {
        btn.disabled = false; spin.classList.add('d-none');
      }
    }

    async function deleteBackup(name) {
      const confirmResult = await msvConfirmDelete('dieses Backup');
      if (!confirmResult.isConfirmed) return;
      try {
        await apiJSON(API, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ action: 'delete', name, key: API_KEY })
        });
        toast('Backup gelöscht', 'success');
        loadList();
      } catch (e) {
        toast((e?.message) || 'Löschen fehlgeschlagen', 'error');
      }
    }

    // Modal-Funktionen
    function showRestoreModal(name, type, file = null) {
      currentRestoreName = name;
      currentRestoreType = type;
      currentRestoreFile = file;
      
      document.getElementById('backupFileName').textContent = name;
      document.getElementById('modalConfirmContent').classList.remove('d-none');
      document.getElementById('modalProgressContent').classList.add('d-none');
      document.getElementById('modalErrorContent').classList.add('d-none');
      document.getElementById('modalFooter').classList.remove('d-none');
      document.getElementById('btnConfirmRestore').classList.remove('d-none');
      document.getElementById('btnModalCancel').textContent = 'Abbrechen';
      
      restoreModal.show();
    }

    function showProgress() {
      document.getElementById('modalConfirmContent').classList.add('d-none');
      document.getElementById('modalProgressContent').classList.remove('d-none');
      document.getElementById('modalErrorContent').classList.add('d-none');
      document.getElementById('modalFooter').classList.add('d-none');
    }
    
    function showError(message, details = null) {
      document.getElementById('modalConfirmContent').classList.add('d-none');
      document.getElementById('modalProgressContent').classList.add('d-none');
      document.getElementById('modalErrorContent').classList.remove('d-none');
      document.getElementById('modalFooter').classList.remove('d-none');
      document.getElementById('btnConfirmRestore').classList.add('d-none');
      document.getElementById('btnModalCancel').textContent = 'Schliessen';
      
      // Fehlermeldung setzen
      document.getElementById('errorMessage').textContent = message || 'Ein unbekannter Fehler ist aufgetreten.';
      
      // Details anzeigen falls vorhanden
      const detailsElement = document.getElementById('errorDetails');
      if (details) {
        let detailText = '';
        if (typeof details === 'object') {
          // Formatiere das Objekt schön
          if (details.exit !== undefined) detailText += `Exit Code: ${details.exit}\n`;
          if (details.details) detailText += `\nAusgabe:\n${details.details}\n`;
          if (details.payload && typeof details.payload === 'object') {
            detailText += `\nServer Response:\n${JSON.stringify(details.payload, null, 2)}`;
          }
        } else {
          detailText = String(details);
        }
        detailsElement.textContent = detailText || 'Keine weiteren Details verfügbar.';
        detailsElement.parentElement.style.display = 'block';
      } else {
        detailsElement.parentElement.style.display = 'none';
      }
    }

    async function performRestore() {
      const vorherSichern = document.getElementById('restoreVorherSichern').checked ? '1' : '';
      const sicherungHinweis = (data) => data && data.sicherung ? ' – vorher gesichert: ' + data.sicherung : '';
      showProgress();

      try {
        if (currentRestoreType === 'file') {
          // Restore von hochgeladener Datei
          const fd = new FormData();
          fd.append('action', 'restore');
          fd.append('file', currentRestoreFile);
          fd.append('key', API_KEY);
          fd.append('vorher_sichern', vorherSichern);

          const res = await fetch(API, { method: 'POST', body: fd });
          const data = await res.json().catch(() => ({ success: false, message: 'Unerwartete Antwort' }));

          if (!res.ok || data.success === false) throw data;

          toast('Restore erfolgreich ausgeführt' + sicherungHinweis(data), 'success');
          document.getElementById('restoreFile').value = '';
          
        } else if (currentRestoreType === 'existing') {
          // Restore von vorhandenem Backup
          const data = await apiJSON(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'restore_existing', name: currentRestoreName, key: API_KEY, vorher_sichern: vorherSichern })
          });

          toast('Restore erfolgreich aus Backup: ' + (data.file || currentRestoreName) + sicherungHinweis(data), 'success');
        }

        restoreModal.hide();
        if (vorherSichern) loadList();

      } catch (e) {
        console.error('Restore-Fehler:', e);
        
        // Zeige Fehler im Modal an
        let errorMessage = e?.message || 'Restore fehlgeschlagen';
        let errorDetails = null;
        
        if (e?.payload) {
          errorDetails = e.payload;
          // Wenn es spezifische Details gibt, zeige sie
          if (e.payload.details) {
            errorMessage = e.payload.message || errorMessage;
          }
        } else if (typeof e === 'object') {
          errorDetails = e;
        }
        
        showError(errorMessage, errorDetails);
        
        // Toast zusätzlich für kurze Info
        toast(errorMessage, 'error');
      }
    }

    // Events
    document.addEventListener('DOMContentLoaded', function() {
      // Modal initialisieren
      restoreModal = new bootstrap.Modal(document.getElementById('restoreModal'));
      
      // Button Events
      document.getElementById('btnBackupNow').addEventListener('click', createBackup);
      document.getElementById('btnReload').addEventListener('click', loadList);
      
      // Restore from file
      document.getElementById('formRestore').addEventListener('submit', function (ev) {
        ev.preventDefault();
        const f = document.getElementById('restoreFile').files[0];
        if (!f) { 
          toast('Bitte Datei wählen', 'warning'); 
          return; 
        }
        showRestoreModal(f.name, 'file', f);
      });
      
      // Tabellen-Events (Delete und Restore existing)
      document.querySelector('#tblBackups tbody').addEventListener('click', function (ev) {
        const del = ev.target.closest('.btnDel');
        if (del) { 
          deleteBackup(del.dataset.name); 
          return; 
        }

        const rest = ev.target.closest('.btnRestoreExisting');
        if (rest) {
          showRestoreModal(rest.dataset.name, 'existing');
        }
      });
      
      // Modal Confirm Button
      document.getElementById('btnConfirmRestore').addEventListener('click', performRestore);
      
      // Initial laden
      loadList();
    });
  })();
</script>

<?php include 'footer.inc.php'; ?>