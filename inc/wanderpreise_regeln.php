<?php
// wanderpreise_regeln.php - Wanderpreise Automatische Zuordnungsregeln
include 'dbconnect.inc.php';

// CSRF Token generieren
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Seitenspezifische Styles definieren - EXAKT wie bei backup_restore.php
$page_specific_css = "
/* Wanderpreise Regeln spezifische Styles */
.main-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    padding: 2rem;
    margin-bottom: 2rem;
}

.regel-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    padding: 1.5rem;
    margin-bottom: 1.25rem;
}

.regel-card:first-child { border-left: 4px solid var(--success-color); }
.regel-card:nth-child(2) { border-left: 4px solid var(--info-color); }
.regel-card:last-child { border-left: 4px solid var(--primary-color); }

.card-title {
    color: var(--secondary-color);
    font-weight: 600;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: .5rem;
}

.sql-editor {
    font-family: 'Courier New', monospace;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 0.5rem;
    min-height: 150px;
}

.placeholder-badge {
    background: #e3f2fd;
    color: #1976d2;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.85rem;
    font-family: monospace;
    margin-right: 0.5rem;
}

/* Animation */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

.regel-card {
    animation: fadeIn .3s ease-out;
}
";

// Header einbinden - WICHTIG: Das definiert content-background!
include 'header.inc.php';
?>

<div class="container-fluid">
  <div class="row">
    <div class="col-xl-8 col-lg-11 col-12 ps-0">
      <!-- Außen-Container -->
      <div class="main-content-wrapper">
        <!-- Header-Zeile -->
        <div class="row mb-4">
          <div class="col-md-12">
            <h2 class="h4 mb-0" style="color: var(--secondary-color);">
              <i class="bi bi-gear me-2"></i> Wanderpreise Automatische Zuordnungsregeln
            </h2>
            <p class="text-muted mb-0">SQL-Regeln für automatische Gewinnerzuordnung definieren</p>
          </div>
        </div>

        <!-- Weißer Hintergrund-Container -->
        <div class="content-background">
          <!-- Info Alert -->
          <div class="alert alert-info mb-4">
            <i class="bi bi-info-circle me-2"></i>
            <strong>SQL-Regeln für automatische Gewinnerzuordnung</strong><br>
            Definieren Sie SQL-Abfragen, die automatisch den Gewinner für einen Wanderpreis ermitteln.
          </div>

          <!-- Neue Regel hinzufügen -->
          <div class="regel-card">
            <h5 class="card-title">
              <i class="bi bi-plus-circle"></i>
              Neue Regel erstellen
            </h5>
            
            <form id="addRegelForm">
              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
              
              <div class="row mb-3">
                <div class="col-md-4">
                  <label class="form-label">Regel-Code (eindeutig)</label>
                  <input type="text" name="regel_code" class="form-control" required 
                         placeholder="z.B. jahresmeister_300m">
                </div>
                <div class="col-md-8">
                  <label class="form-label">Regel-Name</label>
                  <input type="text" name="regel_name" class="form-control" required 
                         placeholder="z.B. Jahresmeister 300m">
                </div>
              </div>
              
              <div class="mb-3">
                <label class="form-label">Beschreibung</label>
                <textarea name="regel_beschreibung" class="form-control" rows="2"
                          placeholder="Beschreiben Sie, was diese Regel macht..."></textarea>
              </div>
              
              <div class="mb-3">
                <label class="form-label">
                  SQL-Query 
                  <small class="text-muted">
                    (muss <code>gewinner_id</code> zurückgeben, optional: <code>resultat</code>, <code>rang</code>)
                  </small>
                </label>
                <div class="mb-2">
                  <strong>Verfügbare Platzhalter:</strong>
                  <span class="placeholder-badge">{jahr}</span>
                  <span class="placeholder-badge">{wanderpreis_id}</span>
                </div>
                <textarea name="sql_query" class="form-control sql-editor" required
                          placeholder="SELECT mitglied_id AS gewinner_id, punkte AS resultat FROM ..."></textarea>
              </div>
              
              <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="aktiv" id="regelAktiv" checked>
                <label class="form-check-label" for="regelAktiv">
                  Regel ist aktiv
                </label>
              </div>
              
              <button type="submit" class="btn btn-success">
                <i class="bi bi-save me-2"></i>Regel speichern
              </button>
            </form>
          </div>

          <!-- Beispiel-Regeln -->
          <div class="regel-card">
            <h5 class="card-title">
              <i class="bi bi-lightbulb"></i>
              Beispiel-Regeln
            </h5>
            
            <div class="accordion" id="beispielAccordion">
              <!-- Jahresmeister -->
              <div class="accordion-item">
                <h2 class="accordion-header">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                          data-bs-target="#beispiel1">
                    Jahresmeister (Höchste Gesamtpunktzahl)
                  </button>
                </h2>
                <div id="beispiel1" class="accordion-collapse collapse" data-bs-parent="#beispielAccordion">
                  <div class="accordion-body">
                    <pre class="sql-editor">SELECT
    m.ID AS gewinner_id,
    'Test-Resultat' AS resultat,
    '1. Rang' AS rang
