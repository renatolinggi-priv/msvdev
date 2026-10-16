<?php
/**
 * Glocke / Mitteilungen: was zählt als «neu»?
 *
 * Ungelesen und nicht abgelaufen. Erinnerungen (Einsätze, JM-Tage, Termine, Umfragen) tragen seit Migration 120
 * das Datum, für das sie gelten (gilt_bis = Termin bzw. Antwortfrist). Danach zählen sie nicht mehr als neu,
 * auch wenn niemand sie geöffnet hat. Mitteilungen ohne Datum (Vorstand, Chat, Tausch, Fotos) bleiben neu,
 * bis sie gelesen sind.
 *
 * Läuft auch, solange die Migration noch nicht ausgeführt ist: dann ohne Ablauf wie bisher.
 */
if (!function_exists('inboxHatGiltBis')) {
    function inboxHatGiltBis(PDO $db): bool
    {
        static $hat = null;
        if ($hat === null) {
            try {
                $hat = (bool) $db->query("SHOW COLUMNS FROM benachrichtigungen_inbox LIKE 'gilt_bis'")->fetch();
            } catch (Throwable $e) {
                $hat = false;
            }
        }
        return $hat;
    }

    /** SQL-Bedingung für «neu» (ungelesen und nicht abgelaufen). */
    function inboxNeuBedingung(PDO $db): string
    {
        return 'gelesen_am IS NULL' . (inboxHatGiltBis($db) ? ' AND (gilt_bis IS NULL OR gilt_bis >= CURDATE())' : '');
    }

    /** Spalte «abgelaufen» (0/1) für die Liste. */
    function inboxAbgelaufenSpalte(PDO $db): string
    {
        return inboxHatGiltBis($db) ? '(gilt_bis IS NOT NULL AND gilt_bis < CURDATE()) AS abgelaufen' : '0 AS abgelaufen';
    }

    /** Anzahl neuer Mitteilungen eines Benutzers (Badge). */
    function inboxNeuAnzahl(PDO $db, int $userId): int
    {
        $s = $db->prepare('SELECT COUNT(*) FROM benachrichtigungen_inbox WHERE user_id = ? AND ' . inboxNeuBedingung($db));
        $s->execute([$userId]);
        return (int) $s->fetchColumn();
    }
}
