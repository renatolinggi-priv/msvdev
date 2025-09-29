<?php
// header.inc.php - Modernisierte & gehärtete Version

// -------------------------------
// Ausgabepuffer starten (verhindert "Headers already sent")
// -------------------------------
if (ob_get_level() === 0) {
    ob_start();
}

// -------------------------------
// Sicherheitseinstellungen für Sessions
// -------------------------------
// Kompatible Erkennung von HTTPS (auch hinter Proxy)
$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
);

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax'); // Lax statt Strict für bessere Kompatibilität
if ($isHttps) {
    // Nur aktivieren, wenn HTTPS tatsächlich aktiv ist
    ini_set('session.cookie_secure', 1);
}

// -------------------------------
// Session starten (nur wenn noch nicht gestartet)
// -------------------------------
if (session_status() == PHP_SESSION_NONE) {
    session_start();
    // Nur User mit ID 1 ist Admin
if (!function_exists('user_can_manage_navigation')) {
    function user_can_manage_navigation(): bool {
        return (int)($_SESSION['user_id'] ?? 0) === 1;
    }
}
}

// -------------------------------
// Config einbinden
// -------------------------------
$config_path = file_exists('dbconnect.inc.php') ? 'dbconnect.inc.php' : 'inc/dbconnect.inc.php';
require_once $config_path;

// -------------------------------
// Session-Timeout (60 Minuten) & Login-Pfad
// -------------------------------
$timeout_duration = 60 * 60;
$login_path = file_exists('login.php') ? 'login.php' : '../login.php';

// -------------------------------
// Session-Validation
// -------------------------------
if (isset($_SESSION['user_id'])) {

    // Timeout?
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

    // Aktivität aktualisieren
    $_SESSION['last_activity'] = time();

    // CSRF-Token setzen
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

} else {
    // Nicht eingeloggt → zur Login-Seite
    header("Location: $login_path");
    exit();
}

// -------------------------------
// Sicherheits-Header
// -------------------------------
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY'); // Clickjacking-Schutz
    header('X-XSS-Protection: 1; mode=block'); // legacy, schadet nicht
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // HSTS nur bei HTTPS aktivieren
    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }

    // Content Security Policy (wie bei dir, nur leicht geglättet)
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com; img-src 'self' data:; font-src 'self' https://cdn.jsdelivr.net; connect-src 'self';");
}

// -------------------------------
// Hilfsfunktionen
// -------------------------------
function escape_output($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function secure_url($path) {
    return htmlspecialchars($path, ENT_QUOTES, 'UTF-8');
}

function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// -------------------------------
// Seitentitel
// -------------------------------
function get_page_title($conn) {
    $default_title = "MSV Wilen - Jahresmeisterschaft";

    try {
        $current_page = basename($_SERVER["PHP_SELF"]);

        $sql = "SELECT n1.Text AS Text, n2.Text AS Parent 
                FROM navigation n1 
                LEFT JOIN navigation n2 ON n1.ParentID = n2.ID 
                WHERE n1.Link = '" . $current_page . "'";
        // nutzt projektinterne DB-Helferfunktion
        $result = connect_db($sql);

        if (!$result) {
            return $default_title;
        }

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $parent = $row['Parent'] ? $row['Parent'] : 'Wilen';
            return "MSV " . $parent . " - " . $row['Text'];
        }

        return $default_title;

    } catch (Exception $e) {
        error_log("Fehler beim Laden des Seitentitels: " . $e->getMessage());
        return $default_title;
    }
}

