<?php
/**
 * inc/wanderpreise/regel_builder.inc.php
 *
 * Zentrale Logik fuer den gefuehrten Wanderpreise-Regel-Builder.
 *
 * Statt rohes SQL zu schreiben, waehlt der Admin im Panel einen Wettbewerb +
 * Kategorie. Daraus erzeugt wp_build_regel_sql() ein geprueftes SELECT, das in
 * wanderpreise_regeln.sql_query gespeichert wird. auto_zuordnung.php fuehrt
 * weiterhin nur das gespeicherte sql_query aus -> dieser Builder ist additiv
 * und beruehrt die bestehende Ausfuehrung nicht.
 *
 * SICHERHEIT: In das generierte SQL fliessen NUR Werte aus festen Whitelists
 * (Registry-Keys, Kategorie-Enum, Richtung-Enum) sowie konstante String-
 * Literale ('1. Rang'). Es gibt keinen freien Benutzertext im SQL -> keine
 * Injection-Flaeche.
 *
 * Dieselbe Registry speist auch die Tabellen-/Spalten-Referenz im Panel
 * (Phase 1.2), damit Doku und Generator nicht auseinanderlaufen.
 */

/**
 * Erlaubte Regel-Typen.
 *  - 'custom'           = rohes SQL (Experten-Modus, heutiges Verhalten)
 *  - 'einzelwettbewerb' = gefuehrt: Wettbewerb + Kategorie + Richtung
 *  - 'baukasten'        = gefuehrt + frei klickbare Bedingungen/Sortierung
 */
function wp_regel_typen(): array {
    return ['custom', 'einzelwettbewerb', 'baukasten'];
}

/** Operatoren-Whitelist fuer den Bedingungs-Baukasten. */
function wp_baukasten_operators(): array {
    return ['=', '!=', '<', '<=', '>', '>=', 'IS NULL', 'IS NOT NULL'];
}

/**
 * Validiert einen Baukasten-Wert. Erlaubt ist NUR eine Zahl oder der
 * Platzhalter {jahr} -> keine Injection-Flaeche (String-/Kategorie-Vergleiche
 * laufen ueber die dedizierte Kategorie-Steuerung, nicht ueber freie Werte).
 *
 * @return string der unveraenderte, sichere Wert
 * @throws InvalidArgumentException bei unzulaessigem Wert
 */
function wp_validate_baukasten_value(string $val): string {
    $val = trim($val);
    if ($val === '{jahr}') return '{jahr}';
    if (preg_match('/^-?\d+(\.\d+)?$/', $val)) return $val;
    throw new InvalidArgumentException("Ungueltiger Wert (nur Zahl oder {jahr} erlaubt): $val");
}

/** Baut eine Spalten-Map (key => ['label','expr']) aus rohen Spaltennamen (Alias r.). */
function wp_cols(array $names): array {
    $out = [];
    foreach ($names as $n) {
        $out[$n] = ['label' => $n, 'expr' => 'r.' . $n];
    }
    return $out;
}

/**
 * Effektive Spalten eines Wettbewerbs fuer den Baukasten:
 * Pseudo-Spalte 'score' (= berechnetes Resultat) + die Rohspalten der Registry.
 */
function wp_wettbewerb_columns(array $w): array {
    $cols = ['score' => ['label' => 'Resultat (berechnet)', 'expr' => $w['score_expr']]];
    foreach (($w['columns'] ?? []) as $key => $def) {
        $cols[$key] = $def;
    }
    return $cols;
}

/**
 * Prueft, ob die Builder-Spalten (Migration 027) bereits existieren.
 * Ermoeglicht graceful degradation, falls die Migration noch nicht lief
 * (wichtig unter PHP 8.1+, wo eine fehlerhafte Query eine Exception wirft).
 */
