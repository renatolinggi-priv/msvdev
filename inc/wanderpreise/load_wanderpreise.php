<?php
// load_wanderpreise.php
require_once 'wanderpreise_config.php';
require_once '../dbconnect.inc.php';

try {
    // SQL Query aufbauen
    $sql = "SELECT
                w.id,
                w.bezeichnung,
                w.beschreibung,
                w.beschaffung_datum,
                w.min_anzahl_gewinne,
                w.hersteller,
                w.created_at,
                COUNT(wg.id) as anzahl_gewinner,
                MAX(wg.jahr) as letztes_jahr,
                COALESCE(aktueller_gewinner.gewinner_name, 'Nicht vergeben') as aktueller_gewinner,
                COALESCE(aktueller_gewinner.jahr, 0) as aktueller_gewinner_jahr
            FROM wanderpreise w
            LEFT JOIN wanderpreise_gewinner wg ON w.id = wg.wanderpreis_id
            LEFT JOIN (
                SELECT
                    wg2.wanderpreis_id,
                    CONCAT(m.Name, ' ', m.Vorname) as gewinner_name,
                    wg2.jahr
                FROM wanderpreise_gewinner wg2
                INNER JOIN mitglieder m ON wg2.gewinner_id = m.ID
                INNER JOIN (
                    SELECT wanderpreis_id, MAX(jahr) as max_jahr
                    FROM wanderpreise_gewinner
                    GROUP BY wanderpreis_id
                ) latest ON wg2.wanderpreis_id = latest.wanderpreis_id
                         AND wg2.jahr = latest.max_jahr
            ) aktueller_gewinner ON w.id = aktueller_gewinner.wanderpreis_id
            GROUP BY w.id
            ORDER BY w.bezeichnung";
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        echo '<table class="table table-hover">
                <thead>
                    <tr>
                        <th>Wanderpreis</th>
                        <th>Hersteller</th>
                        <th>Aktueller Gewinner</th>
                        <th>Gewinner-Historie</th>
                        <th>Min. Gewinne</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>';
        
        while ($row = $result->fetch_assoc()) {
            $anschaffung_jahr = $row['beschaffung_datum']; // Jetzt nur Jahr gespeichert
            $gewinner_info = $row['aktueller_gewinner'];
            if ($row['aktueller_gewinner_jahr'] > 0) {
                $gewinner_info .= ' (' . $row['aktueller_gewinner_jahr'] . ')';
            }
            
            echo '<tr class="wanderpreis-row" ' .
     'data-wanderpreis-id="' . (int)$row['id'] . '" ' .
     'data-bezeichnung="' . htmlspecialchars($row['bezeichnung'], ENT_QUOTES, 'UTF-8') . '" ' .
     'style="cursor:pointer;">';

            echo '<td><strong>' . htmlspecialchars($row['bezeichnung']) . '</strong>';
            if (!empty($row['beschreibung'])) {
                echo '<br><small class="text-muted">' . htmlspecialchars(substr($row['beschreibung'], 0, 60)) .
                     (strlen($row['beschreibung']) > 60 ? '...' : '') . '</small>';
            }
            echo '<br><small class="text-info">Anschaffung: ' . $anschaffung_jahr . '</small>';
            echo '</td>';
            
            // Hersteller-Spalte
            echo '<td>';
            if (!empty($row['hersteller'])) {
                echo '<strong>' . htmlspecialchars($row['hersteller']) . '</strong>';
            } else {
                echo '<span class="text-muted">Nicht angegeben</span>';
            }
            echo '</td>';
            echo '<td>' . htmlspecialchars($gewinner_info) . '</td>';
            echo '<td>';
            if ($row['anzahl_gewinner'] > 0) {
                echo '<i class="bi bi-trophy me-1"></i>' . $row['anzahl_gewinner'] . ' Gewinner';
                if ($row['letztes_jahr']) {
                    echo '<br><small class="text-muted">Zuletzt: ' . $row['letztes_jahr'] . '</small>';
                }
            } else {
                echo '<span class="text-muted">Noch keine Gewinner</span>';
            }
            echo '</td>';
            echo '<td><span class="badge bg-info">' . $row['min_anzahl_gewinne'] . 'x</span></td>';
            echo '<td>';
            echo '<div class="btn-group" role="group">';
            echo '<button type="button" class="btn btn-outline-primary btn-icon edit-wanderpreis" 
                         data-id="' . $row['id'] . '" title="Bearbeiten">
                     <i class="bi bi-pencil"></i>
                  </button>';
            echo '<button type="button" class="btn btn-outline-info btn-icon view-gewinner" 
                         data-id="' . $row['id'] . '" title="Gewinner anzeigen">
                     <i class="bi bi-eye"></i>
                  </button>';
            echo '<button type="button" class="btn btn-outline-danger btn-icon delete-wanderpreis" 
                         data-id="' . $row['id'] . '" title="Löschen">
                     <i class="bi bi-trash"></i>
                  </button>';
            echo '</div>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        
        // Zusammenfassung
        $result->data_seek(0); // Reset result pointer
        $total_count = $result->num_rows;
        
        echo '<div class="mt-3 p-3 bg-light rounded">';
        echo '<div class="row text-center">';
        echo '<div class="col-md-12">';
        echo '<strong>' . $total_count . '</strong> Wanderpreise erfasst';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
    } else {
        echo '<div class="p-4 text-center text-muted">';
        echo '<i class="bi bi-info-circle me-2"></i>';
        echo 'Keine Wanderpreise gefunden.';
        echo '</div>';
    }
    
} catch (Exception $e) {
    echo '<div class="p-4 text-center text-danger">';
    echo '<i class="bi bi-exclamation-triangle me-2"></i>';
    echo 'Fehler beim Laden der Wanderpreise: ' . htmlspecialchars($e->getMessage());
    echo '</div>';
}
?>