// -------------------------------
// Logout Modal
// -------------------------------
function render_logout_modal() {
    ?>
    <!-- Logout Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title" id="logoutModalLabel">
                        <i class="bi bi-box-arrow-right me-2"></i>Abmelden
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <i class="bi bi-question-circle text-warning" style="font-size: 3rem;"></i>
                    <p class="mt-3 mb-0">Möchtest du dich wirklich abmelden?</p>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Abbrechen
                    </button>
                    <a href="<?php echo file_exists('user_logout.php') ? 'user_logout.php' : '../user_logout.php'; ?>" class="btn btn-danger">
                        <i class="bi bi-box-arrow-right me-2"></i>Ja, abmelden
                    </a>
                </div>
            </div>
        </div>
    </div>

    <style>
    #logoutModal .modal-content {
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }
    #logoutModal .modal-header {
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        border-radius: 15px 15px 0 0;
        padding: 1.5rem;
    }
    #logoutModal .modal-body { padding: 2rem; }
    #logoutModal .modal-footer {
        padding: 1.5rem;
        background: #f8f9fa;
        border-radius: 0 0 15px 15px;
    }
    #logoutModal .btn {
        padding: 0.5rem 1.5rem;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    #logoutModal .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }
    #logoutModal .btn-secondary { background: #6c757d; border: none; }
    #logoutModal .btn-danger { background: linear-gradient(135deg, #dc3545, #c82333); border: none; }
    </style>
    <?php
}
// -------------------------------
// Backup Modal
// -------------------------------
function render_backup_modal() {
    ?>
    <!-- Backup Modal -->
    <div class="modal fade" id="backupModal" tabindex="-1" aria-labelledby="backupModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title" id="backupModalLabel">
                        <i class="bi bi-hdd-network me-2"></i>Backup
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <div id="backupProgressContainer" style="display: none;">
                        <div class="progress mb-3">
                            <div id="backupProgressBar" class="progress-bar" role="progressbar" style="width: 0%"></div>
                        </div>
                        <p id="backupStatusText">Backup wird gestartet...</p>
                    </div>
                    <div id="backupResultContainer" style="display: none;">
                        <i id="backupResultIcon" class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                        <p id="backupResultText" class="mt-3 mb-0">Backup erfolgreich abgeschlossen!</p>
                    </div>
                    <div id="backupErrorContainer" style="display: none;">
                        <i class="bi bi-exclamation-circle text-danger" style="font-size: 3rem;"></i>
                        <p id="backupErrorText" class="mt-3 mb-0">Beim Backup ist ein Fehler aufgetreten.</p>
                    </div>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button id="backupStartButton" type="button" class="btn btn-primary">
                        <i class="bi bi-play-circle me-2"></i>Backup starten
                    </button>
                    <button id="backupCloseButton" type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="display: none;">
                        <i class="bi bi-x-circle me-2"></i>Schließen
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
    #backupModal .modal-content {
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }
    #backupModal .modal-header {
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        border-radius: 15px 15px 0 0;
        padding: 1.5rem;
    }
    #backupModal .modal-body { padding: 2rem; }
    #backupModal .modal-footer {
        padding: 1.5rem;
        background: #f8f9fa;
        border-radius: 0 0 15px 15px;
    }
    #backupModal .btn {
        padding: 0.5rem 1.5rem;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    #backupModal .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }
    #backupModal .btn-primary { background: linear-gradient(135deg, #0d6efd, #0b5ed7); border: none; }
    #backupModal .btn-secondary { background: #6c757d; border: none; }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const backupModal = document.getElementById('backupModal');
        const backupStartButton = document.getElementById('backupStartButton');
        const backupCloseButton = document.getElementById('backupCloseButton');
        const backupProgressContainer = document.getElementById('backupProgressContainer');
        const backupResultContainer = document.getElementById('backupResultContainer');
        const backupErrorContainer = document.getElementById('backupErrorContainer');
        const backupProgressBar = document.getElementById('backupProgressBar');
        const backupStatusText = document.getElementById('backupStatusText');
        const backupResultText = document.getElementById('backupResultText');
        const backupErrorText = document.getElementById('backupErrorText');
        const backupResultIcon = document.getElementById('backupResultIcon');
        
        // Reset modal state when it's closed
        backupModal.addEventListener('hidden.bs.modal', function () {
            // Reset all containers
            backupProgressContainer.style.display = 'none';
            backupResultContainer.style.display = 'none';
            backupErrorContainer.style.display = 'none';
            
            // Show start button and hide close button
            backupStartButton.style.display = 'block';
            backupCloseButton.style.display = 'none';
            
            // Reset progress bar
            backupProgressBar.style.width = '0%';
            backupProgressBar.className = 'progress-bar';
            
            // Reset status text
            backupStatusText.textContent = 'Backup wird gestartet...';
        });
        
        // Start backup when button is clicked
        backupStartButton.addEventListener('click', function() {
            // Show progress container and hide start button
            backupProgressContainer.style.display = 'block';
            backupStartButton.style.display = 'none';
            
            // Start backup process
            startBackup();
        });
        
        function startBackup() {
            // Reset progress bar
            backupProgressBar.style.width = '0%';
            backupProgressBar.className = 'progress-bar';
            
            // Update status text
            backupStatusText.textContent = 'Backup wird gestartet...';
            
            // Make AJAX request to start backup
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '../admin/backup_api.php?action=run', true);
            xhr.setRequestHeader('X-CSRF', '<?php echo $_SESSION['csrf_token'] ?? ''; ?>');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === XMLHttpRequest.DONE) {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                // Start polling for status updates
                                pollBackupStatus();
                            } else {
                                // Show error result
                                backupProgressContainer.style.display = 'none';
                                backupErrorContainer.style.display = 'block';
                                backupErrorText.textContent = response.message || 'Beim Backup ist ein Fehler aufgetreten.';
                                backupCloseButton.style.display = 'block';
                            }
                        } catch (e) {
                            // Show error result
                            backupProgressContainer.style.display = 'none';
                            backupErrorContainer.style.display = 'block';
                            backupErrorText.textContent = 'Beim Backup ist ein Fehler aufgetreten.';
                            backupCloseButton.style.display = 'block';
                        }
                    } else {
                        // Show error result
                        backupProgressContainer.style.display = 'none';
                        backupErrorContainer.style.display = 'block';
                        backupErrorText.textContent = 'Beim Backup ist ein Fehler aufgetreten.';
                        backupCloseButton.style.display = 'block';
                    }
                }
            };
            
            xhr.send();
        }
        
        function pollBackupStatus() {
            const xhr = new XMLHttpRequest();
            xhr.open('GET', '../admin/backup_api.php?action=status', true);
            xhr.setRequestHeader('X-CSRF', '<?php echo $_SESSION['csrf_token'] ?? ''; ?>');
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === XMLHttpRequest.DONE) {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                // Update progress bar and status text
                                backupProgressBar.style.width = response.status === 'completed' ? '100%' : '50%';
                                backupStatusText.textContent = response.message;
                                
                                // Update progress text if available
                                if (response.progress) {
                                    // Extract the last line of progress text
                                    const lines = response.progress.trim().split('\n');
                                    if (lines.length > 0) {
                                        const lastLine = lines[lines.length - 1];
                                        // Extract the message part after the timestamp
                                        const messageMatch = lastLine.match(/\[\d{2}:\d{2}:\d{2}\]\s*(.*)/);
                                        if (messageMatch && messageMatch[1]) {
                                            backupStatusText.textContent = messageMatch[1];
                                        }
                                    }
                                }
                                
                                // If backup is still running, continue polling
                                if (response.status === 'running') {
                                    setTimeout(pollBackupStatus, 1000); // Poll every second
                                } else {
                                    // Backup completed
                                    backupProgressContainer.style.display = 'none';
                                    backupResultContainer.style.display = 'block';
                                    backupResultText.textContent = 'Backup erfolgreich abgeschlossen!';
                                    backupResultIcon.className = 'bi bi-check-circle text-success';
                                    backupResultIcon.style.fontSize = '3rem';
                                    backupCloseButton.style.display = 'block';
                                }
                            } else {
                                // Show error result
                                backupProgressContainer.style.display = 'none';
                                backupErrorContainer.style.display = 'block';
                                backupErrorText.textContent = response.message || 'Beim Backup ist ein Fehler aufgetreten.';
                                backupCloseButton.style.display = 'block';
                            }
                        } catch (e) {
                            // Show error result
                            backupProgressContainer.style.display = 'none';
                            backupErrorContainer.style.display = 'block';
                            backupErrorText.textContent = 'Beim Backup ist ein Fehler aufgetreten.';
                            backupCloseButton.style.display = 'block';
                        }
                    } else {
                        // Show error result
                        backupProgressContainer.style.display = 'none';
                        backupErrorContainer.style.display = 'block';
                        backupErrorText.textContent = 'Beim Backup ist ein Fehler aufgetreten.';
                        backupCloseButton.style.display = 'block';
                    }
                }
            };
            
            xhr.send();
        }
    });
    </script>
    <?php
}