function wp_regeln_has_builder_columns(mysqli $conn): bool {
    try {
        $r = $conn->query("SHOW COLUMNS FROM `wanderpreise_regeln` LIKE 'regel_typ'");
        return $r && $r->num_rows > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Registry der Builder-Wettbewerbe: key => Konfiguration.
 *
 * Annahme (wie bei den bestehenden heim-/kantonalstich-Regeln): pro Mitglied
 * und Jahr gibt es hoechstens eine Resultatzeile je Wettbewerb. Damit ist die
 * einfache "ORDER BY score LIMIT 1"-Form ohne GROUP BY korrekt.
 *
 * Tabellen-Alias im generierten SQL: r (Resultate), m (mitglieder), w (Waffen).
 */
function wp_wettbewerb_registry(): array {
    return [
        'glueck' => [
            'label'      => 'Glückstich',
            'table'      => 'glueck',
            'member_col' => 'MitgliedID',
            'year_col'   => 'Jahr',
            'score_expr' => 'GREATEST(COALESCE(r.GSchuss1,0), COALESCE(r.GSchuss2,0), COALESCE(r.GSchuss3,0))',
            'data_filter'=> '(COALESCE(r.GSchuss1,0) <> 0 OR COALESCE(r.GSchuss2,0) <> 0 OR COALESCE(r.GSchuss3,0) <> 0)',
            'tiebreak'   => 'm.Geburtsdatum ASC',
            'category'   => true,
            'columns'    => wp_cols(['GSchuss1', 'GSchuss2', 'GSchuss3']),
        ],
        'kunst' => [
            'label'      => 'Kunststich',
            'table'      => 'kunst',
            'member_col' => 'MitgliedID',
            'year_col'   => 'Jahr',
            'score_expr' => '(COALESCE(r.KSchuss1,0) + COALESCE(r.KSchuss2,0) + COALESCE(r.KSchuss3,0) + COALESCE(r.KSchuss4,0) + COALESCE(r.KSchuss5,0))',
            'data_filter'=> 'COALESCE(r.KSchuss1,0) <> 0',
            'tiebreak'   => 'm.Geburtsdatum ASC',
            'category'   => true,
            'columns'    => wp_cols(['KSchuss1', 'KSchuss2', 'KSchuss3', 'KSchuss4', 'KSchuss5']),
        ],
        'heimresultate' => [
            'label'      => 'Heimmeisterschaft',
            'table'      => 'heimresultate',
            'member_col' => 'MitgliedID',
            'year_col'   => 'Jahr',
            'score_expr' => '(COALESCE(r.Passe1,0) + COALESCE(r.Passe2,0) + COALESCE(r.Passe3,0) + COALESCE(r.Passe4,0) + COALESCE(r.Passe5,0) + COALESCE(r.Passe6,0) + COALESCE(r.Passe7,0) + COALESCE(r.Passe8,0))',
            'data_filter'=> '(COALESCE(r.Passe1,0) > 0 OR COALESCE(r.Passe2,0) > 0 OR COALESCE(r.Passe3,0) > 0 OR COALESCE(r.Passe4,0) > 0 OR COALESCE(r.Passe5,0) > 0 OR COALESCE(r.Passe6,0) > 0 OR COALESCE(r.Passe7,0) > 0 OR COALESCE(r.Passe8,0) > 0)',
            'tiebreak'   => 'm.Name ASC, m.Vorname ASC',
            'category'   => true,
            'columns'    => wp_cols(['Passe1', 'Passe2', 'Passe3', 'Passe4', 'Passe5', 'Passe6', 'Passe7', 'Passe8']),
        ],
        'kantiresultate' => [
            'label'      => 'Kantonalstich',
            'table'      => 'kantiresultate',
            'member_col' => 'MitgliedID',
            'year_col'   => 'Jahr',
            'score_expr' => '(COALESCE(r.Passe1,0) + COALESCE(r.Passe2,0) + COALESCE(r.Passe3,0) + COALESCE(r.Passe4,0) + COALESCE(r.Passe5,0))',
            'data_filter'=> '(COALESCE(r.Passe1,0) > 0 OR COALESCE(r.Passe2,0) > 0 OR COALESCE(r.Passe3,0) > 0 OR COALESCE(r.Passe4,0) > 0 OR COALESCE(r.Passe5,0) > 0)',
            'tiebreak'   => 'm.Name ASC, m.Vorname ASC',
            'category'   => true,
            'columns'    => wp_cols(['Passe1', 'Passe2', 'Passe3', 'Passe4', 'Passe5']),
        ],
        'endstich' => [
            'label'      => 'Endstich',
            'table'      => 'endstich',
            'member_col' => 'MitgliedID',
            'year_col'   => 'Jahr',
            'score_expr' => '(COALESCE(r.Schuss1,0) + COALESCE(r.Schuss2,0) + COALESCE(r.Schuss3,0) + COALESCE(r.Schuss4,0) + COALESCE(r.Schuss5,0) + COALESCE(r.Schuss6,0) + COALESCE(r.Schuss7,0) + COALESCE(r.Schuss8,0) + COALESCE(r.Schuss9,0) + COALESCE(r.Schuss10,0))',
            'data_filter'=> 'COALESCE(r.Schuss1,0) <> 0',
            'tiebreak'   => 'r.Tiefschuss ASC, m.Geburtsdatum ASC',
            'category'   => true,
            'columns'    => wp_cols(['Schuss1', 'Schuss2', 'Schuss3', 'Schuss4', 'Schuss5', 'Schuss6', 'Schuss7', 'Schuss8', 'Schuss9', 'Schuss10', 'Tiefschuss']),
        ],
        'zabig' => [
            'label'      => 'Zabigstich',
            'table'      => 'zabig',
            'member_col' => 'MitgliedID',
            'year_col'   => 'Jahr',
            'score_expr' => '(COALESCE(r.ZSchuss1,0) + COALESCE(r.ZSchuss2,0) + COALESCE(r.ZSchuss3,0) + COALESCE(r.ZSchuss4,0) + COALESCE(r.ZSchuss5,0) + COALESCE(r.ZSchuss6,0))',
            'data_filter'=> 'COALESCE(r.ZSchuss1,0) <> 0',
            'tiebreak'   => 'm.Geburtsdatum ASC',
            'category'   => true,
            'columns'    => wp_cols(['ZSchuss1', 'ZSchuss2', 'ZSchuss3', 'ZSchuss4', 'ZSchuss5', 'ZSchuss6', 'Ansage']),
        ],
        'cup' => [
            'label'      => 'Vereinscup',
            'table'      => 'cupFinalResults',
            'member_col' => 'ParticipantID',
            'year_col'   => 'Year',
            'score_expr' => 'COALESCE(r.Result,0)',
            'data_filter'=> 'r.Result IS NOT NULL',
            'tiebreak'   => 'r.LowShot DESC',
            'category'   => false,
            'columns'    => wp_cols(['Result', 'LowShot']),
        ],
    ];
}

/**
 * Schlanke Registry fuer das Frontend (JSON): key => {label, category, columns:[{key,label}]}.
 * Liefert die im Baukasten waehlbaren Spalten je Wettbewerb.
 */
function wp_wettbewerbe_client(): array {
    $out = [];
    foreach (wp_wettbewerb_registry() as $key => $w) {
        $cols = [];
        foreach (wp_wettbewerb_columns($w) as $ck => $cdef) {
            $cols[] = ['key' => $ck, 'label' => $cdef['label']];
        }
        $out[$key] = [
            'label'    => $w['label'],
            'category' => !empty($w['category']),
            'columns'  => $cols,
        ];
    }
    return $out;
}

/**
 * Normalisiert die Kategorie-Eingabe auf '' | 'Kat. A' | 'Kat. B'.
 * Akzeptiert 'A'/'B'/'Kat. A'/'Kat. B'/'' (alles andere -> '').
 */
function wp_normalize_kategorie(?string $kat): string {
    $kat = trim((string)$kat);
    if ($kat === '') return '';
    if (preg_match('/A$/i', $kat)) return 'Kat. A';
    if (preg_match('/B$/i', $kat)) return 'Kat. B';
    return '';
}

/**
 * Erzeugt das SQL fuer eine gefuehrte Regel ('einzelwettbewerb' oder 'baukasten').
 *
 * Gemeinsames Skelett (immer implizit, schuetzt das Pflicht-Ergebnis):
 *   SELECT m.ID AS gewinner_id, <score> AS resultat, '1. Rang' AS rang
 *   FROM <table> r INNER JOIN mitglieder m ...
 *   WHERE r.<year> = {jahr} AND <data_filter> [AND w.Kategorie = ...]
 *
 * 'einzelwettbewerb': $params = ['wettbewerb','kategorie','richtung']
 * 'baukasten':        + 'filter' => [{col,op,val}], 'sort' => [{col,dir}]
 *
 * @return string Generiertes, ausfuehrbares SELECT (mit {jahr}-Platzhalter)
 * @throws InvalidArgumentException bei ungueltigen/whitelist-fremden Werten
 */
function wp_build_regel_sql(string $typ, array $params): string {
    if (!in_array($typ, ['einzelwettbewerb', 'baukasten'], true)) {
        throw new InvalidArgumentException("Unbekannter Regel-Typ: $typ");
    }

    $registry = wp_wettbewerb_registry();
    $key = (string)($params['wettbewerb'] ?? '');
    if (!isset($registry[$key])) {
        throw new InvalidArgumentException("Unbekannter Wettbewerb: $key");
    }
    $w = $registry[$key];

    // Kategorie nur wenn der Wettbewerb sie unterstuetzt.
    $kat = $w['category'] ? wp_normalize_kategorie($params['kategorie'] ?? '') : '';
    $score = $w['score_expr'];

    // --- Gemeinsames Skelett ---
    $lines = [];
    $lines[] = 'SELECT';
    $lines[] = '    m.ID AS gewinner_id,';
    $lines[] = '    ' . $score . ' AS resultat,';
    $lines[] = "    '1. Rang' AS rang";
    $lines[] = 'FROM ' . $w['table'] . ' r';
    $lines[] = 'INNER JOIN mitglieder m ON m.ID = r.' . $w['member_col'];
    if ($kat !== '') {
        $lines[] = 'INNER JOIN Waffen w ON w.ID = m.WaffenID';
    }
    $lines[] = 'WHERE r.' . $w['year_col'] . ' = {jahr}';
    $lines[] = '  AND ' . $w['data_filter'];
    if ($kat !== '') {
        // $kat stammt aus wp_normalize_kategorie() -> nur 'Kat. A'/'Kat. B' moeglich.
        $lines[] = "  AND w.Kategorie = '" . $kat . "'";
    }

    if ($typ === 'baukasten') {
        $colMap = wp_wettbewerb_columns($w);
        $ops    = wp_baukasten_operators();

        // Zusatz-Filter (mit AND verknuepft)
        foreach ((array)($params['filter'] ?? []) as $f) {
            $col = (string)($f['col'] ?? '');
            $op  = strtoupper(trim((string)($f['op'] ?? '')));
            if (!isset($colMap[$col])) throw new InvalidArgumentException("Unbekannte Spalte: $col");
            if (!in_array($op, $ops, true)) throw new InvalidArgumentException("Unbekannter Operator: $op");
            $expr = $colMap[$col]['expr'];
            if ($op === 'IS NULL' || $op === 'IS NOT NULL') {
                $lines[] = '  AND ' . $expr . ' ' . $op;
            } else {
                $val = wp_validate_baukasten_value((string)($f['val'] ?? ''));
                $lines[] = '  AND ' . $expr . ' ' . $op . ' ' . $val;
            }
        }

        // Sortierung (leer -> Default score DESC + tiebreak)
        $orderParts = [];
        foreach ((array)($params['sort'] ?? []) as $s) {
            $col = (string)($s['col'] ?? '');
            if (!isset($colMap[$col])) throw new InvalidArgumentException("Unbekannte Sortier-Spalte: $col");
            $dir = strtoupper(trim((string)($s['dir'] ?? 'DESC')));
            if ($dir !== 'ASC') $dir = 'DESC';
            $orderParts[] = $colMap[$col]['expr'] . ' ' . $dir;
        }
        if (!$orderParts) {
            $orderParts[] = $score . ' DESC';
            if (!empty($w['tiebreak'])) $orderParts[] = $w['tiebreak'];
        }
        $lines[] = 'ORDER BY ' . implode(', ', $orderParts);
    } else {
        // einzelwettbewerb: einfache Richtung
        $richtung = strtoupper(trim((string)($params['richtung'] ?? 'DESC')));
        if ($richtung !== 'ASC') $richtung = 'DESC';
        $order = $score . ' ' . $richtung;
        if (!empty($w['tiebreak'])) $order .= ', ' . $w['tiebreak'];
        $lines[] = 'ORDER BY ' . $order;
    }

    $lines[] = 'LIMIT 1';
    return implode("\n", $lines);
}

/**
 * Baut das params-Array fuer wp_build_regel_sql() aus dem Request.
 * filter/sort werden als JSON-Strings (filter_json/sort_json) uebertragen.
 * Wird von save_regel.php und build_regel_preview.php gemeinsam genutzt.
 */
function wp_params_from_post(array $post): array {
    $filter = json_decode((string)($post['filter_json'] ?? '[]'), true);
    $sort   = json_decode((string)($post['sort_json'] ?? '[]'), true);
    return [
        'wettbewerb' => $post['wettbewerb'] ?? '',
        'kategorie'  => $post['kategorie'] ?? '',
        'richtung'   => $post['richtung'] ?? 'DESC',
        'filter'     => is_array($filter) ? $filter : [],
        'sort'       => is_array($sort) ? $sort : [],
    ];
}

/**
 * Tabellen-/Spalten-Referenz fuer das Hilfe-Panel (Phase 1.2).
 * Rein statische Doku der real existierenden Result-Tabellen.
 */
function wp_regel_schema_reference(): array {
    return [
        ['table' => 'glueck',          'member' => 'MitgliedID',   'year' => 'Jahr', 'cols' => 'GSchuss1–GSchuss3'],
        ['table' => 'kunst',           'member' => 'MitgliedID',   'year' => 'Jahr', 'cols' => 'KSchuss1–KSchuss5'],
        ['table' => 'heimresultate',   'member' => 'MitgliedID',   'year' => 'Jahr', 'cols' => 'Passe1–Passe8'],
        ['table' => 'kantiresultate',  'member' => 'MitgliedID',   'year' => 'Jahr', 'cols' => 'Passe1–Passe5'],
        ['table' => 'endstich',        'member' => 'MitgliedID',   'year' => 'Jahr', 'cols' => 'Schuss1–Schuss10, Tiefschuss'],
        ['table' => 'zabig',           'member' => 'MitgliedID',   'year' => 'Jahr', 'cols' => 'ZSchuss1–ZSchuss6, Ansage'],
        ['table' => 'schwini',         'member' => 'MitgliedID',   'year' => 'Jahr', 'cols' => 'P1Schuss1–6, P2Schuss1–6'],
        ['table' => 'jmresultate',     'member' => 'mitgliederID', 'year' => '— (über jmdefinition.year)', 'cols' => 'jmdefinitionID, Punkte'],
        ['table' => 'cupFinalResults', 'member' => 'ParticipantID', 'year' => 'Year', 'cols' => 'Result, LowShot'],
        ['table' => 'mitglieder',      'member' => 'ID',           'year' => '—',    'cols' => 'Vorname, Name, Geburtsdatum, WaffenID, Status, Verstorben'],
        ['table' => 'Waffen',          'member' => '—',            'year' => '—',    'cols' => 'ID, Kategorie (\'Kat. A\' / \'Kat. B\') — Join über mitglieder.WaffenID'],
    ];
}
