<?php
// inc/rangliste_import/fsa_teilnehmerliste_parser.php
// Parst die FSA-Teilnehmerliste "nach Verein" (bundesuebung.ch / Indoor Swiss Shooting AG)
// mit Obligatorisch- (OP) und Feldschiessen-Resultaten (FS) pro Teilnehmer.
//
// Layout: pro Verein ein Abschnitt (Titel "... - Obligatorisch + Feldschiessen" + Waffe
// G300/P25 ...), darunter Zeilen "Nr Name Jg Kat [JSK] [OP] [OP W1] [OP W2] [FS]".
// Die Spaltenzuordnung ist NUR ueber die X-Koordinaten moeglich (eine Zeile mit einer
// einzigen Zahl kann OP ODER FS sein). Die Spalten-Anker werden pro Seite aus den
// rotierten Kopfzeilen-Fragmenten ("Resultat"+"OP", "OP W1", "OP W2", "Resultat FS")
// bestimmt. Kleine hochgestellte Zahlen (Anzahl Nachschiessen, z.B. "51 2") liegen
// ausserhalb der Spalten-Toleranz und werden dadurch ignoriert.
//
// Die Punkte-Symbole (Karte / Verblieben / AK / KA) sind Vektorgrafik, kein Text —
// sie sind NICHT extrahierbar. "Absolviert" heisst darum: ein Resultat ist vorhanden.
//
// ALLE Seiten/Vereins-Abschnitte werden gelesen (ein eigener Schuetze kann bei einem
// anderen Verein bzw. mit einer anderen Waffe geschossen haben); das Mitglieder-
// Matching und die Zusammenfuehrung pro Mitglied macht der Aufrufer (import_api.php).