// -------------------------------
// Titel ermitteln
// -------------------------------
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

    <!-- ZENTRALE MSV STYLES -->
    <link rel="stylesheet" href="../css/msv-styles.css?v=<?php echo time(); ?>">
    
    <!-- Einheitliche Resultate Styles -->
    <link rel="stylesheet" href="../css/fixes/resultate-unified.css?v=<?php echo time(); ?>">

    <!-- Seitenspezifische Styles werden hier eingefügt -->
    <?php if (isset($page_specific_css)): ?>
        <style><?php echo $page_specific_css; ?></style>
    <?php endif; ?>

    <!-- jQuery MUSS VOR allen jQuery-abhängigen Scripts geladen werden -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Bootstrap Bundle (inkl. Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- jQuery UI -->
    <script src="https://cdn.jsdelivr.net/npm/jquery-ui@1.13.2/dist/jquery-ui.min.js"></script>

    <!-- Session Timeout & Navigation -->
    <script>
        $(document).ready(function() {
            // ---------------------------
            // Session Timeout Management
            // ---------------------------
            var timeoutDuration = <?php echo (int)$timeout_duration * 1000; ?>; // ms
            var warningDuration = 2 * 60 * 1000; // 2 Minuten Warnung
            var logoutTimer, warningTimer;
            var warningShown = false;

            function resetTimers() {
                clearTimeout(logoutTimer);
                clearTimeout(warningTimer);

                if (warningShown) {
                    $('#sessionWarning').remove();
                    warningShown = false;
                }

                warningTimer = setTimeout(function() {
                    showSessionWarning();
                }, Math.max(0, timeoutDuration - warningDuration));

                logoutTimer = setTimeout(function() {
                    window.location.href = "<?php echo $login_path; ?>?timeout=1";
                }, timeoutDuration);
            }

            function showSessionWarning() {
                if (!warningShown) {
                    var warningHtml = `
                        <div id="sessionWarning" class="alert alert-warning alert-dismissible fade show session-warning" role="alert" style="position:fixed; right:1rem; bottom:1rem; z-index:1080;">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <strong>Session läuft ab!</strong><br>
                            Du wirst in 2 Minuten automatisch abgemeldet.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    `;
                    $('body').append(warningHtml);
                    warningShown = true;
                    setTimeout(function() { $('#sessionWarning').fadeOut(); }, 10000);
                }
            }

            $(document).on('mousemove keypress click scroll', resetTimers);
            resetTimers();

            // ---------------------------
            // Moderne Navigation Effekte
            // ---------------------------
            initializeModernNavigation();
        });

        // ---------------------------
        // Moderne Navigation JavaScript
        // ---------------------------
        function initializeModernNavigation() {
            let lastScrollTop = 0;
            const navbar = document.querySelector('.navbar');

            window.addEventListener('scroll', function() {
                const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                if (navbar) {
                    if (scrollTop > 50) {
                        navbar.classList.add('scrolled');
                    } else {
                        navbar.classList.remove('scrolled');
                    }
                }
                lastScrollTop = scrollTop;
            });

            const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
            let activeDropdown = null;
            let globalHoverTimeout = null;

            dropdownToggles.forEach(toggle => {
                const dropdownElement = toggle.parentElement;
                const dropdownMenu = toggle.nextElementSibling;
                let isHovering = false;

                if (window.innerWidth > 991) {
                    [toggle, dropdownMenu].forEach(element => {
                        if (element) {
                            element.addEventListener('mouseenter', function() {
                                isHovering = true;
                                clearTimeout(globalHoverTimeout);

                                if (activeDropdown && activeDropdown !== toggle) {
                                    const bsActiveDropdown = bootstrap.Dropdown.getInstance(activeDropdown);
                                    if (bsActiveDropdown) { bsActiveDropdown.hide(); }
                                }

                                const bsDropdown = bootstrap.Dropdown.getOrCreateInstance(toggle);
                                bsDropdown.show();
                                activeDropdown = toggle;
                            });
                        }
                    });

                    dropdownElement.addEventListener('mouseleave', function(e) {
                        isHovering = false;
                        const rect = dropdownElement.getBoundingClientRect();
                        const mouseX = e.clientX, mouseY = e.clientY;

                        if (mouseX < rect.left || mouseX > rect.right || mouseY < rect.top || mouseY > rect.bottom) {
                            globalHoverTimeout = setTimeout(() => {
                                if (!isHovering && activeDropdown === toggle) {
                                    const bsDropdown = bootstrap.Dropdown.getInstance(toggle);
                                    if (bsDropdown) {
                                        bsDropdown.hide();
                                        activeDropdown = null;
                                    }
                                }
                            }, 200);
                        }
                    });

                    dropdownElement.addEventListener('mouseenter', function() {
                        isHovering = true;
                        clearTimeout(globalHoverTimeout);
                    });
                }
            });

            // Smooth Scrolling für interne Links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    const href = this.getAttribute('href');
                    // Prüfe ob es ein gültiger Anker ist (mehr als nur '#')
                    if (href && href.length > 1) {
                        const target = document.querySelector(href);
                        if (target) {
                            e.preventDefault();
                            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    }
                });
            });

            // Aktive Navigation hervorheben
            highlightActiveNavigation();

            // Dropdown-Animationen leicht verbessern
            const dropdownMenus = document.querySelectorAll('.dropdown-menu');
            dropdownMenus.forEach(menu => {
                menu.addEventListener('show.bs.dropdown', function() {
                    this.classList.remove('hide');
                    this.style.display = 'block';
                });
                menu.addEventListener('hide.bs.dropdown', function() {
                    this.classList.add('hide');
                    setTimeout(() => { this.classList.remove('hide'); }, 100);
                });
            });
        }

        // ---------------------------
        // Aktive Navigation markieren
        // ---------------------------
        function highlightActiveNavigation() {
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.nav-link, .dropdown-item');

            navLinks.forEach(link => {
                const href = link.getAttribute('href');
                if (href && href.includes(currentPage)) {
                    link.classList.add('active');
                    const parentDropdown = link.closest('.dropdown');
                    if (parentDropdown) {
                        const dropdownToggle = parentDropdown.querySelector('.dropdown-toggle');
                        if (dropdownToggle) { dropdownToggle.classList.add('active'); }
                    }
                }
            });
        }

        // Platzhalter für künftige Suche
        function addNavigationSearch() {}
    </script>
    <script>
