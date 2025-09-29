<?php
include 'dbconnect.inc.php'; // Stellen Sie sicher, dass dies die richtige Datei für Ihre Datenbankverbindung ist
include 'header.inc.php'; // Falls Sie eine Header-Datei haben

// Prüfen, ob eine Erfolgsmeldung angezeigt werden soll
if (isset($_GET['success'])) {
    echo '<div class="alert alert-success">Daten erfolgreich gespeichert.</div>';
}
?>

<div class="container container-left">
    <h2>Resultateingabe für Jungschützen</h2>
    
    <!-- Speichern und Löschen Buttons außerhalb des Formulars -->
    <button type="button" class="btn btn-outline-primary" id="saveButton">Speichern</button>
    <button type="button" class="btn btn-outline-info" id="exportButton">Export</button>
    <button type="button" id="delete" class="btn btn-outline-danger">Löschen</button>
    
    <form id="jungschuetzenForm" method="post" action="jsresultate/save_jungschuetzen_resultate.php">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Jungschütze</th>
                        <?php
                        // Ermitteln der Spalten aus der Datenbanktabelle 'jungschuetzen_resultate'
                        $columns = [];
                        $resultColumns = $conn->query("SHOW COLUMNS FROM jungschuetzen_resultate");

                        if ($resultColumns) {
                            while ($column = $resultColumns->fetch_assoc()) {
                                $field = $column['Field'];
                                
                                // Überspringen von unerwünschten Spalten (z.B. ID, Timestamps)
                                $excludedColumns = ['ID', 'JungschuetzeID', 'created_at', 'updated_at', 'Anerkennungskarte1', 'Anerkennungskarte']; // Passen Sie diese Liste nach Bedarf an
                                if (in_array($field, $excludedColumns)) {
                                    continue;
                                }

                                $columns[] = $field;

                                // Umwandlung des Spaltennamens in einen lesbaren Titel
                                $label = ucwords(str_replace('_', ' ', $field));

                                // Umwandlung von 'Belehrungsschiessen1' zu '1. Belehrungsschiessen'
                                $label = preg_replace('/^(.*?)(\d+)$/', '$2. $1', $label);

                                echo "<th class='vertical-header'><div>$label</div></th>";
                            }
                        } else {
                            echo "<th colspan='100%'>Fehler beim Abrufen der Spalteninformationen.</th>";
                        }
                        ?>
                    </tr>
                </thead>
                <tbody id="resultateTableBody">
                    <!-- Die Tabelleninhalte werden hier per AJAX geladen -->
                </tbody>
            </table>
        </div>
    </form>
</div>

<!-- Modal zur Bestätigung für das Löschen aller Daten -->
<div class="modal fade" id="confirmModal" tabindex="-1" role="dialog" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmModalLabel">Bestätigung</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Schließen">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                Sind Sie sicher, dass Sie diese Aktion durchführen möchten?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-outline-danger" id="confirmAction">Bestätigen</button>
            </div>
        </div>
    </div>
</div>

<style>
/* CSS für vertikale Tabellenüberschriften */
.vertical-header span {
    display: block;
    width: 150px; /* Passen Sie die Höhe der Zelle an */
    padding: 0;
    margin: 0;
    text-align: center;
    transform: rotate(-90deg);
    transform-origin: bottom left; /* Ändern Sie den Ursprung für bessere Ausrichtung */
    white-space: nowrap; /* Verhindert Zeilenumbrüche */
    font-size: 12px;
}

.container-left {
    margin-left: 0 !important;
    margin-right: auto !important;
}

.form-control {
    width: 80px; /* Breite nach Bedarf anpassen */
    padding: 0;
    margin: 0 auto;
}

.input-breit {
    width: 32px; /* Passen Sie die Breite nach Bedarf an */
    padding: 0;
    margin: 0 auto;
    height: 30px;
    font-size: 12px; /* Optional: Größere Schrift für bessere Lesbarkeit */
    box-sizing: border-box;
    text-align: center; /* Text zentrieren */
}

