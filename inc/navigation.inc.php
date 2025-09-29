<?php
// navigation.inc.php – Verbesserte mehrstufige Navigation mit anwählbaren Parent-Links


class NavigationManager {
    private static $navigationCache = null;
    private static $instance = null;

    public static function getInstance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function loadNavigationData() {
        global $conn; // Globale Datenbankverbindung aus config.php
        
        if (self::$navigationCache !== null) return self::$navigationCache;

        $sql = "SELECT ID, Text, Link, ParentID, SortOrder FROM navigation ORDER BY ParentID, SortOrder, ID";
        $result = $conn->query($sql); // Verwende die globale $conn direkt
        
        if (!$result) {
            error_log("Navigation Query Error: " . $conn->error);
            return [];
        }

        $byParent = [];
        $flat = [];
        while ($row = $result->fetch_assoc()) {
            $parentId = (int)$row['ParentID'];
            if (!isset($byParent[$parentId])) $byParent[$parentId] = [];
            $byParent[$parentId][] = $row;
            $flat[(int)$row['ID']] = $row;
        }

        self::$navigationCache = ['byParent'=>$byParent, 'flat'=>$flat];
        return self::$navigationCache;
    }

    public function generateNavigation() {
        $data = $this->loadNavigationData();
        $byParent = $data['byParent'] ?? [];
        $currentPage = basename($_SERVER['PHP_SELF']);

        echo '<ul class="navbar-nav me-auto">';
        if (isset($byParent[0])) {
            foreach ($byParent[0] as $rootItem) {
                $this->renderItemRecursive($rootItem, $byParent, $currentPage, 0);
            }
        }
        $this->renderUserDropdown($byParent);
        echo '</ul>';

        // Verbessertes JavaScript für mehrstufige Navigation
        $this->printImprovedSubmenuScript();
    }

    private function renderItemRecursive($item, &$byParent, $currentPage, $depth = 0) {
        $id = (int)$item['ID'];
        $text = $this->escape($item['Text']);
        $link = trim((string)$item['Link']);
        $hasChildren = isset($byParent[$id]) && count($byParent[$id]) > 0;
        $isActive = $this->isActiveDeep($id, $link, $currentPage, $byParent);

        if ($depth === 0) {
            // ROOT-EBENE
            if ($hasChildren) {
                echo '<li class="nav-item dropdown">';
                echo '<a class="nav-link dropdown-toggle '.($isActive?'active':'').'" href="#" id="nav_'.$id.'" role="button" data-bs-toggle="dropdown" aria-expanded="false">';
                echo $text;
                echo '</a>';
                echo '<ul class="dropdown-menu" aria-labelledby="nav_'.$id.'">';
                foreach ($byParent[$id] as $child) {
                    $this->renderItemRecursive($child, $byParent, $currentPage, 1);
                }
                echo '</ul>';
                echo '</li>';
            } else {
                echo '<li class="nav-item">';
                echo '<a class="nav-link '.($isActive?'active':'').'" href="'.$this->escape($link).'">'.$text.'</a>';
                echo '</li>';
            }
        } else {
            // UNTERMENÜ-EBENEN
            if ($hasChildren) {
                // Parent mit Kindern - Link UND Submenu-Toggle getrennt
                echo '<li class="dropdown-submenu dropdown-hover">';
                echo '<div class="dropdown-item-wrapper">';
                
                // Anklickbarer Link
                if (!empty($link) && $link !== '#') {
                    echo '<a class="dropdown-item '.($isActive?'active':'').'" href="'.$this->escape($link).'">'.$text.'</a>';
                } else {
                    echo '<span class="dropdown-item dropdown-text '.($isActive?'active':'').'">'.$text.'</span>';
                }
                
                // Separater Toggle-Button für Submenu
                echo '<button class="dropdown-submenu-toggle" type="button" aria-label="Untermenü öffnen">';
                echo '<i class="bi bi-chevron-right"></i>';
                echo '</button>';
                echo '</div>';
                
                // Submenu
                echo '<ul class="dropdown-menu dropdown-submenu-menu">';
                foreach ($byParent[$id] as $child) {
                    $this->renderItemRecursive($child, $byParent, $currentPage, $depth + 1);
                }
                echo '</ul>';
                echo '</li>';
            } else {
                // Normales Item ohne Kinder
                echo '<li><a class="dropdown-item '.($isActive?'active':'').'" href="'.$this->escape($link).'">'.$text.'</a></li>';
            }
        }
    }

