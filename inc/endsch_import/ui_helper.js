// endsch_import/ui_helper.js – vereinheitlichte UI-Utilities (wie Vorlage)
const UIHelper = {
  showToast(message, type = 'info') {
    msvToast(message, type);
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
