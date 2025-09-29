<?php
// get_gewinner_history.php - Historie der Gewinner für einen Wanderpreis anzeigen
require_once 'wanderpreise_config.php';
require_once '../dbconnect.inc.php';

// GET-Parameter einlesen
$wanderpreis_id = isset($_GET['wanderpreis_id']) ? intval($_GET['wanderpreis_id']) : 0;

if ($wanderpreis_id <= 0) {
    echo '<p class="text-muted">Kein Wanderpreis ausgewählt.</p>';
    exit;
}

try {
    // Wanderpreis-Info laden
    $wanderpreis_sql = "SELECT
                            bezeichnung,
                            min_anzahl_gewinne
                        FROM wanderpreise
                        WHERE id = ?";
    $wanderpreis_stmt = $conn->prepare($wanderpreis_sql);
    $wanderpreis_stmt->bind_param("i", $wanderpreis_id);
    $wanderpreis_stmt->execute();
    $wanderpreis_result = $wanderpreis_stmt->get_result();
    
    if ($wanderpreis_result->num_rows === 0) {
        echo '<p class="text-muted">Wanderpreis nicht gefunden.</p>';
        exit;
    }
    
    $wanderpreis = $wanderpreis_result->fetch_assoc();
    
    // Gewinner-Historie laden
    $gewinner_sql = "SELECT 
                        wg.id,
                        wg.jahr,
                        wg.rang,
                        wg.resultat,
                        wg.bemerkung,
                        wg.ist_definitiv,
                        wg.anzahl_gewinne,
                        wg.created_at,
                        CONCAT(m.Name, ' ', m.Vorname) as gewinner_name,
                        m.ID as mitglied_id,
                        w.Kategorie as waffenkategorie
                    FROM wanderpreise_gewinner wg
                    INNER JOIN mitglieder m ON wg.gewinner_id = m.ID
                    LEFT JOIN Waffen w ON w.ID = m.WaffenID
                    WHERE wg.wanderpreis_id = ?
                    ORDER BY wg.jahr DESC";
    
    $gewinner_stmt = $conn->prepare($gewinner_sql);
    $gewinner_stmt->bind_param("i", $wanderpreis_id);
    $gewinner_stmt->execute();
    $gewinner_result = $gewinner_stmt->get_result();
    
    echo '<div class="mb-3">';
    echo '<h6 class="mb-2"><i class="bi bi-award me-1"></i>' . htmlspecialchars($wanderpreis['bezeichnung']) . '</h6>';
    echo '<small class="text-muted">';
    echo 'Kategorie: ' . htmlspecialchars($wanderpreis['kategorie']) . ' | ';
    echo 'Min. für definitiven Besitz: ' . $wanderpreis['min_anzahl_gewinne'] . ' Gewinne';
    echo '</small>';
    echo '</div>';
    
    if ($gewinner_result->num_rows > 0) {
        echo '<div class="table-responsive" style="max-height: 300px; overflow-y: auto;">';
        echo '<table class="table table-sm table-hover">';
        echo '<thead class="table-light">';
        echo '<tr>';
        echo '<th>Jahr</th>';
        echo '<th>Gewinner</th>';
        echo '<th>Rang/Resultat</th>';
        echo '<th>Gewinne</th>';
        echo '<th>Status</th>';
        echo '<th>Aktionen</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        // Gewinner nach Mitglied gruppieren für Anzahl Gewinne
        $gewinner_statistik = [];
        $gewinner_result->data_seek(0); // Reset pointer
        while ($row = $gewinner_result->fetch_assoc()) {
            $mitglied_id = $row['mitglied_id'];
            if (!isset($gewinner_statistik[$mitglied_id])) {
                $gewinner_statistik[$mitglied_id] = [
                    'name' => $row['gewinner_name'],
                    'anzahl' => 0
                ];
            }
            $gewinner_statistik[$mitglied_id]['anzahl']++;
        }
        
        // Zurück zum Anfang für die Ausgabe
        $gewinner_result->data_seek(0);
        
        while ($row = $gewinner_result->fetch_assoc()) {
            $mitglied_gewinne = $gewinner_statistik[$row['mitglied_id']]['anzahl'];
            $ist_definitiv = $mitglied_gewinne >= $wanderpreis['min_anzahl_gewinne'];
            
            echo '<tr>';
            echo '<td><strong>' . $row['jahr'] . '</strong></td>';
            echo '<td>';
            echo htmlspecialchars($row['gewinner_name']);
            if (!empty($row['waffenkategorie'])) {
                echo '<br><small class="text-muted">' . htmlspecialchars($row['waffenkategorie']) . '</small>';
            }
            echo '</td>';
            echo '<td>';
            if (!empty($row['rang'])) {
                echo htmlspecialchars($row['rang']);
            }
            if (!empty($row['resultat'])) {
                echo (!empty($row['rang']) ? '<br>' : '') . '<small class="text-muted">' . htmlspecialchars($row['resultat']) . '</small>';
            }
            if (empty($row['rang']) && empty($row['resultat'])) {
                echo '<span class="text-muted">-</span>';
            }
            echo '</td>';
            echo '<td>';
            echo '<span class="badge bg-info">' . $mitglied_gewinne . 'x</span>';
            echo '</td>';
            echo '<td>';
            if ($ist_definitiv) {
                echo '<span class="badge bg-success">Definitiv</span>';
            } else {
                $noch_benötigt = $wanderpreis['min_anzahl_gewinne'] - $mitglied_gewinne;
                echo '<span class="badge bg-secondary">Noch ' . $noch_benötigt . 'x</span>';
            }
            echo '</td>';
            echo '<td>';
            echo '<div class="btn-group btn-group-sm" role="group">';
            echo '<button type="button" class="btn btn-outline-warning btn-sm edit-gewinner" 
                         data-id="' . $row['id'] . '" 
                         data-jahr="' . $row['jahr'] . '"
                         data-gewinner="' . $row['mitglied_id'] . '"
                         data-rang="' . htmlspecialchars($row['rang']) . '"
                         data-resultat="' . htmlspecialchars($row['resultat']) . '"
                         data-bemerkung="' . htmlspecialchars($row['bemerkung']) . '"
                         title="Bearbeiten">
                     <i class="bi bi-pencil"></i>
                  </button>';
            echo '<button type="button" class="btn btn-outline-danger btn-sm delete-gewinner" 
                         data-id="' . $row['id'] . '" 
                         data-jahr="' . $row['jahr'] . '"
                         data-gewinner="' . htmlspecialchars($row['gewinner_name']) . '"
                         title="Löschen">
                     <i class="bi bi-trash"></i>
                  </button>';
            echo '</div>';
            echo '</td>';
            echo '</tr>';
            
            // Bemerkung als separate Zeile falls vorhanden
            if (!empty($row['bemerkung'])) {
                echo '<tr class="table-active">';
                echo '<td colspan="6">';
                echo '<small><i class="bi bi-chat-text me-1"></i>';
                echo htmlspecialchars($row['bemerkung']);
                echo '</small>';
                echo '</td>';
                echo '</tr>';
            }
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
        
        // Statistik anzeigen
        echo '<div class="mt-3 p-2 bg-light rounded">';
        echo '<h6 class="mb-2">Gewinner-Statistik:</h6>';
        echo '<div class="row">';
        
        foreach ($gewinner_statistik as $mitglied_id => $stats) {
            $ist_definitiv = $stats['anzahl'] >= $wanderpreis['min_anzahl_gewinne'];
            echo '<div class="col-md-6 col-lg-4 mb-2">';
            echo '<div class="d-flex justify-content-between align-items-center">';
            echo '<span>' . htmlspecialchars($stats['name']) . '</span>';
            echo '<span>';
            echo '<span class="badge bg-info">' . $stats['anzahl'] . 'x</span>';
            if ($ist_definitiv) {
                echo ' <span class="badge bg-success">Definitiv</span>';
            }
            echo '</span>';
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
        
    } else {
        echo '<div class="text-center p-4">';
        echo '<i class="bi bi-info-circle text-muted" style="font-size: 2rem;"></i>';
        echo '<p class="text-muted mt-2">Noch keine Gewinner für diesen Wanderpreis eingetragen.</p>';
        echo '</div>';
    }
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">';
    echo '<i class="bi bi-exclamation-triangle me-2"></i>';
    echo 'Fehler beim Laden der Gewinner-Historie: ' . htmlspecialchars($e->getMessage());
    echo '</div>';
    wanderpreise_debug('Error in get_gewinner_history', ['error' => $e->getMessage()]);
}
?>