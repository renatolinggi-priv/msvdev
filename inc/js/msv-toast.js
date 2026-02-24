/**
 * MSV Toast/Benachrichtigungssystem - Zentrale Funktionen
 * Nutzt SweetAlert2 für einheitliche Benachrichtigungen
 */

// Zentrale Toast-Funktion (nutzt SweetAlert2 Toast-Mode)
function msvToast(message, type = 'success') {
    // Bootstrap 'danger' auf SweetAlert2 'error' mappen
    if (type === 'danger') type = 'error';

    // Responsive: Mobile kompakter und unter Navbar, Desktop wie gewohnt
    const isMobile = window.innerWidth < 992;

    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        customClass: {
            popup: isMobile ? 'swal2-toast-mobile' : ''
        },
        didOpen: (toast) => {
            // Mobile: Kompakteres Styling unter dem Hamburger-Button
            if (isMobile) {
                toast.style.top = '60px'; // Unter der Navbar/Hamburger
                toast.style.fontSize = '13px';
                toast.style.padding = '6px 10px';
                toast.style.minWidth = 'auto';
                toast.style.maxWidth = '85%';
                toast.style.right = '10px';
            }
        }
    });
    Toast.fire({ icon: type, title: message });
}

// Zentrale Lösch-Bestätigung mit spezifischem Namen
function msvConfirmDelete(itemName) {
    return Swal.fire({
        title: 'Löschen bestätigen',
        html: `Möchtest du <strong>${itemName}</strong> wirklich löschen?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ja, löschen',
        cancelButtonText: 'Abbrechen'
    });
}

// Zentrale Fehler-Anzeige
function msvError(message) {
    Swal.fire({ icon: 'error', title: 'Fehler', text: message });
}

// Zentrale Erfolgs-Anzeige
function msvSuccess(message) {
    msvToast(message, 'success');
}

// Generische Bestätigung (für beliebige Aktionen)
function msvConfirm(message, title = 'Bestätigen', confirmText = 'Ja, fortfahren') {
    return Swal.fire({
        title: title,
        html: message,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#6c757d',
        confirmButtonText: confirmText,
        cancelButtonText: 'Abbrechen'
    });
}
