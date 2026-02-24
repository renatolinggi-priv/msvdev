<?php
// inc/einsatzplan_parser/name_matcher.php - Ordnet Namen aus Einsatzplänen Mitgliedern zu

/**
 * Matcht eine Liste von Zuweisungen mit der Mitglieder-DB.
 * Fügt jedem Eintrag mitglied_id und match_status hinzu.
 *
 * @param PDO $db PDO-Datenbankverbindung
 * @param array $zuweisungen Array von Zuweisungen aus dem Parser
 * @return array Zuweisungen mit mitglied_id und match_status
 */
function matchMitglieder($db, $zuweisungen) {
    // Alle Mitglieder laden (inkl. inaktive/Ehrenmitglieder, da diese auch Einsätze haben können)
    $stmt = $db->query("SELECT ID, Name, Vorname FROM mitglieder");
    $mitglieder = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Lookup-Maps erstellen
    $exactMap = [];      // "name vorname" => ID
    $reversedMap = [];   // "vorname name" => ID (falls vertauscht)

    foreach ($mitglieder as $m) {
        $key = mb_strtolower(trim($m['Name']) . ' ' . trim($m['Vorname']), 'UTF-8');
        $exactMap[$key] = $m['ID'];

        $keyReversed = mb_strtolower(trim($m['Vorname']) . ' ' . trim($m['Name']), 'UTF-8');
        $reversedMap[$keyReversed] = $m['ID'];
    }

    // Zuweisungen matchen
    foreach ($zuweisungen as &$z) {
        $result = matchSingleName($z['mitglied_name'], $mitglieder, $exactMap, $reversedMap);
        $z['mitglied_id'] = $result['mitglied_id'];
        $z['match_status'] = $result['match_status'];
        $z['matched_name'] = $result['matched_name'];
    }

    return $zuweisungen;
}

/**
 * Versucht einen einzelnen Namen einem Mitglied zuzuordnen.
 *
 * @param string $name Name aus dem Dokument (Format: "Name Vorname")
 * @param array $mitglieder Alle Mitglieder
 * @param array $exactMap Lookup "name vorname" => ID
 * @param array $reversedMap Lookup "vorname name" => ID
 * @return array ['mitglied_id' => int|null, 'match_status' => string, 'matched_name' => string]
 */
function matchSingleName($name, $mitglieder, $exactMap, $reversedMap) {
    $name = trim($name);
    if (empty($name)) {
        return ['mitglied_id' => null, 'match_status' => 'none', 'matched_name' => ''];
    }

    $nameLower = mb_strtolower($name, 'UTF-8');

    // 1. Exakter Match: "Name Vorname" → DB
    if (isset($exactMap[$nameLower])) {
        $id = $exactMap[$nameLower];
        return ['mitglied_id' => $id, 'match_status' => 'exact', 'matched_name' => findDisplayName($mitglieder, $id)];
    }

    // 2. Vertauscht: "Vorname Name" → DB
    if (isset($reversedMap[$nameLower])) {
        $id = $reversedMap[$nameLower];
        return ['mitglied_id' => $id, 'match_status' => 'exact', 'matched_name' => findDisplayName($mitglieder, $id)];
    }

    // 3. Mehrteilige Nachnamen: "von Euw Alexander" → Name="von Euw", Vorname="Alexander"
    //    Strategie: letztes Wort = Vorname, Rest = Nachname
    $parts = preg_split('/\s+/', $name);
    if (count($parts) >= 3) {
        $vorname = array_pop($parts);
        $nachname = implode(' ', $parts);
        $key = mb_strtolower($nachname . ' ' . $vorname, 'UTF-8');
        if (isset($exactMap[$key])) {
            $id = $exactMap[$key];
            return ['mitglied_id' => $id, 'match_status' => 'exact', 'matched_name' => findDisplayName($mitglieder, $id)];
        }
    }

    // 4. Nur Nachname-Match (falls Vorname abgekürzt oder fehlend)
    foreach ($mitglieder as $m) {
        $mName = mb_strtolower(trim($m['Name']), 'UTF-8');
        if ($mName === $nameLower) {
            return ['mitglied_id' => $m['ID'], 'match_status' => 'fuzzy', 'matched_name' => findDisplayName($mitglieder, $m['ID'])];
        }
    }

    // 5. Fuzzy: Teilstring-Match (Name beginnt mit...)
    foreach ($mitglieder as $m) {
        $fullName = mb_strtolower(trim($m['Name']) . ' ' . trim($m['Vorname']), 'UTF-8');
        // Prüfe ob der Name aus dem Dokument ein Anfangsstück des DB-Namens ist (oder umgekehrt)
        if (mb_strlen($nameLower) >= 5 && mb_strpos($fullName, $nameLower) === 0) {
            return ['mitglied_id' => $m['ID'], 'match_status' => 'fuzzy', 'matched_name' => findDisplayName($mitglieder, $m['ID'])];
        }
        if (mb_strlen($fullName) >= 5 && mb_strpos($nameLower, $fullName) === 0) {
            return ['mitglied_id' => $m['ID'], 'match_status' => 'fuzzy', 'matched_name' => findDisplayName($mitglieder, $m['ID'])];
        }
    }

    // Kein Match
    return ['mitglied_id' => null, 'match_status' => 'none', 'matched_name' => ''];
}

/**
 * Findet den Anzeigenamen eines Mitglieds.
 */
function findDisplayName($mitglieder, $id) {
    foreach ($mitglieder as $m) {
        if ($m['ID'] == $id) {
            return $m['Name'] . ' ' . $m['Vorname'];
        }
    }
    return '';
}
