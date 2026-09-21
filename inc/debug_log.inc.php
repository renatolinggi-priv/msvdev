<?php
/**
 * debug_log.inc.php
 *
 * Schalter fuer Entwicklungs-Ausgaben im PHP-Error-Log.
 *
 * Ausgangslage (09.2026): rund die Haelfte aller Zeilen in
 * /home/bdebbd4/php_error.log waren Ablaufprotokolle wie «Lade Endstich-Daten»
 * oder JM-Berechnungen pro Mitglied. Dazwischen gingen echte Fehler unter.
 * Solche Ausgaben laufen neu ueber msv_debug_log() und erscheinen nur, wenn
 * das Debug-Logging eingeschaltet ist.
 *
 * Echte Fehler bleiben unveraendert bei error_log() - die sollen immer kommen.
 *
 * EINSCHALTEN, zwei Wege:
 *   1. In msvjm_config.php (eine Ebene ueber dem Projekt):  'debug_log' => true
 *   2. Vor dem Einbinden:  define('MSV_DEBUG_LOG', true);
 *
 * Standard ist AUS.
 */

/** Ist das Debug-Logging eingeschaltet? Ergebnis wird einmal ermittelt. */
function msv_debug_log_aktiv(): bool
{
    static $aktiv = null;
    if ($aktiv !== null) {
        return $aktiv;
    }
    if (defined('MSV_DEBUG_LOG')) {
        return $aktiv = (bool)MSV_DEBUG_LOG;
    }
    $aktiv = false;
    $pfad = __DIR__ . '/../config.php';
    if (is_file($pfad)) {
        try {
            $conf  = require $pfad;
            $aktiv = !empty($conf['debug_log']);
        } catch (Throwable $e) {
            $aktiv = false;   // Konfiguration unlesbar -> lieber still
        }
    }
    return $aktiv;
}

/**
 * Schreibt eine Entwicklungs-Ausgabe, sofern eingeschaltet.
 *
 * @param string $bereich Kurzes Kuerzel, erscheint in eckigen Klammern
 * @param string $text    Die Meldung
 */
function msv_debug_log(string $bereich, string $text): void
{
    if (!msv_debug_log_aktiv()) {
        return;
    }
    error_log('[' . $bereich . '] ' . $text);
}

/**
 * Wie msv_debug_log(), aber mit Zusatzangaben als Array - passend zu den
 * bestehenden logError($message, $context)-Aufrufen in den Lade-Skripten.
 */
function msv_debug_log_ctx(string $bereich, string $text, array $kontext = []): void
{
    if (!msv_debug_log_aktiv()) {
        return;
    }
    $zusatz = $kontext ? ' Context: ' . json_encode($kontext, JSON_UNESCAPED_UNICODE) : '';
    error_log('[' . $bereich . '] ' . $text . $zusatz);
}