document.addEventListener('DOMContentLoaded', function () {
  // Nur einmal binden
  document.querySelectorAll('.dropdown-submenu > .dropdown-item.dropdown-toggle').forEach(function (toggle) {
    toggle.addEventListener('click', function (e) {
      // Standard-Navigation verhindern, stattdessen Untermenü togglen
      e.preventDefault();
      e.stopPropagation();

      const submenu = this.nextElementSibling;
      if (!submenu) return;

      // Alle Geschwister schließen
      const siblings = this.closest('.dropdown-menu')?.querySelectorAll('.dropdown-menu.show');
      siblings?.forEach(el => { if (el !== submenu) el.classList.remove('show'); });

      // Dieses Untermenü togglen
      submenu.classList.toggle('show');
    });
  });

  // Schließen bei Klick außerhalb / beim Schließen des Parent-Dropdowns
  document.querySelectorAll('.nav-item.dropdown').forEach(function (root) {
    root.addEventListener('hide.bs.dropdown', function () {
      root.querySelectorAll('.dropdown-menu.show').forEach(el => el.classList.remove('show'));
    });
  });
});
</script>

</head>

<body>
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo file_exists('home.php') ? 'home.php' : '../home.php'; ?>">
                <i class="bi bi-house-door me-2"></i>MSV Wilen
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <?php include 'navigation.inc.php'; ?>
            </div>
        </div>
    </nav>

    <?php render_logout_modal(); ?>
    <?php render_backup_modal(); ?>

    <div class="container-fluid">
        <?php
        // Breadcrumb anzeigen (optional)
        if (function_exists('generate_breadcrumb')) {
            // generate_breadcrumb();
        }
        ?>
        <div class="row">
            <div class="col-12"></div>
