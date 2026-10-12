<?php
// load_endsch.php

// Fehler nur ins Log, nie in die Antwort
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// GET-Parameter einlesen
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$kat = isset($_GET['kat']) ? $_GET['kat'] : 'A'; // Standard: A

include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('html'); // Zugriff nur Admin-Bereich (admin/vorstand)
if ($conn->connect_error) {
    http_response_code(500);
    exit(msvFehler('Die Rangliste konnte nicht geladen werden, die Datenbank ist nicht erreichbar. Bitte später die Seite neu laden.', $conn->connect_error));
}

/**
 * Liefert das Ergebnisobjekt für Endschiessen basierend auf Kategorie und Jahr.
 *
 * @param string $kat       Kategorie (z. B. 'A' oder 'B')
 * @param int    $selectedYear  Jahr
 * @return mysqli_result
 */
function getResults($kat, $selectedYear) {
    global $conn;

    $katValue = 'Kat. ' . $kat;

    // Hier folgt die komplexe SQL-Abfrage.
    // Auswahlliste für Mitglieder und Gäste gleich; die hat*-Flags zählen die Zeilen der Stich-Tabellen
    $auswahl = "
        z.Ansage,
        (
            CASE WHEN z.ZSchuss1 >= 91 THEN 10 WHEN z.ZSchuss1 >= 81 THEN 9 WHEN z.ZSchuss1 >= 71 THEN 8 WHEN z.ZSchuss1 >= 61 THEN 7 WHEN z.ZSchuss1 >= 51 THEN 6 WHEN z.ZSchuss1 >= 41 THEN 5 WHEN z.ZSchuss1 >= 31 THEN 4 WHEN z.ZSchuss1 >= 21 THEN 3 WHEN z.ZSchuss1 >= 11 THEN 2 WHEN z.ZSchuss1 >= 1 THEN 1 ELSE 0 END +
            CASE WHEN z.ZSchuss2 >= 91 THEN 10 WHEN z.ZSchuss2 >= 81 THEN 9 WHEN z.ZSchuss2 >= 71 THEN 8 WHEN z.ZSchuss2 >= 61 THEN 7 WHEN z.ZSchuss2 >= 51 THEN 6 WHEN z.ZSchuss2 >= 41 THEN 5 WHEN z.ZSchuss2 >= 31 THEN 4 WHEN z.ZSchuss2 >= 21 THEN 3 WHEN z.ZSchuss2 >= 11 THEN 2 WHEN z.ZSchuss2 >= 1 THEN 1 ELSE 0 END +
            CASE WHEN z.ZSchuss3 >= 91 THEN 10 WHEN z.ZSchuss3 >= 81 THEN 9 WHEN z.ZSchuss3 >= 71 THEN 8 WHEN z.ZSchuss3 >= 61 THEN 7 WHEN z.ZSchuss3 >= 51 THEN 6 WHEN z.ZSchuss3 >= 41 THEN 5 WHEN z.ZSchuss3 >= 31 THEN 4 WHEN z.ZSchuss3 >= 21 THEN 3 WHEN z.ZSchuss3 >= 11 THEN 2 WHEN z.ZSchuss3 >= 1 THEN 1 ELSE 0 END +
            CASE WHEN z.ZSchuss4 >= 91 THEN 10 WHEN z.ZSchuss4 >= 81 THEN 9 WHEN z.ZSchuss4 >= 71 THEN 8 WHEN z.ZSchuss4 >= 61 THEN 7 WHEN z.ZSchuss4 >= 51 THEN 6 WHEN z.ZSchuss4 >= 41 THEN 5 WHEN z.ZSchuss4 >= 31 THEN 4 WHEN z.ZSchuss4 >= 21 THEN 3 WHEN z.ZSchuss4 >= 11 THEN 2 WHEN z.ZSchuss4 >= 1 THEN 1 ELSE 0 END +
            CASE WHEN z.ZSchuss5 >= 91 THEN 10 WHEN z.ZSchuss5 >= 81 THEN 9 WHEN z.ZSchuss5 >= 71 THEN 8 WHEN z.ZSchuss5 >= 61 THEN 7 WHEN z.ZSchuss5 >= 51 THEN 6 WHEN z.ZSchuss5 >= 41 THEN 5 WHEN z.ZSchuss5 >= 31 THEN 4 WHEN z.ZSchuss5 >= 21 THEN 3 WHEN z.ZSchuss5 >= 11 THEN 2 WHEN z.ZSchuss5 >= 1 THEN 1 ELSE 0 END +
            CASE WHEN z.ZSchuss6 >= 91 THEN 10 WHEN z.ZSchuss6 >= 81 THEN 9 WHEN z.ZSchuss6 >= 71 THEN 8 WHEN z.ZSchuss6 >= 61 THEN 7 WHEN z.ZSchuss6 >= 51 THEN 6 WHEN z.ZSchuss6 >= 41 THEN 5 WHEN z.ZSchuss6 >= 31 THEN 4 WHEN z.ZSchuss6 >= 21 THEN 3 WHEN z.ZSchuss6 >= 11 THEN 2 WHEN z.ZSchuss6 >= 1 THEN 1 ELSE 0 END
        ) AS ZabigTotal,
        COALESCE(ROUND(GREATEST(g.GSchuss1, g.GSchuss2, g.GSchuss3)/10,1), 0) AS GlueckTotal,
        COALESCE(SUM(e.Schuss1 + e.Schuss2 + e.Schuss3 + e.Schuss4 + e.Schuss5 + e.Schuss6 + e.Schuss7 + e.Schuss8 + e.Schuss9 + e.Schuss10), 0) AS EndstichTotal,
        COALESCE(SUM(z.ZSchuss1 + z.ZSchuss2 + z.ZSchuss3 + z.ZSchuss4 + z.ZSchuss5 + z.ZSchuss6), 0) AS ZabigTotalDiff,
        COALESCE(SUM(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6), 0) AS Schwini_Summe1,
        COALESCE(SUM(s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6), 0) AS Schwini_Summe2,
        COALESCE(ROUND(SUM(k.KSchuss1 + k.KSchuss2 + k.KSchuss3 + k.KSchuss4 + k.KSchuss5)/10,1), 0) AS KunstTotal,
        GREATEST(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6,
                 s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6) AS MaxSchwini,
        LEAST(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6,
              s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6) AS MinSchwini,
        (
            COALESCE(SUM(e.Schuss1 + e.Schuss2 + e.Schuss3 + e.Schuss4 + e.Schuss5 + e.Schuss6 + e.Schuss7 + e.Schuss8 + e.Schuss9 + e.Schuss10), 0)
            + COALESCE(ROUND(GREATEST(g.GSchuss1, g.GSchuss2, g.GSchuss3)/10,1), 0)
            +
            (
                CASE WHEN z.ZSchuss1 >= 91 THEN 10 WHEN z.ZSchuss1 >= 81 THEN 9 WHEN z.ZSchuss1 >= 71 THEN 8 WHEN z.ZSchuss1 >= 61 THEN 7 WHEN z.ZSchuss1 >= 51 THEN 6 WHEN z.ZSchuss1 >= 41 THEN 5 WHEN z.ZSchuss1 >= 31 THEN 4 WHEN z.ZSchuss1 >= 21 THEN 3 WHEN z.ZSchuss1 >= 11 THEN 2 WHEN z.ZSchuss1 >= 1 THEN 1 ELSE 0 END +
                CASE WHEN z.ZSchuss2 >= 91 THEN 10 WHEN z.ZSchuss2 >= 81 THEN 9 WHEN z.ZSchuss2 >= 71 THEN 8 WHEN z.ZSchuss2 >= 61 THEN 7 WHEN z.ZSchuss2 >= 51 THEN 6 WHEN z.ZSchuss2 >= 41 THEN 5 WHEN z.ZSchuss2 >= 31 THEN 4 WHEN z.ZSchuss2 >= 21 THEN 3 WHEN z.ZSchuss2 >= 11 THEN 2 WHEN z.ZSchuss2 >= 1 THEN 1 ELSE 0 END +
                CASE WHEN z.ZSchuss3 >= 91 THEN 10 WHEN z.ZSchuss3 >= 81 THEN 9 WHEN z.ZSchuss3 >= 71 THEN 8 WHEN z.ZSchuss3 >= 61 THEN 7 WHEN z.ZSchuss3 >= 51 THEN 6 WHEN z.ZSchuss3 >= 41 THEN 5 WHEN z.ZSchuss3 >= 31 THEN 4 WHEN z.ZSchuss3 >= 21 THEN 3 WHEN z.ZSchuss3 >= 11 THEN 2 WHEN z.ZSchuss3 >= 1 THEN 1 ELSE 0 END +
                CASE WHEN z.ZSchuss4 >= 91 THEN 10 WHEN z.ZSchuss4 >= 81 THEN 9 WHEN z.ZSchuss4 >= 71 THEN 8 WHEN z.ZSchuss4 >= 61 THEN 7 WHEN z.ZSchuss4 >= 51 THEN 6 WHEN z.ZSchuss4 >= 41 THEN 5 WHEN z.ZSchuss4 >= 31 THEN 4 WHEN z.ZSchuss4 >= 21 THEN 3 WHEN z.ZSchuss4 >= 11 THEN 2 WHEN z.ZSchuss4 >= 1 THEN 1 ELSE 0 END +
                CASE WHEN z.ZSchuss5 >= 91 THEN 10 WHEN z.ZSchuss5 >= 81 THEN 9 WHEN z.ZSchuss5 >= 71 THEN 8 WHEN z.ZSchuss5 >= 61 THEN 7 WHEN z.ZSchuss5 >= 51 THEN 6 WHEN z.ZSchuss5 >= 41 THEN 5 WHEN z.ZSchuss5 >= 31 THEN 4 WHEN z.ZSchuss5 >= 21 THEN 3 WHEN z.ZSchuss5 >= 11 THEN 2 WHEN z.ZSchuss5 >= 1 THEN 1 ELSE 0 END +
                CASE WHEN z.ZSchuss6 >= 91 THEN 10 WHEN z.ZSchuss6 >= 81 THEN 9 WHEN z.ZSchuss6 >= 71 THEN 8 WHEN z.ZSchuss6 >= 61 THEN 7 WHEN z.ZSchuss6 >= 51 THEN 6 WHEN z.ZSchuss6 >= 41 THEN 5 WHEN z.ZSchuss6 >= 31 THEN 4 WHEN z.ZSchuss6 >= 21 THEN 3 WHEN z.ZSchuss6 >= 11 THEN 2 WHEN z.ZSchuss6 >= 1 THEN 1 ELSE 0 END
            )
            + COALESCE(ROUND(SUM(k.KSchuss1 + k.KSchuss2 + k.KSchuss3 + k.KSchuss4 + k.KSchuss5)/10,1), 0)
            + COALESCE(GREATEST(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6,
                      s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6), 0)
        ) AS GesamtTotal,
        -- Welche Stiche sind erfasst? Fehlende zählen im Total als 0 (wie im Absendenbuch),
        -- in der Tabelle steht ein Strich statt einer Null.
        MAX(s.ID) IS NOT NULL AS hatSchwini,
        MAX(k.ID) IS NOT NULL AS hatKunst,
        MAX(g.ID) IS NOT NULL AS hatGlueck,
        MAX(z.ID) IS NOT NULL AS hatZabig";

    $sql = "(SELECT
        CONVERT(m.Name USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Name,
        CONVERT(m.Vorname USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Vorname,
        m.Geburtsdatum,
        $auswahl
    FROM mitglieder m
    LEFT JOIN endstich e ON m.ID = e.MitgliedID AND e.Jahr = ?
    LEFT JOIN schwini s ON m.ID = s.MitgliedID AND s.Jahr = ?
    LEFT JOIN kunst k ON m.ID = k.MitgliedID AND k.Jahr = ?
    LEFT JOIN glueck g ON m.ID = g.MitgliedID AND g.Jahr = ?
    LEFT JOIN zabig z ON m.ID = z.MitgliedID AND z.Jahr = ?
    LEFT JOIN Waffen w ON w.ID = m.WaffenID
    WHERE w.Kategorie = ?
      AND e.Jahr = ?
    GROUP BY m.ID, m.Vorname, m.Name)";
    $types = "iiiiisi";
    $params = [$selectedYear, $selectedYear, $selectedYear, $selectedYear, $selectedYear, $katValue, $selectedYear];

    // Gäste (ohne Jungschützen) nur in Kat. B und nur, wenn sie alle fünf Stiche geschossen haben (Migration 094)
    if ($kat === 'B') {
        $sql .= "
    UNION ALL
    (SELECT
        CONVERT(ga.name USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Name,
        CONVERT('' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Vorname,
        DATE('2100-01-01') AS Geburtsdatum,
        $auswahl
    FROM endstich_gaeste ga
    INNER JOIN endstich e ON e.GastID = ga.id AND e.Jahr = ga.jahr AND e.Schuss1 != 0
    INNER JOIN schwini s ON s.GastID = ga.id AND s.Jahr = ga.jahr
    INNER JOIN kunst k ON k.GastID = ga.id AND k.Jahr = ga.jahr AND (k.KSchuss1 + k.KSchuss2 + k.KSchuss3 + k.KSchuss4 + k.KSchuss5) > 0
    INNER JOIN glueck g ON g.GastID = ga.id AND g.Jahr = ga.jahr AND GREATEST(g.GSchuss1, g.GSchuss2, g.GSchuss3) > 0
    INNER JOIN zabig z ON z.GastID = ga.id AND z.Jahr = ga.jahr AND (z.ZSchuss1 + z.ZSchuss2 + z.ZSchuss3 + z.ZSchuss4 + z.ZSchuss5 + z.ZSchuss6) > 0
    WHERE ga.jahr = ? AND ga.geburtsdatum IS NULL
      AND GREATEST(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6,
                   s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6) > 0
    GROUP BY ga.id, ga.name)";
        $types .= "i";
        $params[] = $selectedYear;
    }
    $sql .= "
    ORDER BY GesamtTotal DESC, EndstichTotal DESC, Geburtsdatum ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result;
}

$result = getResults($kat, $selectedYear);

$i = 1;
$fehlt = '<span class="es-fehlt" data-tooltip="Noch nicht erfasst">–</span>';
if ($result && $result->num_rows > 0) {
    foreach ($result as $row) {
        // Die Abfrage liefert nur Mitglieder mit Endstich-Eintrag im Jahr (WHERE e.Jahr = ?)
        $rankClass = $i <= 3 ? ' class="rank-' . $i . '"' : '';
        echo '<tr' . $rankClass . '>';
        echo '<td>' . $i . ".</td>";
        echo '<td>' . htmlspecialchars($row["Name"] . " " . $row["Vorname"]) . '</td>';
        echo '<td>' . $row["EndstichTotal"] . '</td>';
        echo '<td>' . ($row["hatSchwini"] ? $row["MaxSchwini"] . ' (' . $row["MinSchwini"] . ')' : $fehlt) . '</td>';
        echo '<td>' . ($row["hatKunst"] ? $row["KunstTotal"] : $fehlt) . '</td>';
        echo '<td>' . ($row["hatGlueck"] ? $row["GlueckTotal"] : $fehlt) . '</td>';
        echo '<td>' . ($row["hatZabig"] ? $row["ZabigTotal"] : $fehlt) . '</td>';
        echo '<td>' . ($row["hatZabig"] && $row["Ansage"] !== null ? ($row["Ansage"] - $row["ZabigTotalDiff"]) : $fehlt) . '</td>';
        echo '<td>' . $row["GesamtTotal"] . '</td>';
        echo '</tr>';
        $i++;
    }
} else {
    require_once __DIR__ . '/../partials/empty_state.inc.php';
    echo msv_empty_row(9, 'Keine Resultate in Kat. ' . ($kat === 'B' ? 'B' : 'A') . ' für ' . $selectedYear);
}

$conn->close();
exit();
?>
