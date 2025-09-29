<?php
// endsch_import/import_handler.php – Backend-Endpoint (ALLE Stiche)
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

$dbconnect_path = file_exists('../dbconnect.inc.php') ? '../dbconnect.inc.php' : 'dbconnect.inc.php';
require_once $dbconnect_path;

function db_mysqli() {
    if (function_exists('connect_db_mysqli')) return connect_db_mysqli();
    $config_path = file_exists('../config.php') ? '../config.php' : 'config.php';
    require_once $config_path;
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($mysqli->connect_errno) throw new Exception('DB connect error: '.$mysqli->connect_error);
    $mysqli->set_charset('utf8mb4');
    return $mysqli;
}
function json_ok($arr=[]) { echo json_encode(['success'=>true]+$arr); exit; }
function json_err($msg, $extra=[]) { echo json_encode(['success'=>false,'message'=>$msg]+$extra); exit; }

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    if ($action === 'get_all_stich_definitions') {
        $db = db_mysqli();
        // Die 6 gewünschten Stiche (inkl. Sie und Er)
        $stmt = $db->prepare("
            SELECT stich, restable, nummer1, nummer2, nummer3
            FROM interne_stichdefinition
            WHERE stich IN ('Endstich','Kunst','Glück','Zabig','Schwini','Sie und Er')
        ");
        $stmt->execute();
        $res = $stmt->get_result();
        $map = []; // program_number => {stich, restable}
        while ($row = $res->fetch_assoc()) {
            foreach (['nummer1','nummer2','nummer3'] as $k) {
                if (!empty($row[$k])) {
                    $num = (string)$row[$k];
                    $map[$num] = ['stich'=>$row['stich'], 'restable'=>$row['restable']];
                }
            }
        }
        $stmt->close();
        json_ok(['program_map'=>$map]); // z.B. {"522":{"stich":"Endstich","restable":"endstich"},...}
    }

    if ($action === 'find_member_by_license') {
        $license = trim($_GET['license'] ?? '');
        if (!$license) json_err('license fehlt');
        $db = db_mysqli();
        
        // DEBUG: Log the license search
        error_log("[ENDSCH-DEBUG] Searching for license: " . $license);
        
        // Die Lizenznummer entspricht in dieser DB der Member ID
        $sql = "SELECT ID, CONCAT(Name,' ',Vorname) AS Vollname FROM mitglieder WHERE ID=? LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('s', $license);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        
        error_log("[ENDSCH-DEBUG] Query result: " . print_r($row, true));
        
        $stmt->close();
        if ($row) {
            error_log("[ENDSCH-DEBUG] Member found: ID=" . $row['ID'] . ", Name=" . $row['Vollname']);
            json_ok(['member_id'=>(int)$row['ID'], 'member_name'=>$row['Vollname']]);
        }
        
        error_log("[ENDSCH-DEBUG] No member found for license: " . $license);
        json_ok(['member_id'=>null]);
    }

    if ($action === 'check_existing_data') {
        $mitglied_id = (int)($_GET['mitglied_id'] ?? 0);
        $jahr = (int)($_GET['jahr'] ?? date('Y'));
        if (!$mitglied_id || !$jahr) json_err('Parameter fehlen');
        
        $db = db_mysqli();
        
        // Prüfe alle 5 Stiche auf bestehende Daten mit Details
        $existing_data = [];
        $detailed_data = [];
        
        // Endstich
        $stmt = $db->prepare("SELECT Schuss1,Schuss2,Schuss3,Schuss4,Schuss5,Schuss6,Schuss7,Schuss8,Schuss9,Schuss10,Tiefschuss,AbsendenAnmeldung FROM endstich WHERE MitgliedID=? AND Jahr=? LIMIT 1");
        $stmt->bind_param('ii', $mitglied_id, $jahr);
        $stmt->execute();
        $endstich = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($endstich) {
            $existing_data[] = 'Endstich';
            $shots = array_filter([$endstich['Schuss1'],$endstich['Schuss2'],$endstich['Schuss3'],$endstich['Schuss4'],$endstich['Schuss5'],$endstich['Schuss6'],$endstich['Schuss7'],$endstich['Schuss8'],$endstich['Schuss9'],$endstich['Schuss10']], fn($v) => $v !== null);
            $total = array_sum($shots);
            $anmeldungen = isset($endstich['AbsendenAnmeldung']) ? $endstich['AbsendenAnmeldung'] : 0;
            $detailed_data['Endstich'] = [
                'total' => $total,
                'tiefschuss' => $endstich['Tiefschuss'],
                'shots' => $shots,
                'anmeldungen' => $anmeldungen,
                'display' => "Total: $total (Tiefschuss: {$endstich['Tiefschuss']}, Anmeldungen: $anmeldungen)"
            ];
        }
        
        // Kunst
        $stmt = $db->prepare("SELECT KSchuss1,KSchuss2,KSchuss3,KSchuss4,KSchuss5 FROM kunst WHERE MitgliedID=? AND Jahr=? LIMIT 1");
        $stmt->bind_param('ii', $mitglied_id, $jahr);
        $stmt->execute();
        $kunst = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($kunst) {
            $existing_data[] = 'Kunst';
            $shots = array_filter([$kunst['KSchuss1'],$kunst['KSchuss2'],$kunst['KSchuss3'],$kunst['KSchuss4'],$kunst['KSchuss5']], fn($v) => $v !== null);
            $total = array_sum($shots);
            $detailed_data['Kunst'] = [
                'total' => $total,
                'shots' => $shots,
                'display' => "Total: $total (" . implode(', ', $shots) . ")"
            ];
        }
        
        // Glück
        $stmt = $db->prepare("SELECT GSchuss1,GSchuss2,GSchuss3 FROM glueck WHERE MitgliedID=? AND Jahr=? LIMIT 1");
        $stmt->bind_param('ii', $mitglied_id, $jahr);
        $stmt->execute();
        $glueck = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($glueck) {
            $existing_data[] = 'Glück';
            $shots = array_filter([$glueck['GSchuss1'],$glueck['GSchuss2'],$glueck['GSchuss3']], fn($v) => $v !== null);
            $max = $shots ? max($shots) : 0;
            $detailed_data['Glück'] = [
                'total' => $max,
                'shots' => $shots,
                'display' => "Maximum: $max (" . implode(', ', $shots) . ")"
            ];
        }
        
        // Zabig
        $stmt = $db->prepare("SELECT ZSchuss1,ZSchuss2,ZSchuss3,ZSchuss4,ZSchuss5,ZSchuss6,Ansage FROM zabig WHERE MitgliedID=? AND Jahr=? LIMIT 1");
        $stmt->bind_param('ii', $mitglied_id, $jahr);
        $stmt->execute();
        $zabig = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($zabig) {
            $existing_data[] = 'Zabig';
            $shots = array_filter([$zabig['ZSchuss1'],$zabig['ZSchuss2'],$zabig['ZSchuss3'],$zabig['ZSchuss4'],$zabig['ZSchuss5'],$zabig['ZSchuss6']], fn($v) => $v !== null);
            $total = array_sum($shots);
            $detailed_data['Zabig'] = [
                'total' => $total,
                'shots' => $shots,
                'ansage' => $zabig['Ansage'],
                'display' => "Total: $total (" . implode(', ', $shots) . ")"
            ];
        }
        
        // Schwini
        $stmt = $db->prepare("SELECT P1Schuss1,P1Schuss2,P1Schuss3,P1Schuss4,P1Schuss5,P1Schuss6,P2Schuss1,P2Schuss2,P2Schuss3,P2Schuss4,P2Schuss5,P2Schuss6 FROM schwini WHERE MitgliedID=? AND Jahr=? LIMIT 1");
        $stmt->bind_param('ii', $mitglied_id, $jahr);
        $stmt->execute();
        $schwini = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($schwini) {
            $p1_shots = array_filter([$schwini['P1Schuss1'],$schwini['P1Schuss2'],$schwini['P1Schuss3'],$schwini['P1Schuss4'],$schwini['P1Schuss5'],$schwini['P1Schuss6']], fn($v) => $v !== null);
            $p2_shots = array_filter([$schwini['P2Schuss1'],$schwini['P2Schuss2'],$schwini['P2Schuss3'],$schwini['P2Schuss4'],$schwini['P2Schuss5'],$schwini['P2Schuss6']], fn($v) => $v !== null);
            
            $p1_total = array_sum($p1_shots);
            $p2_total = array_sum($p2_shots);
            
            // P1 als separater Eintrag
            if (count($p1_shots) > 0) {
                $existing_data[] = 'Schwini P1';
                $detailed_data['Schwini P1'] = [
                    'total' => $p1_total,
                    'shots' => $p1_shots,
                    'display' => "Total P1: $p1_total (" . implode(',', $p1_shots) . ")"
                ];
            }
            
            // P2 als separater Eintrag
            if (count($p2_shots) > 0) {
                $existing_data[] = 'Schwini P2';
                $detailed_data['Schwini P2'] = [
                    'total' => $p2_total,
                    'shots' => $p2_shots,
                    'display' => "Total P2: $p2_total (" . implode(',', $p2_shots) . ")"
                ];
            }
        }
        
        // Sie und Er (aus endresultate_partner Tabelle)
        $stmt = $db->prepare("SELECT SieErSchuss1,SieErSchuss2,SieErSchuss3,SieErSchuss4,SieErSchuss5,SieErSchuss6,SieErSchuss7,SieErSchuss8,SieErSchuss9,SieErSchuss10 FROM endresultate_partner WHERE MitgliedID=? AND Jahr=? LIMIT 1");
        $stmt->bind_param('ii', $mitglied_id, $jahr);
        $stmt->execute();
        $sieunder = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($sieunder) {
            // Prüfe ob Sie und Er Daten vorhanden sind (Schuss 6-10)
            $shots_6_10 = array_filter([
                $sieunder['SieErSchuss6'],
                $sieunder['SieErSchuss7'],
                $sieunder['SieErSchuss8'],
                $sieunder['SieErSchuss9'],
                $sieunder['SieErSchuss10']
            ], fn($v) => $v !== null);
            
            if (count($shots_6_10) > 0) {
                $existing_data[] = 'Sie und Er';
                $total = array_sum($shots_6_10);
                $detailed_data['Sie und Er'] = [
                    'total' => $total,
                    'shots' => $shots_6_10,
                    'display' => "Total: $total (" . implode(', ', $shots_6_10) . ")"
                ];
            }
        }
        
        if (count($existing_data) > 0) {
            json_ok([
                'success' => true,
                'has_existing' => true,
                'existing_stiche' => $existing_data,
                'detailed_data' => $detailed_data,
                'message' => 'Bereits vorhandene Daten: ' . implode(', ', $existing_data)
            ]);
        } else {
            json_ok([
                'success' => true,
                'has_existing' => false,
                'existing_stiche' => [],  // WICHTIG: Leeres Array statt undefined
                'detailed_data' => []
            ]);
        }
    }

    if ($action === 'import_stich_shots') {
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) json_err('CSRF ungültig');

        $mitglied_id = (int)($_POST['mitglied_id'] ?? 0);
        $jahr = (int)($_POST['jahr'] ?? date('Y'));
        $program_number = trim($_POST['program_number'] ?? '');
        $shots = json_decode($_POST['shots'] ?? '[]', true);
        $tiefschuss_werte = json_decode($_POST['tiefschuss_werte'] ?? '[]', true);
        
        // Zusätzliche Parameter
        $zabig_ansage = isset($_POST['zabig_ansage']) ? (int)$_POST['zabig_ansage'] : null;
        $endstich_absenden_anmeldung = isset($_POST['endstich_absenden_anmeldung']) ? (int)$_POST['endstich_absenden_anmeldung'] : null;
        
        if (!$mitglied_id || !$jahr || !$program_number || empty($shots)) json_err('Parameter unvollständig');

        $db = db_mysqli();

        // Zu welcher Tabelle gehört die Programmnummer?
        $stmt = $db->prepare("
            SELECT stich, restable 
            FROM interne_stichdefinition
            WHERE nummer1=? OR nummer2=? OR nummer3=? LIMIT 1
        ");
        $stmt->bind_param('sss', $program_number, $program_number, $program_number);
        $stmt->execute();
        $def = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$def) json_err('Programmnummer unbekannt');
        $restable = $def['restable'];
        $stich = $def['stich'];

        // existiert schon?
        $stmt = $db->prepare("SELECT ID FROM `$restable` WHERE MitgliedID=? AND Jahr=? LIMIT 1");
        $stmt->bind_param('ii', $mitglied_id, $jahr);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $clean = array_values(array_map('intval', array_filter($shots, fn($v)=> is_numeric($v))));
        $ok = false;

        $db->begin_transaction();
        try {
            switch ($restable) {
                case 'endstich': {
                    // Für Endstich: normale Wertungen für Schüsse, höchste 100er Wertung für Tiefschuss
                    $arr = array_pad(array_slice($clean, 0, 10), 10, null);
                    
                    // DEBUG: Log all relevant data
                    error_log("[ENDSCH-DEBUG] Endstich import - shots: " . json_encode($shots));
                    error_log("[ENDSCH-DEBUG] Endstich import - tiefschuss_werte: " . json_encode($tiefschuss_werte));
                    error_log("[ENDSCH-DEBUG] Endstich import - clean shots: " . json_encode($clean));
                    error_log("[ENDSCH-DEBUG] Endstich import - padded arr: " . json_encode($arr));
                    
                    // Tiefschuss aus den übertragenen 100er Wertungen berechnen
                    if (!empty($tiefschuss_werte) && is_array($tiefschuss_werte)) {
                        $clean_tiefschuss = array_values(array_map('intval', array_filter($tiefschuss_werte, fn($v) => is_numeric($v) && $v > 0)));
                        $tief = $clean_tiefschuss ? max($clean_tiefschuss) : 0;
                        error_log("[ENDSCH-DEBUG] Using tiefschuss_werte - clean: " . json_encode($clean_tiefschuss) . ", max: " . $tief);
                    } else {
                        // Fallback: wenn keine separaten Tiefschuss-Werte, dann Maximum der normalen Schüsse
                        $vals = array_filter($arr, fn($v) => $v !== null);
                        $tief = $vals ? max($vals) : 0;
                        error_log("[ENDSCH-DEBUG] Using fallback - vals: " . json_encode($vals) . ", max: " . $tief);
                    }

                    $absenden_anmeldung = $endstich_absenden_anmeldung !== null ? $endstich_absenden_anmeldung : 0;
                    
                    if ($existing) {
                        $sql = "UPDATE endstich SET Schuss1=?,Schuss2=?,Schuss3=?,Schuss4=?,Schuss5=?,Schuss6=?,Schuss7=?,Schuss8=?,Schuss9=?,Schuss10=?,Tiefschuss=?,AbsendenAnmeldung=? WHERE MitgliedID=? AND Jahr=?";
                        $stmt = $db->prepare($sql);
                        $stmt->bind_param('iiiiiiiiiiiiii', // 14 Parameter: 10 Schüsse + Tiefschuss + AbsendenAnmeldung + MitgliedID + Jahr
                            $arr[0],$arr[1],$arr[2],$arr[3],$arr[4],$arr[5],$arr[6],$arr[7],$arr[8],$arr[9],
                            $tief, $absenden_anmeldung, $mitglied_id, $jahr
                        );
                    } else {
                        $sql = "INSERT INTO endstich (MitgliedID,Schuss1,Schuss2,Schuss3,Schuss4,Schuss5,Schuss6,Schuss7,Schuss8,Schuss9,Schuss10,Tiefschuss,Jahr,AbsendenAnmeldung) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                        $stmt = $db->prepare($sql);
                        $stmt->bind_param('iiiiiiiiiiiiii', // 14 Parameter: MitgliedID + 10 Schüsse + Tiefschuss + Jahr + AbsendenAnmeldung
                            $mitglied_id,$arr[0],$arr[1],$arr[2],$arr[3],$arr[4],$arr[5],$arr[6],$arr[7],$arr[8],$arr[9],$tief,$jahr,$absenden_anmeldung
                        );
                    }
                    $ok = $stmt->execute(); $stmt->close();
                    break;
                }
                case 'kunst': {
                    $arr = array_pad(array_slice($clean, 0, 5), 5, null);
                    if ($existing) {
                        $sql = "UPDATE kunst SET KSchuss1=?,KSchuss2=?,KSchuss3=?,KSchuss4=?,KSchuss5=? WHERE MitgliedID=? AND Jahr=?";
                        $stmt = $db->prepare($sql);
                        $stmt->bind_param('iiiiiii', $arr[0],$arr[1],$arr[2],$arr[3],$arr[4], $mitglied_id, $jahr);
                    } else {
                        $sql = "INSERT INTO kunst (KSchuss1,KSchuss2,KSchuss3,KSchuss4,KSchuss5,MitgliedID,Jahr) VALUES (?,?,?,?,?,?,?)";
                        $stmt = $db->prepare($sql);
                        $stmt->bind_param('iiiiiii', $arr[0],$arr[1],$arr[2],$arr[3],$arr[4], $mitglied_id, $jahr);
                    }
                    $ok = $stmt->execute(); $stmt->close();
                    break;
                }
                case 'glueck': {
                    $arr = array_pad(array_slice($clean, 0, 3), 3, null);
                    if ($existing) {
                        $sql = "UPDATE glueck SET GSchuss1=?,GSchuss2=?,GSchuss3=? WHERE MitgliedID=? AND Jahr=?";
                        $stmt = $db->prepare($sql);
                        $stmt->bind_param('iiiii', $arr[0],$arr[1],$arr[2], $mitglied_id, $jahr);
                    } else {
                        $sql = "INSERT INTO glueck (MitgliedID,GSchuss1,GSchuss2,GSchuss3,Jahr) VALUES (?,?,?,?,?)";
                        $stmt = $db->prepare($sql);
                        $stmt->bind_param('iiiii', $mitglied_id, $arr[0],$arr[1],$arr[2], $jahr);
                    }
                    $ok = $stmt->execute(); $stmt->close();
                    break;
                }
                case 'zabig': {
                    $arr = array_pad(array_slice($clean, 0, 6), 6, null);
                    $ansage = $zabig_ansage !== null ? $zabig_ansage : 0;
                    if ($existing) {
                        $sql = "UPDATE zabig SET ZSchuss1=?,ZSchuss2=?,ZSchuss3=?,ZSchuss4=?,ZSchuss5=?,ZSchuss6=?,Ansage=? WHERE MitgliedID=? AND Jahr=?";
                        $stmt = $db->prepare($sql);
                        $stmt->bind_param('iiiiiiiii', $arr[0],$arr[1],$arr[2],$arr[3],$arr[4],$arr[5], $ansage, $mitglied_id, $jahr);
                    } else {
                        $sql = "INSERT INTO zabig (MitgliedID,ZSchuss1,ZSchuss2,ZSchuss3,ZSchuss4,ZSchuss5,ZSchuss6,Ansage,Jahr) VALUES (?,?,?,?,?,?,?,?,?)";
                        $stmt = $db->prepare($sql);
                        $stmt->bind_param('iiiiiiiii', $mitglied_id,$arr[0],$arr[1],$arr[2],$arr[3],$arr[4],$arr[5], $ansage, $jahr);
                    }
                    $ok = $stmt->execute(); $stmt->close();
                    break;
                }
                case 'schwini': {
                    // Schwini: 6 Schüsse pro Import
                    // Erste Passe → P1Schuss1-6, Zweite Passe → P2Schuss1-6
                    $current_shots = array_pad(array_slice($clean, 0, 6), 6, null);
                    
                    if ($existing) {
                        // Prüfe ob P1 leer ist (erste Passe) oder schon gefüllt (zweite Passe)
                        $check_sql = "SELECT P1Schuss1 FROM schwini WHERE MitgliedID=? AND Jahr=? LIMIT 1";
                        $check_stmt = $db->prepare($check_sql);
                        $check_stmt->bind_param('ii', $mitglied_id, $jahr);
                        $check_stmt->execute();
                        $result = $check_stmt->get_result()->fetch_assoc();
                        $check_stmt->close();
                        
                        if ($result && $result['P1Schuss1'] === null) {
                            // P1 ist leer → erste Passe speichern
                            $sql = "UPDATE schwini SET P1Schuss1=?,P1Schuss2=?,P1Schuss3=?,P1Schuss4=?,P1Schuss5=?,P1Schuss6=? WHERE MitgliedID=? AND Jahr=?";
                            $stmt = $db->prepare($sql);
                            $stmt->bind_param('iiiiiiii', $current_shots[0],$current_shots[1],$current_shots[2],$current_shots[3],$current_shots[4],$current_shots[5], $mitglied_id, $jahr);
                        } else {
                            // P1 ist gefüllt → zweite Passe speichern
                            $sql = "UPDATE schwini SET P2Schuss1=?,P2Schuss2=?,P2Schuss3=?,P2Schuss4=?,P2Schuss5=?,P2Schuss6=? WHERE MitgliedID=? AND Jahr=?";
                            $stmt = $db->prepare($sql);
                            $stmt->bind_param('iiiiiiii', $current_shots[0],$current_shots[1],$current_shots[2],$current_shots[3],$current_shots[4],$current_shots[5], $mitglied_id, $jahr);
                        }
                    } else {
                        // Neuer Eintrag → erste Passe speichern
                        $sql = "INSERT INTO schwini (MitgliedID, P1Schuss1,P1Schuss2,P1Schuss3,P1Schuss4,P1Schuss5,P1Schuss6, Jahr) VALUES (?,?,?,?,?,?,?,?)";
                        $stmt = $db->prepare($sql);
                        $stmt->bind_param('iiiiiiii', $mitglied_id, $current_shots[0],$current_shots[1],$current_shots[2],$current_shots[3],$current_shots[4],$current_shots[5], $jahr);
                    }
                    $ok = $stmt->execute(); $stmt->close();
                    break;
                }
                case 'endresultate_partner': {
                    // Sie und Er: 5 Schüsse in SieErSchuss6-10 speichern
                    $arr = array_pad(array_slice($clean, 0, 5), 5, null);
                    
                    // Prüfe ob bereits ein Eintrag existiert
                    $check_stmt = $db->prepare("SELECT ID FROM endresultate_partner WHERE MitgliedID=? AND Jahr=? LIMIT 1");
                    $check_stmt->bind_param('ii', $mitglied_id, $jahr);
                    $check_stmt->execute();
                    $partner_exists = $check_stmt->get_result()->fetch_assoc();
                    $check_stmt->close();
                    
                    if ($partner_exists) {
                        // Update bestehenden Eintrag - nur Sie und Er Schüsse
                        $sql = "UPDATE endresultate_partner SET SieErSchuss6=?,SieErSchuss7=?,SieErSchuss8=?,SieErSchuss9=?,SieErSchuss10=? WHERE MitgliedID=? AND Jahr=?";
                        $stmt = $db->prepare($sql);
                        $stmt->bind_param('iiiiiii', $arr[0],$arr[1],$arr[2],$arr[3],$arr[4], $mitglied_id, $jahr);
                    } else {
                        // Neuer Eintrag mit PartnerName als Platzhalter
                        $sql = "INSERT INTO endresultate_partner (MitgliedID,Jahr,PartnerName,SieErSchuss6,SieErSchuss7,SieErSchuss8,SieErSchuss9,SieErSchuss10) VALUES (?,?,?,?,?,?,?,?)";
                        $stmt = $db->prepare($sql);
                        $partner_name = 'Partner'; // Default Partner Name
                        $stmt->bind_param('iisiiiiii', $mitglied_id, $jahr, $partner_name, $arr[0],$arr[1],$arr[2],$arr[3],$arr[4]);
                    }
                    $ok = $stmt->execute(); $stmt->close();
                    break;
                }
                default: throw new Exception('Unbekannte Zieltabelle: '.$restable);
            }

            if (!$ok) throw new Exception('DB write failed');
            $db->commit();
            json_ok(['message'=>'Import OK','stich'=>$stich,'restable'=>$restable]);
        } catch (Throwable $e) {
            $db->rollback();
            json_err('Transaktion fehlgeschlagen: '.$e->getMessage());
        }
    }

    json_err('Unknown action');
} catch (Throwable $e) {
    json_err('Serverfehler: '.$e->getMessage());
}