    private function isActiveDeep($itemId, $itemLink, $currentPage, &$byParent) {
        if ($currentPage === basename((string)$itemLink)) return true;
        if (!isset($byParent[$itemId])) return false;
        foreach ($byParent[$itemId] as $child) {
            if ($currentPage === basename((string)$child['Link'])) return true;
            if ($this->isActiveDeep((int)$child['ID'], $child['Link'], $currentPage, $byParent)) return true;
        }
        return false;
    }

    private function renderUserDropdown(&$byParent) {
        $username = $_SESSION['username'] ?? 'Benutzer';

        echo '<li class="nav-item dropdown ms-auto">';
        echo '<a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">';
        echo '<i class="bi bi-person-circle me-1"></i>'.$this->escape($username).'</a>';
        echo '<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">';

        // Standard-Einträge aus Navigation (ParentID=101)
        $hasUserItems = false;
        if (isset($byParent[101])) {
            $icons = ['Passwort ändern'=>'bi-key','Profil'=>'bi-person','Einstellungen'=>'bi-gear'];
            foreach ($byParent[101] as $item) {
                if ($item['Text'] !== 'User anlegen') {
                    if( $item['Link'] !== 'backup_restore.php'){
                        $icon = $icons[$item['Text']] ?? 'bi-circle';
                        echo '<li><a class="dropdown-item" href="'.$this->escape($item['Link']).'"><i class="bi '.$icon.' me-2"></i>'.$this->escape($item['Text']).'</a></li>';
                        $hasUserItems = true;
                    }
                }
            }
            if ($hasUserItems) {
                echo '<li><hr class="dropdown-divider"></li>';
            }
        }

        // >>> HIER: Admin-Link "Navigation verwalten" integrieren (nur für Admins) <<<
        $canManage = function_exists('user_can_manage_navigation')
            ? user_can_manage_navigation()
            : (!empty($_SESSION['is_admin']));

        if ($canManage) {
            $adminHref = file_exists('admin/nav_admin.php') ? 'admin/nav_admin.php' : '../admin/nav_admin.php';
            echo '<li><a class="dropdown-item" href="'.$this->escape($adminHref).'"><i class="bi bi-menu-button-wide me-2"></i>Navigation verwalten</a></li>';
            echo '<li><hr class="dropdown-divider"></li>';
            
            // Backup option that opens the modal
            //echo '<li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#backupModal"><i class="bi bi-hdd-network me-2"></i>Backup</a></li>';
            //echo '<li><hr class="dropdown-divider"></li>';
        }

        // Abmelden
        echo '<li><a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal"><i class="bi bi-box-arrow-right me-2"></i>Abmelden</a></li>';
        echo '</ul></li>';
    }

