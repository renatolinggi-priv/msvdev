<?php
// navigation.inc.php - Kompakte Version mit allen Funktionen

class NavigationManager {
    private static $navigationCache = null;
    private static $instance = null;

    // Diese Links erscheinen nur im Usermenu, nicht in der Hauptnavigation
    private $userMenuOnlyLinks = ['password_change.php', 'backup_restore.php'];

    public static function getInstance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function loadNavigationData() {
        global $conn;
        
        if (self::$navigationCache !== null) return self::$navigationCache;

        $sql = "SELECT ID, Text, Link, ParentID, SortOrder FROM navigation ORDER BY ParentID, SortOrder, ID";
        $result = $conn->query($sql);
        
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

        // Desktop Navigation
        echo '<ul class="navbar-nav me-auto">';
        if (isset($byParent[0])) {
            foreach ($byParent[0] as $rootItem) {
                $this->renderItemRecursive($rootItem, $byParent, $currentPage, 0);
            }
        }
        $this->renderUserDropdown($byParent);
        echo '</ul>';

        $this->printCompactStyles();
        $this->printCompactScript();
    }

    public function generateMobileMenu() {
        $data = $this->loadNavigationData();
        $byParent = $data['byParent'] ?? [];
        $currentPage = basename($_SERVER['PHP_SELF']);
        $this->generateMobileOffCanvas($byParent, $currentPage);
    }

    private function generateMobileOffCanvas(&$byParent, $currentPage) {
        $username = $_SESSION['username'] ?? 'Benutzer';

        echo '<!-- Mobile Off-Canvas Menu -->';
        echo '<div class="offcanvas-overlay"></div>';
        echo '<div class="offcanvas-nav">';

        // Header
        echo '<div class="offcanvas-header">';
        echo '<h5 class="offcanvas-title"><i class="bi bi-list-ul me-2"></i>Navigation</h5>';
        echo '<button class="offcanvas-close" aria-label="Schließen">';
        echo '<i class="bi bi-x"></i>';
        echo '</button>';
        echo '</div>';

        // Body
        echo '<div class="offcanvas-body">';
        echo '<ul class="mobile-nav-list">';

        // Main menu items
        if (isset($byParent[0])) {
            foreach ($byParent[0] as $rootItem) {
                $this->renderMobileItem($rootItem, $byParent, $currentPage, 0);
            }
        }

        echo '</ul>';

        // User section
        echo '<div class="mobile-user-section">';
        echo '<div class="mobile-user-header">';
        echo '<div class="mobile-user-icon"><i class="bi bi-person-fill"></i></div>';
        echo '<div class="mobile-user-name">'.$this->escape($username).'</div>';
        echo '</div>';

        // User menu items
        echo '<ul class="mobile-nav-list">';

        // Einträge direkt per Link-Name suchen (unabhängig von ParentID in der DB)
        $mobileData = $this->loadNavigationData();
        $mobileFlat = $mobileData['flat'] ?? [];
        $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
        $userMenuIcons = ['password_change.php' => 'bi-key', 'backup_restore.php' => 'bi-hdd'];
        foreach ($mobileFlat as $item) {
            $link = trim($item['Link']);
            if (!in_array($link, $this->userMenuOnlyLinks)) continue;
            if ($link === 'backup_restore.php' && !$isAdmin) continue;
            $isActive = $this->isActiveDeep((int)$item['ID'], $link, $currentPage, $byParent);
            $icon = $userMenuIcons[$link] ?? 'bi-circle';
            $colorClass = $link === 'backup_restore.php' ? ' text-warning' : '';
            echo '<li class="mobile-nav-item">';
            echo '<a class="mobile-user-menu-link '.($isActive?'active':'').'" href="'.$this->escape($link).'">';
            echo '<i class="bi '.$icon.$colorClass.' me-2"></i>';
            echo $this->escape($item['Text']);
            echo '</a></li>';
        }

        // Admin links
        if ($isAdmin) {
            echo '<li class="mobile-nav-item">';
            echo '<a class="mobile-user-menu-link" href="benutzerverwaltung.php">';
            echo '<i class="bi bi-people-fill text-warning me-2"></i>Benutzerverwaltung';
            echo '</a></li>';
        }

        // Portal-Link
        $portalHref = file_exists('portal/dashboard.php') ? 'portal/dashboard.php' : '../portal/dashboard.php';
        echo '<li class="mobile-nav-item">';
        echo '<a class="mobile-user-menu-link" href="'.$this->escape($portalHref).'">';
        echo '<i class="bi bi-phone text-primary me-2"></i>Mitgliederportal';
        echo '</a></li>';

        // Changelog-Link
        echo '<li class="mobile-nav-item">';
        echo '<a class="mobile-user-menu-link" href="changelog.php">';
        echo '<i class="bi bi-megaphone me-2"></i>Changelog';
        echo '</a></li>';

        // Logout
        echo '<li class="mobile-nav-item">';
        echo '<a class="mobile-user-menu-link text-danger" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">';
        echo '<i class="bi bi-box-arrow-right me-2"></i>Abmelden';
        echo '</a></li>';

        echo '</ul>';
        echo '</div>'; // mobile-user-section

        echo '</div>'; // offcanvas-body
        echo '</div>'; // offcanvas-nav
    }

