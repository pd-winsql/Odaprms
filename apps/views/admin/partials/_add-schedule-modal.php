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
                    <p class="text-muted small mb-0">Add one or more appointment dates. Each date can have its own maximum number of patients.</p>
                    <div id="scheduleRows" class="d-flex flex-column gap-2"></div>
                    <button type="button" class="btn vd-btn-outline align-self-start" id="addScheduleRow"><i class="ti ti-plus me-1"></i>Add another date</button>
                    <input type="hidden" name="clinic_id" id="modalClinicId">
                    <div id="addError" class="text-danger small d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn vd-btn-gold">Add Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function() {
        const modalElement = document.getElementById('addScheduleModal');
        const form = document.getElementById('add-schedule-form');
        const rows = document.getElementById('scheduleRows');
        const minDate = <?= json_encode(date('Y-m-d')) ?>;

        let nextScheduleIndex = 0;

        function addRow(values = {}) {
            const rowIndex = nextScheduleIndex++;

            // Build one independently editable schedule row.
            const row = document.createElement('div');
            row.className = 'd-flex align-items-end gap-2 schedule-entry-row';
            row.innerHTML = `
                <div class="flex-grow-1">
                    <label class="vd-label form-label mb-1">Date</label>
                    <input
                        type="date"
                        name="schedules[${rowIndex}][sched_date]"
                        class="form-control vd-input"
                        min="${minDate}"
                        value="${values.sched_date || ''}"
                        required>
                </div>

                <div style="width: 130px;">
                    <label class="vd-label form-label mb-1">Max Patients</label>
                    <input
                        type="number"
                        name="schedules[${rowIndex}][max_appointments]"
                        class="form-control vd-input"
                        value="${values.max_appointments || 8}"
                        min="1"
                        max="50"
                        required>
                </div>

                <button type="button"
                        class="btn vd-btn-outline schedule-row-remove"
                        aria-label="Remove schedule date"
                        title="Remove date">
                    <i class="ti ti-trash"></i>
                </button>
            `;
            row.querySelector('.schedule-row-remove').addEventListener('click', () => {
                // Keep one empty row available instead of removing the last one.
                if (rows.children.length === 1) {
                    row.querySelector('input[type="date"]').value = '';
                    return;
                }
                row.remove();
            });
            rows.appendChild(row);
        }

        modalElement.addEventListener('show.bs.modal', function(e) {
            // Start each bulk-add session with a clean row for the selected clinic.
            const btn = e.relatedTarget; // the button that triggered the modal
            document.getElementById('modalClinicName').textContent = btn.dataset.clinicName;
            document.getElementById('modalClinicId').value = btn.dataset.clinicId;
            document.getElementById('addError').classList.add('d-none');
            document.getElementById('addError').textContent = '';

            // Reset the schedule rows to a single empty row for the new clinic.
            nextScheduleIndex = 0;
            rows.replaceChildren();
            addRow();
        });
        document.getElementById('addScheduleRow').addEventListener('click', () => addRow());

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            // FormData preserves the schedules[][field] rows for the batch endpoint.
            const formData = new FormData(this);
            formData.append('action', 'add_schedule');
            formData.append('csrf_token', <?= json_encode($_SESSION['csrf_token'] ?? '') ?>);
            const submitButton = this.querySelector('button[type="submit"]');
            LoadingUI.setButton(submitButton, true, 'Adding…');

            fetch('../../controllers/scheduleController.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(text => {
                    if (text.trim() === 'success') {
                        // Refresh after the modal closes so all newly added dates appear.
                        const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
                        modalElement.addEventListener('hidden.bs.modal', () => {
                            if (typeof window.refreshSchedulePage === 'function') window.refreshSchedulePage();
                            else if (typeof loadpage === 'function') loadpage('schedule-content.php');
                        }, {
                            once: true
                        });
                        modal.hide();
                        const count = rows.children.length;
                        if (typeof showToast === 'function') showToast(`${count} schedule${count === 1 ? '' : 's'} added successfully!`, true);
                    } else {
                        document.getElementById('addError').textContent = text;
                        document.getElementById('addError').classList.remove('d-none');
                        if (typeof showToast === 'function') showToast(text, false);
                    }
                })
                .catch(err => {
                    if (typeof showToast === 'function') showToast('Network error. Please try again.', false);
                    console.error(err);
                })
                .finally(() => LoadingUI.setButton(submitButton, false));
        });
    })();
</script>