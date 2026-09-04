(function () {
    'use strict';

    const STORAGE_PREFIX = 'vdPatientAppointmentNotifications:v1:';
    const MAX_NOTIFICATIONS = 8;
    const NOTIFICATION_TYPES = {
        deposit_required: { destination: 'billing-content.php', icon: 'ti-receipt' },
        payment_rejected: { destination: 'billing-content.php', icon: 'ti-alert-triangle' },
        appointment_confirmed: { destination: 'home-content.php', icon: 'ti-circle-check' },
        appointment_rejected: { destination: 'history-content.php', icon: 'ti-calendar-x' },
        appointment_cancelled: { destination: 'history-content.php', icon: 'ti-calendar-cancel' }
    };

    function safeParse(value) {
        try {
            return JSON.parse(value);
        } catch (error) {
            return null;
        }
    }

    function formatDate(value, options) {
        if (!value) return '';
        const normalized = String(value).includes('T') ? String(value) : String(value).replace(' ', 'T');
        const date = new Date(normalized);
        if (!Number.isFinite(date.getTime())) return '';
        return new Intl.DateTimeFormat('en-PH', options).format(date);
    }

    function appointmentDate(value) {
        if (!value) return 'your scheduled date';
        const date = new Date(`${value}T00:00:00`);
        if (!Number.isFinite(date.getTime())) return 'your scheduled date';
        return new Intl.DateTimeFormat('en-PH', {
            month: 'short', day: 'numeric', year: 'numeric'
        }).format(date);
    }

    function relativeTime(value) {
        const normalized = String(value || '').includes('T')
            ? String(value)
            : String(value || '').replace(' ', 'T');
        const timestamp = Date.parse(normalized);
        if (!Number.isFinite(timestamp)) return 'Recently';

        const elapsedSeconds = Math.max(0, Math.round((Date.now() - timestamp) / 1000));
        if (elapsedSeconds < 60) return 'Just now';
        if (elapsedSeconds < 3600) {
            const minutes = Math.floor(elapsedSeconds / 60);
            return `${minutes} minute${minutes === 1 ? '' : 's'} ago`;
        }
        if (elapsedSeconds < 86400) {
            const hours = Math.floor(elapsedSeconds / 3600);
            return `${hours} hour${hours === 1 ? '' : 's'} ago`;
        }
        const days = Math.floor(elapsedSeconds / 86400);
        return `${days} day${days === 1 ? '' : 's'} ago`;
    }

    function peso(value) {
        const amount = Number(value);
        if (!Number.isFinite(amount) || amount <= 0) return 'deposit';
        return new Intl.NumberFormat('en-PH', {
            style: 'currency', currency: 'PHP', minimumFractionDigits: 2
        }).format(amount) + ' deposit';
    }

    function notificationType(previous, current) {
        if (current.depositStatus === 'Rejected' && previous?.depositStatus !== 'Rejected') {
            return 'payment_rejected';
        }
        if (current.status === previous?.status) return null;
        if (current.status === 'Awaiting Deposit') return 'deposit_required';
        if (current.status === 'Confirmed') return 'appointment_confirmed';
        if (current.status === 'Rejected') return 'appointment_rejected';
        if (current.status === 'Cancelled') return 'appointment_cancelled';
        return null;
    }

    function notificationMessage(type, appointment) {
        const date = appointmentDate(appointment.date);
        const clinic = appointment.clinicName ? ` at ${appointment.clinicName}` : '';
        const reason = appointment.reason ? ` Reason: ${appointment.reason}` : '';
        const deadline = formatDate(appointment.paymentDeadlineAt, {
            month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit'
        });

        if (type === 'deposit_required') {
            const due = deadline ? ` by ${deadline}` : '';
            return `Your appointment request for ${date}${clinic} was accepted. Submit the ${peso(appointment.depositAmount)}${due}.`;
        }
        if (type === 'payment_rejected') {
            const due = deadline ? ` Upload corrected proof by ${deadline}.` : '';
            return `Your payment proof for the ${date} appointment needs correction.${reason}${due}`;
        }
        if (type === 'appointment_confirmed') {
            return `Your appointment on ${date}${clinic} is confirmed.`;
        }
        if (type === 'appointment_rejected') {
            return `Your appointment request for ${date}${clinic} was not accepted.${reason}`;
        }
        return `Your appointment on ${date}${clinic} was cancelled.${reason}`;
    }

    function normalizeAppointment(item) {
        return {
            appointmentId: Math.max(0, Number(item?.appointment_id) || 0),
            status: String(item?.status || ''),
            depositStatus: String(item?.deposit_status || ''),
            depositAmount: Number(item?.deposit_amount) || 0,
            date: String(item?.date || ''),
            clinicName: String(item?.clinic_name || ''),
            paymentDeadlineAt: String(item?.payment_deadline_at || ''),
            reason: String(item?.patient_reason || ''),
            stateChangedAt: String(item?.state_changed_at || '')
        };
    }

    function create(config) {
        const button = document.getElementById(config.buttonId);
        const panel = document.getElementById(config.panelId);
        const list = document.getElementById(config.listId);
        const empty = document.getElementById(config.emptyId);
        const caughtUp = document.getElementById(config.caughtUpId);
        const markAllButton = document.getElementById(config.markAllId);
        const dot = document.getElementById(config.dotId);
        if (!button || !panel || !list || !empty || !caughtUp || !markAllButton || !dot) return null;

        const storageKey = STORAGE_PREFIX + String(config.userId);

        function defaultState() {
            return { initialized: false, appointments: {}, notifications: [] };
        }

        function loadState() {
            let stored = null;
            try {
                stored = safeParse(window.localStorage.getItem(storageKey));
            } catch (error) {
                stored = null;
            }
            if (!stored || typeof stored !== 'object') return defaultState();
            const notifications = Array.isArray(stored.notifications)
                ? stored.notifications.filter(item => item && NOTIFICATION_TYPES[item.type]).slice(0, MAX_NOTIFICATIONS)
                : [];
            return {
                initialized: stored.initialized === true,
                appointments: stored.appointments && typeof stored.appointments === 'object' ? stored.appointments : {},
                notifications
            };
        }

        let state = loadState();

        function saveState() {
            try {
                window.localStorage.setItem(storageKey, JSON.stringify(state));
            } catch (error) {
                // The status panel still works for this page when storage is unavailable.
            }
        }

        function closePanel(restoreFocus = false) {
            panel.hidden = true;
            button.setAttribute('aria-expanded', 'false');
            if (restoreFocus) button.focus();
        }

        function render() {
            const unread = state.notifications.filter(item => !item.read).length;
            dot.hidden = unread === 0;
            button.setAttribute(
                'aria-label',
                unread === 0
                    ? 'Appointment notifications, no unread updates'
                    : `Appointment notifications, ${unread} unread update${unread === 1 ? '' : 's'}`
            );
            markAllButton.hidden = unread === 0;
            empty.hidden = state.notifications.length > 0;
            caughtUp.hidden = state.notifications.length === 0 || unread > 0;
            list.replaceChildren();

            state.notifications.forEach(notification => {
                const definition = NOTIFICATION_TYPES[notification.type];
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'vd-notification-item' + (notification.read ? '' : ' is-unread');

                const icon = document.createElement('span');
                icon.className = 'vd-notification-item-icon';
                icon.setAttribute('aria-hidden', 'true');
                const glyph = document.createElement('i');
                glyph.className = `ti ${definition.icon}`;
                icon.appendChild(glyph);

                const copy = document.createElement('span');
                copy.className = 'vd-notification-item-copy';
                const message = document.createElement('span');
                message.className = 'vd-notification-item-message';
                message.textContent = notification.message;
                const time = document.createElement('time');
                time.className = 'vd-notification-item-time';
                time.dateTime = notification.createdAt;
                time.textContent = relativeTime(notification.createdAt);
                copy.append(message, time);

                item.append(icon, copy);
                if (!notification.read) {
                    const unreadIndicator = document.createElement('span');
                    unreadIndicator.className = 'vd-notification-unread-indicator';
                    unreadIndicator.setAttribute('aria-hidden', 'true');
                    item.appendChild(unreadIndicator);
                }
                item.addEventListener('click', () => {
                    notification.read = true;
                    saveState();
                    render();
                    closePanel();
                    config.onNavigate?.(definition.destination);
                });
                list.appendChild(item);
            });
        }

        function addNotification(type, appointment) {
            const changedAt = appointment.stateChangedAt || new Date().toISOString();
            const id = `${type}:${appointment.appointmentId}:${changedAt}`;
            if (state.notifications.some(item => item.id === id)) return;
            state.notifications.unshift({
                id,
                type,
                message: notificationMessage(type, appointment),
                createdAt: changedAt,
                read: false
            });
            state.notifications = state.notifications.slice(0, MAX_NOTIFICATIONS);
        }

        function observe(items) {
            const appointments = Array.isArray(items) ? items.map(normalizeAppointment) : [];
            if (!state.initialized) {
                appointments.forEach(item => {
                    if (item.appointmentId) state.appointments[item.appointmentId] = item;
                });
                state.initialized = true;
                saveState();
                render();
                return;
            }

            appointments.forEach(current => {
                if (!current.appointmentId) return;
                const previous = state.appointments[current.appointmentId] || null;
                const type = notificationType(previous, current);
                if (type && (previous || current.status !== 'Pending Review')) {
                    addNotification(type, current);
                }
                state.appointments[current.appointmentId] = current;
            });
            saveState();
            render();
        }

        async function refresh() {
            try {
                const response = await fetch(config.endpoint, {
                    cache: 'no-store',
                    headers: { Accept: 'application/json' }
                });
                if (!response.ok) return;
                const result = await response.json();
                if (result?.success) observe(result.appointments);
            } catch (error) {
                // A later poll will retry; appointment pages remain the source of truth.
            }
        }

        button.addEventListener('click', () => {
            const willOpen = panel.hidden;
            panel.hidden = !willOpen;
            button.setAttribute('aria-expanded', String(willOpen));
            if (willOpen) panel.querySelector('button:not([hidden])')?.focus();
        });
        markAllButton.addEventListener('click', () => {
            state.notifications.forEach(item => { item.read = true; });
            saveState();
            render();
            list.querySelector('.vd-notification-item')?.focus();
        });
        document.addEventListener('click', event => {
            if (!panel.hidden && !panel.contains(event.target) && !button.contains(event.target)) closePanel();
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && !panel.hidden) closePanel(true);
        });
        window.addEventListener('storage', event => {
            if (event.key !== storageKey) return;
            state = loadState();
            render();
        });
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) refresh();
        });

        render();
        refresh();
        window.setInterval(() => {
            if (!document.hidden) refresh();
        }, Math.max(10000, Number(config.pollInterval) || 10000));
        return { refresh, observe };
    }

    window.PatientAppointmentNotifications = { create };
})();
