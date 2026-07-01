<?php
// header.inc.php - Kompakte & optimierte Version

// Ausgabepuffer starten
if (ob_get_level() === 0) ob_start();

// Basepath für Assets (funktioniert aus jedem Unterverzeichnis)
$incBase = str_replace($_SERVER['DOCUMENT_ROOT'], '', str_replace('\\', '/', __DIR__)) . '/';

// HTTPS-Erkennung
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
           (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
           (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

// Zentrale Session-Konfiguration (inkl. Cross-Subdomain Cookie-Domain)
require_once __DIR__ . '/session_config.inc.php';
if (!function_exists('user_can_manage_navigation')) {
    function user_can_manage_navigation(): bool {
        return (int)($_SESSION['user_id'] ?? 0) === 1;
    }
}

// Config einbinden
$config_path = file_exists('dbconnect.inc.php') ? 'dbconnect.inc.php' : 'inc/dbconnect.inc.php';
require_once $config_path;
require_once __DIR__.'/ui/buttons.inc.php';
require_once __DIR__.'/remember_me.inc.php';
// Session-Timeout (60 Minuten)
$timeout_duration = 60 * 60;
$login_path = file_exists('login.php') ? 'login.php' : '../login.php';

// Session-Validation
// iOS PWA: Session aus Remember-Cookie wiederherstellen falls nötig
if (!isset($_SESSION['user_id'])) {
    restoreSessionFromToken();
}

if (isset($_SESSION['user_id'])) {
    // Timeout-Check
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
        session_unset();
        session_destroy();
        header("Location: $login_path?timeout=1");
        exit();
    } else {
        // Session-Regeneration alle 30 Minuten
        if (!isset($_SESSION['regenerated']) || (time() - $_SESSION['regenerated']) > 1800) {
            session_regenerate_id(true);
            $_SESSION['regenerated'] = time();
        }

        $_SESSION['last_activity'] = time();

        // CSRF-Token
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }
} else {
    header("Location: $login_path");
    exit();
}


// Sicherheits-Header
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    
    // CSP mit cdn.jsdelivr.net für Source Maps
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com https://www.google.com https://www.gstatic.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data:; font-src 'self' https://cdn.jsdelivr.net; connect-src 'self' https://cdn.jsdelivr.net https://www.google.com https://api.pwnedpasswords.com wss://localhost:* ws://localhost:*; frame-src https://www.google.com; frame-ancestors 'self'; base-uri 'self'; form-action 'self';");
}

// Rollen-Session-Variablen setzen falls noetig (fuer bestehende Admins nach Migration)
if (isset($_SESSION['user_id']) && !isset($_SESSION['user_role'])) {
    $role_stmt = $conn->prepare("SELECT role, status, mitglied_id, full_name FROM users WHERE id = ?");
    $role_stmt->bind_param("i", $_SESSION['user_id']);
    $role_stmt->execute();
    $role_result = $role_stmt->get_result();
    if ($role_result && $role_row = $role_result->fetch_assoc()) {
        $_SESSION['user_role'] = $role_row['role'] ?? 'admin';
        $_SESSION['user_status'] = $role_row['status'] ?? 'approved';
        $_SESSION['mitglied_id'] = $role_row['mitglied_id'];
        $_SESSION['user_name'] = $role_row['full_name'];
    }
    $role_stmt->close();
}

// Mitglieder duerfen nicht auf Admin-Bereich zugreifen - Redirect zum Portal
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'mitglied') {
    header('Location: /portal/dashboard.php');
    exit();
}

// Pending-Registrierungen zaehlen (fuer Admin-Badge)
$pending_registrations = 0;
if (isset($_SESSION['user_id'])) {
    try {
        $pending_result = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE status='pending'");
        if ($pending_result) {
            $pending_registrations = intval($pending_result->fetch_assoc()['cnt']);
        }
    } catch (Exception $e) {
        // Ignorieren falls Spalte noch nicht existiert
    }
}

