<?php
/**
 * Zahlen aus Erfassungsformularen prüfen, bevor gespeichert wird: nie still kappen oder auf 0 setzen,
 * sondern ablehnen und sagen, was nicht stimmt. Gegenstück im Browser: msvPruefeZahl() in inc/js/msv-toast.js.
 *
 * $regeln: Feldname => [min, max, Nachkommastellen, 'Name für Meldungen']
 * Leere Felder zählen wie bisher als 0 und werden nicht beanstandet; Komma gilt als Dezimalpunkt.
 */

/** Liste lesbarer Fehler, z.B. «Endstich Schuss 3: 77 – erlaubt 0 bis 10». Leer = alles gültig. */
function msvPruefeZahlen(array $daten, array $regeln): array
{
    $fehler = [];
    foreach ($regeln as $feld => [$min, $max, $stellen, $name]) {
        if (!array_key_exists($feld, $daten)) continue;
        $roh = trim(str_replace(',', '.', (string)$daten[$feld]));
        if ($roh === '') continue;
        if (!is_numeric($roh)) {
            $fehler[] = $name . ': «' . mb_substr($roh, 0, 12) . '» ist keine Zahl';
            continue;
        }
        $z = (float)$roh;
        if ($z < $min || $z > $max) {
            $fehler[] = $name . ': ' . $roh . ' – erlaubt ' . $min . ' bis ' . $max;
        } elseif (abs(round($z, $stellen) - $z) > 1e-9) {
            $fehler[] = $name . ': ' . $roh . ' – ' . ($stellen === 0 ? 'nur ganze Zahlen' : 'höchstens ' . $stellen . ' Nachkommastelle');
        }
    }
    return $fehler;
}

/** Bei Fehlern mit 422 und lesbarer Meldung abbrechen (JSON), sonst nichts tun. */
function msvPruefeZahlenOderAbbruch(array $daten, array $regeln): void
{
    $fehler = msvPruefeZahlen($daten, $regeln);
    if (!$fehler) return;
    http_response_code(422);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Ungültige Eingabe – ' . implode('; ', array_slice($fehler, 0, 5)) . (count($fehler) > 5 ? ' (und ' . (count($fehler) - 5) . ' weitere)' : ''),
        'fehler'  => $fehler,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Regeln für eine Reihe gleichartiger Felder: Präfix + Nummern von..bis. */
function msvZahlRegeln(string $praefix, int $von, int $bis, float $min, float $max, int $stellen, string $name): array
{
    $r = [];
    for ($i = $von; $i <= $bis; $i++) $r[$praefix . $i] = [$min, $max, $stellen, $name . ' ' . $i];
    return $r;
}
