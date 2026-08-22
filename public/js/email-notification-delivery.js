(function () {
    'use strict';

    const config = window.emailNotificationDeliveryConfig || {};
    if (!config.endpoint || !config.csrfToken) return;

    function deliver(notificationId = null, busyRetries = 0) {
        const body = new FormData();
        body.append('action', 'deliverPending');
        body.append('csrf_token', config.csrfToken);
        if (notificationId) body.append('notification_id', String(notificationId));

        // This second request is deliberately not awaited by status actions.
        // keepalive gives it a chance to finish even if the page is refreshed.
        return fetch(config.endpoint, {
            method: 'POST',
            body,
            keepalive: true
        }).then(response => {
            if (!response.ok) throw new Error('Notification delivery request failed.');
            return response.json();
        }).then(result => {
            // A dashboard-startup retry may already hold the delivery lock.
            // Retry a newly-created notification briefly instead of waiting for
            // the next dashboard visit.
            if (result?.busy && notificationId && busyRetries < 2) {
                setTimeout(() => deliver(notificationId, busyRetries + 1), 1500);
            }
            return result;
        }).catch(error => {
            // Delivery remains Pending in the database and will be retried the
            // next time an authorized staff dashboard is opened.
            console.warn('Patient notification remains queued:', error);
            return null;
        });
    }

    window.EmailNotificationDelivery = { deliver };

    // Dashboard startup doubles as a lightweight retry mechanism for messages
    // missed because a prior tab closed or temporarily lost its connection.
    deliver();
})();
