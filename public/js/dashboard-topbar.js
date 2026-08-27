(() => {
    'use strict';

    const TIME_ZONE = 'Asia/Manila';
    const dateElement = document.getElementById('vdTopbarDate');
    const clockElement = document.getElementById('vdTopbarClock');

    if (!dateElement || !clockElement) return;
    const dateFormatter = new Intl.DateTimeFormat('en-PH', {
        timeZone: TIME_ZONE,
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric'
    });
    const timeFormatter = new Intl.DateTimeFormat('en-PH', {
        timeZone: TIME_ZONE,
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    });

    function updateTopbar() {
        const now = new Date();
        dateElement.textContent = dateFormatter.format(now);
        dateElement.dateTime = now.toISOString();
        clockElement.textContent = timeFormatter.format(now);
        clockElement.dateTime = now.toISOString();
    }

    updateTopbar();
    window.setInterval(updateTopbar, 60000);
})();
