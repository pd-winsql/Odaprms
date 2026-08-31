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
                            <clock-timepicker id="scheduleStartPicker" class="vd-clock-timepicker" format="HH:mm" precision="00:05" minimum="00:00" maximum="23:50" required vibrate="false">
                                <input type="text" id="scheduleStartTime" name="start_time" class="form-control vd-input vd-schedule-time-input" placeholder="Select opening time" autocomplete="off" inputmode="numeric" required>
                            </clock-timepicker>
                        </div>
                        <div class="col-6">
                            <label class="vd-label form-label" for="scheduleEndTime">Closing time</label>
                            <clock-timepicker id="scheduleEndPicker" class="vd-clock-timepicker" format="HH:mm" precision="00:05" minimum="00:05" maximum="23:55" required vibrate="false">
                                <input type="text" id="scheduleEndTime" name="end_time" class="form-control vd-input vd-schedule-time-input" placeholder="Select closing time" autocomplete="off" inputmode="numeric" required>
                            </clock-timepicker>
                        </div>
                    </div>
                    <div class="vd-schedule-selection-count vd-schedule-time-help"><i class="ti ti-clock-hour-4 me-1" aria-hidden="true"></i>Times are selected in five-minute increments.</div>
                    <div id="scheduleTimeAvailability" class="vd-schedule-availability d-none" role="status" aria-live="polite"></div>
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
    const startTimePicker = document.getElementById('scheduleStartPicker');
    const endTimePicker = document.getElementById('scheduleEndPicker');
    const selectionCount = document.getElementById('selectedScheduleCount');
    const errorElement = document.getElementById('scheduleFormError');
    const availabilityElement = document.getElementById('scheduleTimeAvailability');
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
    const existingScheduleWindows = <?= json_encode($scheduleWindows ?? []) ?>;
    const transitionMinutes = <?= (int) ($scheduleModel->getTransitionMinutes() ?? 90) ?>;
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

    function pickerTime(picker, input) {
        return String(picker?.value || input.value || '').trim();
    }

    function setPickerValue(picker, input, value) {
        input.value = value;
        if (customElements.get('clock-timepicker')) picker.value = value;
    }

    function setClockPickerEnabled(picker, input, enabled) {
        if (customElements.get('clock-timepicker')) picker.disabled = !enabled;
        input.classList.toggle('vd-input-locked', !enabled);
        input.setAttribute('aria-disabled', enabled ? 'false' : 'true');
    }

    function setDatePickerEnabled(instance, enabled) {
        instance._input.disabled = !enabled;
        instance._input.classList.toggle('vd-input-locked', !enabled);
    }

    function isFiveMinuteTime(value) {
        return /^\d{2}:\d{2}$/.test(value) && Number(value.slice(3, 5)) % 5 === 0;
    }

    function timeToMinutes(value) {
        if (!/^\d{2}:\d{2}$/.test(value)) return null;
        const [hours, minutes] = value.split(':').map(Number);
        return hours * 60 + minutes;
    }

    function minutesToTime(totalMinutes) {
        const safeMinutes = Math.max(0, Math.min(1439, totalMinutes));
        return `${String(Math.floor(safeMinutes / 60)).padStart(2, '0')}:${String(safeMinutes % 60).padStart(2, '0')}`;
    }

    function formatTime(value) {
        const minutes = timeToMinutes(value);
        if (minutes === null) return value;
        const hour24 = Math.floor(minutes / 60);
        const minute = minutes % 60;
        const hour12 = hour24 % 12 || 12;
        return `${hour12}:${String(minute).padStart(2, '0')} ${hour24 >= 12 ? 'PM' : 'AM'}`;
    }

    function resetTimeBounds() {
        if (!customElements.get('clock-timepicker')) return;
        startTimePicker.minimum = '00:00';
        startTimePicker.maximum = '23:50';
        endTimePicker.minimum = '00:05';
        endTimePicker.maximum = '23:55';
    }

    function selectedScheduleDates() {
        if ((form.dataset.mode || 'add') === 'edit') {
            const selectedDate = editDatePicker.selectedDates[0];
            return selectedDate ? [flatpickr.formatDate(selectedDate, 'Y-m-d')] : [];
        }
        return datePicker.selectedDates.map(date => flatpickr.formatDate(date, 'Y-m-d'));
    }

    function getTimeConflict() {
        const clinicId = Number(document.getElementById('modalClinicId').value);
        const scheduleId = Number(scheduleIdInput.value || 0);
        const startMinutes = timeToMinutes(pickerTime(startTimePicker, startTimeInput));
        const endMinutes = timeToMinutes(pickerTime(endTimePicker, endTimeInput));
        const selectedDates = selectedScheduleDates();
        if (!clinicId || startMinutes === null || endMinutes === null || startMinutes >= endMinutes || selectedDates.length === 0) return null;

        return existingScheduleWindows.find(windowItem => {
            if (Number(windowItem.schedule_id) === scheduleId || !selectedDates.includes(windowItem.sched_date)) return false;
            if (Number(windowItem.clinic_id) === clinicId) return true;
            const existingStart = timeToMinutes(windowItem.start_time);
            const existingEnd = timeToMinutes(windowItem.end_time);
            return !(startMinutes >= existingEnd + transitionMinutes || existingStart >= endMinutes + transitionMinutes);
        }) || null;
    }

    function availabilityMessage(conflict) {
        const clinicId = Number(document.getElementById('modalClinicId').value);
        if (Number(conflict.clinic_id) === clinicId) {
            return 'This clinic already has an availability window on the selected date.';
        }
        const before = timeToMinutes(conflict.start_time) - transitionMinutes;
        const after = timeToMinutes(conflict.end_time) + transitionMinutes;
        const choices = [];
        if (before > 0) choices.push(`closes by ${formatTime(minutesToTime(before))}`);
        if (after < 1440) choices.push(`opens from ${formatTime(minutesToTime(after))}`);
        const requirement = choices.length ? ` Choose a window that ${choices.join(' or ')}.` : '';
        return `${conflict.clinic_name} operates ${formatTime(conflict.start_time)}–${formatTime(conflict.end_time)} on this date.${requirement}`;
    }

    function updateTimeAvailability() {
        const conflict = getTimeConflict();
        const dates = selectedScheduleDates();
        const relatedWindows = existingScheduleWindows.filter(windowItem =>
            dates.includes(windowItem.sched_date)
            && Number(windowItem.clinic_id) !== Number(document.getElementById('modalClinicId').value)
            && Number(windowItem.schedule_id) !== Number(scheduleIdInput.value || 0)
        );
        availabilityElement.classList.toggle('d-none', !conflict && relatedWindows.length === 0);
        availabilityElement.classList.toggle('is-conflict', Boolean(conflict));
        availabilityElement.replaceChildren();
        if (conflict) {
            const icon = document.createElement('i');
            const message = document.createElement('span');
            icon.className = 'ti ti-alert-circle';
            icon.setAttribute('aria-hidden', 'true');
            message.textContent = availabilityMessage(conflict);
            availabilityElement.append(icon, message);
        } else if (relatedWindows.length > 0) {
            const icon = document.createElement('i');
            const message = document.createElement('span');
            icon.className = 'ti ti-circle-check';
            icon.setAttribute('aria-hidden', 'true');
            message.textContent = `The selected hours keep the required ${transitionMinutes}-minute separation from the other clinic.`;
            availabilityElement.append(icon, message);
        }
        return conflict;
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
            updateTimeAvailability();
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
        onChange() {
            updateTimeAvailability();
        },
        onReady(selectedDates, dateString, instance) {
            instance.calendarContainer.classList.add('vd-schedule-calendar');
        }
    });
    [startTimePicker, endTimePicker].forEach(picker => {
        picker.addEventListener('input', updateTimeAvailability);
        picker.addEventListener('change', updateTimeAvailability);
    });

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
        setDatePickerEnabled(editDatePicker, true);
        setClockPickerEnabled(startTimePicker, startTimeInput, true);
        setClockPickerEnabled(endTimePicker, endTimeInput, true);
        resetTimeBounds();
        setPickerValue(startTimePicker, startTimeInput, button.dataset.defaultStart || '08:00');
        setPickerValue(endTimePicker, endTimeInput, button.dataset.defaultEnd || '17:00');
        datePicker.set('disable', occupiedDatesByClinic[button.dataset.clinicId] || []);
        datePicker.clear();
        editDatePicker.clear();
        updateSelectionCount(0);
        setError('');
        updateTimeAvailability();
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
        resetTimeBounds();
        setPickerValue(startTimePicker, startTimeInput, button.dataset.startTime);
        setPickerValue(endTimePicker, endTimeInput, button.dataset.endTime);
        setDatePickerEnabled(editDatePicker, !locked);
        setClockPickerEnabled(startTimePicker, startTimeInput, !locked);
        setClockPickerEnabled(endTimePicker, endTimeInput, !locked);
        datePicker.clear();
        updateSelectionCount(0);
        setError('');
        updateTimeAvailability();
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
            const start = pickerTime(startTimePicker, startTimeInput);
            const end = pickerTime(endTimePicker, endTimeInput);
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
            const conflict = updateTimeAvailability();
            if (conflict) {
                setError(availabilityMessage(conflict));
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
                const start = pickerTime(startTimePicker, startTimeInput);
                const end = pickerTime(endTimePicker, endTimeInput);
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
                const conflict = updateTimeAvailability();
                if (conflict) {
                    setError(availabilityMessage(conflict));
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
