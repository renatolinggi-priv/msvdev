<?php
// navigation.inc.php - Kompakte Version mit allen Funktionen

class NavigationManager {
    private static $navigationCache = null;
    private static $instance = null;

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

        // Standard-Einträge aus Navigation (ParentID=101)
        if (isset($byParent[101])) {
            $icons = ['Passwort ändern'=>'bi-key','Profil'=>'bi-person','Einstellungen'=>'bi-gear'];
            foreach ($byParent[101] as $item) {
                if ($item['Text'] !== 'User anlegen' && $item['Link'] !== 'backup_restore.php') {
                    $icon = $icons[$item['Text']] ?? 'bi-circle';
                    echo '<li><a class="dropdown-item" href="'.$this->escape($item['Link']).'"><i class="bi '.$icon.' me-2"></i>'.$this->escape($item['Text']).'</a></li>';
                }
            }
            echo '<li><hr class="dropdown-divider"></li>';
        }

        // Benutzerverwaltung - nur für User "renato"
        if (strtolower($username) === 'renato') {
            echo '<li><a class="dropdown-item" href="benutzerverwaltung.php"><i class="bi bi-people-fill me-2 text-warning"></i>Benutzerverwaltung</a></li>';
        }

        // Admin-Link "Navigation verwalten" (nur für Admins)
        if (function_exists('user_can_manage_navigation') ? user_can_manage_navigation() : !empty($_SESSION['is_admin'])) {
            $adminHref = file_exists('admin/nav_admin.php') ? 'admin/nav_admin.php' : '../admin/nav_admin.php';
            echo '<li><a class="dropdown-item" href="'.$this->escape($adminHref).'"><i class="bi bi-menu-button-wide me-2"></i>Navigation verwalten</a></li>';
            echo '<li><hr class="dropdown-divider"></li>';
        }

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
    height: var(--nav-height);
    padding: 0.5rem 1rem !important;
    transition: var(--nav-transition);
}

.navbar.scrolled {
    box-shadow: var(--nav-shadow-scrolled);
}

.navbar-brand {
    font-size: 1.1rem;
    font-weight: 600;
    padding: 0.25rem 0;
}

.navbar-nav .nav-link {
    font-size: 0.9rem;
    font-weight: 500;
    padding: 0.4rem 0.8rem !important;
    margin: 0 0.2rem;
    border-radius: 6px;
    transition: var(--nav-transition);
    position: relative;
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

.navbar-nav .nav-link.user-menu {
    background: rgba(52, 152, 219, 0.1);
    padding: 0.3rem 0.6rem !important;
    border-radius: 20px;
    font-size: 0.85rem;
}

/* Navigation Dropdown spezifisch */
.navbar .dropdown-menu {
    margin-top: 0.25rem !important;
    padding: 0.5rem;
    border: none;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    min-width: 180px;
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
    transform: translateX(2px);
}

/* Subtilerer Active-Style für Dropdown Items */
.navbar .dropdown-item.active {
    background: rgba(52, 152, 219, 0.15);
    color: #2563eb;
    font-weight: 600;
    border-left: 3px solid #3498db;
    padding-left: calc(0.75rem - 3px);
}

/* Alternativer Style: Punkt statt voller Hintergrund */
.navbar .dropdown-item.active::before {
    content: "\2022";
    position: absolute;
    left: 0.3rem;
    color: #3498db;
    font-size: 1.2rem;
    line-height: 1;
    display: none; /* Deaktiviert, da wir Border-Left verwenden */
}

.navbar .dropdown-item i {
    width: 1.2rem;
    font-size: 0.9rem;
    opacity: 0.7;
}

/* Kompakte Submenus - Navigation spezifisch */
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
    min-width: 160px;
    border-radius: 8px;
    box-shadow: 0 6px 16px rgba(0,0,0,0.1);
    padding: 0.4rem;
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

/* Mobile */
@media (max-width: 991.98px) {
    .navbar-collapse {
        background: white;
        margin-top: 0.5rem;
        padding: 1rem;
        border-radius: 8px;
        box-shadow: var(--nav-shadow-scrolled);
    }
    
    .navbar .dropdown-submenu-menu {
        position: static;
        margin: 0.25rem 0 0.25rem 1rem;
        box-shadow: none;
        background: rgba(248,249,250,0.5);
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
    // Scroll-Effekt
    window.addEventListener("scroll", function() {
        const navbar = document.querySelector(".navbar");
        if (window.pageYOffset > 50) {
            navbar?.classList.add("scrolled");
        } else {
            navbar?.classList.remove("scrolled");
        }
    });
    
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
                    const icon = this.querySelector(".dropdown-submenu-toggle i");
                    if (icon) icon.style.transform = "rotate(90deg)";
                }
            });
            
            elem.addEventListener("mouseleave", function() {
                const submenu = this.querySelector(".dropdown-submenu-menu");
                if (submenu) {
                    timeout = setTimeout(() => {
                        submenu.classList.remove("show");
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