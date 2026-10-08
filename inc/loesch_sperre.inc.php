<?php
/**
 * Löschen nur ohne Geschichte (Entscheid 08.10.2026): Bevor ein Mitglied oder ein JM-Anlass gelöscht wird,
 * zählt msvBezuege(), was in anderen Tabellen darauf verweist. Gefunden werden die Spalten zur Laufzeit über
 * information_schema, damit neue Tabellen (Einsätze, JSK, Galerie …) ohne Pflege mitgezählt werden.
 *
 * Nutzer: inc/mitgliederverwaltung/delete_mitglied.php, inc/jmdefinition/delete_jmdefinition.php.
 * Gegenstück im Browser: msvLoeschenGesperrt() in inc/js/msv-toast.js.
 */

/**
 * @param string[] $spalten  Spaltennamen (klein geschrieben), die auf den Datensatz zeigen, z.B. ['mitglied_id', 'mitgliedid']
 * @param string[] $ohne     Tabellen (klein), die nicht zählen (der Datensatz selbst, Protokolle, Konfiguration)
 * @param array    $namen    Tabelle (klein) oder Präfix mit «*» => lesbare Bezeichnung; mehrere Tabellen dürfen sich eine teilen
 * @return array<string,int> Bezeichnung => Anzahl Zeilen (nur > 0)
 */
function msvBezuege(mysqli $conn, array $spalten, int $id, array $ohne, array $namen): array
{
    $platz = implode(',', array_fill(0, count($spalten), '?'));
    $st = $conn->prepare("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
                          WHERE TABLE_SCHEMA = DATABASE() AND LOWER(COLUMN_NAME) IN ($platz)");
    $st->bind_param(str_repeat('s', count($spalten)), ...$spalten);
    $st->execute();
    $treffer = $st->get_result()->fetch_all(MYSQLI_NUM);
    $st->close();

    $bezuege = [];
    foreach ($treffer as [$tabelle, $spalte]) {
        $klein = strtolower($tabelle);
        if (in_array($klein, $ohne, true)) continue;
        $sql = 'SELECT COUNT(*) FROM `' . str_replace('`', '``', $tabelle) . '` WHERE `' . str_replace('`', '``', $spalte) . '` = ?';
        $q = $conn->prepare($sql);
        if (!$q) continue;
        $q->bind_param('i', $id);
        $q->execute();
        $n = (int)$q->get_result()->fetch_row()[0];
        $q->close();
        if ($n > 0) {
            $name = msvBezugName($klein, $namen);
            $bezuege[$name] = ($bezuege[$name] ?? 0) + $n;
        }
    }
    return $bezuege;
}

function msvBezugName(string $tabelle, array $namen): string
{
    if (isset($namen[$tabelle])) return $namen[$tabelle];
    foreach ($namen as $muster => $name) {
        if (substr($muster, -1) === '*' && strncmp($tabelle, substr($muster, 0, -1), strlen($muster) - 1) === 0) return $name;
    }
    return $tabelle;
}

/** JSON-Antwort und Ende. */
function msvLoeschAntwort(array $daten, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($daten, JSON_UNESCAPED_UNICODE);
    exit;
}