if (!class_exists('Smalot\\PdfParser\\Parser')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

/**
 * Parst eine FSA-Teilnehmerliste aus einer PDF-Datei.
 *
 * @param string $filepath Pfad zur PDF-Datei
 * @param bool   $debug    Debug-Infos zurueckgeben
 * @return array|null null, wenn das PDF keine FSA-Teilnehmerliste ist, sonst
 *                    ['success' => bool, 'rows' => [...], 'message' => string]
 *                    mit rows-Eintraegen:
 *                    ['nr','name','jg','kategorie','jsk','op','op_w1','op_w2','fs','verein','waffe']
 */
function parseFsaTeilnehmerliste($filepath, $debug = false) {
    if (!file_exists($filepath) || !class_exists('Smalot\\PdfParser\\Parser')) {
        return null;
    }

    try {
        $parser = new Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($filepath);
    } catch (Throwable $e) {
        return null;
    }

    // ---- Erkennung: ist das ueberhaupt eine FSA-Teilnehmerliste? ----
    $isFsa = false;
    foreach (array_slice($pdf->getPages(), 0, 2) as $page) {
        try {
            $text = $page->getText();
        } catch (Throwable $e) {
            continue;
        }
        if (mb_stripos($text, 'Teilnehmerliste nach Verein') !== false
            || mb_stripos($text, 'bundesuebung.ch') !== false) {
            $isFsa = true;
            break;
        }
    }
    if (!$isFsa) {
        return null;
    }

    $rows = [];
    $debugLines = [];
    $verein = '';
    $waffe = '';
    $anchors = null; // Spalten-Anker der zuletzt gesehenen Kopfzeile

    foreach ($pdf->getPages() as $page) {
        try {
            $dataTm = $page->getDataTm();
        } catch (Throwable $e) {
            continue;
        }
        if (empty($dataTm)) {
            continue;
        }

        // Fragmente einsammeln, rotierte (Spalten-Titel) getrennt
        $frags = [];
        $rotFrags = [];
        foreach ($dataTm as $item) {
            if (count($item) < 2 || !is_array($item[0]) || count($item[0]) < 6) {
                continue;
            }
            // FSA-PDFs verwenden geschuetzte Leerzeichen (NBSP) -> normalisieren,
            // sonst schlagen Namens-Matching und Kopfzeilen-Erkennung fehl
            $text = preg_replace('/[\x{00A0}\x{202F}\x{2007}]/u', ' ', $item[1]);
            $text = trim(preg_replace('/\s+/u', ' ', $text));
            if ($text === '') {
                continue;
            }
            $f = ['x' => (float) $item[0][4], 'y' => (float) $item[0][5], 't' => $text];
            if (abs((float) $item[0][1]) > 0.001) {
                $rotFrags[] = $f; // um 90 Grad gedrehte Kopfzeilen-Beschriftung
            } else {
                $frags[] = $f;
            }
        }

        // Spalten-Anker aus den rotierten Kopfzeilen bestimmen (Seite kalibriert sich selbst)
        $pageAnchors = fsaColumnAnchors($rotFrags);
        if ($pageAnchors !== null) {
            $anchors = $pageAnchors;
        }
        if ($anchors === null || empty($frags)) {
            continue; // ohne Anker keine Spaltenzuordnung moeglich
        }

        // Zeilen bilden: Y absteigend (oben -> unten), Y-Toleranz 4 Punkte
        usort($frags, function ($a, $b) {
            if (abs($a['y'] - $b['y']) < 3) {
                return $a['x'] <=> $b['x'];
            }
            return $b['y'] <=> $a['y'];
        });
        $lines = [];
        $current = [];
        $currentY = null;
        foreach ($frags as $f) {
            if ($currentY === null || abs($f['y'] - $currentY) > 4) {
                if (!empty($current)) {
                    $lines[] = $current;
                }
                $current = [$f];
                $currentY = $f['y'];
            } else {
                $current[] = $f;
            }
        }
        if (!empty($current)) {
            $lines[] = $current;
        }

        // Zeilen interpretieren
        $pendingName = null; // erste Zeile eines umbrochenen Namens
        foreach ($lines as $line) {
            usort($line, fn($a, $b) => $a['x'] <=> $b['x']);
            $joined = implode(' ', array_column($line, 't'));

            // Abschnitts-Titel: "<Verein> (Nr) - Obligatorisch + Feldschiessen [Waffe]"
            if (mb_stripos($joined, 'Obligatorisch + Feldschiessen') !== false) {
                $verein = fsaExtractVereinName($joined);
                if (preg_match('/\b([A-Z]{1,2}\d{2,3})\s*$/', $joined, $m)) {
                    $waffe = $m[1];
                }
                $pendingName = null;
                continue;
            }
            // Waffen-Zeile direkt unter dem Abschnitts-Titel (z.B. "G300")
            if (count($line) === 1 && $line[0]['x'] < 100 && preg_match('/^[A-Z]{1,2}\d{2,3}$/', $line[0]['t'])) {
                $waffe = $line[0]['t'];
                $pendingName = null;
                continue;
            }

            // Teilnehmer-Zeile: beginnt mit 4-stelliger Nr., enthaelt einen Jahrgang
            $first = $line[0];
            $isRow = preg_match('/^\d{4}$/', $first['t']) && $first['x'] < 80;
            $jg = null;
            if ($isRow) {
                foreach ($line as $f) {
                    if ($f['x'] >= 200 && $f['x'] <= 265 && preg_match('/^(19|20)\d{2}$/', $f['t'])) {
                        $jg = (int) $f['t'];
                        break;
                    }
                }
                if ($jg === null) {
                    $isRow = false;
                }
            }

            if (!$isRow) {
                // Reine Namens-Zeile (erste Zeile eines umbrochenen Namens)?
                $isNameOnly = !empty($line);
                foreach ($line as $f) {
                    if ($f['x'] < 75 || $f['x'] > 242 || preg_match('/\d/', $f['t'])) {
                        $isNameOnly = false;
                        break;
                    }
                }
                $pendingName = $isNameOnly ? trim(implode(' ', array_column($line, 't'))) : null;
                continue;
            }

            // Name = Fragmente zwischen Nr. und Jahrgang
            $nameParts = [];
            $kategorie = '';
            $jsk = false;
            foreach ($line as $f) {
                if ($f['x'] >= 75 && $f['x'] <= 242) {
                    $nameParts[] = $f['t'];
                } elseif ($f['t'] === 'JSK') {
                    $jsk = true;
                } elseif ($f['x'] > 250 && $f['x'] <= 292 && preg_match('/^[A-Z]{1,2}\d{0,2}$/', $f['t'])) {
                    $kategorie = $f['t']; // E, S, V, SV, U17, U21
                }
            }
            $name = trim(implode(' ', $nameParts));
            if ($pendingName !== null && $name !== '') {
                $name = $pendingName . ' ' . $name;
            }
            $pendingName = null;
            if ($name === '') {
                continue;
            }

            // Resultate ueber die Spalten-Anker zuordnen (Toleranz 9 Punkte;
            // hochgestellte Anzahl-Nachschiessen liegen >12 Punkte daneben)
            $vals = ['op' => null, 'w1' => null, 'w2' => null, 'fs' => null];
            foreach ($line as $f) {
                if ($f['x'] <= 292 || !preg_match('/^\d{1,3}$/', $f['t'])) {
                    continue;
                }
                foreach ($anchors as $col => $ax) {
                    if ($ax !== null && abs($f['x'] - $ax) <= 9.0 && $vals[$col] === null) {
                        $vals[$col] = (int) $f['t'];
                        break;
                    }
                }
            }

            $row = [
                'nr'        => $first['t'],
                'name'      => $name,
                'jg'        => $jg,
                'kategorie' => $kategorie,
                'jsk'       => $jsk,
                'op'        => $vals['op'],
                'op_w1'     => $vals['w1'],
                'op_w2'     => $vals['w2'],
                'fs'        => $vals['fs'],
                'verein'    => $verein,
                'waffe'     => $waffe,
            ];
            $rows[] = $row;
            if ($debug && count($debugLines) < 60) {
                $debugLines[] = $joined;
            }
        }
    }

    if (empty($rows)) {
        return [
            'success' => false,
            'rows'    => [],
            'message' => 'FSA-Teilnehmerliste erkannt, aber keine Teilnehmer-Zeilen gefunden.',
        ];
    }

    $result = [
        'success' => true,
        'rows'    => $rows,
        'message' => count($rows) . ' Teilnehmer-Zeilen erkannt',
    ];
    if ($debug) {
        $result['debug'] = ['row_count' => count($rows), 'sample_lines' => $debugLines];
    }
    return $result;
}

/**
 * Bestimmt die X-Zentren der Resultat-Spalten aus den rotierten Kopfzeilen.
 * Zweizeilige Titel ("Resultat" + "OP") werden gepaart: Zentrum = Mittelwert der
 * beiden X-Werte. "Resultat FS" ist einzeilig -> eigenes X.
 *
 * @return array|null ['op'=>float|null,'w1'=>float|null,'w2'=>float|null,'fs'=>float|null]
 */
function fsaColumnAnchors($rotFrags) {
    $find = function ($needle) use ($rotFrags) {
        foreach ($rotFrags as $f) {
            if (strcasecmp($f['t'], $needle) === 0) {
                return $f;
            }
        }
        return null;
    };
    // Naechstes "Resultat"-Fragment links des Labels (max. 15 Punkte entfernt)
    $pairWithResultat = function ($labelFrag) use ($rotFrags) {
        if ($labelFrag === null) {
            return null;
        }
        $best = null;
        foreach ($rotFrags as $f) {
            if (strcasecmp($f['t'], 'Resultat') !== 0) {
                continue;
            }
            $dx = $labelFrag['x'] - $f['x'];
            if ($dx > 0 && $dx <= 15 && abs($f['y'] - $labelFrag['y']) < 8) {
                if ($best === null || $dx < ($labelFrag['x'] - $best['x'])) {
                    $best = $f;
                }
            }
        }
        return ($best !== null) ? ($best['x'] + $labelFrag['x']) / 2.0 : $labelFrag['x'];
    };

    $op = $pairWithResultat($find('OP'));
    $w1 = $pairWithResultat($find('OP W1'));
    $w2 = $pairWithResultat($find('OP W2'));
    $fsFrag = $find('Resultat FS');
    $fs = ($fsFrag !== null) ? $fsFrag['x'] : null;

    if ($op === null && $fs === null) {
        return null; // keine brauchbare Kopfzeile auf dieser Seite
    }
    return ['op' => $op, 'w1' => $w1, 'w2' => $w2, 'fs' => $fs];
}

/**
 * Extrahiert den Vereinsnamen aus der Abschnitts-Titelzeile,
 * z.B. "Militärschützenverein Wilen b/Wollerau (1.05.0.02.079) - Obligatorisch + ..." .
 */
function fsaExtractVereinName($joined) {
    $name = $joined;
    $pos = mb_strpos($name, '(');
    if ($pos !== false) {
        $name = mb_substr($name, 0, $pos);
    } else {
        $pos = mb_stripos($name, ' - Obligatorisch');
        if ($pos !== false) {
            $name = mb_substr($name, 0, $pos);
        }
    }
    return trim($name);
}
