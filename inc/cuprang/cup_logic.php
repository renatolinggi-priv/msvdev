<?php
// inc/cup/cup_logic.php
// Geschäftslogik: Gewinner/Verlierer bestimmen, Ranking sortieren, Hilfsfunktionen

if (!function_exists('cup_compare_pair')) {
    // Vergleicht zwei Resultate inkl. Tiefschuss (LowShot): höher ist besser.
    function cup_compare_pair(?int $resA, ?int $lsA, ?int $resB, ?int $lsB): int {
        $ra = (int)($resA ?? -INF);
        $rb = (int)($resB ?? -INF);
        if ($ra !== $rb) return ($ra > $rb) ? 1 : -1;
        $la = (int)($lsA ?? -INF);
        $lb = (int)($lsB ?? -INF);
        if ($la !== $lb) return ($la > $lb) ? 1 : -1;
        return 0;
    }
}

if (!function_exists('cup_winner_index')) {
    /**
     * Winner-Index für 2er- oder 3er-Paarung (1|2|3).
     * Beachtet ManualWinner (falls 1..3).
     * Bei 3er-Paarung: Winner = Bester (höchstes Resultat, bei Gleichstand höherer LowShot).
     */
    function cup_winner_index(array $row): ?int {
        if (!empty($row['ManualWinner']) && in_array((int)$row['ManualWinner'], [1,2,3], true)) {
            return (int)$row['ManualWinner'];
        }
        $cmp12 = cup_compare_pair($row['Result1'] ?? null, $row['LowShot1'] ?? null,
                                  $row['Result2'] ?? null, $row['LowShot2'] ?? null);
        if (empty($row['Participant3'])) {
            if ($cmp12 > 0) return 1;
            if ($cmp12 < 0) return 2;
            // Gleichstand: LowShot gleich -> kein Winner eindeutig
            return null;
        }
        // 3er: best of three
        $score = [
            1 => [$row['Result1'] ?? null, $row['LowShot1'] ?? null],
            2 => [$row['Result2'] ?? null, $row['LowShot2'] ?? null],
            3 => [$row['Result3'] ?? null, $row['LowShot3'] ?? null],
        ];
        $best = 1;
        foreach ([2,3] as $i) {
            $c = cup_compare_pair($score[$i][0], $score[$i][1], $score[$best][0], $score[$best][1]);
            if ($c > 0) $best = $i;
        }
        return $best;
    }
}

if (!function_exists('cup_three_loser_index')) {
    /**
     * Für 3er-Paarung: bestimmt den "Verlierer" (schlechtester).
     * Falls ManualWinner gesetzt ist, loser = einer der anderen beiden mit schlechterem Score.
     */
    function cup_three_loser_index(array $row): ?int {
        if (empty($row['Participant3'])) return null;
        $score = [
            1 => [$row['Result1'] ?? null, $row['LowShot1'] ?? null],
            2 => [$row['Result2'] ?? null, $row['LowShot2'] ?? null],
            3 => [$row['Result3'] ?? null, $row['LowShot3'] ?? null],
        ];
        // loser = schlechtester
        $worst = 1;
        foreach ([2,3] as $i) {
            $c = cup_compare_pair($score[$i][0], $score[$i][1], $score[$worst][0], $score[$worst][1]);
            if ($c < 0) $worst = $i;
        }
        // Sonderfall ManualWinner: Stelle sicher, dass Gewinner != Loser (bei totaler Gleichheit)
        if (!empty($row['ManualWinner']) && in_array((int)$row['ManualWinner'], [1,2,3], true)) {
            if ($worst === (int)$row['ManualWinner']) {
                // wenn alles gleich ist, wähle einen anderen als loser deterministisch
                foreach ([1,2,3] as $i) {
                    if ($i !== (int)$row['ManualWinner']) { $worst = $i; break; }
                }
            }
        }
        return $worst;
    }
}

if (!function_exists('cup_compute_ranking')) {
    /**
     * Erzeugt sortierte Rangliste mit RANG (ties mit gleichem Punkte+Tiefschuss gleich).
     * rows: [ ['Teilnehmer'=>..., 'Punkte'=>int, 'Tiefschuss'=>int], ... ]
     */
    function cup_compute_ranking(array $rows): array {
        // sortieren
        usort($rows, function($a, $b) {
            $ra = (int)($a['Punkte'] ?? 0); $rb = (int)($b['Punkte'] ?? 0);
            if ($ra !== $rb) return ($ra > $rb) ? -1 : 1;
            $la = (int)($a['Tiefschuss'] ?? 0); $lb = (int)($b['Tiefschuss'] ?? 0);
            if ($la !== $lb) return ($la > $lb) ? -1 : 1;
            return strcmp((string)($a['Teilnehmer'] ?? ''), (string)($b['Teilnehmer'] ?? ''));
        });
        // ranken
        $rank = 0; $i = 0; $prev = null;
        foreach ($rows as &$r) {
            $i++;
            $key = ($r['Punkte'] ?? '') . '|' . ($r['Tiefschuss'] ?? '');
            if ($key !== $prev) { $rank = $i; $prev = $key; }
            $r['Rang'] = $rank;
        }
        unset($r);
        return $rows;
    }
}