    private function renderMobileItem($item, &$byParent, $currentPage, $depth = 0) {
        $id = (int)$item['ID'];
        $text = $this->escape($item['Text']);
        $link = trim((string)$item['Link']);

        // Links die nur im Usermenu erscheinen sollen, nicht in der mobilen Hauptnavigation
        if (in_array($link, $this->userMenuOnlyLinks)) return;

        $hasChildren = isset($byParent[$id]) && count($byParent[$id]) > 0;
        $isActive = $this->isActiveDeep($id, $link, $currentPage, $byParent);

        echo '<li class="mobile-nav-item">';

        if ($hasChildren) {
            // Has submenu - accordion toggle
            echo '<a class="mobile-nav-link '.($isActive?'active':'').'" href="#" data-has-submenu>';
            echo $text;
            echo '<i class="bi bi-chevron-down"></i>';
            echo '</a>';

            // Submenu
            echo '<ul class="mobile-submenu">';
            foreach ($byParent[$id] as $child) {
                $this->renderMobileSubmenuItem($child, $byParent, $currentPage, 1);
            }
            echo '</ul>';
        } else {
            // No submenu - direct link
            echo '<a class="mobile-nav-link '.($isActive?'active':'').'" href="'.$this->escape($link).'">';
            echo $text;
            echo '</a>';
        }

        echo '</li>';
    }

    private function renderMobileSubmenuItem($item, &$byParent, $currentPage, $depth) {
        $id = (int)$item['ID'];
        $text = $this->escape($item['Text']);
        $link = trim((string)$item['Link']);

        // Links die nur im Usermenu erscheinen sollen, nicht in der mobilen Hauptnavigation
        if (in_array($link, $this->userMenuOnlyLinks)) return;

        $hasChildren = isset($byParent[$id]) && count($byParent[$id]) > 0;
        $isActive = $this->isActiveDeep($id, $link, $currentPage, $byParent);

        echo '<li class="mobile-submenu-item">';

        if ($hasChildren) {
            // Nested submenu
            echo '<a class="mobile-submenu-link '.($isActive?'active':'').'" href="#" data-has-submenu>';
            echo $text;
            echo '<i class="bi bi-chevron-down ms-auto"></i>';
            echo '</a>';
            echo '<ul class="mobile-submenu mobile-nested-submenu">';
            foreach ($byParent[$id] as $child) {
                $this->renderMobileSubmenuItem($child, $byParent, $currentPage, $depth + 1);
            }
            echo '</ul>';
        } else {
            echo '<a class="mobile-submenu-link '.($isActive?'active':'').'" href="'.$this->escape($link).'">';
            echo $text;
            echo '</a>';
        }

        echo '</li>';
    }

