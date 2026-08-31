<!-- Shared Add/Edit Schedule Modal -->
<div class="modal fade" id="addScheduleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content vd-modal-content vd-schedule-modal-content">
            <div class="modal-header">
                <div>
                    <div class="vd-action-modal-kicker">Schedule management</div>
                    <h5 class="vd-modal-title mb-0"><span id="scheduleModalAction">Add Schedule</span> for <span id="modalClinicName"></span></h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="add-schedule-form">
                <div class="modal-body d-flex flex-column gap-3">
                    <p class="vd-schedule-modal-intro mb-0" id="scheduleModalIntro">Select dates and set the clinic’s availability window. Another clinic may use the same date when at least 90 minutes separates their windows.</p>

                    <div id="scheduleBatchDateGroup">
                        <label class="vd-label form-label" for="scheduleDates">Schedule dates</label>
                        <input type="text" id="scheduleDates"
                            class="form-control vd-input vd-schedule-date-input"
                            placeholder="Select dates" autocomplete="off"
                            aria-describedby="selectedScheduleCount" required>
                        <div id="selectedScheduleCount" class="vd-schedule-selection-count">No dates selected</div>
                    </div>

                    <div id="scheduleSingleDateGroup" class="d-none">
                        <label class="vd-label form-label" for="scheduleEditDate">Schedule date</label>
                        <input type="text" id="scheduleEditDate" class="form-control vd-input vd-schedule-date-input" placeholder="Select date" autocomplete="off">
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="vd-label form-label" for="scheduleStartTime">Opening time</label>
                            <input type="text" id="scheduleStartTime" name="start_time" class="form-control vd-input vd-schedule-time-input" placeholder="Select opening time" autocomplete="off" required>
                        </div>
                        <div class="col-6">
                            <label class="vd-label form-label" for="scheduleEndTime">Closing time</label>
                            <input type="text" id="scheduleEndTime" name="end_time" class="form-control vd-input vd-schedule-time-input" placeholder="Select closing time" autocomplete="off" required>
                        </div>
                    </div>
                    <div class="vd-schedule-selection-count vd-schedule-time-help"><i class="ti ti-clock-hour-4 me-1" aria-hidden="true"></i>Times are selected in five-minute increments.</div>
                    <div id="scheduleEditLockNote" class="vd-schedule-lock-note d-none"><i class="ti ti-lock" aria-hidden="true"></i><span>This schedule already has bookings, so its date and clinic hours are locked. Capacity can still be adjusted.</span></div>
                    <div class="vd-schedule-policy-note"><i class="ti ti-user-clock" aria-hidden="true"></i><span>Patients will be instructed to arrive by the opening time or earlier and will be served first come, first served.</span></div>

                    <div>
                        <label class="vd-label form-label" id="scheduleCapacityLabel" for="scheduleMaxAppointments">Max patients per selected date</label>
                        <input type="number" id="scheduleMaxAppointments"
                            class="form-control vd-input" value="8" min="1" max="50" required>
                        <div id="scheduleCapacityHelp" class="vd-schedule-selection-count d-none"></div>
                    </div>

                    <input type="hidden" name="clinic_id" id="modalClinicId">
                    <input type="hidden" name="schedule_id" id="modalScheduleId">
                    <div id="scheduleFormError" class="text-danger small d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn vd-btn-gold" id="scheduleModalSubmit">Add Schedules</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    const modalElement = document.getElementById('addScheduleModal');
    const form = document.getElementById('add-schedule-form');
    const dateInput = document.getElementById('scheduleDates');
    const editDateInput = document.getElementById('scheduleEditDate');
    const maxAppointmentsInput = document.getElementById('scheduleMaxAppointments');
    const startTimeInput = document.getElementById('scheduleStartTime');
    const endTimeInput = document.getElementById('scheduleEndTime');
    const selectionCount = document.getElementById('selectedScheduleCount');
    const errorElement = document.getElementById('scheduleFormError');
    const batchDateGroup = document.getElementById('scheduleBatchDateGroup');
    const singleDateGroup = document.getElementById('scheduleSingleDateGroup');
    const modalAction = document.getElementById('scheduleModalAction');
    const modalIntro = document.getElementById('scheduleModalIntro');
    const lockNote = document.getElementById('scheduleEditLockNote');
    const capacityLabel = document.getElementById('scheduleCapacityLabel');
    const capacityHelp = document.getElementById('scheduleCapacityHelp');
    const scheduleIdInput = document.getElementById('modalScheduleId');
    const submitButton = document.getElementById('scheduleModalSubmit');
    const occupiedDatesByClinic = <?= json_encode($occupiedScheduleDatesByClinic ?? []) ?>;
    const csrfToken = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;

    if (!modalElement || !form) return;
    if (typeof flatpickr !== 'function') {
        errorElement.textContent = 'The schedule picker could not load. Refresh the page and try again.';
        errorElement.classList.remove('d-none');
        submitButton.disabled = true;
        return;
    }

    function updateSelectionCount(count) {
        selectionCount.textContent = count === 0
            ? 'No dates selected'
            : `${count} date${count === 1 ? '' : 's'} selected`;
    }

    function setError(message) {
        errorElement.textContent = message;
        errorElement.classList.toggle('d-none', !message);
    }

    function buildTimePicker(input) {
        return flatpickr(input, {
            enableTime: true,
            noCalendar: true,
            dateFormat: 'h:i K',
            minuteIncrement: 5,
            time_24hr: false,
            allowInput: false,
            disableMobile: true,
            onReady(selectedDates, dateString, instance) {
                instance.calendarContainer.classList.add('vd-schedule-time-picker');
            }
        });
    }

    function pickerTime(instance) {
        return instance.selectedDates[0]
            ? flatpickr.formatDate(instance.selectedDates[0], 'H:i')
            : '';
    }

    function setPickerEnabled(instance, enabled) {
        instance._input.disabled = !enabled;
        instance._input.classList.toggle('vd-input-locked', !enabled);
    }

    function isFiveMinuteTime(value) {
        return /^\d{2}:\d{2}$/.test(value) && Number(value.slice(3, 5)) % 5 === 0;
    }

    const today = new Date();
    const endOfNextMonth = new Date(today.getFullYear(), today.getMonth() + 2, 0);
    const datePicker = flatpickr(dateInput, {
        mode: 'multiple',
        minDate: 'today',
        maxDate: endOfNextMonth,
        dateFormat: 'Y-m-d',
        conjunction: ', ',
        disable: [],
        disableMobile: true,
        onChange(selectedDates) {
            updateSelectionCount(selectedDates.length);
        },
        onReady(selectedDates, dateString, instance) {
            instance.calendarContainer.classList.add('vd-schedule-calendar');
        }
    });
    const editDatePicker = flatpickr(editDateInput, {
        mode: 'single',
        minDate: 'today',
        maxDate: endOfNextMonth,
        dateFormat: 'Y-m-d',
        disable: [],
        disableMobile: true,
        onReady(selectedDates, dateString, instance) {
            instance.calendarContainer.classList.add('vd-schedule-calendar');
        }
    });
    const startTimePicker = buildTimePicker(startTimeInput);
    const endTimePicker = buildTimePicker(endTimeInput);

    function configureAddMode(button) {
        form.dataset.mode = 'add';
        form.dataset.booked = '0';
        modalAction.textContent = 'Add Schedule';
        document.getElementById('modalClinicName').textContent = button.dataset.clinicName;
        document.getElementById('modalClinicId').value = button.dataset.clinicId;
        scheduleIdInput.value = '';
        modalIntro.textContent = 'Select dates and set the clinic’s availability window. Another clinic may use the same date when at least 90 minutes separates their windows.';
        batchDateGroup.classList.remove('d-none');
        singleDateGroup.classList.add('d-none');
        dateInput.required = true;
        editDateInput.required = false;
        lockNote.classList.add('d-none');
        capacityLabel.textContent = 'Max patients per selected date';
        capacityHelp.classList.add('d-none');
        maxAppointmentsInput.min = '1';
        maxAppointmentsInput.value = 8;
        submitButton.textContent = 'Add Schedules';
        setPickerEnabled(editDatePicker, true);
        setPickerEnabled(startTimePicker, true);
        setPickerEnabled(endTimePicker, true);
        startTimePicker.setDate(button.dataset.defaultStart || '08:00', false, 'H:i');
        endTimePicker.setDate(button.dataset.defaultEnd || '17:00', false, 'H:i');
        datePicker.set('disable', occupiedDatesByClinic[button.dataset.clinicId] || []);
        datePicker.clear();
        editDatePicker.clear();
        updateSelectionCount(0);
        setError('');
    }

    function configureEditMode(button) {
        const booked = Number(button.dataset.booked || 0);
        const locked = booked > 0;
        const clinicId = button.dataset.clinicId;
        const originalDate = button.dataset.date;

        form.dataset.mode = 'edit';
        form.dataset.booked = String(booked);
        modalAction.textContent = 'Edit Schedule';
        document.getElementById('modalClinicName').textContent = button.dataset.clinicName;
        document.getElementById('modalClinicId').value = clinicId;
        scheduleIdInput.value = button.dataset.id;
        modalIntro.textContent = locked
            ? 'Adjust this schedule’s capacity. Its date and clinic hours are preserved because patients are already booked.'
            : 'Update this schedule’s date, clinic hours, and capacity. Conflicting clinic windows will still be prevented.';
        batchDateGroup.classList.add('d-none');
        singleDateGroup.classList.remove('d-none');
        dateInput.required = false;
        editDateInput.required = !locked;
        lockNote.classList.toggle('d-none', !locked);
        capacityLabel.textContent = 'Maximum patients';
        capacityHelp.textContent = `Minimum capacity: ${Math.max(1, booked)}`;
        capacityHelp.classList.remove('d-none');
        maxAppointmentsInput.min = String(Math.max(1, booked));
        maxAppointmentsInput.value = button.dataset.max || 8;
        submitButton.textContent = 'Save Changes';

        const blockedDates = (occupiedDatesByClinic[clinicId] || []).filter(date => date !== originalDate);
        editDatePicker.set('disable', blockedDates);
        editDatePicker.setDate(originalDate, false, 'Y-m-d');
        startTimePicker.setDate(button.dataset.startTime, false, 'H:i');
        endTimePicker.setDate(button.dataset.endTime, false, 'H:i');
        setPickerEnabled(editDatePicker, !locked);
        setPickerEnabled(startTimePicker, !locked);
        setPickerEnabled(endTimePicker, !locked);
        datePicker.clear();
        updateSelectionCount(0);
        setError('');
    }

    modalElement.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        if (!button) return;
        if (button.dataset.scheduleMode === 'edit') configureEditMode(button);
        else configureAddMode(button);
    });

    form.addEventListener('submit', async function(event) {
        event.preventDefault();

        const mode = form.dataset.mode || 'add';
        const booked = Number(form.dataset.booked || 0);
        const maxAppointments = Number(maxAppointmentsInput.value);
        const minimumCapacity = Math.max(1, booked);
        if (!Number.isInteger(maxAppointments) || maxAppointments < minimumCapacity || maxAppointments > 50) {
            setError(`Maximum patients must be between ${minimumCapacity} and 50.`);
            return;
        }

        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('clinic_id', document.getElementById('modalClinicId').value);
        formData.append('max_appointments', String(maxAppointments));

        let successMessage = 'Schedule updated.';
        let loadingLabel = 'Saving…';
        let selectedDates = [];
        if (mode === 'add') {
            selectedDates = [...datePicker.selectedDates].sort((a, b) => a - b);
            const start = pickerTime(startTimePicker);
            const end = pickerTime(endTimePicker);
            if (selectedDates.length === 0) {
                setError('Select at least one schedule date.');
                return;
            }
            if (!isFiveMinuteTime(start) || !isFiveMinuteTime(end)) {
                setError('Opening and closing times must use five-minute increments.');
                return;
            }
            if (start >= end) {
                setError('Closing time must be later than opening time.');
                return;
            }
            selectedDates.forEach((date, index) => {
                formData.append(`schedules[${index}][sched_date]`, flatpickr.formatDate(date, 'Y-m-d'));
                formData.append(`schedules[${index}][start_time]`, start);
                formData.append(`schedules[${index}][end_time]`, end);
                formData.append(`schedules[${index}][max_appointments]`, String(maxAppointments));
            });
            formData.append('action', 'add_schedule');
            loadingLabel = 'Adding…';
            successMessage = `${selectedDates.length} schedule${selectedDates.length === 1 ? '' : 's'} added successfully!`;
        } else {
            formData.append('schedule_id', scheduleIdInput.value);
            formData.append('action', booked === 0 ? 'update_schedule_window' : 'edit_schedule');
            if (booked === 0) {
                const selectedDate = editDatePicker.selectedDates[0];
                const start = pickerTime(startTimePicker);
                const end = pickerTime(endTimePicker);
                if (!selectedDate) {
                    setError('Select a schedule date.');
                    return;
                }
                if (!isFiveMinuteTime(start) || !isFiveMinuteTime(end)) {
                    setError('Opening and closing times must use five-minute increments.');
                    return;
                }
                if (start >= end) {
                    setError('Closing time must be later than opening time.');
                    return;
                }
                formData.append('sched_date', flatpickr.formatDate(selectedDate, 'Y-m-d'));
                formData.append('start_time', start);
                formData.append('end_time', end);
            }
        }

        LoadingUI.setButton(submitButton, true, loadingLabel);
        setError('');

        try {
            const response = await fetch('../../controllers/scheduleController.php', {
                method: 'POST',
                body: formData
            });
            const responseText = await response.text();
            let successful = responseText.trim() === 'success';
            let errorMessage = responseText.trim();
            if (mode === 'edit' && booked === 0) {
                try {
                    const payload = JSON.parse(responseText);
                    successful = payload.success === true;
                    errorMessage = payload.message || '';
                } catch (error) {
                    successful = false;
                    errorMessage = 'The schedule response could not be read.';
                }
            }

            if (!successful) {
                setError(errorMessage || 'Unable to save the schedule.');
                if (typeof showToast === 'function') showToast(errorMessage || 'Unable to save the schedule.', false);
                return;
            }

            modalElement.addEventListener('hidden.bs.modal', () => {
                if (typeof window.refreshSchedulePage === 'function') window.refreshSchedulePage();
                else if (typeof loadpage === 'function') loadpage('schedule-content.php');
            }, { once: true });
            bootstrap.Modal.getOrCreateInstance(modalElement).hide();
            if (typeof showToast === 'function') showToast(successMessage, true);
        } catch (error) {
            setError('Network error. Please try again.');
            if (typeof showToast === 'function') showToast('Network error. Please try again.', false);
            console.error(error);
        } finally {
            LoadingUI.setButton(submitButton, false);
        }
    });
})();
</script>