    public function generateBreadcrumb() {
        $data = $this->loadNavigationData();
        $byParent = $data['byParent'] ?? [];
        $flat = $data['flat'] ?? [];
        $currentPage = basename($_SERVER['PHP_SELF']);

        $currentItem = null;
        foreach ($flat as $item) {
            if (basename((string)$item['Link']) === $currentPage) {
                $currentItem = $item; break;
            }
        }
        if (!$currentItem) return;

        $breadcrumbs = [$currentItem];
        $parentId = (int)$currentItem['ParentID'];
        while ($parentId > 0 && isset($flat[$parentId])) {
            array_unshift($breadcrumbs, $flat[$parentId]);
            $parentId = (int)$flat[$parentId]['ParentID'];
        }

        if (count($breadcrumbs) > 1) {
            echo '<nav aria-label="breadcrumb" class="mt-2"><ol class="breadcrumb">';
            echo '<li class="breadcrumb-item"><a href="home.php"><i class="bi bi-house"></i> Home</a></li>';
            foreach ($breadcrumbs as $i => $crumb) {
                if ($i === count($breadcrumbs) - 1) {
                    echo '<li class="breadcrumb-item active" aria-current="page">'.$this->escape($crumb['Text']).'</li>';
                } else {
                    echo '<li class="breadcrumb-item"><a href="'.$this->escape($crumb['Link']).'">'.$this->escape($crumb['Text']).'</a></li>';
                }
            }
            echo '</ol></nav>';
        }
    }

    private function escape($s) { 
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); 
    }

    private function printImprovedSubmenuScript() {
        echo <<<'SCRIPT'
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Desktop: Hover-Verhalten für Submenus
    if (window.innerWidth >= 992) {
        document.querySelectorAll('.dropdown-submenu.dropdown-hover').forEach(function(elem) {
            let timeout;
            
            elem.addEventListener('mouseenter', function() {
                clearTimeout(timeout);
                const submenu = this.querySelector('.dropdown-submenu-menu');
                if (submenu) {
                    // Schließe andere Submenus auf gleicher Ebene
                    const siblings = this.parentElement.querySelectorAll('.dropdown-submenu-menu.show');
                    siblings.forEach(s => {
                        if (s !== submenu) s.classList.remove('show');
                    });
                    
                    // Positionierung prüfen
                    submenu.classList.add('show');
                    const rect = submenu.getBoundingClientRect();
                    if (rect.right > window.innerWidth - 10) {
                        submenu.classList.add('dropdown-submenu-left');
                    } else {
                        submenu.classList.remove('dropdown-submenu-left');
                    }
                }
            });
            
            elem.addEventListener('mouseleave', function() {
                const submenu = this.querySelector('.dropdown-submenu-menu');
                if (submenu) {
                    timeout = setTimeout(() => {
                        submenu.classList.remove('show');
                    }, 200);
                }
            });
        });
    }
    
    // Toggle-Button für mobile und Desktop-Klick
    document.querySelectorAll('.dropdown-submenu-toggle').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const submenu = this.closest('.dropdown-submenu').querySelector('.dropdown-submenu-menu');
            if (submenu) {
                // Schließe andere Submenus
                const parent = this.closest('.dropdown-menu');
                parent.querySelectorAll('.dropdown-submenu-menu.show').forEach(s => {
                    if (s !== submenu) s.classList.remove('show');
                });
                
                submenu.classList.toggle('show');
                
                // Positionierung für Desktop
                if (window.innerWidth >= 992) {
                    const rect = submenu.getBoundingClientRect();
                    if (rect.right > window.innerWidth - 10) {
                        submenu.classList.add('dropdown-submenu-left');
                    } else {
                        submenu.classList.remove('dropdown-submenu-left');
                    }
                }
            }
        });
    });
    
    // Schließe alle Submenus wenn Hauptmenü schließt
    document.querySelectorAll('.nav-item.dropdown').forEach(function(dropdown) {
        dropdown.addEventListener('hide.bs.dropdown', function() {
            this.querySelectorAll('.dropdown-submenu-menu.show').forEach(menu => {
                menu.classList.remove('show');
            });
        });
    });
    
    // Verhindere dass Klick auf Parent-Link das Dropdown schließt
    document.querySelectorAll('.dropdown-submenu .dropdown-item').forEach(function(item) {
        item.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
});
</script>

<style>
/* ==========================================
   VERBESSERTE MEHRSTUFIGE NAVIGATION
   ========================================== */

/* Basis Dropdown-Submenu Struktur */
.dropdown-submenu {
    position: relative;
}

