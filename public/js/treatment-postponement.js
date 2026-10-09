(function () {
    'use strict';
    const modal = document.getElementById('treatmentPostponementModal');
    if (!modal || typeof bootstrap === 'undefined') return;
    const form = modal.querySelector('form');
    const list = document.getElementById('postponementSchedules');
    const error = document.getElementById('postponementError');
    const confirm = document.getElementById('postponementConfirm');
    const reason = document.getElementById('postponementReason');
    const panel = document.getElementById('postponementSchedulesPanel');
    const instance = bootstrap.Modal.getOrCreateInstance(modal);
    const endpoint = () => window.vdAppUrl('apps/controllers/treatmentPostponementController.php');
    let trigger, appointmentId, csrf, selectedSchedule = '', busy = false, requestVersion = 0;
    const isReschedule = () => form.elements.next_step.value === 'reschedule';
    const showError = message => { error.textContent = message; error.hidden = !message; };
    function updateMode() {
        panel.hidden = !isReschedule();
        document.getElementById('postponementLaterNote').hidden = isReschedule();
        confirm.textContent = isReschedule() ? 'Confirm reschedule' : 'Postpone treatment';
        confirm.disabled = busy || (isReschedule() && !selectedSchedule);
    }
    async function loadSchedules() {
        const version = ++requestVersion;
        selectedSchedule = '';
        list.textContent = 'Loading schedules…';
        updateMode();
        try {
            const response = await fetch(endpoint() + '?appointment_id=' + encodeURIComponent(appointmentId), { cache: 'no-store' });
            const result = await response.json();
            if (version !== requestVersion) return;
            if (!response.ok || !result.success) throw new Error('Unable to load schedules. Retry by reopening this dialog, or choose Book later.');
            list.replaceChildren();
            if (!result.schedules.length) list.textContent = 'No eligible schedules. Choose Book later.';
            for (const schedule of result.schedules) {
                const available = Number(schedule.available_slots);
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'vd-postponement-schedule';
                button.dataset.scheduleId = schedule.schedule_id;
                button.dataset.full = String(available <= 0);
                button.disabled = available <= 0 || busy;
                button.setAttribute('aria-pressed', 'false');
                const title = document.createElement('strong');
                title.textContent = new Date(schedule.sched_date + 'T12:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
                const detail = document.createElement('span');
                const time = value => new Date('2000-01-01T' + value).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' });
                detail.textContent = `${schedule.clinic_name} · ${time(schedule.start_time)}–${time(schedule.end_time)}`;
                const slots = document.createElement('span');
                slots.className = 'vd-postponement-slots';
                slots.textContent = available > 0 ? `${available} ${available === 1 ? 'slot' : 'slots'} available` : 'Full · 0 slots';
                button.append(title, detail, slots);
                button.addEventListener('click', () => {
                    selectedSchedule = String(schedule.schedule_id);
                    list.querySelectorAll('button').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
                    showError(''); updateMode();
                });
                list.append(button);
            }
        } catch (failure) {
            if (version === requestVersion) list.textContent = failure.message;
        }
    }
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-postpone-treatment]');
        if (!button || busy) return;
        trigger = button; appointmentId = button.dataset.postponeTreatment; csrf = button.dataset.csrf;
        form.reset(); showError('');
        document.getElementById('postponementPatient').textContent = button.dataset.patient;
        instance.show(); loadSchedules();
    });
    form.addEventListener('change', event => {
        if (event.target.name === 'next_step') { showError(''); updateMode(); }
    });
    modal.addEventListener('shown.bs.modal', () => reason.focus());
    modal.addEventListener('hide.bs.modal', event => { if (busy) event.preventDefault(); });
    modal.addEventListener('hidden.bs.modal', () => {
        requestVersion++;
        if (trigger?.isConnected) trigger.focus({ preventScroll: true });
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (busy || !form.reportValidity()) return;
        if (isReschedule() && !selectedSchedule) { showError('Choose a schedule or Book later.'); return; }
        const body = new FormData(form);
        body.append('appointment_id', appointmentId); body.append('csrf_token', csrf);
        body.append('schedule_id', isReschedule() ? selectedSchedule : '0');
        busy = true; showError('');
        form.querySelectorAll('button,input,textarea').forEach(control => control.disabled = true);
        confirm.textContent = 'Saving…';
        let succeeded = false;
        try {
            const response = await fetch(endpoint(), { method: 'POST', body });
            const result = await response.json();
            if (!response.ok || !result.success) {
                if (result.code === 'schedule_full') await loadSchedules();
                throw new Error(result.message || 'Unable to postpone treatment.');
            }
            for (const notification of [result.notification, result.replacement_notification]) {
                if (notification?.id) window.EmailNotificationDelivery?.deliver(notification.id);
            }
            succeeded = true;
            window.showToast(result.message, true);
        } catch (failure) { showError(failure.message); }
        finally {
            busy = false;
            form.querySelectorAll('button,input,textarea').forEach(control => {
                control.disabled = control.dataset.full === 'true';
            });
            updateMode();
        }
        if (succeeded) {
            modal.addEventListener('hidden.bs.modal', () => document.querySelector('[data-page="dashboard-content.php"]')?.click(), { once: true });
            instance.hide();
        }
    });
})();
