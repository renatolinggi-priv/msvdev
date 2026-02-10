// check_resultscsv/ui_helper.js – UI-Utilities für CSV Viewer
const UIHelper = {
  showToast(message, type = 'info') {
    msvToast(message, type);
  }
};

// Globalen Export sicherstellen
window.UIHelper = UIHelper;
