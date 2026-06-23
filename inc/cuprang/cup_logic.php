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

if (!function_exists('cup_pair_states')) {
    /**
     * Bestimmt für eine Paarung den Status jedes Teilnehmers (Weiterkommen).
     * Identische Logik wie inc/cup.php (Live-Editor):
     *   - ManualWinner = Teilnehmer-ID (positiv = Gewinner, negativ = Verlierer bei 3er-Gleichstand)
     *   - Advancers (1 oder 2) bestimmt bei 3er-Paarungen, wie viele weiterkommen (Default 2)
     * Rückgabe: ['states' => [1=>'win'|'lose'|'', 2=>..., 3=>...], 'advCount' => int]
     * (advCount = Anzahl Weiterkommender → 1 ⇒ Label "Gewinner", 2 ⇒ Label "Weiter")
     */
    function cup_pair_states(array $row): array {
        $p1 = (int)($row['Participant1'] ?? 0);
        $p2 = (int)($row['Participant2'] ?? 0);
        $p3 = !empty($row['Participant3']) ? (int)$row['Participant3'] : 0;
        $manual = (isset($row['ManualWinner']) && $row['ManualWinner'] !== '' && $row['ManualWinner'] !== null)
                  ? (int)$row['ManualWinner'] : 0;
        $states = [1 => '', 2 => '', 3 => ''];

        if (!$p3) {
            // 2er-Paarung
            $r1 = is_numeric($row['Result1'] ?? null) ? (int)$row['Result1'] : null;
            $r2 = is_numeric($row['Result2'] ?? null) ? (int)$row['Result2'] : null;
            $winnerId = 0;
            if ($manual > 0) {
                $winnerId = $manual;
            } elseif ($r1 !== null && $r2 !== null && $r1 !== $r2) {
                $winnerId = ($r1 > $r2) ? $p1 : $p2;
            }
            if ($winnerId && $winnerId === $p1) { $states[1] = 'win'; $states[2] = 'lose'; }
            elseif ($winnerId && $winnerId === $p2) { $states[2] = 'win'; $states[1] = 'lose'; }
            return ['states' => $states, 'advCount' => 1];
        }

        // 3er-Paarung
        $advancers = (isset($row['Advancers']) && (int)$row['Advancers'] === 1) ? 1 : 2;
        $parts = [
            ['id' => $p1, 'r' => (int)($row['Result1'] ?? 0), 'ls' => (int)($row['LowShot1'] ?? 0)],
            ['id' => $p2, 'r' => (int)($row['Result2'] ?? 0), 'ls' => (int)($row['LowShot2'] ?? 0)],
            ['id' => $p3, 'r' => (int)($row['Result3'] ?? 0), 'ls' => (int)($row['LowShot3'] ?? 0)],
        ];
        $anyResult = ($parts[0]['r'] > 0 || $parts[1]['r'] > 0 || $parts[2]['r'] > 0);
        usort($parts, function ($a, $b) {
            if ($a['r'] === $b['r']) return $b['ls'] <=> $a['ls'];
            return $b['r'] <=> $a['r'];
        });
        $sortedIds = array_column($parts, 'id');

        if ($advancers === 1) {
            $winners = ($manual > 0) ? [$manual] : [$sortedIds[0]];
        } else {
            if ($manual < 0) {
                $loserId = abs($manual);
                $winners = array_values(array_filter($sortedIds, fn($id) => $id !== $loserId));
            } elseif ($manual > 0) {
                $winners = [$manual];
                foreach ($sortedIds as $id) { if ($id !== $manual && count($winners) < 2) $winners[] = $id; }
            } else {
                $winners = [$sortedIds[0], $sortedIds[1]];
            }
        }

        if (!$anyResult) return ['states' => $states, 'advCount' => $advancers];

        foreach ([1 => $p1, 2 => $p2, 3 => $p3] as $slot => $id) {
            $states[$slot] = in_array($id, $winners, true) ? 'win' : 'lose';
        }
        return ['states' => $states, 'advCount' => count($winners)];
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
