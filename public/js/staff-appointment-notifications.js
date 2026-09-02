(function () {
    'use strict';

    const STORAGE_PREFIX = 'vdStaffAppointmentNotifications:v1:';
    const MAX_NOTIFICATIONS = 8;
    const NOTIFICATION_TYPES = {
        appointment_created: {
            message: 'A new appointment request was received.',
            destination: 'appointment-content.php',
            icon: 'ti-calendar-plus'
        },
        deposit_updated: {
            message: 'A deposit record was updated.',
            destination: 'payment-review-content.php',
            icon: 'ti-receipt'
        }
    };

    function safeParse(value) {
        try {
            return JSON.parse(value);
        } catch (error) {
            return null;
        }
    }

    function relativeTime(value) {
        const timestamp = Date.parse(value);
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
        const initialCursors = {
            appointmentId: Math.max(0, Number(config.initialAppointmentId) || 0),
            depositVersion: String(config.initialDepositVersion || '0:0:0')
        };

        function defaultState() {
            return {
                cursors: { ...initialCursors },
                notifications: []
            };
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
                cursors: {
                    appointmentId: Math.max(0, Number(stored.cursors?.appointmentId) || 0),
                    depositVersion: String(stored.cursors?.depositVersion || initialCursors.depositVersion)
                },
                notifications
            };
        }

        let state = loadState();

        function saveState() {
            try {
                window.localStorage.setItem(storageKey, JSON.stringify(state));
            } catch (error) {
                // Storage can be unavailable in private or restricted browsing.
            }
        }

        function closePanel(restoreFocus = false) {
            panel.hidden = true;
            button.setAttribute('aria-expanded', 'false');
            if (restoreFocus) button.focus();
        }

        function unreadCount() {
            return state.notifications.filter(item => !item.read).length;
        }

        function render() {
            const unread = unreadCount();
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
                const type = NOTIFICATION_TYPES[notification.type];
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'vd-notification-item' + (notification.read ? '' : ' is-unread');
                item.dataset.notificationId = notification.id;

                const icon = document.createElement('span');
                icon.className = 'vd-notification-item-icon';
                icon.setAttribute('aria-hidden', 'true');
                const iconGlyph = document.createElement('i');
                iconGlyph.className = `ti ${type.icon}`;
                icon.appendChild(iconGlyph);

                const copy = document.createElement('span');
                copy.className = 'vd-notification-item-copy';
                if (!notification.read) {
                    const unreadText = document.createElement('span');
                    unreadText.className = 'visually-hidden';
                    unreadText.textContent = 'Unread notification. ';
                    copy.appendChild(unreadText);
                }
                const message = document.createElement('span');
                message.className = 'vd-notification-item-message';
                message.textContent = notification.message || type.message;
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
                    if (typeof config.onNavigate === 'function') {
                        config.onNavigate({
                            type: notification.type,
                            destination: notification.destination || type.destination,
                            appointmentId: notification.appointmentId || null
                        });
                    }
                });
                list.appendChild(item);
            });
        }

        function addNotification(type, idSuffix, details = {}) {
            const definition = NOTIFICATION_TYPES[type];
            if (!definition) return;
            const id = `${type}:${String(idSuffix)}`;
            if (state.notifications.some(item => item.id === id)) return;

            state.notifications.unshift({
                id,
                type,
                message: definition.message,
                destination: definition.destination,
                appointmentId: details.appointmentId || null,
                createdAt: new Date().toISOString(),
                read: false
            });
            state.notifications = state.notifications.slice(0, MAX_NOTIFICATIONS);
        }

        function observe(feed) {
            const appointmentId = Math.max(0, Number(feed.appointmentId) || 0);
            const depositVersion = String(feed.depositVersion || '0:0:0');
            const previousAppointmentId = state.cursors.appointmentId;
            const previousDepositVersion = state.cursors.depositVersion;
            const hasNewAppointment = appointmentId > previousAppointmentId;
            const hasDepositChange = depositVersion !== previousDepositVersion;

            if (hasNewAppointment) {
                addNotification('appointment_created', appointmentId, { appointmentId });
            }
            if (hasDepositChange) {
                addNotification('deposit_updated', depositVersion);
            }

            // A restored or replaced database can have a lower cursor. Treat it as
            // the new baseline instead of generating a misleading notification.
            state.cursors.appointmentId = appointmentId;
            state.cursors.depositVersion = depositVersion;
            saveState();
            render();

            return { hasNewAppointment, hasDepositChange };
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
            const firstItem = list.querySelector('.vd-notification-item');
            if (firstItem) firstItem.focus();
            else button.focus();
        });

        document.addEventListener('click', event => {
            if (!panel.hidden && !panel.contains(event.target) && !button.contains(event.target)) {
                closePanel();
            }
        });

        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && !panel.hidden) closePanel(true);
        });

        window.addEventListener('storage', event => {
            if (event.key !== storageKey) return;
            state = loadState();
            render();
        });

        render();
        return { observe };
    }

    window.StaffAppointmentNotifications = { create };
})();
