<?php
// inc/changelog_portal.inc.php
// Gemeinsame Helfer fuer die Portal-Sicht auf changelog.json.
// Wird von portal/changelog.php (Neuigkeiten-Seite) und portal/portal_footer.php
// (globales "Was ist neu"-Modal) genutzt, damit die Filterlogik nur einmal existiert.

/**
 * Liefert die fuer das Portal sichtbare, gefilterte Changelog-Struktur.
 *
 * - admin_only-Eintraege erscheinen NIE im Portal (auch nicht fuer Vorstand/Admin).
 * - Nicht-Vorstand (normale Mitglieder) sehen nur Eintraege mit "portal": true.
 * - Releases ohne sichtbare Eintraege werden verworfen.
 *
 * Reihenfolge bleibt erhalten (changelog.json ist neuste-zuerst sortiert).
 *
 * @param bool $showIntern  true = Vorstand/Admin (sehen auch "portal": false)
 * @return array            Liste von Release-Objekten (mit gefiltertem 'aenderungen')
 */
function getPortalChangelog(bool $showIntern): array {
    $json_path = __DIR__ . '/../changelog.json';
    if (!file_exists($json_path)) {
        return [];
    }
    $changelog = json_decode(file_get_contents($json_path), true) ?: [];

    foreach ($changelog as &$release) {
        $aenderungen = $release['aenderungen'] ?? [];
        $aenderungen = array_filter($aenderungen, function ($e) use ($showIntern) {
            if (!empty($e['admin_only'])) {
                return false; // nie im Portal
            }
            if (!$showIntern && empty($e['portal'])) {
                return false; // Mitglieder sehen nur portal:true
            }
            return true;
        });
        // array_values: saubere, neu indizierte Liste fuer JSON/Iteration
        $release['aenderungen'] = array_values($aenderungen);
    }
    unset($release);

    $changelog = array_filter($changelog, function ($r) {
        return !empty($r['aenderungen']);
    });

    return array_values($changelog);
}

/**
 * Version des neusten (obersten) sichtbaren Releases, oder null wenn leer.
 *
 * @param array $changelog  Ergebnis von getPortalChangelog()
 * @return string|null
 */
function portalChangelogNewest(array $changelog): ?string {
    if (empty($changelog)) {
        return null;
    }
    return $changelog[0]['version'] ?? null;
}

/**
 * Flache Liste der Eintraege aus allen Releases, die NEUER sind als $seen,
 * fuer die Modal-Darstellung. Jeder Eintrag wird um version + datum des Releases
 * angereichert.
 *
 * Vergleich ueber die Array-Reihenfolge: alle Releases VOR dem mit Version $seen
 * gelten als neu. Ist $seen null oder nicht (mehr) enthalten, gelten alle
 * sichtbaren Eintraege als neu.
 *
 * @param array       $changelog  Ergebnis von getPortalChangelog()
 * @param string|null $seen       zuletzt bestaetigte Version
 * @return array                  Liste: ['version','datum','aenderungen'=>[...]]
 */
function portalChangelogSince(array $changelog, ?string $seen): array {
    $result = [];
    foreach ($changelog as $release) {
        if ($seen !== null && ($release['version'] ?? null) === $seen) {
            break; // ab hier (inkl.) bereits gesehen
        }
        $result[] = [
            'version'     => $release['version'] ?? '',
            'datum'       => $release['datum'] ?? '',
            'aenderungen' => $release['aenderungen'] ?? [],
        ];
    }
    return $result;
}
