<!-- Add Schedule Modal -->
<div class="modal fade" id="addScheduleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content vd-modal-content">
            <div class="modal-header">
                <div>
                    <div class="vd-action-modal-kicker">Schedule management</div>
                    <h5 class="vd-modal-title mb-0">Add Schedule for <span id="modalClinicName"></span></h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="add-schedule-form">
                <div class="modal-body d-flex flex-column gap-3">
                    <p class="text-muted small mb-0">Select dates in the current or next month. Dates with an existing schedule are unavailable.</p>

                    <div>
                        <label class="vd-label form-label" for="scheduleDates">Schedule dates</label>
                        <input type="text" id="scheduleDates"
                            class="form-control vd-input vd-schedule-date-input"
                            placeholder="Select dates" autocomplete="off"
                            aria-describedby="selectedScheduleCount" required>
                        <div id="selectedScheduleCount" class="vd-schedule-selection-count">No dates selected</div>
                    </div>

                    <div>
                        <label class="vd-label form-label" for="scheduleMaxAppointments">Max patients per selected date</label>
                        <input type="number" id="scheduleMaxAppointments"
                            class="form-control vd-input" value="8" min="1" max="50" required>
                    </div>

                    <input type="hidden" name="clinic_id" id="modalClinicId">
                    <div id="addError" class="text-danger small d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn vd-btn-gold">Add Schedules</button>
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
    const maxAppointmentsInput = document.getElementById('scheduleMaxAppointments');
    const selectionCount = document.getElementById('selectedScheduleCount');
    const errorElement = document.getElementById('addError');
    const occupiedDates = <?= json_encode($occupiedScheduleDates ?? []) ?>;

    if (!modalElement || !form) return;
    if (typeof flatpickr !== 'function') {
        errorElement.textContent = 'The calendar could not load. Refresh the page and try again.';
        errorElement.classList.remove('d-none');
        form.querySelector('button[type="submit"]').disabled = true;
        return;
    }

    // Keep the visible summary in sync with the multiple-date picker.
    function updateSelectionCount(count) {
        selectionCount.textContent = count === 0
            ? 'No dates selected'
            : `${count} date${count === 1 ? '' : 's'} selected`;
    }

    // Allow the rest of this month and all of next month, then block occupied dates.
    const today = new Date();
    const endOfNextMonth = new Date(today.getFullYear(), today.getMonth() + 2, 0);
    const datePicker = flatpickr(dateInput, {
        mode: 'multiple',
        minDate: 'today',
        maxDate: endOfNextMonth,
        dateFormat: 'Y-m-d',
        conjunction: ', ',
        disable: occupiedDates,
        disableMobile: true,
        onChange(selectedDates) {
            updateSelectionCount(selectedDates.length);
        },
        onReady(selectedDates, dateString, instance) {
            instance.calendarContainer.classList.add('vd-schedule-calendar');
        }
    });

    // Reset the form whenever staff starts scheduling for a clinic.
    modalElement.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        if (!button) return;

        document.getElementById('modalClinicName').textContent = button.dataset.clinicName;
        document.getElementById('modalClinicId').value = button.dataset.clinicId;
        maxAppointmentsInput.value = 8;
        datePicker.clear();
        updateSelectionCount(0);
        errorElement.textContent = '';
        errorElement.classList.add('d-none');
    });

    // Convert selected dates into the batch structure expected by the controller.
    form.addEventListener('submit', async function(event) {
        event.preventDefault();

        const selectedDates = [...datePicker.selectedDates].sort((a, b) => a - b);
        const maxAppointments = Number(maxAppointmentsInput.value);
        if (selectedDates.length === 0) {
            errorElement.textContent = 'Select at least one schedule date.';
            errorElement.classList.remove('d-none');
            return;
        }
        if (!Number.isInteger(maxAppointments) || maxAppointments < 1 || maxAppointments > 50) {
            errorElement.textContent = 'Maximum patients must be between 1 and 50.';
            errorElement.classList.remove('d-none');
            return;
        }

        const formData = new FormData(this);
        selectedDates.forEach((date, index) => {
            formData.append(`schedules[${index}][sched_date]`, flatpickr.formatDate(date, 'Y-m-d'));
            formData.append(`schedules[${index}][max_appointments]`, String(maxAppointments));
        });
        formData.append('action', 'add_schedule');
        formData.append('csrf_token', <?= json_encode($_SESSION['csrf_token'] ?? '') ?>);

        const submitButton = this.querySelector('button[type="submit"]');
        LoadingUI.setButton(submitButton, true, 'Adding…');
        errorElement.classList.add('d-none');

        try {
            const response = await fetch('../../controllers/scheduleController.php', {
                method: 'POST',
                body: formData
            });
            const text = await response.text();

            if (text.trim() !== 'success') {
                errorElement.textContent = text;
                errorElement.classList.remove('d-none');
                if (typeof showToast === 'function') showToast(text, false);
                return;
            }

            const count = selectedDates.length;
            modalElement.addEventListener('hidden.bs.modal', () => {
                if (typeof window.refreshSchedulePage === 'function') window.refreshSchedulePage();
                else if (typeof loadpage === 'function') loadpage('schedule-content.php');
            }, { once: true });
            bootstrap.Modal.getOrCreateInstance(modalElement).hide();
            if (typeof showToast === 'function') {
                showToast(`${count} schedule${count === 1 ? '' : 's'} added successfully!`, true);
            }
        } catch (error) {
            if (typeof showToast === 'function') showToast('Network error. Please try again.', false);
            console.error(error);
        } finally {
            LoadingUI.setButton(submitButton, false);
        }
    });
})();
</script>
