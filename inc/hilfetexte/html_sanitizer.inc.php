<?php
/**
 * inc/hilfetexte/html_sanitizer.inc.php – Whitelist-Sanitizer für Hilfetexte.
 *
 * Wird beim Speichern von hilfetexte.inhalt_html angewendet, damit nur sichere Tags und
 * Attribute persistiert werden. Bei der Anzeige (msv-help.js) wird das gespeicherte HTML
 * ohne weiteres Escaping gerendert – der Inhalt ist bereits sicher.
 *
 * Erlaubte Tags: p, br, strong, em, b, i, u, ul, ol, li, code, pre, h4, h5, h6, a
 * Erlaubte Attribute: nur href auf <a> (http, https, mailto, relative Seiten-Links).
 * Externe Links (http/https) öffnen im neuen Tab, interne im selben.
 *
 * Portiert aus jungschuetzen.sksg.ch/shared/html_sanitizer.php.
 */

const HILFE_HTML_ALLOWED_TAGS = '<p><br><strong><em><b><i><u><ul><ol><li><code><pre><h4><h5><h6><a>';

if (!function_exists('hilfeSanitizeHtml')) {
    function hilfeSanitizeHtml(string $html): string
    {
        // 1. Nur erlaubte Tags behalten (entfernt <script>, <style>, <iframe>, <img> ...).
        $clean = strip_tags($html, HILFE_HTML_ALLOWED_TAGS);

        // 2. <a>: nur href mit sicherem Schema, alle anderen Attribute weg.
        $clean = preg_replace_callback('/<a\b([^>]*)>/i', static function ($m) {
            $attrs = $m[1];
            if (preg_match('/href\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $attrs, $h)) {
                $href = $h[2] !== '' ? $h[2] : ($h[3] ?? '');
                $istExtern = (bool)preg_match('#^https?://#i', $href);
                $istOk = $istExtern
                    || preg_match('#^mailto:[^\s]+@#i', $href)
                    || preg_match('#^(\.\./)?[a-z0-9._/\-]+\.php(\?[^\s"\']*)?$#i', $href);
                if ($istOk && stripos($href, 'javascript:') === false) {
                    $hrefSafe = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
                    return $istExtern
                        ? '<a href="' . $hrefSafe . '" rel="noopener noreferrer" target="_blank">'
                        : '<a href="' . $hrefSafe . '">';
                }
            }
            return '<a>';
        }, $clean);

        // 3. Defensive: on*-Handler auf erlaubten Tags entfernen.
        $clean = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^>\s]+)/i', '', $clean);

        return trim($clean);
    }
}
