<?php
/**
 * Raster-Erfassung (Heimmeisterschaft, Kantonalstich): Markup der Tabellen-Card und der Schnellerfassung.
 * Verhalten: js/msv-raster-erfassen.js (MsvRaster.init), Aussehen: Block «Raster-Erfassung» in css/msv-ui.css.
 *
 * $r = [
 *   'kennung'    => 'heimresultate',  // Form #<kennung>Form, Tabelle #<kennung>Tabelle, Hilfe <kennung>.tabelle/.schnellerfassung
 *   'passen'     => 8,
 *   'min_breite' => 880,              // Mindestbreite des Rasters in px (darunter scrollt es seitwärts)
 *   'sort_rang'  => false,            // Sortierung «Rang» aktiv (?sortierung=rang)
 *   'csrf'       => $csrf,
 * ]
 * Aufruf: msv_raster_select2() nach dem Header, msv_raster_tabelle($r) in .main-content-wrapper.ui-vollhoehe
 *         nach der Kopf-Card, msv_raster_panel($r) nach dem Container (das Panel liegt fest über der Seite).
 */
if (!function_exists('msv_raster_tabelle')) {
    // Select2 (Schützenwahl in der Schnellerfassung), Stil im Block «Raster-Erfassung». defer: hält den Seitenaufbau
    // nicht an; fehlt Select2 (CDN nicht erreichbar), bleibt die normale Auswahlliste.
    function msv_raster_select2(): void {
        ?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js" defer></script>
        <?php
    }

    function msv_raster_tabelle(array $r): void {
        $k = htmlspecialchars($r['kennung'], ENT_QUOTES, 'UTF-8');
        $n = (int) $r['passen'];
        $rang = !empty($r['sort_rang']);
        ?>
                <form id="<?= $k ?>Form" class="ui-karte ui-vollhoehe-karte" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($r['csrf'], ENT_QUOTES, 'UTF-8') ?>">

                    <div class="ui-tab-kopf">
                        <span class="ui-tab-titel">Mitglieder <button type="button" class="btn-help" data-help="<?= $k ?>.tabelle" aria-label="Hilfe"></button></span>
                        <span class="hk-steuer-label" aria-hidden="true">Sortierung</span>
                        <div class="ui-filter" role="group" aria-label="Sortierung" id="hkSortierung" data-tooltip="«Rang»: nach Kategorie und Total, ordnet nach dem Speichern neu">
                            <button type="button" data-sort="name" aria-pressed="<?= $rang ? 'false' : 'true' ?>">Name</button>
                            <button type="button" data-sort="rang" aria-pressed="<?= $rang ? 'true' : 'false' ?>">Rang</button>
                        </div>
                        <div class="ui-filter d-none d-md-flex" role="group" aria-label="Nach Resultaten filtern" id="hkFilterGruppe">
                            <button type="button" data-filter="alle" aria-pressed="true">Alle <span id="nAlle">0</span></button>
                            <button type="button" data-filter="mit" aria-pressed="false">Mit Resultaten <span id="nMit">0</span></button>
                            <button type="button" data-filter="ohne" aria-pressed="false">Ohne <span id="nOhne">0</span></button>
                        </div>
                        <label class="ui-suche d-none d-md-flex">
                            <i class="bi bi-search" aria-hidden="true"></i>
                            <span class="visually-hidden">Mitglied suchen</span>
                            <input type="search" id="hkSuche" placeholder="Mitglied suchen" autocomplete="off">
                        </label>
                    </div>
                    <div class="alert alert-danger small py-2 px-3 msv-eingabe-fehler" id="hkFehler" role="alert" hidden></div>

                    <!-- Laptop: Raster -->
                    <div class="desktop-table-container">
                        <div class="ui-scroll hk-scroll" id="hkScroll">
                            <table class="table mb-0 ui-raster" id="<?= $k ?>Tabelle" style="--raster-min-breite: <?= (int) $r['min_breite'] ?>px;">
                                <thead>
                                    <tr>
                                        <th scope="col">Rang · Mitglied</th>
                                        <?php for ($p = 1; $p <= $n; $p++): ?><th scope="col">Passe <?= $p ?></th><?php endfor; ?>
                                        <th scope="col">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="ui-leer">
                                        <td colspan="<?= $n + 2 ?>"><div class="spinner-border spinner-border-sm me-2" aria-hidden="true"></div>Lade Resultate …</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Handy: Karte pro Mitglied (zentrales Kartendesign) -->
                    <div class="mobile-cards-container">
                        <div class="mobile-search raster-mobil-suche">
                            <div class="position-relative">
                                <i class="bi bi-search search-icon" aria-hidden="true"></i>
                                <input type="search" class="form-control" id="rasterSucheMobil" placeholder="Mitglied suchen …" aria-label="Mitglied suchen" autocomplete="off">
                            </div>
                            <span class="raster-mobil-zaehler" id="rasterMobilZaehler" aria-live="polite"></span>
                        </div>
                        <div class="mobile-cards-scroll" id="rasterKarten"></div>
                    </div>

                    <!-- Handy: Speichern bleibt erreichbar, sobald etwas nicht gespeichert und der Kopf weggescrollt ist -->
                    <div class="raster-speicherleiste" id="rasterSpeicherleiste" hidden>
                        <span class="raster-speicherleiste-text"><span class="ui-punkt" aria-hidden="true"></span><span id="rasterSpeicherleisteText"></span></span>
                        <button type="submit" class="btn btn-primary" id="rasterSpeicherleisteBtn"><i class="bi bi-save me-1" aria-hidden="true"></i>Speichern</button>
                    </div>

                    <div class="ui-tasten d-none d-md-flex">
                        <span><kbd>Enter</kbd> / <kbd>Tab</kbd> nächstes Feld</span>
                        <span><kbd>↑</kbd> <kbd>↓</kbd> gleiche Passe, andere Zeile</span>
                        <span><kbd>Ctrl</kbd>+<kbd>S</kbd> speichern</span>
                        <span>Name anklicken oder <kbd>Enter</kbd> auf dem Namen: Schnellerfassung</span>
                    </div>
                    <span id="hkBesteText" hidden>beste Passe</span>
                </form>
        <?php
    }

    function msv_raster_panel(array $r): void {
        $k = htmlspecialchars($r['kennung'], ENT_QUOTES, 'UTF-8');
        $n = (int) $r['passen'];
        // 8 Passen in zwei Reihen zu 4, bis 5 Passen in einer Reihe
        $spalten = $n <= 5 ? $n : (int) ceil($n / 2);
        ?>
<!-- Schnellerfassung: Slide-Panel -->
<div class="panel-overlay" id="entryOverlay"></div>
<div class="hybrid-edit-panel ui-erfassen ui-raster-panel" id="entryPanel" style="--panel-width: 540px;">
    <div class="panel-header">
        <div class="min-w-0">
            <div class="d-flex align-items-center gap-2"><h2 class="mb-0" id="entryName">Erfassen</h2><button type="button" class="btn-help" data-help="<?= $k ?>.schnellerfassung" aria-label="Hilfe"></button></div>
            <small class="panel-pos" id="entrySubtitle"></small>
        </div>
        <div class="d-flex align-items-center gap-1 ms-auto">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="entryPrev" data-tooltip="Vorheriger Schütze" aria-label="Vorheriger Schütze">
                <i class="bi bi-chevron-up" aria-hidden="true"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="entryNext" data-tooltip="Nächster Schütze" aria-label="Nächster Schütze">
                <i class="bi bi-chevron-down" aria-hidden="true"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="entryClose" data-tooltip="Schliessen (Esc)" aria-label="Schliessen (Esc)">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div>
    </div>
    <div class="panel-body">
        <label class="hk-feldtitel" for="entryMemberSelect">Schütze wechseln</label>
        <div class="mb-3">
            <select id="entryMemberSelect" class="form-select form-select-sm"></select>
        </div>
        <div class="hk-erfassung">
            <div class="hk-erfassung-kopf">
                <span class="hk-erfassung-titel">Passen</span>
                <span class="hk-total" id="entryTotalBadge"><span>Total</span>0</span>
            </div>
            <div class="entry-passen-grid" id="entryPassenGrid" style="--raster-spalten: <?= $spalten ?>;"></div>
        </div>
        <p class="hk-hinweis">Enter springt ins nächste Feld, im letzten Feld wird gespeichert. Leere Felder vor einer späteren Passe werden als 0 gespeichert; ein geleertes Feld löscht die gespeicherte Passe.</p>
    </div>
    <div class="panel-footer">
        <div class="d-flex gap-2 w-100 align-items-center">
            <span class="small text-muted me-auto d-none d-md-inline"><kbd class="ui-kbd">Ctrl</kbd>+<kbd class="ui-kbd">Enter</kbd> weiter</span>
            <button type="button" class="btn btn-outline-primary btn-sm" id="entrySaveBtn">
                <i class="bi bi-save me-1" aria-hidden="true"></i>Speichern
            </button>
            <button type="button" class="btn btn-primary btn-sm" id="entrySaveNextBtn">
                Speichern &amp; Weiter <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</div>
        <?php
    }
}
