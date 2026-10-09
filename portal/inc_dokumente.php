<?php
// inc_dokumente.php – Dokumentliste fürs Portal (Einsatzpläne, Protokolle, JSK-Dokumente).
// Mitglieder sehen nur, was sie brauchen: Titel, Datum, Beschreibung. Die ganze Zeile öffnet das
// Dokument im Viewer (openPortalDoc in portal_footer.php), der den Titel statt des Dateinamens zeigt.
// Eingeschränkte Sichtbarkeit (Vorstand/Admin) steht als Schloss dabei; «für alle» braucht keinen Hinweis.
// Verwaltung (Hochladen, Bearbeiten, Löschen) läuft im Admin unter inc/dokumente_verwaltung.php.
// Nur Funktionen, keine Ausgabe.

/** «12. März 2026» aus einem Datum (Y-m-d), leer wenn keines */
function portalDokumentDatum(?string $datum): string {
    $ts = $datum ? strtotime($datum) : false;
    if (!$ts) return '';
    $monate = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
    return date('j', $ts) . '. ' . $monate[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

/** Liste der Dokumente (Zeilen aus vorstand_dokumente) */
function portalDokumentListe(array $dokumente): void {
    echo '<div class="p-list">';
    foreach ($dokumente as $doc) {
        $ext = strtolower(pathinfo((string) $doc['dateiname'], PATHINFO_EXTENSION));
        // Symbol und Chip-Farbe nach Dateityp (portal.css): PDF=rot, Bild/Excel=grün, Word=blau
        [$icon, $chip] = match (true) {
            $ext === 'pdf'                               => ['pdf', 'red'],
            in_array($ext, ['xlsx', 'xls'], true)        => ['excel', 'green'],
            in_array($ext, ['jpg', 'jpeg', 'png'], true) => ['image', 'green'],
            default                                      => ['word', 'blue'],
        };
        $schloss = ['admin' => 'Nur Admin', 'vorstand' => 'Nur Vorstand'][$doc['sichtbar_fuer']] ?? '';
        $datum   = portalDokumentDatum($doc['datum'] ?? null);
        $titel   = (string) $doc['titel'];
        $oeffnen = 'openPortalDoc(' . (int) $doc['id'] . ', ' . json_encode((string) $doc['dateiname']) . ', ' . json_encode($titel) . ')';
        ?>
    <div class="p-list-row doc-zeile position-relative" id="doc-<?php echo (int) $doc['id']; ?>">
        <div class="p-chip lg <?php echo $chip; ?>" aria-hidden="true"><i class="bi bi-file-earmark-<?php echo $icon; ?>"></i></div>
        <div class="p-list-body">
            <div class="p-list-title"><?php echo htmlspecialchars($titel); ?><?php if ($schloss): ?> <span class="doc-schloss"><i class="bi bi-lock" aria-hidden="true"></i><?php echo $schloss; ?></span><?php endif; ?></div>
            <?php if ($datum): ?><div class="p-list-meta"><?php echo $datum; ?></div><?php endif; ?>
            <?php if (!empty($doc['beschreibung'])): ?><div class="p-list-meta mt-1"><?php echo htmlspecialchars($doc['beschreibung']); ?></div><?php endif; ?>
        </div>
        <div class="p-list-actions">
            <button type="button" class="btn btn-sm btn-outline-primary stretched-link" aria-label="<?php echo htmlspecialchars($titel . ' öffnen'); ?>" onclick="<?php echo htmlspecialchars($oeffnen); ?>">Öffnen</button>
        </div>
    </div>
        <?php
    }
    echo '</div>';
}