// Hilfsfunktionen
function escape_output($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function secure_url($path) {
    return htmlspecialchars($path, ENT_QUOTES, 'UTF-8');
}

function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Seitentitel ermitteln
function get_page_title($conn) {
    $default_title = "MSV Wilen - Jahresmeisterschaft";
    
    try {
        $current_page = basename($_SERVER["PHP_SELF"]);
        $stmt = $conn->prepare("SELECT n1.Text AS Text, n2.Text AS Parent
                FROM navigation n1
                LEFT JOIN navigation n2 ON n1.ParentID = n2.ID
                WHERE n1.Link = ?");
        $stmt->bind_param("s", $current_page);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row['Text'] . " - MSV Wilen";
        }
        $stmt->close();
        return $default_title;
    } catch (Exception $e) {
        error_log("Fehler beim Laden des Seitentitels: " . $e->getMessage());
        return $default_title;
    }
}

$Seitentitel = get_page_title($conn);
$navPageTitle = preg_replace('/\s+-\s+MSV Wilen$/', '', $Seitentitel);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo escape_output($Seitentitel); ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../icons/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="../icons/icon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../icons/icon-16x16.png">

    <!-- PWA -->
    <link rel="manifest" href="../manifest.json">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="MSV Wilen">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <!-- jQuery UI CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jquery-ui@1.13.2/dist/themes/ui-lightness/jquery-ui.css">
    
    <!-- MSV Styles (?v=filemtime: cachebar, bricht Cache nur bei Datei-Aenderung) -->
    <link rel="stylesheet" href="../css/msv-styles.css?v=<?php echo @filemtime(__DIR__ . '/../css/msv-styles.css') ?: '1'; ?>">
    <link rel="stylesheet" href="../css/fixes/resultate-unified.css?v=<?php echo @filemtime(__DIR__ . '/../css/fixes/resultate-unified.css') ?: '1'; ?>">
    <link rel="stylesheet" href="../css/mobile-cards.css?v=<?php echo @filemtime(__DIR__ . '/../css/mobile-cards.css') ?: '1'; ?>">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery UI -->
    <script src="https://cdn.jsdelivr.net/npm/jquery-ui@1.13.2/dist/jquery-ui.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- MSV Toast System -->
    <script src="<?php echo $incBase; ?>js/msv-toast.js?v=<?php echo @filemtime(__DIR__ . '/js/msv-toast.js') ?: '1'; ?>"></script>
    <!-- MSV Tooltip System -->
    <script src="<?php echo $incBase; ?>js/msv-tooltips.js?v=<?php echo @filemtime(__DIR__ . '/js/msv-tooltips.js') ?: '1'; ?>"></script>
    <!-- MSV Mobile Cards Helper -->
    <script src="<?php echo $incBase; ?>js/mobile-cards.js?v=<?php echo @filemtime(__DIR__ . '/js/mobile-cards.js') ?: '1'; ?>"></script>

    <style>
        /* Kompakte Basis-Styles */
        :root {
            --nav-height: 56px;
        }
        
        /* Kompakte Modals */
        .modal-content {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px 15px 0 0;
            padding: 1rem 1.5rem;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            padding: 1rem 1.5rem;
            background: #f8f9fa;
            border-radius: 0 0 15px 15px;
        }
        
        /* Einheitliche, kompakte Button-Höhe (~33px). ACHTUNG: Dieser Inline-<style>
           lädt NACH css/msv-styles.css und gewinnt daher die Kaskade – Button-Grösse
           hier pflegen, nicht (nur) in msv-styles.css. .btn-sm MUSS separat gesetzt
           werden (Bootstrap nutzt dafür nur CSS-Variablen, die hier wirkungslos sind). */
        .btn {
            padding: 0.3rem 0.85rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.875rem;
            line-height: 1.5;
            transition: all 0.3s ease;
        }
        .btn-sm,
        .btn-group-sm > .btn {
            padding: 0.2rem 0.6rem;
            font-size: 0.8rem;
            border-radius: 6px;
            line-height: 1.5;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        /* Session Warning kompakt */
        .session-warning {
            max-width: 300px;
            font-size: 0.9rem;
            border-radius: 10px;
        }
    </style>

    <script>
        $(document).ready(function() {
            // Session Timeout Management (kompakt)
            var timeoutDuration = <?php echo (int)$timeout_duration * 1000; ?>;
            var warningDuration = 120000; // 2 Minuten
            var logoutTimer, warningTimer, warningShown = false;

            function resetTimers() {
                clearTimeout(logoutTimer);
                clearTimeout(warningTimer);

                if (warningShown) {
                    $('#sessionWarning').remove();
                    warningShown = false;
                }

                warningTimer = setTimeout(showSessionWarning, timeoutDuration - warningDuration);
                logoutTimer = setTimeout(function() {
                    window.location.href = "<?php echo $login_path; ?>?timeout=1";
                }, timeoutDuration);
            }

            function showSessionWarning() {
                if (!warningShown) {
                    $('body').append(`
                        <div id="sessionWarning" class="alert alert-warning alert-dismissible fade show session-warning" role="alert"
                             style="position:fixed; right:1rem; bottom:1rem; z-index:1080;">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <strong>Session läuft ab!</strong><br>
                            Automatische Abmeldung in 2 Min.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `);
                    warningShown = true;
                }
            }

            $(document).on('mousemove keypress click scroll', resetTimers);
            resetTimers();
        });
    </script>

    <?php if (!empty($page_specific_css)): ?>
    <style>
    <?php echo $page_specific_css; ?>
    </style>
    <?php endif; ?>
</head>

<body<?php echo isset($body_class) ? ' class="' . escape_output($body_class) . '"' : ''; ?>>
    <script>
        // Navigation-Layout-Preference (Sidebar links / Topbar oben) VOR dem Rendern
        // anwenden -> verhindert sichtbares Umspringen ("Flicker") beim Seitenaufbau.
        try {
            if (localStorage.getItem('msvNavSidebar') === '1') {
                document.body.classList.add('nav-sidebar');
            }
        } catch (e) {}
    </script>
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo file_exists('home.php') ? 'home.php' : '../home.php'; ?>">
                <img src="../icons/icon-32x32.png" alt="MSV" width="24" height="24" style="border-radius:4px;"><span class="navbar-brand-text"> MSV Wilen</span>
                <?php if ($pending_registrations > 0): ?>
                    <span class="badge bg-warning text-dark" style="font-size: 0.65rem; vertical-align: top; margin-left: 4px;"
                          data-tooltip="<?php echo $pending_registrations; ?> neue Registrierung<?php echo $pending_registrations > 1 ? 'en' : ''; ?>">
                        <?php echo $pending_registrations; ?>
                    </span>
                <?php endif; ?>
            </a>

            <span class="navbar-page-title"><?php echo escape_output($navPageTitle); ?></span>

            <button class="navbar-toggler" type="button">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <?php include 'navigation.inc.php'; ?>
            </div>
        </div>
    </nav>

    <?php
    // Mobile Off-Canvas Menu (außerhalb navbar-collapse)
    NavigationManager::getInstance()->generateMobileMenu();
    ?>

    <!-- Kompakte Logout Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title">
                        <i class="bi bi-box-arrow-right me-2"></i>Abmelden
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-3">
                    <i class="bi bi-question-circle text-warning" style="font-size: 2.5rem;"></i>
                    <p class="mt-3 mb-0">Wirklich abmelden?</p>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Abbrechen
                    </button>
                    <a href="<?php echo file_exists('user_logout.php') ? 'user_logout.php' : '../user_logout.php'; ?>"
                       class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-box-arrow-right me-1"></i>Abmelden
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">