/* Fokus-Effekt außerhalb der Medienabfrage */
.form-control.input-breit:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
}

@media (max-width: 768px) {
    .input-breit {
        width: 32px; /* Passen Sie die Breite nach Bedarf an */
        padding: 0;
        margin: 0 auto;
        height: 30px;
        font-size: 12px; /* Optional: Größere Schrift für bessere Lesbarkeit */
        box-sizing: border-box;
        text-align: center;
    }
}
</style>

<script>
$(document).ready(function() {
    // Funktion zum Laden der Jungschützen-Resultate
    function loadJMResultate() {
        $.ajax({
            url: 'jsresultate/load_jungschuetzen_resultate_form.php',
            type: 'GET',
            success: function(response) {
                $('#resultateTableBody').html(response);
            },
            error: function(xhr, status, error) {
                console.error('Fehler beim Laden der Daten:', error);
                showMessage('Fehler beim Laden der Daten.', 'danger');
            }
        });
    }

    // Initiales Laden der Daten beim Seitenaufbau
    loadJMResultate();

    // Event-Listener für den "Speichern" Button
    $('#saveButton').on('click', function(e) {
        e.preventDefault(); // Verhindert das Standard-Formular-Absenden
        $('#jungschuetzenForm').submit(); // Löst das Formular-Submit aus
    });

    // Event-Listener für das Formular-Submit via AJAX
    $('#jungschuetzenForm').on('submit', function(e) {
        e.preventDefault(); // Verhindert das Standard-Formular-Absenden

        $.ajax({
            url: 'jsresultate/save_jungschuetzen_resultate.php',
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                // Erfolgsmeldung anzeigen
                showMessage(response, 'success');

                // Tabelle neu laden
                loadJMResultate();
            },
            error: function(xhr, status, error) {
                // Fehlermeldung anzeigen
                showMessage('Fehler beim Speichern der Daten.', 'danger');
                console.error('Fehler:', error);
            }
        });
    });

    // Event-Listener für den "Löschen" Button
    $('#delete').on('click', function(e) {
        e.preventDefault(); // Verhindert, dass der Button das Formular absendet
        deleteType = 'all'; // Setze den Typ auf 'all'
        $('#confirmModal').modal('show');
    });

    // Event-Listener für den Bestätigungs-Button im Modal
    $('#confirmAction').on('click', function() {
        if (deleteType === 'all') {
            // AJAX-Aufruf für das Löschen aller aktuellen Resultate
            $.ajax({
                url: 'jsresultate/delete_jungschuetzen_resultate.php',
                method: 'POST',
                success: function(response) {
                    console.log('Alle aktuellen Resultate erfolgreich gelöscht');
                    showMessage(response, 'success');

                    // Tabelle neu laden
                    loadJMResultate();
                },
                error: function(xhr, status, error) {
                    console.error('Fehler beim Löschen der aktuellen Resultate:', error);
                    showMessage('Fehler beim Löschen der aktuellen Resultate.', 'danger');
                }
            });
        }

        // Modal schließen
        $('#confirmModal').modal('hide');
    });

    // Event-Listener für den "Export" Button
    $('#exportButton').on('click', function(e) {
        e.preventDefault(); // Verhindert Standardverhalten
        window.location.href = 'jsresultate/export_jungschuetzenresultate.php'; // Leitet zur Export-Datei weiter
    });

    // Funktion zur Anzeige von Nachrichten
    function showMessage(message, type) {
        var messageDiv = $('<div class="alert alert-' + type + '"></div>').text(message);
        $('.container-left').prepend(messageDiv);

        // Erfolgsmeldung nach einigen Sekunden ausblenden
        setTimeout(function() {
            messageDiv.fadeOut('slow', function() {
                $(this).remove();
            });
        }, 5000);
    }
});
</script>

<?php
include 'footer.inc.php'; // Falls Sie eine Footer-Datei haben
?>