/* Wrapper für Parent-Item mit Toggle */
.dropdown-item-wrapper {
    display: flex;
    align-items: center;
    position: relative;
    padding: 0;
    margin: 2px 0;  /* Nur vertikaler Abstand, kein horizontaler */
    border-radius: 0.5rem;
    transition: background-color 0.15s ease;
}

.dropdown-item-wrapper:hover {
    background-color: rgba(108, 117, 125, 0.08);
}

/* Parent-Link Style */
.dropdown-submenu .dropdown-item,
.dropdown-submenu .dropdown-text {
    flex: 1;
    padding: 0.75rem 1rem;
    margin: 0;
    border-radius: 0.5rem 0 0 0.5rem;
    border: none;
    background: transparent;
    transition: none;
}

.dropdown-submenu .dropdown-text {
    display: inline-block;
    color: #212529;
    cursor: default;
    font-weight: 500;
}

/* Toggle-Button für Submenu */
.dropdown-submenu-toggle {
    padding: 0.75rem 1rem;
    background: transparent;
    border: none;
    border-left: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 0 0.5rem 0.5rem 0;
    cursor: pointer;
    transition: all 0.15s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
}

.dropdown-submenu-toggle:hover {
    background-color: rgba(108, 117, 125, 0.1);
}

.dropdown-submenu-toggle:focus {
    outline: none;
    box-shadow: inset 0 0 0 2px rgba(108, 117, 125, 0.3);
}

.dropdown-submenu-toggle i {
    font-size: 0.75rem;
    transition: transform 0.2s ease;
    color: #6c757d;
}

/* Wenn Submenu offen ist, rotiere den Pfeil */
.dropdown-submenu:has(.dropdown-submenu-menu.show) .dropdown-submenu-toggle i {
    transform: rotate(90deg);
}

/* Submenu Positionierung */
.dropdown-submenu-menu {
    position: absolute;
    top: 0;
    left: 100%;
    margin-left: 0.5rem;
    min-width: 220px;
    display: none;
    z-index: 1050;
    
    /* Übernehme Hauptmenü-Styles */
    border: none;
    border-radius: 1rem;
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    background: rgba(255, 255, 255, 0.98);
    padding: 0.75rem 0;
}

