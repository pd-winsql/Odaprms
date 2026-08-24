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
        second: '2-digit',
        hour12: true
    });
    const hourFormatter = new Intl.DateTimeFormat('en-GB', {
        timeZone: TIME_ZONE,
        hour: '2-digit',
        hourCycle: 'h23'
    });

    function getGreeting(hour) {
        if (hour < 12) return 'Good morning';
        if (hour < 18) return 'Good afternoon';
        return 'Good evening';
    }

    function updateTopbar() {
        const now = new Date();
        const hour = Number(hourFormatter.format(now));
        const greetingElement = document.getElementById('vdDashboardGreeting');

        if (greetingElement) {
            const userName = greetingElement.dataset.userName || 'there';
            greetingElement.textContent = `${getGreeting(hour)}, ${userName}`;
        }
        dateElement.textContent = dateFormatter.format(now);
        dateElement.dateTime = now.toISOString();
        clockElement.textContent = timeFormatter.format(now);
        clockElement.dateTime = now.toISOString();
    }

    updateTopbar();
    window.setInterval(updateTopbar, 1000);
})();