FROM mitglieder m
WHERE m.Status = 1
ORDER BY m.ID DESC
LIMIT 1</pre>
                  </div>
                </div>
              </div>
              
              <!-- Bester Endstich -->
              <div class="accordion-item">
                <h2 class="accordion-header">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                          data-bs-target="#beispiel2">
                    Bester Endstich
                  </button>
                </h2>
                <div id="beispiel2" class="accordion-collapse collapse" data-bs-parent="#beispielAccordion">
                  <div class="accordion-body">
                    <pre class="sql-editor">SELECT 
    e.MitgliedID AS gewinner_id,
    CONCAT(e.Total, ' Punkte') AS resultat,
    '1. Rang Endstich' AS rang
FROM endresultate e
WHERE e.Jahr = {jahr}
    AND e.Total = (
        SELECT MAX(Total) 
        FROM endresultate 
        WHERE Jahr = {jahr}
    )
LIMIT 1</pre>
                  </div>
                </div>
              </div>
              
              <!-- Bester Gruppenstich -->
              <div class="accordion-item">
                <h2 class="accordion-header">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                          data-bs-target="#beispiel3">
                    Bester Gruppenstich (spezifische Kategorie)
                  </button>
                </h2>
                <div id="beispiel3" class="accordion-collapse collapse" data-bs-parent="#beispielAccordion">
                  <div class="accordion-body">
                    <pre class="sql-editor">SELECT 
    g.MitgliedID AS gewinner_id,
    CONCAT(g.Resultat, ' Punkte') AS resultat,
    CONCAT('1. Rang ', g.Kategorie) AS rang
FROM gruppenstiche g
INNER JOIN mitglieder m ON g.MitgliedID = m.ID
WHERE g.Jahr = {jahr}
    AND g.Kategorie = 'Gewehr 300m'
ORDER BY g.Resultat DESC
LIMIT 1</pre>
                  </div>
                </div>
              </div>
              
              <!-- Meiste Teilnahmen -->
              <div class="accordion-item">
                <h2 class="accordion-header">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                          data-bs-target="#beispiel4">
                    Fleißigster Schütze (meiste Teilnahmen)
                  </button>
                </h2>
                <div id="beispiel4" class="accordion-collapse collapse" data-bs-parent="#beispielAccordion">
                  <div class="accordion-body">
                    <pre class="sql-editor">SELECT 
    MitgliedID AS gewinner_id,
    CONCAT(COUNT(*), ' Teilnahmen') AS resultat,
    'Fleißpreis' AS rang
FROM (
    SELECT MitgliedID FROM gruppenstiche WHERE Jahr = {jahr}
    UNION ALL
    SELECT MitgliedID FROM endresultate WHERE Jahr = {jahr}
    UNION ALL
    SELECT MitgliedID FROM feldschiessen WHERE Jahr = {jahr}
) AS teilnahmen
GROUP BY MitgliedID
ORDER BY COUNT(*) DESC
LIMIT 1</pre>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Bestehende Regeln -->
          <div class="regel-card">
            <h5 class="card-title">
              <i class="bi bi-list"></i>
              Bestehende Regeln
            </h5>
            
            <div id="regelListContainer">
              <div class="text-center p-3">
                <div class="spinner-border spinner-border-sm"></div> Lade Regeln...
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Toast Container -->
<div id="toast-container" style="position: fixed; top: 70px; right: 20px; z-index: 9999;"></div>

