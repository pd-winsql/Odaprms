document.addEventListener('DOMContentLoaded', () => {
    const logoutLinks = document.querySelectorAll('[data-logout-confirm]');
    const modalElement = document.getElementById('logoutModal');
    const confirmButton = document.getElementById('confirmLogoutBtn');

    if (!modalElement || !confirmButton) {
        return;
    }

    const logoutModal = bootstrap.Modal.getOrCreateInstance(modalElement);
    let logoutUrl = '';

    logoutLinks.forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            logoutUrl = link.getAttribute('data-logout-confirm') || '';
            logoutModal.show();
        });
    });

    confirmButton.addEventListener('click', () => {
        if (logoutUrl) {
            window.location.href = logoutUrl;
        }
    });
});