    private function renderItemRecursive($item, &$byParent, $currentPage, $depth = 0) {
        $id = (int)$item['ID'];
        $text = $this->escape($item['Text']);
        $link = trim((string)$item['Link']);

        // Links die nur im Usermenu erscheinen sollen, nicht in der Hauptnavigation
        if (in_array($link, $this->userMenuOnlyLinks)) return;

        $hasChildren = isset($byParent[$id]) && count($byParent[$id]) > 0;
        $isActive = $this->isActiveDeep($id, $link, $currentPage, $byParent);

        if ($depth === 0) {
            // ROOT-EBENE
            if ($hasChildren) {
                echo '<li class="nav-item dropdown">';
                echo '<a class="nav-link dropdown-toggle '.($isActive?'active':'').'" href="#" id="nav_'.$id.'" role="button" data-bs-toggle="dropdown">';
                echo $text;
                echo '</a>';
                echo '<ul class="dropdown-menu">';
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
                echo '<li class="dropdown-submenu">';
                echo '<div class="dropdown-item-wrapper">';
                
                if (!empty($link) && $link !== '#') {
                    echo '<a class="dropdown-item '.($isActive?'active':'').'" href="'.$this->escape($link).'">'.$text.'</a>';
                } else {
                    echo '<span class="dropdown-item dropdown-text '.($isActive?'active':'').'">'.$text.'</span>';
                }
                
                echo '<button class="dropdown-submenu-toggle" type="button">';
                echo '<i class="bi bi-chevron-right"></i>';
                echo '</button>';
                echo '</div>';
                
                echo '<ul class="dropdown-menu dropdown-submenu-menu">';
                foreach ($byParent[$id] as $child) {
                    $this->renderItemRecursive($child, $byParent, $currentPage, $depth + 1);
                }
                echo '</ul>';
                echo '</li>';
            } else {
                echo '<li><a class="dropdown-item '.($isActive?'active':'').'" href="'.$this->escape($link).'">'.$text.'</a></li>';
            }
        }
    }

    private function isActiveDeep($itemId, $itemLink, $currentPage, &$byParent) {
        // Normalisiere beide Pfade für den Vergleich
        $itemLinkNorm = $this->normalizePath($itemLink);
        $currentPageNorm = $this->normalizePath($currentPage);
        
        // Direkter Vergleich (entweder vollständiger Pfad oder Dateiname)
        if ($currentPageNorm === $itemLinkNorm) return true;
        
        // Fallback: Vergleiche nur Dateinamen
        if (basename($currentPageNorm) === basename($itemLinkNorm)) {
            // Prüfe ob es sich um denselben Ordner handelt oder keine Ordner angegeben sind
            $currentDir = dirname($currentPageNorm);
            $itemDir = dirname($itemLinkNorm);
            if ($currentDir === $itemDir || $currentDir === '.' || $itemDir === '.') {
                return true;
            }
        }
        
        // Rekursiv Kinder prüfen
        if (!isset($byParent[$itemId])) return false;
        foreach ($byParent[$itemId] as $child) {
            if ($this->isActiveDeep((int)$child['ID'], $child['Link'], $currentPage, $byParent)) {
                return true;
            }
        }
        return false;
    }
    
    // Hilfsfunktion zum Normalisieren von Pfaden
    private function normalizePath($path) {
        // Entferne führende/abschließende Slashes und normalisiere
        $path = trim((string)$path);
        $path = str_replace('\\', '/', $path);
        
        // Wenn es nur ein Dateiname ist, gib ihn zurück
        if (strpos($path, '/') === false) {
            return $path;
        }
        
        // Für relative Pfade: normalisiere sie
        // z.B. "./admin/file.php" -> "admin/file.php"
        $path = preg_replace('#^\\./+#', '', $path);
        
        return $path;
    }

    private function renderUserDropdown(&$byParent) {
        $username = $_SESSION['username'] ?? 'Benutzer';

        echo '<li class="nav-item dropdown ms-auto">';
        echo '<a class="nav-link dropdown-toggle user-menu" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">';
        echo '<i class="bi bi-person-circle me-1"></i><span class="d-none d-lg-inline">'.$this->escape($username).'</span></a>';
        echo '<ul class="dropdown-menu dropdown-menu-end">';

        // Einträge direkt per Link-Name suchen (unabhängig von ParentID in der DB)
        $data = $this->loadNavigationData();
        $navFlat = $data['flat'] ?? [];

        $icons = ['Passwort ändern'=>'bi-key','Sichern & Wiederherstellen'=>'bi-hdd','Profil'=>'bi-person','Einstellungen'=>'bi-gear'];
        $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
        $hasUserItems = false;
        foreach ($navFlat as $item) {
            $link = trim($item['Link']);
            if (!in_array($link, $this->userMenuOnlyLinks)) continue;
            if ($link === 'backup_restore.php' && !$isAdmin) continue;
            $icon = $icons[$item['Text']] ?? 'bi-circle';
            $colorClass = $link === 'backup_restore.php' ? ' text-warning' : '';
            echo '<li><a class="dropdown-item" href="'.$this->escape($link).'"><i class="bi '.$icon.' me-2'.$colorClass.'"></i>'.$this->escape($item['Text']).'</a></li>';
            $hasUserItems = true;
        }
        if ($hasUserItems) echo '<li><hr class="dropdown-divider"></li>';

        // Admin-Einträge (Benutzerverwaltung + Navigation verwalten)
        if (($_SESSION['user_role'] ?? '') === 'admin') {
            echo '<li><a class="dropdown-item" href="benutzerverwaltung.php"><i class="bi bi-people-fill me-2 text-warning"></i>Benutzerverwaltung</a></li>';
            $adminHref = file_exists('admin/nav_admin.php') ? 'admin/nav_admin.php' : '../admin/nav_admin.php';
            echo '<li><a class="dropdown-item" href="'.$this->escape($adminHref).'"><i class="bi bi-menu-button-wide me-2"></i>Navigation verwalten</a></li>';
            echo '<li><hr class="dropdown-divider"></li>';
        }

        // Portal-Link fuer Admin/Vorstand
        $portalHref = file_exists('portal/dashboard.php') ? 'portal/dashboard.php' : '../portal/dashboard.php';
        echo '<li><a class="dropdown-item" href="'.$this->escape($portalHref).'"><i class="bi bi-phone me-2 text-primary"></i>Mitgliederportal</a></li>';
        echo '<li><a class="dropdown-item" href="changelog.php"><i class="bi bi-megaphone me-2"></i>Changelog</a></li>';
        echo '<li><hr class="dropdown-divider"></li>';

        echo '<li><a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal"><i class="bi bi-box-arrow-right me-2"></i>Abmelden</a></li>';
        echo '</ul></li>';
    }

