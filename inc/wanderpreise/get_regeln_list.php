<?php
// get_regeln_list.php
require_once '../dbconnect.inc.php';

$conn = get_db_connection();
if (!$conn) {
    echo '<div class="alert alert-danger">Datenbankverbindung fehlgeschlagen</div>';
    exit;
}

try {
    $sql = "SELECT * FROM wanderpreise_regeln ORDER BY regel_name";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        echo '<div class="table-responsive">
              <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Beschreibung</th>
                        <th>Status</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>';
        
        while ($row = $result->fetch_assoc()) {
            $status_badge = $row['aktiv'] ? 
                '<span class="badge bg-success">Aktiv</span>' : 
                '<span class="badge bg-secondary">Inaktiv</span>';
            
            echo '<tr class="regel-item">';
            echo '<td><code>' . htmlspecialchars($row['regel_code']) . '</code></td>';
            echo '<td><strong>' . htmlspecialchars($row['regel_name']) . '</strong></td>';
            echo '<td><small>' . htmlspecialchars($row['regel_beschreibung']) . '</small></td>';
            echo '<td>' . $status_badge . '</td>';
            echo '<td>';
            echo '<div class="btn-group btn-group-sm">';
            echo '<button class="btn btn-outline-info view-sql"
                         data-bs-toggle="collapse"
                         data-bs-target="#sql_' . $row['id'] . '">
                    <i class="bi bi-code"></i> SQL
                  </button>';
            echo '<button class="btn btn-outline-primary test-sql"
                         data-sql="' . htmlspecialchars($row['sql_query'], ENT_QUOTES) . '">
                    <i class="bi bi-play"></i> Test
                  </button>';
            echo '<button class="btn btn-outline-warning edit-regel"
                         data-id="' . $row['id'] . '"
                         data-code="' . htmlspecialchars($row['regel_code'], ENT_QUOTES) . '"
                         data-name="' . htmlspecialchars($row['regel_name'], ENT_QUOTES) . '"
                         data-beschreibung="' . htmlspecialchars($row['regel_beschreibung'], ENT_QUOTES) . '"
                         data-sql="' . htmlspecialchars($row['sql_query'], ENT_QUOTES) . '"
                         data-aktiv="' . $row['aktiv'] . '">
                    <i class="bi bi-pencil"></i>
                  </button>';
            echo '<button class="btn btn-outline-danger delete-regel"
                         data-id="' . $row['id'] . '">
                    <i class="bi bi-trash"></i>
                  </button>';
            echo '</div>';
            echo '</td>';
            echo '</tr>';
            
            // SQL-Code Zeile (versteckt)
            echo '<tr>';
            echo '<td colspan="5" class="p-0">';
            echo '<div class="collapse" id="sql_' . $row['id'] . '">';
            echo '<div class="p-3 bg-light">';
            echo '<pre class="mb-0" style="font-size: 0.875rem;">' . htmlspecialchars($row['sql_query']) . '</pre>';
            echo '<div class="test-result mt-2"></div>';
            echo '</div>';
            echo '</div>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table></div>';
        
        // Zähler
        echo '<div class="mt-3 text-muted">
              <i class="bi bi-info-circle me-1"></i>
              ' . $result->num_rows . ' Regeln definiert</div>';
        
    } else {
        echo '<div class="alert alert-info">
              <i class="bi bi-info-circle me-2"></i>
              Noch keine Regeln definiert. Erstellen Sie die erste Regel oben.</div>';
    }
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Fehler: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

$conn->close();
?>