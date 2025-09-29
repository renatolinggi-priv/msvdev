// check_resultscsv/ui_helper.js – UI-Utilities für CSV Viewer
const UIHelper = {
  getToastContainer() {
    let el = document.getElementById('toast-container');

    if (!el) {
      el = document.createElement('div');
      el.id = 'toast-container';
    }

    // Immer direkt unter <body> hängen
    if (el.parentNode !== document.body) {
      document.body.appendChild(el);
    }

    // Styles setzen
    Object.assign(el.style, {
      position: 'fixed',
      top: '70px',
      right: '20px',
      zIndex: '9999',
      left: ''
    });

    return el;
  },

  showToast(message, type = 'info', duration = 4000) {
    const container = this.getToastContainer();
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-bg-${type === 'error' ? 'danger' : type} border-0 show`;
    toast.setAttribute('role', 'alert');
    toast.style.marginBottom = '0.5rem';
    toast.innerHTML = `
      <div class="d-flex">
        <div class="toast-body">${message}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>`;
    container.appendChild(toast);
    toast.querySelector('.btn-close').addEventListener('click', () => toast.remove());
    setTimeout(() => toast.remove(), duration);
  }
};

// Globalen Export sicherstellen
window.UIHelper = UIHelper;
