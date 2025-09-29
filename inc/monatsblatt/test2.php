
2. **Zusätzlich** sortierst du (wie bisher) diese Datumsangaben nach Timestamp, um am Ende die Tage in korrekter Reihenfolge zu bekommen.  
- Dazu kannst du ein simples Array `$all_timestamps` verwenden, bei dem du pro gefundenem Datum (z.B. „2025-08-22“) den Unix-Timestamp als Key hinterlegst, wie bisher.

3. **JMSchiesstage-Einträge** bleiben in einem gesonderten Array, wobei du pro `[Datum => [Event => Liste von Zeitblöcken]]` speichern kannst. So kannst du später einfach prüfen, ob an einem bestimmten Tag für ein bestimmtes Event Zeitblöcke existieren.

4. **In der Ausgabe** gehst du dann sortiert nach den Timestamps vor; für jeden Tag fragst du, welche Events laut `$parsed_dates[$tag]` anstehen. Für jedes dieser Events fragst du wiederum `$events_by_date[$tag][$eventName]` ab.  
- Wenn Zeitblöcke da sind, listest du sie auf.  
- Wenn keine Zeitblöcke da sind, gibst du die gewünschte Zeile mit „Keine Zeit [Eventname]“ aus.

---

## Beispiel-Code

Unten ein auskommentiertes Beispielskript (Anpassung deines bisherigen Scripts), das genau diese Logik implementiert. Lies dir insbesondere die Schritte 1–4 genau durch. 