<script>
$(document).ready(function() {
    // Toast-Funktion
    function showToast(message, type = 'info') {
        const colors = {
            'success': '#28a745',
            'error': '#dc3545',
            'warning': '#ffc107',
            'info': '#6c757d'
        };
        
        const toast = $('<div>')
            .css({
                'background-color': colors[type],
                'color': 'white',
                'padding': '12px 20px',
                'margin-bottom': '10px',
                'border-radius': '6px',
                'box-shadow': '0 4px 12px rgba(0,0,0,0.15)',
                'opacity': '0',
                'transform': 'translateX(100%)',
                'transition': 'all 0.3s'
            })
            .html(message);
        
        $('#toast-container').append(toast);
        
        setTimeout(() => {
            toast.css({'opacity': '1', 'transform': 'translateX(0)'});
        }, 100);
        
        setTimeout(() => {
            toast.css({'opacity': '0', 'transform': 'translateX(100%)'});
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }
    
    // Regeln laden
    function loadRegeln() {
        $.get('wanderpreise/get_regeln_list.php', function(response) {
            $('#regelListContainer').html(response);
        }).fail(function() {
            $('#regelListContainer').html('<div class="alert alert-danger">Fehler beim Laden der Regeln</div>');
        });
    }
    
    // Neue Regel speichern
    $('#addRegelForm').on('submit', function(e) {
        e.preventDefault();
        
        // Formulardaten sammeln
        const formData = $(this).serialize();
        const $btn = $(this).find('button[type="submit"]');
        const originalText = $btn.html();
        
        // Prüfen ob wir im Bearbeitungsmodus sind
        const editId = $(this).data('edit-id');
        let postData = formData;
        if (editId) {
            postData += '&id=' + encodeURIComponent(editId);
        }
        
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');
        
        $.post('wanderpreise/save_regel.php', postData, function(response) {
            if (response.success) {
                showToast(response.message, 'success');
                $('#addRegelForm')[0].reset();
                $('#addRegelForm').removeData('edit-id');
                $('#addRegelForm button[type="submit"]').html('<i class="bi bi-save me-2"></i>Regel speichern');
                loadRegeln();
            } else {
                showToast('Fehler: ' + (response.message || 'Unbekannter Fehler'), 'error');
            }
        }, 'json').fail(function() {
            showToast('Fehler beim Speichern der Regel', 'error');
        }).always(function() {
            $btn.prop('disabled', false).html(originalText);
        });
    });
    
    // Regel löschen
    $(document).on('click', '.delete-regel', function() {
        if (!confirm('Möchten Sie diese Regel wirklich löschen?')) return;
        
        const regelId = $(this).data('id');
        const $btn = $(this);
        
        $.post('wanderpreise/delete_regel.php', {
            id: regelId,
            csrf_token: $('input[name="csrf_token"]').val()
        }, function(response) {
            if (response.success) {
                showToast('Regel gelöscht', 'success');
                loadRegeln();
            } else {
                showToast('Fehler beim Löschen', 'error');
            }
        }, 'json');
    });
    
    // Regel bearbeiten
    $(document).on('click', '.edit-regel', function() {
        const $btn = $(this);
        const id = $btn.data('id');
        const code = $btn.data('code');
        const name = $btn.data('name');
        const beschreibung = $btn.data('beschreibung');
        const sql = $btn.data('sql');
        const aktiv = $btn.data('aktiv');
        
        // Formular mit Daten füllen
        $('input[name="regel_code"]').val(code);
        $('input[name="regel_name"]').val(name);
        $('textarea[name="regel_beschreibung"]').val(beschreibung);
        $('textarea[name="sql_query"]').val(sql);
        $('input[name="aktiv"]').prop('checked', aktiv == 1);
        
        // Formular-Modus auf "Bearbeiten" setzen
        $('#addRegelForm').data('edit-id', id);
        $('#addRegelForm button[type="submit"]').html('<i class="bi bi-save me-2"></i>Regel aktualisieren');
        
        // Zum Formular scrollen
        $('html, body').animate({
            scrollTop: $('#addRegelForm').offset().top - 100
        }, 500);
    });
    
    // SQL testen
    $(document).on('click', '.test-sql', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const sql = $btn.data('sql');
        const $row = $btn.closest('tr');
        const $nextRow = $row.next('tr');
        const $resultDiv = $nextRow.find('.test-result');
        
        // Öffne den Collapse-Bereich falls geschlossen
        const collapseId = $row.find('.view-sql').attr('data-bs-target');
        const $collapse = $(collapseId);
        if (!$collapse.hasClass('show')) {
            $collapse.collapse('show');
        }
        
        // Zeige Lade-Indikator
        $resultDiv.html('<div class="text-center"><div class="spinner-border spinner-border-sm me-2"></div>Teste SQL...</div>');
        
        // AJAX Request
        $.ajax({
            url: 'wanderpreise/test_regel_sql.php',
            method: 'POST',
            data: {
                sql: sql,
                jahr: new Date().getFullYear(),
                csrf_token: $('input[name="csrf_token"]').val()
            },
            dataType: 'json',
            success: function(response) {
                console.log('Test Response:', response);
                if (response.success) {
                    $resultDiv.html('<div class="alert alert-success mb-0"><i class="bi bi-check-circle me-2"></i>' + response.message + '</div>');
                } else {
                    $resultDiv.html('<div class="alert alert-danger mb-0"><i class="bi bi-x-circle me-2"></i>' + response.message + '</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Test Error:', error);
                console.log('Response:', xhr.responseText);
                $resultDiv.html('<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-circle me-2"></i>Fehler beim Testen: ' + error + '</div>');
            }
        });
    });
    
    // Initial laden
    loadRegeln();
});
</script>

<?php include 'footer.inc.php'; ?>