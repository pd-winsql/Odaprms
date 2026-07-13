document.addEventListener('DOMContentLoaded', () => {
    const logoutLinks = document.querySelectorAll('[data-logout-confirm]');
    const modalElement = document.getElementById('logoutModal');
    const confirmButton = document.getElementById('confirmLogoutBtn');

    const resetModals = () => {
        document.querySelectorAll('.modal').forEach((modal) => {
            const instance = bootstrap.Modal.getInstance(modal);
            if (instance) {
                instance.hide();
            }
            modal.classList.remove('show');
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        });

        document.querySelectorAll('.modal-backdrop').forEach((backdrop) => backdrop.remove());
        document.body.classList.remove('modal-open');
    };

    resetModals();

    if (!modalElement || !confirmButton) {
        return;
    }

    const logoutModal = bootstrap.Modal.getOrCreateInstance(modalElement);
    let logoutUrl = '';

    logoutLinks.forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            logoutUrl = link.getAttribute('data-logout-confirm') || '';
            resetModals();
            logoutModal.show();
        });
    });

    confirmButton.addEventListener('click', () => {
        if (logoutUrl) {
            window.location.href = logoutUrl;
        }
    });

    window.resetDentalAssistantModals = resetModals;
});