```php
<?php
// ============================================================================
// export_monatsblatt_html_detail.php
// ============================================================================
// Was wird gemacht?
//  1) Alle Events aus JMDefinition laden (für bestimmtes $year).
//  2) In jedem Event-Feld "Schiesstage" Datumsangaben parsen -> Für JEDEN gefundenen Tag
//     vermerken wir: "An Tag X findet Event ID#... (Bezeichnung) statt".
//  3) Alle Einträge in JMSchiesstage laden -> pro Tag und Event (ID) speichern.
//  4) Sortierte Ausgabe über alle gefundenen Tage, und innerhalb eines Tages über
//     alle zugehörigen Events.
//
// ============================================================================

require_once '../config.php';

// ----- Einstellungen / Konstanten -----
$year = 2025;

$month_translation = [
 "Januar" => "January", "Februar" => "February", "März" => "March",
 "April"  => "April",   "Mai"     => "May",      "Juni"  => "June",
 "Juli"   => "July",    "August"  => "August",   "September" => "September",
 "Oktober"=> "October", "November"=> "November", "Dezember"  => "December"
];

// Regulärer Ausdruck zum Erfassen von Datumsangaben
$regex = "/\b([0-9]{1,2})\.\s?(Januar|Februar|März|April|Mai|Juni|Juli|August|September|Oktober|November|Dezember)\s?([0-9]{4})?\b/";

// ============================================================================
// 1) JMDefinition lesen und Datumsangaben pro Event ID speichern
// ============================================================================
$sql = "SELECT ID, Bezeichnung, Schiesstage 
     FROM JMDefinition
     WHERE year = ? 
       AND Schiesstage IS NOT NULL
       AND Schiesstage != '' 
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $year);
$stmt->execute();
$result = $stmt->get_result();

$parsed_dates  = [];  // speichert, welche Events an welchem Tag stattfinden
                   // z.B. $parsed_dates['2025-08-22'] = [ [id=>82, 'bezeichnung'=>'XYZ'], ... ]

$all_timestamps = []; // Speicherung des Unix-Timestamps pro Datum, für spätere Sortierung
                   // z.B. $all_timestamps['2025-08-22'] = 123456789

while ($row = $result->fetch_assoc()) {
 $eventID   = $row['ID'];
 $eventName = $row['Bezeichnung'];
 $schiesstageText = $row['Schiesstage'];

 // Alle Datumsangaben aus dem Text holen
 if (preg_match_all($regex, $schiesstageText, $matches, PREG_SET_ORDER)) {
     foreach ($matches as $m) {
         $day        = $m[1];
         $month_de   = $m[2];
         $year_found = isset($m[3]) && !empty($m[3]) ? $m[3] : $year;

         // Datum in englischer Form für strtotime
         $date_str_de = "$day. $month_de $year_found";
         $date_str_en = str_replace(array_keys($month_translation), array_values($month_translation), $date_str_de);
         $ts          = strtotime($date_str_en);

         if ($ts === false) {
             // Falls mal was schiefgeht
             echo "<p>Fehler: strtotime() für '$date_str_de' schlug fehl</p>";
             continue;
         }

         // Y-m-d als Schlüssel
         $ymd = date('Y-m-d', $ts);

         // Noch nicht angelegt? Dann init
         if (!isset($parsed_dates[$ymd])) {
             $parsed_dates[$ymd] = [];
         }

         // Event eintragen
         $parsed_dates[$ymd][] = [
             'id'          => $eventID,
             'bezeichnung' => $eventName
         ];

         // Timestamp speichern, um am Ende die Tage sortiert auszugeben
         // (falls schon vorhanden, überschreiben wir hier nicht -> egal)
         if (!isset($all_timestamps[$ymd])) {
             $all_timestamps[$ymd] = $ts;
         }
     }
 }
}
$stmt->close();

// ============================================================================
// 2) JMSchiesstage lesen: pro Tag + EventID Zeitblöcke ablegen
// ============================================================================
$sql2 = "
 SELECT s.jm_id, s.schiesstag, s.start_time, s.end_time
 FROM JMSchiesstage s
 WHERE s.year = ?
 ORDER BY s.schiesstag, s.start_time
";
$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("i", $year);
$stmt2->execute();
$result2 = $stmt2->get_result();

// Struktur: $events_by_date_and_id['2025-08-22'][82] = [ [start=>'..', end=>'..'], ... ]
$events_by_date_and_id = [];

while ($row2 = $result2->fetch_assoc()) {
 $ymd       = $row2['schiesstag'];  // z.B. '2025-08-22'
 $eventID   = $row2['jm_id'];
 $startTime = $row2['start_time'];
 $endTime   = $row2['end_time'];

 if (!isset($events_by_date_and_id[$ymd])) {
     $events_by_date_and_id[$ymd] = [];
 }
 if (!isset($events_by_date_and_id[$ymd][$eventID])) {
     $events_by_date_and_id[$ymd][$eventID] = [];
 }
 $events_by_date_and_id[$ymd][$eventID][] = [
     'start' => $startTime,
     'end'   => $endTime
 ];
}
$stmt2->close();

// ============================================================================
// 3) Sortierte Ausgabe aller gefundenen Tage
//    - Für jeden Tag: alle Events (aus $parsed_dates) durchgehen
//    - Aus $events_by_date_and_id die Zeitblöcke holen
// ============================================================================
if (empty($all_timestamps)) {
 echo "<p>Keine Datumsangaben gefunden.</p>";
 $conn->close();
 exit;
}

// sortiere $all_timestamps nach Wert (Timestamp)
asort($all_timestamps); // => wir haben z.B. '2025-03-14', '2025-03-15' ...

echo "<h2>Detailübersicht zu Schiesstagen $year</h2>";

foreach ($all_timestamps as $ymd => $ts) {
 // z.B. $ymd = '2025-08-22'
 // ermitteln wir das deutsche Original-Datum, um es hübsch auszugeben
 $datum_de = date('d. F Y', $ts); 
 // Wir müssen hier "F" (Monatsname englisch) wieder ins Deutsche übersetzen, 
 // oder wir nutzen eine alternative Formatierung und ersetzen. 
 // Für ein schnelles Beispiel:
 // F = englischer Monatsname, den man hier nochmal per str_replace anpassen könnte.
 // Oder besser, wir bauen uns den Datumsstring manuell:
 //   date('d', $ts) . '. ' . <DeutscherMonat> . ' ' . date('Y', $ts)
 // Dafür bräuchten wir noch ein mapping english->german. 
 // Zur Einfachheit machen wir’s jetzt so:
 $englishMonth = date('F', $ts); // z.B. "August"
 $monthsEnToDe = [
     'January'=>'Januar','February'=>'Februar','March'=>'März','April'=>'April','May'=>'Mai','June'=>'Juni',
     'July'=>'Juli','August'=>'August','September'=>'September','October'=>'Oktober','November'=>'November','December'=>'Dezember'
 ];
 $monat_de = $monthsEnToDe[$englishMonth] ?? $englishMonth;
 $tag_de   = date('d', $ts);
 $jahr_de  = date('Y', $ts);
 $datum_de = "$tag_de. $monat_de $jahr_de";

 echo "<hr>";
 echo "<h3>Schiesstag ($datum_de)</h3>";

 // Gibt es in $parsed_dates[$ymd] Einträge?
 if (!isset($parsed_dates[$ymd]) || empty($parsed_dates[$ymd])) {
     // Wurde gar kein Event in JMDefinition vermerkt -> dann "keine Zeit"
     // (Theoretisch könnte das passieren, wenn Schiesstage-Text kein Datum enthielt)
     echo "<p>Keine Events für diesen Tag in JMDefinition.</p>";
     continue;
 }

 // In $parsed_dates[$ymd] stehen alle Events, die im Text vermerkt sind.
 // => Für jedes dieser Events prüfen, ob es Zeitblöcke in JMSchiesstage hat:
 foreach ($parsed_dates[$ymd] as $eventInfo) {
     $eid   = $eventInfo['id'];
     $ename = $eventInfo['bezeichnung'];

     // Sind Zeitblöcke da?
     if (
         isset($events_by_date_and_id[$ymd]) &&
         isset($events_by_date_and_id[$ymd][$eid]) &&
         !empty($events_by_date_and_id[$ymd][$eid])
     ) {
         // Wir haben mind. einen Zeitblock -> ausgeben
         $timeBlocks = $events_by_date_and_id[$ymd][$eid];
         $isFirst    = true;
         foreach ($timeBlocks as $tb) {
             $start_hm = substr($tb['start'], 0, 5);
             $end_hm   = substr($tb['end'],   0, 5);

             if ($isFirst) {
                 echo "<p>$start_hm - $end_hm $ename</p>";
                 $isFirst = false;
             } else {
                 echo "<p>$start_hm - $end_hm (ist auch $ename, muss aber nicht nochmals angezeigt werden)</p>";
             }
         }
     } else {
         // Keine Zeitblöcke in JMSchiesstage => "Keine Zeit ..."
         echo "<p>Keine Zeit $ename</p>";
     }
 }
}

$conn->close();
?>
