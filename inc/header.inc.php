<?php
// header.inc.php - Kompakte & optimierte Version

// Ausgabepuffer starten
if (ob_get_level() === 0) ob_start();

// HTTPS-Erkennung
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
           (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
           (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

// Session-Sicherheit
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
if ($isHttps) ini_set('session.cookie_secure', 1);

// Session starten
if (session_status() == PHP_SESSION_NONE) {
    session_start();
    if (!function_exists('user_can_manage_navigation')) {
        function user_can_manage_navigation(): bool {
            return (int)($_SESSION['user_id'] ?? 0) === 1;
        }
    }
}

// Config einbinden
$config_path = file_exists('dbconnect.inc.php') ? 'dbconnect.inc.php' : 'inc/dbconnect.inc.php';
require_once $config_path;
require_once __DIR__.'/ui/buttons.inc.php';
// Session-Timeout (60 Minuten)
$timeout_duration = 60 * 60;
$login_path = file_exists('login.php') ? 'login.php' : '../login.php';

// Session-Validation
if (isset($_SESSION['user_id'])) {
    // Timeout-Check
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
        session_unset();
        session_destroy();
        header("Location: $login_path?timeout=1");
        exit();
    }

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
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com https://www.google.com https://www.gstatic.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data:; font-src 'self' https://cdn.jsdelivr.net; connect-src 'self' https://cdn.jsdelivr.net https://www.google.com; frame-src https://www.google.com; frame-ancestors 'self'; base-uri 'self'; form-action 'self';");
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
            $parent = $row['Parent'] ?: 'Wilen';
            $stmt->close();
            return "MSV " . $parent . " - " . $row['Text'];
        }
        $stmt->close();
        return $default_title;
    } catch (Exception $e) {
        error_log("Fehler beim Laden des Seitentitels: " . $e->getMessage());
        return $default_title;
    }
}

$Seitentitel = get_page_title($conn);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape_output($Seitentitel); ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <!-- jQuery UI CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jquery-ui@1.13.2/dist/themes/ui-lightness/jquery-ui.css">
    
    <!-- MSV Styles -->
    <link rel="stylesheet" href="../css/msv-styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/fixes/resultate-unified.css?v=<?php echo time(); ?>">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery UI -->
    <script src="https://cdn.jsdelivr.net/npm/jquery-ui@1.13.2/dist/jquery-ui.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- MSV Toast System -->
    <script src="js/msv-toast.js?v=<?php echo time(); ?>"></script>

    <style>
        /* Kompakte Basis-Styles */
        :root {
            --nav-height: 56px;
        }
        
        body {
            padding-top: var(--nav-height);
        }
        
        .navbar {
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            border-bottom: 1px solid rgba(0,0,0,0.05);
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
        
        .btn {
            padding: 0.4rem 1.2rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
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
</head>

<body<?php echo isset($body_class) ? ' class="' . escape_output($body_class) . '"' : ''; ?>>
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo file_exists('home.php') ? 'home.php' : '../home.php'; ?>">
                <i class="bi bi-bullseye"></i> MSV Wilen
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <?php include 'navigation.inc.php'; ?>
            </div>
        </div>
    </nav>

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
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Abbrechen
                    </button>
                    <a href="<?php echo file_exists('user_logout.php') ? 'user_logout.php' : '../user_logout.php'; ?>" 
                       class="btn btn-danger btn-sm">
                        <i class="bi bi-box-arrow-right me-1"></i>Abmelden
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">