/* Sichtbares Submenu */
.dropdown-submenu-menu.show {
    display: block;
    animation: submenuFadeIn 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Links ausgerichtetes Submenu (wenn rechts kein Platz) */
.dropdown-submenu-menu.dropdown-submenu-left {
    left: auto;
    right: 100%;
    margin-left: 0;
    margin-right: 0.5rem;
}

/* Animation für Submenus */
@keyframes submenuFadeIn {
    from { 
        opacity: 0; 
        transform: translateX(-10px);
    }
    to { 
        opacity: 1; 
        transform: translateX(0);
    }
}

/* Verbindungslinie zwischen Parent und Submenu */
.dropdown-submenu-menu::before {
    content: "";
    position: absolute;
    top: 15px;
    left: -8px;
    width: 8px;
    height: 2px;
    background: rgba(108, 117, 125, 0.2);
}

.dropdown-submenu-menu.dropdown-submenu-left::before {
    left: auto;
    right: -8px;
}

/* Desktop Hover-Effekte */
@media (min-width: 992px) {
    /* Automatisches Öffnen bei Hover */
    .dropdown-submenu.dropdown-hover:hover > .dropdown-submenu-menu {
        display: block;
        animation: submenuFadeIn 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    /* Hover-Effekt verstärken */
    .dropdown-submenu:hover > .dropdown-item-wrapper {
        background-color: rgba(108, 117, 125, 0.1);
    }
    
    /* Toggle-Button dezenter bei Hover */
    .dropdown-submenu.dropdown-hover .dropdown-submenu-toggle {
        opacity: 0.6;
        min-width: 32px;
        padding: 0.75rem 0.5rem;
    }
    
    .dropdown-submenu.dropdown-hover:hover .dropdown-submenu-toggle {
        opacity: 1;
    }
}

/* Mobile Anpassungen */
@media (max-width: 991.98px) {
    /* Submenus untereinander stapeln */
    .dropdown-submenu-menu {
        position: static;
        margin: 0.5rem 0 0.5rem 1.5rem;
        box-shadow: none;
        background: rgba(248, 249, 250, 0.5);
        border-left: 2px solid rgba(108, 117, 125, 0.2);
        border-radius: 0.5rem;
    }
    
    .dropdown-submenu-menu::before {
        display: none;
    }
    
    /* Toggle-Button prominenter auf Mobile */
    .dropdown-submenu-toggle {
        min-width: 48px;
        background-color: rgba(108, 117, 125, 0.05);
    }
    
    /* Parent-Links auf Mobile */
    .dropdown-item-wrapper {
        margin: 0.25rem 0;  /* Kein horizontaler Abstand */
    }
}

/* Aktive Zustände */
.dropdown-submenu .dropdown-item.active,
.dropdown-submenu .dropdown-text.active {
    background-color: rgba(108, 117, 125, 0.12);
    color: var(--dark-color);
    font-weight: 600;
}

/* Disabled Zustände */
.dropdown-submenu .dropdown-item:disabled,
.dropdown-submenu .dropdown-text.disabled {
    opacity: 0.5;
    pointer-events: none;
}

/* Verschachtelung Level 3+ */
.dropdown-submenu .dropdown-submenu .dropdown-submenu-menu {
    font-size: 0.875rem;
}

.dropdown-submenu .dropdown-submenu .dropdown-item-wrapper {
    padding-left: 0.5rem;
}

/* Icon in Parent-Items */
.dropdown-submenu .dropdown-item i,
.dropdown-submenu .dropdown-text i {
    margin-right: 0.5rem;
    opacity: 0.7;
}

/* Smooth Scrolling für lange Menüs */
.dropdown-submenu-menu {
    max-height: calc(100vh - 120px);
    overflow-y: auto;
    overflow-x: hidden;
}

.dropdown-submenu-menu::-webkit-scrollbar {
    width: 6px;
}

.dropdown-submenu-menu::-webkit-scrollbar-track {
    background: transparent;
}

.dropdown-submenu-menu::-webkit-scrollbar-thumb {
    background: rgba(108, 117, 125, 0.3);
    border-radius: 3px;
}

.dropdown-submenu-menu::-webkit-scrollbar-thumb:hover {
    background: rgba(108, 117, 125, 0.5);
}

/* Spezielle Styles für dreistufige Navigation */
.dropdown-menu .dropdown-menu .dropdown-menu {
    font-size: 0.85rem;
}

/* Visueller Indikator für Parent-Items mit Link */
.dropdown-submenu .dropdown-item:not([href="#"])::after {
    content: "→";
    position: absolute;
    right: 3.5rem;
    opacity: 0;
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.dropdown-submenu .dropdown-item:not([href="#"]):hover::after {
    opacity: 0.5;
    transform: translateX(2px);
}

/* Breadcrumb-artige Anzeige in Submenus */
.dropdown-submenu-menu .dropdown-header {
    padding: 0.5rem 1.5rem;
    font-size: 0.75rem;
    text-transform: uppercase;
    color: #6c757d;
    font-weight: 600;
    letter-spacing: 0.5px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    margin-bottom: 0.5rem;
}

/* Fade-Out Effekt für zu tiefe Verschachtelung */
.dropdown-menu .dropdown-menu .dropdown-menu .dropdown-menu {
    opacity: 0.9;
}

/* Accessibility Improvements */
.dropdown-submenu-toggle:focus-visible {
    outline: 2px solid #0d6efd;
    outline-offset: -2px;
}

.dropdown-submenu .dropdown-item:focus-visible {
    outline: 2px solid #0d6efd;
    outline-offset: -2px;
    background-color: rgba(13, 110, 253, 0.1);
}
</style>
SCRIPT;
    }
}

// Verwendung:
$nav = NavigationManager::getInstance();
$nav->generateNavigation();
?>
