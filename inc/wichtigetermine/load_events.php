<?php
//load_events.php
include '../config.php';

// Sicherstellen, dass das Jahr als Parameter übergeben wurde
if (!isset($_GET['year']) || !is_numeric($_GET['year'])) {
  die("Ungültige Anfrage.");
}

$year = intval($_GET['year']);

// SQL-Abfrage: Alle Events des angegebenen Jahres laden
$sql = "SELECT * FROM wichtige_termine WHERE year = ? ORDER BY date, time";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $year);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
  // Moderne Card-basierte Darstellung
  echo '<div class="events-list-card">';
  echo '<div class="events-header">';
  echo '<i class="bi bi-calendar3 me-2"></i>';
  echo 'Termine ' . $year;
  echo '<span class="badge bg-secondary ms-2">' . $result->num_rows . '</span>';
  echo '</div>';
  
  // Desktop: Tabelle
  echo '<div class="desktop-table-container">';
  echo '<div class="table-responsive">';
  echo '<table class="table table-hover align-middle mb-0" id="eventsTable">';
  echo '<thead class="table-light">';
  echo '<tr>';
  echo '<th class="text-start" style="width: 40%;"><i class="bi bi-tag me-2"></i>Bezeichnung</th>';
  echo '<th class="text-center" style="width: 20%;"><i class="bi bi-calendar-date me-2"></i>Datum</th>';
  echo '<th class="text-center" style="width: 20%;"><i class="bi bi-clock me-2"></i>Zeit</th>';
  echo '<th class="text-center" style="width: 20%;">Aktionen</th>';
  echo '</tr>';
  echo '</thead>';
  echo '<tbody>';

  $month_names = [
    1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
    5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'
  ];
  
  $current_month = 0;
  
  while ($row = $result->fetch_assoc()) {
    $event_month = date('n', strtotime($row['date']));
    
    // Monats-Separator einfügen
    if ($event_month != $current_month) {
      echo '<tr class="table-secondary">';
      echo '<td colspan="4" class="fw-bold text-muted small py-2">';
      echo '<i class="bi bi-calendar2-month me-2"></i>' . $month_names[$event_month] . ' ' . $year;
      echo '</td>';
      echo '</tr>';
      $current_month = $event_month;
    }
    
    // Wochentag bestimmen
    $weekday = date('l', strtotime($row['date']));
    $weekday_de = [
      'Monday' => 'Mo', 'Tuesday' => 'Di', 'Wednesday' => 'Mi',
      'Thursday' => 'Do', 'Friday' => 'Fr', 'Saturday' => 'Sa', 'Sunday' => 'So'
    ];
    
    echo '<tr>';
    
    // Bezeichnung
    echo '<td class="text-start">';
    echo '<div class="d-flex align-items-center">';
    echo '<div>';
    echo '<div class="fw-semibold">' . htmlspecialchars($row['name']) . '</div>';
    echo '</div>';
    echo '</div>';
    echo '</td>';
    
    // Datum mit Wochentag
    echo '<td class="text-center">';
    echo '<div class="text-nowrap">';
    echo '<span class="badge bg-light text-dark me-1">' . $weekday_de[$weekday] . '</span>';
    echo date("d.m.Y", strtotime($row['date']));
    echo '</div>';
    echo '</td>';
    
    // Zeit
    echo '<td class="text-center">';
    echo '<span class="badge bg-info text-dark">' . htmlspecialchars($row['time']) . '</span>';
    echo '</td>';
    
    // Aktionen
    echo '<td class="text-center">';
    echo '<div class="btn-group btn-group-sm" role="group">';
    echo '<button class="btn btn-outline-primary edit-event" '
        . 'data-id="' . $row['ID'] . '" '
        . 'data-name="' . htmlspecialchars($row['name'], ENT_QUOTES) . '" '
        . 'data-date="' . $row['date'] . '" '
        . 'data-time="' . htmlspecialchars($row['time'], ENT_QUOTES) . '" '
        . 'data-bs-toggle="tooltip" title="Bearbeiten">';
    echo '<i class="bi bi-pencil-square"></i>';
    echo '</button>';
    echo '<button class="btn btn-outline-danger delete-event" '
        . 'data-id="' . $row['ID'] . '" '
        . 'data-bs-toggle="tooltip" title="Löschen">';
    echo '<i class="bi bi-trash"></i>';
    echo '</button>';
    echo '</div>';
    echo '</td>';
    
    echo '</tr>';
  }

  echo '</tbody>';
  echo '</table>';
  echo '</div>'; // Ende table-responsive
  echo '</div>'; // Ende desktop-table-container

  // Mobile: Cards
  echo '<div class="mobile-cards-container" id="mobileEventsCards">';
  echo '<div class="mobile-search">';
  echo '<div class="position-relative">';
  echo '<i class="bi bi-search search-icon"></i>';
  echo '<input type="text" class="form-control" placeholder="Suchen..." oninput="filterMobileEvents(this)">';
  echo '</div>';
  echo '</div>';
  echo '<div class="mobile-cards-scroll">';
  echo '<!-- Cards werden per JavaScript generiert -->';
  echo '</div>';
  echo '</div>';

  echo '</div>'; // Ende events-list-card
  
  // JavaScript für Tooltips
  echo '<script>';
  echo 'var tooltipTriggerList = [].slice.call(document.querySelectorAll(\'[data-bs-toggle="tooltip"]\'));';
  echo 'var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {';
  echo '  return new bootstrap.Tooltip(tooltipTriggerEl);';
  echo '});';
  echo '</script>';
  
} else {
  // Keine Events gefunden
  echo '<div class="events-list-card">';
  echo '<div class="events-header">';
  echo '<i class="bi bi-calendar3 me-2"></i>';
  echo 'Termine ' . $year;
  echo '</div>';
  echo '<div class="p-5 text-center">';
  echo '<i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i>';
  echo '<p class="text-muted mt-3 mb-0">Keine Termine für ' . $year . ' gefunden.</p>';
  echo '<p class="text-muted small">Keine Termine eingetragen.</p>';
  echo '</div>';
  echo '</div>';
}

$stmt->close();
$conn->close();
?>
