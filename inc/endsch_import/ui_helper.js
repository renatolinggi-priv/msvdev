// endsch_import/ui_helper.js – vereinheitlichte UI-Utilities (wie Vorlage)
const UIHelper = {
  getToastContainer() {
    let el = document.getElementById('toast-container');

    if (!el) {
      el = document.createElement('div');
      el.id = 'toast-container';
    }

    // Immer direkt unter <body> hängen (falls es irgendwo anders im DOM liegt)
    if (el.parentNode !== document.body) {
      document.body.appendChild(el);
    }

    // Styles IMMER setzen (robust gegen vorherige Zustände)
    Object.assign(el.style, {
      position: 'fixed',
      top: '70px',  // ggf. 20px, wenn’s besser passt
      right: '20px',
      zIndex: '9999',
      left: ''      // sicherstellen, dass nichts von links “klemmt”
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
  },

  showLoading(text = 'Bitte warten…') {
    let overlay = document.getElementById('loading-overlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.id = 'loading-overlay';
      overlay.className = 'loading-overlay';
      overlay.innerHTML = `
        <div class="loading-box d-flex align-items-center">
          <div class="spinner-border" role="status" aria-hidden="true"></div>
          <div class="ms-3">${text}</div>
        </div>`;
      document.body.appendChild(overlay);
    }
    overlay.style.display = 'flex';
  },

  hideLoading() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) overlay.style.display = 'none';
  },

  setActiveStep(stepIndex /* 1..3 */) {
    const steps = document.querySelectorAll('.workflow-steps .step');
    steps.forEach((el, i) => {
      el.classList.remove('active', 'completed');
      if (i < stepIndex - 1) el.classList.add('completed');
      if (i === stepIndex - 1) el.classList.add('active');
    });
    document.querySelectorAll('.phase').forEach(p => p.classList.remove('active'));
    const phase = document.querySelector(`#phase${stepIndex}`);
    if (phase) phase.classList.add('active');
  }
};

// Globalen Export sicherstellen
window.UIHelper = UIHelper;
