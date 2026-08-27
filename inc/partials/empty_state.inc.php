<?php
/**
 * Einheitliche Empty-State-Zeile für Tabellen.
 * Aufruf: echo msv_empty_row(6, 'Keine Einträge gefunden');
 */
if (!function_exists('msv_empty_row')) {
    function msv_empty_row(int $colspan, string $text = 'Keine Einträge gefunden', string $icon = 'bi-inbox'): string {
        return '<tr class="msv-empty-row"><td colspan="' . $colspan
             . '" class="text-center text-muted py-4"><i class="bi ' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8')
             . ' d-block mb-2" style="font-size:1.6rem;opacity:.5;"></i>'
             . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</td></tr>';
    }
}
