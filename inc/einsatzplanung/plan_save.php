<?php
/**
 * inc/einsatzplanung/plan_save.php – Plan anlegen / kopieren / Metadaten ändern
 *
 * POST action=create   typ, titel, jahr, [termine_aus_jm=1]           → neuer leerer Plan (Standard-Funktionen für Obli/Feld)
 * POST action=copy     quelle_id, titel, jahr, [termine_aus_jm=1]     → Kopie (Endstand nach Tausch)
 * POST action=update   plan_id, titel, fusstext                        → Metadaten
 * Antwort: {success, plan_id, message}
 */
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
require_once __DIR__ . '/plan_helpers.inc.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') ep_json(['success' => false, 'message' => 'Methode nicht erlaubt'], 405);
csrf_require(true);

$db     = getDB();
$action = $_POST['action'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);

try {
    if ($action === 'create' || $action === 'copy') {
        $jahr  = (int)($_POST['jahr'] ?? 0);
        $titel = trim((string)($_POST['titel'] ?? ''));
        $ausJm = !empty($_POST['termine_aus_jm']);
        if ($jahr < 2000 || $jahr > 2100) ep_json(['success' => false, 'message' => 'Ungültiges Jahr'], 422);
        if ($titel === '') ep_json(['success' => false, 'message' => 'Titel fehlt'], 422);

        $db->beginTransaction();
        if ($action === 'copy') {
            $quelle = ep_plan_laden($db, (int)($_POST['quelle_id'] ?? 0));
            if (!$quelle) { $db->rollBack(); ep_json(['success' => false, 'message' => 'Quellplan nicht gefunden'], 404); }
            $planId = ep_plan_kopieren($db, $quelle, $jahr, mb_substr($titel, 0, 100), $userId, $ausJm);
            $msg = 'Plan aus «' . $quelle['titel'] . '» kopiert';
        } else {
            $typ = $_POST['typ'] ?? 'sonstiges';
            if (!isset(EP_TYPEN[$typ])) ep_json(['success' => false, 'message' => 'Ungültiger Typ'], 422);
            $layout = ep_layout_fuer_typ($typ);
            // Stammdaten (Funktionen, Rollen, Farbe, Fusstext) aus einem bestehenden Plan gleichen Typs übernehmen – ohne Personen
            $vorlage = null;
            if ((int)($_POST['struktur_von'] ?? 0) > 0) {
                $vorlage = ep_plan_laden($db, (int)$_POST['struktur_von']);
                if ($vorlage && $vorlage['typ'] !== $typ) $vorlage = null;
            }
            $db->prepare("INSERT INTO einsatz_plaene (jahr, typ, titel, layout, status, fusstext, erstellt_von) VALUES (?, ?, ?, ?, 'entwurf', ?, ?)")
               ->execute([$jahr, $typ, mb_substr($titel, 0, 100), $vorlage ? $vorlage['layout'] : $layout, $vorlage ? $vorlage['fusstext'] : ep_fusstext_default($typ), $userId]);
            $planId = (int)$db->lastInsertId();
            if ($vorlage) {
                try { $db->prepare("UPDATE einsatz_plaene SET farbe = ?, vorlage_plan_id = ? WHERE id = ?")->execute([$vorlage['farbe'] ?? null, (int)$vorlage['id'], $planId]); } catch (Throwable $e) {}
                $layout = $vorlage['layout'];
            }

            // Termine aus JM-Definition (Obli/Feld); sonst aus der Vorlage auf den gleichen Wochentag verschoben
            $nTermine = 0;
            if ($ausJm) {
                $insT = $db->prepare("INSERT INTO einsatz_plan_termine (plan_id, bezeichnung, datum, zeit_von, zeit_bis, sort) VALUES (?, ?, ?, ?, ?, ?)");
                foreach (ep_termine_aus_jm($db, $typ, $jahr) as $i => $t) {
                    $insT->execute([$planId, ep_termin_bezeichnung_auto($typ, $i), $t['datum'], $t['zeit_von'], $t['zeit_bis'], $i]);
                    $nTermine++;
                }
            }
            if ($nTermine === 0 && $vorlage && $vorlage['termine']) {
                ep_termine_uebernehmen($db, $vorlage, $planId, $jahr);
                $nTermine = count($vorlage['termine']);
                $termineHinweis = ', Termine auf den gleichen Wochentag verschoben – bitte prüfen';
            }
            // Startfunktionen: aus der Vorlage (Gruppe, Bezeichnung, Anzahl, Rolle) oder Standard je Typ
            $start = [];
            if ($vorlage) {
                foreach ($vorlage['funktionen'] as $f) $start[] = [(string)($f['gruppe'] ?? ''), $f['bezeichnung'], (int)$f['anzahl'], $f['rolle'] ?? null];
            } elseif ($typ === 'obligatorisch') {
                $start = [['Büro', 'Anmeldung', 2], ['Büro', 'Munition', 2], ['', 'EDV – Erfassung / Abrechnung', 1], ['', 'Standchef', 1],
                          ['', 'Schützenmeister', 5], ['', 'Warner', 5], ['', 'Parkplatzdienst', 1], ['', 'Türkontrolle Eingang Schützenhaus', 1], ['', 'Türkontrolle Ausgang (300m)', 1]];
            } elseif ($typ === 'feldschiessen') {
                $start = [['', 'EDV – Erfassung / Abrechnung', 1], ['Büro', 'Anmeldung', 1], ['Büro', 'Einteilung + Munition', 1], ['', 'Standchef', 1],
                          ['', 'Schützenmeister', 1], ['', 'Warner', 1], ['', 'Standblatt-Kurier', 1], ['', 'Speaker / Warner', 1], ['', 'Türkontrolle Eingang', 1]];
            } elseif ($typ === 'schlossturm') {
                // Funktionen der OK-Einsatzliste (drei Vereine) mit Anfrage-Rolle; Vereinszuteilung je Position im Raster
                $start = [['', 'Parkdienst', 3, 'Parkdienst'], ['', 'Türkontrolle', 1, 'Türkontrolle'], ['', 'Standblätter', 1, 'Büro'], ['', 'Kasse', 1, 'Büro'], ['', 'Munition', 1, 'Büro'],
                          ['', 'Auszahlung', 1, 'Büro'], ['', 'Auszeichnungen', 1, 'Büro'], ['', 'EDV / Anlage', 1, 'OK'], ['', 'Schiessleitung', 1, 'OK'], ['', 'Schützenmeister', 3, 'Schützenmeister'],
                          ['', 'Kurier', 1, 'OK'], ['', 'Znüni / Zvieri', 2, 'OK'], ['', 'Warner', 11, 'Warner']];
            } elseif ($typ === 'chilbi') {
                $start = [['', 'Zelt Aufstellen', 0], ['', 'Aufstellen', 0], ['', 'Chef', 0], ['', 'Chef / Musik', 0], ['', 'Einsatz Stand', 0], ['', 'Einsatz Bar', 0], ['', 'Aufräumen', 0]];
            }
            $insF = $db->prepare("INSERT INTO einsatz_plan_funktionen (plan_id, gruppe, bezeichnung, rolle, anzahl, sort) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($start as $i => $row) { [$g, $b, $a] = $row; $insF->execute([$planId, $g !== '' ? $g : null, $b, $row[3] ?? null, $a, ($i + 1) * 10]); }
            $msg = 'Plan angelegt';
        }
        $db->commit();
        ep_slots_sicherstellen($db, $planId);
        ep_ok_funktionen_anwenden($db, $planId);   // Funktionen «immer vom OK besetzt» (settings), nur Schlossturm
        if ($action === 'create' && !empty($vorlage)) $msg .=' – Funktionen, Farbe und Fusstext aus «' . $vorlage['titel'] . '» übernommen' . ($termineHinweis ?? '');
        ep_json(['success' => true, 'plan_id' => $planId, 'message' => $msg]);
    }

    if ($action === 'update') {
        $planId = (int)($_POST['plan_id'] ?? 0);
        $plan = ep_plan_laden($db, $planId);
        if (!$plan) ep_json(['success' => false, 'message' => 'Plan nicht gefunden'], 404);
        $titel = trim((string)($_POST['titel'] ?? $plan['titel']));
        if ($titel === '') ep_json(['success' => false, 'message' => 'Titel fehlt'], 422);
        $fusstext = array_key_exists('fusstext', $_POST) ? trim(str_replace("\r", '', (string)$_POST['fusstext'])) : $plan['fusstext'];
        $db->prepare("UPDATE einsatz_plaene SET titel = ?, fusstext = ? WHERE id = ?")
           ->execute([mb_substr($titel, 0, 100), $fusstext !== '' ? $fusstext : null, $planId]);
        if (array_key_exists('farbe', $_POST)) {
            // Titelfarbe (#RRGGBB) für Word/PDF; leer = Standardgrau. Migration 055
            $farbe = trim((string)$_POST['farbe']);
            $farbe = preg_match('/^#?[0-9a-fA-F]{6}$/', $farbe) ? '#' . strtoupper(ltrim($farbe, '#')) : null;
            if ($farbe === '#D9D9D9') $farbe = null;
            try { $db->prepare("UPDATE einsatz_plaene SET farbe = ? WHERE id = ?")->execute([$farbe, $planId]); }
            catch (Throwable $e) { error_log('[einsatzplanung/plan_save] farbe: ' . $e->getMessage()); }
        }
        if (array_key_exists('umfrage_id', $_POST)) {
            // Verknüpfung zur Portal-Umfrage (Quelle der Verfügbarkeiten); Migration 053
            try { $db->prepare("UPDATE einsatz_plaene SET umfrage_id = ? WHERE id = ?")->execute([(int)$_POST['umfrage_id'] > 0 ? (int)$_POST['umfrage_id'] : null, $planId]); }
            catch (Throwable $e) { error_log('[einsatzplanung/plan_save] umfrage_id: ' . $e->getMessage()); }
        }
        ep_projizieren_wenn_freigegeben($db, $planId);
        ep_json(['success' => true, 'plan_id' => $planId, 'message' => 'Gespeichert']);
    }

    ep_json(['success' => false, 'message' => 'Unbekannte Aktion'], 400);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[einsatzplanung/plan_save] ' . $e->getMessage());
    ep_json(['success' => false, 'message' => 'Plan konnte nicht gespeichert werden'], 500);
}
