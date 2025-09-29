// ui_helper.js - UI Helper Functions
const UIHelper = {
    showToast(message, type = 'info') {
        const toastHtml = `
            <div class="toast-message toast-${type}">
                <i class="bi ${type === 'success' ? 'bi-check-circle' : type === 'warning' ? 'bi-exclamation-triangle' : type === 'error' ? 'bi-x-circle' : 'bi-info-circle'} me-2"></i>
                ${message}
            </div>
        `;
        
        const toastElement = $(toastHtml);
        $('#toast-container').append(toastElement);
        
        setTimeout(() => {
            toastElement.addClass('show');
        }, 100);
        
        setTimeout(() => {
            toastElement.removeClass('show');
            setTimeout(() => {
                toastElement.remove();
            }, 300);
        }, 3000);
    }
};