    public function generateBreadcrumb() {
        $data = $this->loadNavigationData();
        $flat = $data['flat'] ?? [];
        $currentPage = basename($_SERVER['PHP_SELF']);

        $currentItem = null;
        foreach ($flat as $item) {
            if (basename((string)$item['Link']) === $currentPage) {
                $currentItem = $item; 
                break;
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
            echo '<li class="breadcrumb-item"><a href="home.php"><i class="bi bi-house"></i></a></li>';
            foreach ($breadcrumbs as $i => $crumb) {
                if ($i === count($breadcrumbs) - 1) {
                    echo '<li class="breadcrumb-item active">'.$this->escape($crumb['Text']).'</li>';
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

    private function printCompactStyles() {
        echo '<style>
/* Kompakte Navigation Styles - Spezifisch für Navigation */
:root {
    --nav-height: 56px;
    --nav-shadow: 0 2px 4px rgba(0,0,0,0.08);
    --nav-shadow-scrolled: 0 4px 12px rgba(0,0,0,0.12);
    --nav-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --nav-active-color: #2563eb;
    --nav-active-bg: rgba(52, 152, 219, 0.12);
}

.navbar {
    min-height: var(--nav-height);
    padding: 0.5rem 1rem !important;
    background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
    border-bottom: 1px solid rgba(0,0,0,0.05);
    z-index: 1030;
    transition: var(--nav-transition);
}

.navbar.scrolled {
    box-shadow: var(--nav-shadow-scrolled);
}

/* Seitentitel in Navbar — nur für iOS PWA relevant, auf Desktop ausgeblendet */
.navbar-page-title {
    display: none;
}

.navbar-page-title:empty {
    display: none;
}

/* iOS PWA: Brand-Text ausblenden, Seitentitel immer sichtbar */
.ios-pwa .navbar-brand-text {
    display: none;
}

.ios-pwa .navbar-page-title {
    opacity: 1;
    max-width: 65vw;
}

/* iOS PWA: Erstes h1/h2/h3 im Content ausblenden (Titel steht bereits in Navbar) */
.ios-pwa .container-fluid > .row > .col-12 > h1,
.ios-pwa .container-fluid > .row > .col-12 > h2,
.ios-pwa .container-fluid > .row > .col-12 > h3 {
    display: none;
}

.navbar-brand {
    font-size: 1.1rem;
    font-weight: 600;
    padding: 0.25rem 0;
}

.navbar-nav {
    flex-wrap: nowrap;
    align-items: center;
    width: 100%;
}

.navbar-nav .nav-link {
    font-size: 0.85rem;
    font-weight: 500;
    padding: 0.35rem 0.5rem !important;
    margin: 0;
    border-radius: 6px;
    transition: var(--nav-transition);
    position: relative;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    flex-shrink: 1;
    min-width: 0;
}

.navbar-nav .nav-link:hover {
    background: rgba(52, 152, 219, 0.08);
    transform: translateY(-1px);
}

/* Subtilerer Active-Style */
.navbar-nav .nav-link.active {
    background: rgba(52, 152, 219, 0.12);
    color: #2563eb !important;
    font-weight: 600;
    position: relative;
}

/* Unterstrich für aktives Element */
.navbar-nav .nav-link.active::after {
    content: "";
    position: absolute;
    bottom: -2px;
    left: 20%;
    right: 20%;
    height: 3px;
    background: linear-gradient(90deg, #3498db, #2563eb);
    border-radius: 3px;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from { width: 0; left: 50%; right: 50%; }
    to { left: 20%; right: 20%; }
}

.navbar-nav .nav-item.ms-auto {
    flex-shrink: 0;
}

.navbar-nav .nav-link.user-menu {
    background: rgba(52, 152, 219, 0.1);
    padding: 0.3rem 0.6rem !important;
    border-radius: 20px;
    font-size: 0.85rem;
    flex-shrink: 0;
}

/* Navigation Dropdown spezifisch */
.navbar .dropdown-menu {
    margin-top: 0.25rem !important;
    padding: 0.5rem;
    border: none;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    min-width: 180px;
    z-index: 1040;
    animation: dropdownFade 0.2s ease;
}

/* Hover-Indikator für Dropdown-Toggle */
@media (min-width: 992px) {
    .navbar .nav-item.dropdown:hover .nav-link {
        background: rgba(52, 152, 219, 0.08);
    }
}

@keyframes dropdownFade {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}

.navbar .dropdown-item {
    font-size: 0.875rem;
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
    margin: 0.1rem 0;
    transition: var(--nav-transition);
    position: relative;
}

.navbar .dropdown-item:hover {
    background: rgba(52, 152, 219, 0.1);
}

/* Subtilerer Active-Style für Dropdown Items */
.navbar .dropdown-item.active {
    background: rgba(52, 152, 219, 0.15);
    color: #2563eb;
    font-weight: 600;
    border-left: 3px solid #3498db;
    padding-left: calc(0.75rem - 3px);
}

.navbar .dropdown-item i {
    width: 1.2rem;
    font-size: 0.9rem;
    opacity: 0.7;
}

/* Kompakte Submenus - Navigation spezifisch */
.navbar .dropdown-submenu {
    position: relative;
}

.navbar .dropdown-item-wrapper {
    display: flex;
    align-items: center;
    margin: 0.1rem 0;
    border-radius: 6px;
}

.navbar .dropdown-submenu .dropdown-item,
.navbar .dropdown-submenu .dropdown-text {
    flex: 1;
    margin: 0;
    border-radius: 6px 0 0 6px;
}

.navbar .dropdown-submenu-toggle {
    padding: 0.5rem;
    background: transparent;
    border: none;
    border-left: 1px solid rgba(0,0,0,0.08);
    cursor: pointer;
    min-width: 32px;
    transition: var(--nav-transition);
}

.navbar .dropdown-submenu-toggle:hover {
    background: rgba(52, 152, 219, 0.1);
}

.navbar .dropdown-submenu-toggle i {
    font-size: 0.7rem;
    transition: transform 0.2s;
}

.navbar .dropdown-submenu-menu {
    position: absolute;
    top: 0;
    left: 100%;
    margin-left: 0.25rem;
    display: none;
    min-width: 180px;
    border-radius: 8px;
    box-shadow: 0 6px 16px rgba(0,0,0,0.1);
    padding: 0.4rem;
    background: rgba(255, 255, 255, 0.98);
}

.navbar .dropdown-submenu-menu.show {
    display: block;
    animation: submenuSlide 0.15s ease;
}

@keyframes submenuSlide {
    from { opacity: 0; transform: translateX(-8px); }
    to { opacity: 1; transform: translateX(0); }
}

.navbar .dropdown-divider {
    margin: 0.3rem 0;
    opacity: 0.2;
}

/* === MOBILE OFF-CANVAS MENU === */
@media (max-width: 991.98px) {
    /* Hide default Bootstrap collapse */
    .navbar-collapse {
        display: none !important;
    }

    /* Hamburger Button - Touch-friendly */
    .navbar-toggler {
        min-width: 48px;
        min-height: 48px;
        padding: 12px;
        border: none;
        font-size: 24px;
    }

    .navbar-toggler:focus {
        box-shadow: none;
    }

    /* Off-Canvas Container */
    .offcanvas-nav {
        position: fixed;
        top: 0;
        left: -320px;
        width: 320px;
        height: 100vh;
        background: white;
        z-index: 9999;
        transition: left 0.3s ease;
        overflow-y: auto;
        box-shadow: 4px 0 12px rgba(0,0,0,0.15);
    }

    .offcanvas-nav.show {
        left: 0;
    }

    /* Overlay */
    .offcanvas-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 9998;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s ease, visibility 0.3s ease;
    }

    .offcanvas-overlay.show {
        opacity: 1;
        visibility: visible;
    }

    /* Off-Canvas Header */
    .offcanvas-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem 1.25rem;
        border-bottom: 2px solid #e9ecef;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    }

    .offcanvas-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--secondary-color);
        margin: 0;
    }

    .offcanvas-close {
        min-width: 44px;
        min-height: 44px;
        background: transparent;
        border: none;
        font-size: 28px;
        color: #6c757d;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        transition: all 0.2s;
    }

    .offcanvas-close:active {
        background: #e9ecef;
        transform: scale(0.95);
    }

    /* Off-Canvas Body */
    .offcanvas-body {
        padding: 0;
    }

    /* Mobile Menu Items */
    .mobile-nav-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .mobile-nav-item {
        border-bottom: 1px solid #f0f0f0;
    }

    .mobile-nav-link {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px;
        color: #212529;
        text-decoration: none;
        font-size: 16px;
        font-weight: 500;
        min-height: 48px;
        transition: all 0.2s;
    }

    .mobile-nav-link:active {
        background: #f8f9fa;
    }

    .mobile-nav-link.active {
        background: rgba(52, 152, 219, 0.1);
        color: #2563eb;
        font-weight: 600;
        border-left: 4px solid #3498db;
        padding-left: 16px;
    }

    .mobile-nav-link i.bi-chevron-down {
        font-size: 14px;
        transition: transform 0.2s;
    }

    .mobile-nav-link.expanded i.bi-chevron-down {
        transform: rotate(180deg);
    }

    /* Mobile Submenu (Accordion) */
    .mobile-submenu {
        max-height: 0;
        overflow: hidden;
        background: #f8f9fa;
        transition: max-height 0.3s ease;
    }

    .mobile-submenu.show {
        max-height: 1000px;
    }

    .mobile-submenu-item {
        border-bottom: 1px solid #e9ecef;
    }

    .mobile-submenu-item:last-child {
        border-bottom: none;
    }

    .mobile-submenu-link {
        display: block;
        padding: 14px 20px 14px 40px;
        color: #495057;
        text-decoration: none;
        font-size: 15px;
        min-height: 48px;
        transition: all 0.2s;
    }

    .mobile-submenu-link:active {
        background: #e9ecef;
    }

    .mobile-submenu-link.active {
        background: rgba(52, 152, 219, 0.15);
        color: #2563eb;
        font-weight: 600;
    }

    /* Nested Submenu (Level 2) */
    .mobile-nested-submenu {
        background: #e9ecef;
    }

    .mobile-nested-submenu .mobile-submenu-link {
        padding-left: 60px;
        font-size: 14px;
    }

    /* User Section */
    .mobile-user-section {
        margin-top: auto;
        border-top: 2px solid #dee2e6;
        background: #f8f9fa;
    }

    .mobile-user-header {
        display: flex;
        align-items: center;
        padding: 12px 16px;
        background: white;
        border-bottom: 1px solid #e9ecef;
    }

    .mobile-user-icon {
        width: 36px;
        height: 36px;
        background: linear-gradient(135deg, #3498db, #2563eb);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 16px;
        margin-right: 10px;
        flex-shrink: 0;
    }

    .mobile-user-name {
        font-weight: 600;
        color: #212529;
        font-size: 15px;
    }

    /* User-Menu Links (ohne Chevron, Icon+Text nebeneinander) */
    .mobile-user-menu-link {
        display: flex;
        align-items: center;
        padding: 13px 20px;
        color: #212529;
        text-decoration: none;
        font-size: 15px;
        font-weight: 500;
        min-height: 48px;
        transition: background 0.2s;
    }

    .mobile-user-menu-link:active {
        background: #e9ecef;
    }

    .mobile-user-menu-link.active {
        background: rgba(52, 152, 219, 0.1);
        color: #2563eb;
        font-weight: 600;
        border-left: 4px solid #3498db;
        padding-left: 16px;
    }

    .mobile-user-menu-link i {
        font-size: 15px;
        width: 20px;
        flex-shrink: 0;
    }
}

/* Desktop: Off-Canvas Elemente komplett ausblenden */
@media (min-width: 992px) {
    .offcanvas-nav,
    .offcanvas-overlay {
        display: none !important;
    }
}

/* Breadcrumb kompakt */
.breadcrumb {
    font-size: 0.85rem;
    padding: 0.5rem 0;
    margin-bottom: 0.5rem;
}

.breadcrumb-item + .breadcrumb-item::before {
    content: "\203A";
}
</style>';
    }

    private function printCompactScript() {
        echo '<script>
document.addEventListener("DOMContentLoaded", function() {
    // iOS PWA detection
    if (window.navigator.standalone === true) {
        document.body.classList.add("ios-pwa");
    }

    // Scroll-Effekt
    window.addEventListener("scroll", function() {
        const navbar = document.querySelector(".navbar");
        if (window.pageYOffset > 50) {
            navbar?.classList.add("scrolled");
        } else {
            navbar?.classList.remove("scrolled");
        }
    });

    // === MOBILE OFF-CANVAS MENU ===
    // Immer initialisieren (CSS regelt Sichtbarkeit per Media Query)
    initMobileOffCanvas();

    function initMobileOffCanvas() {
        const offcanvas = document.querySelector(".offcanvas-nav");
        const overlay = document.querySelector(".offcanvas-overlay");
        const toggler = document.querySelector(".navbar-toggler");
        const closeBtn = document.querySelector(".offcanvas-close");

        if (!offcanvas || !overlay || !toggler) {
            console.error("Off-Canvas elements not found:", {
                offcanvas: !!offcanvas,
                overlay: !!overlay,
                toggler: !!toggler
            });
            return;
        }

        console.log("Off-Canvas menu initialized");

        // Remove Bootstrap data attributes from toggler
        toggler.removeAttribute("data-bs-toggle");
        toggler.removeAttribute("data-bs-target");

        // Open menu
        toggler.addEventListener("click", function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log("Toggler clicked - opening menu");
            offcanvas.classList.add("show");
            overlay.classList.add("show");
            document.body.style.overflow = "hidden";
        });

        // Close menu
        function closeMenu() {
            offcanvas.classList.remove("show");
            overlay.classList.remove("show");
            document.body.style.overflow = "";
        }

        if (closeBtn) {
            closeBtn.addEventListener("click", closeMenu);
        }

        overlay.addEventListener("click", closeMenu);

        // Accordion for submenus (all levels)
        document.querySelectorAll(".mobile-nav-link[data-has-submenu], .mobile-submenu-link[data-has-submenu]").forEach(link => {
            link.addEventListener("click", function(e) {
                e.preventDefault();
                const submenu = this.nextElementSibling;

                // Close sibling submenus at the same level only
                const parentList = this.closest("ul");
                parentList?.querySelectorAll("[data-has-submenu].expanded").forEach(other => {
                    if (other !== this) {
                        other.classList.remove("expanded");
                        other.nextElementSibling?.classList.remove("show");
                    }
                });

                // Toggle current submenu
                this.classList.toggle("expanded");
                submenu?.classList.toggle("show");

                // Persist level-1 submenu state across page navigations
                if (this.classList.contains("mobile-nav-link")) {
                    const allL1Toggles = document.querySelectorAll(".mobile-nav-link[data-has-submenu]");
                    const idx = Array.from(allL1Toggles).indexOf(this);
                    if (this.classList.contains("expanded")) {
                        sessionStorage.setItem("msvOpenSubmenu", idx);
                    } else {
                        sessionStorage.removeItem("msvOpenSubmenu");
                    }
                }
            });
        });

        // Restore persisted submenu state on page load
        const savedIdx = sessionStorage.getItem("msvOpenSubmenu");
        if (savedIdx !== null) {
            const allL1Toggles = document.querySelectorAll(".mobile-nav-link[data-has-submenu]");
            const targetLink = allL1Toggles[parseInt(savedIdx)];
            if (targetLink) {
                targetLink.classList.add("expanded");
                targetLink.nextElementSibling?.classList.add("show");
            }
        }

        // Close menu when link is clicked (not submenu toggle)
        document.querySelectorAll(".mobile-nav-link:not([data-has-submenu]), .mobile-submenu-link:not([data-has-submenu]), .mobile-user-menu-link").forEach(link => {
            link.addEventListener("click", function() {
                // Small delay for better UX
                setTimeout(closeMenu, 150);
            });
        });

        // Swipe to close (optional enhancement)
        let touchStartX = 0;
        offcanvas.addEventListener("touchstart", function(e) {
            touchStartX = e.touches[0].clientX;
        });

        offcanvas.addEventListener("touchmove", function(e) {
            const touchX = e.touches[0].clientX;
            const diff = touchX - touchStartX;

            // Swipe left to close
            if (diff < -50) {
                closeMenu();
            }
        });
    }
    
    // Hover-Funktionalität für Hauptnavigation (Desktop)
    if (window.innerWidth >= 992) {
        // Hauptmenü-Dropdowns bei Hover
        document.querySelectorAll(".navbar .nav-item.dropdown").forEach(dropdown => {
            let timeout;
            const toggle = dropdown.querySelector(".dropdown-toggle");
            const menu = dropdown.querySelector(".dropdown-menu");
            
            // Mouseenter auf dem gesamten Dropdown-Bereich
            dropdown.addEventListener("mouseenter", function() {
                clearTimeout(timeout);
                // Bootstrap Dropdown programmatisch öffnen
                const bsDropdown = bootstrap.Dropdown.getOrCreateInstance(toggle);
                bsDropdown.show();
            });
            
            // Mouseleave mit kleiner Verzögerung
            dropdown.addEventListener("mouseleave", function() {
                timeout = setTimeout(() => {
                    const bsDropdown = bootstrap.Dropdown.getInstance(toggle);
                    if (bsDropdown) {
                        bsDropdown.hide();
                    }
                }, 100); // 100ms Verzögerung für bessere Usability
            });
            
            // Click-Toggle bleibt für Touch-Geräte erhalten
            toggle.addEventListener("click", function(e) {
                if (window.innerWidth >= 992) {
                    e.preventDefault();
                    // Bei Click navigieren wenn Link vorhanden
                    if (this.href && this.href !== "#") {
                        window.location.href = this.href;
                    }
                }
            });
        });
        
        // Submenu-Hover (bleibt wie es war)
        document.querySelectorAll(".navbar .dropdown-submenu").forEach(elem => {
            let timeout;
            elem.addEventListener("mouseenter", function() {
                clearTimeout(timeout);
                const submenu = this.querySelector(".dropdown-submenu-menu");
                if (submenu) {
                    submenu.classList.add("show");
                    // Viewport-Overflow-Fix: Submenu links anzeigen wenn rechts kein Platz
                    const rect = submenu.getBoundingClientRect();
                    if (rect.right > window.innerWidth) {
                        submenu.style.left = "auto";
                        submenu.style.right = "100%";
                        submenu.style.marginLeft = "0";
                        submenu.style.marginRight = "0.25rem";
                    }
                    const icon = this.querySelector(".dropdown-submenu-toggle i");
                    if (icon) icon.style.transform = "rotate(90deg)";
                }
            });

            elem.addEventListener("mouseleave", function() {
                const submenu = this.querySelector(".dropdown-submenu-menu");
                if (submenu) {
                    timeout = setTimeout(() => {
                        submenu.classList.remove("show");
                        // Viewport-Fix zurücksetzen
                        submenu.style.left = "";
                        submenu.style.right = "";
                        submenu.style.marginLeft = "";
                        submenu.style.marginRight = "";
                        const icon = this.querySelector(".dropdown-submenu-toggle i");
                        if (icon) icon.style.transform = "";
                    }, 200);
                }
            });
        });
    }
    
    // Submenu-Toggle für Click (Mobile und als Fallback)
    document.querySelectorAll(".navbar .dropdown-submenu-toggle").forEach(btn => {
        btn.addEventListener("click", function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const submenu = this.closest(".dropdown-submenu").querySelector(".dropdown-submenu-menu");
            const parent = this.closest(".dropdown-menu");
            
            parent.querySelectorAll(".dropdown-submenu-menu.show").forEach(s => {
                if (s !== submenu) s.classList.remove("show");
            });
            
            submenu.classList.toggle("show");
            this.querySelector("i").style.transform = submenu.classList.contains("show") ? "rotate(90deg)" : "";
        });
    });
    
    // Cleanup bei Dropdown-Close
    document.querySelectorAll(".navbar .dropdown").forEach(dropdown => {
        dropdown.addEventListener("hide.bs.dropdown", function() {
            this.querySelectorAll(".dropdown-submenu-menu.show").forEach(menu => {
                menu.classList.remove("show");
            });
            this.querySelectorAll(".dropdown-submenu-toggle i").forEach(icon => {
                icon.style.transform = "";
            });
        });
    });
});
</script>';
    }
}

// Verwendung:
$nav = NavigationManager::getInstance();
$nav->generateNavigation();
?>