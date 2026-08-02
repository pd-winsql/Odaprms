<!-- Add Schedule Modal -->
<div class="modal fade" id="addScheduleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content vd-modal-content p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="vd-modal-title mb-0">Add Schedule for <span id="modalClinicName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="add-schedule-form" class="d-flex flex-column gap-3">
            <div>
                <label class="vd-label form-label">Date</label>
                <input type="date" name="sched_date" class="form-control vd-input" min="<?= date('Y-m-d') ?>" required>
            </div>
            <input type="hidden" name="clinic_id" id="modalClinicId">
            <div>
                <label class="vd-label form-label">Max Appointments</label>
                <input type="number" name="max_appointments" class="form-control vd-input" value="8" min="1" max="50" required>
            </div>
            <div id="addError" class="text-danger small d-none"></div>
            <button type="submit" class="btn vd-btn-gold">Add Schedule</button>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    const modalElement = document.getElementById('addScheduleModal');
    const formElement = document.getElementById('add-schedule-form');

    if (!modalElement || modalElement.dataset.bound === 'true') {
        return;
    }

    modalElement.dataset.bound = 'true';

    const modalInstance = bootstrap.Modal.getOrCreateInstance(modalElement);

    modalElement.addEventListener('show.bs.modal', function(e) {
        const btn = e.relatedTarget;
        if (!btn) return;
        document.getElementById('modalClinicName').textContent = btn.dataset.clinicName;
        document.getElementById('modalClinicId').value = btn.dataset.clinicId;
    });

    if (formElement) {
        formElement.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'add_schedule');
            const submitButton = this.querySelector('button[type="submit"]');
            LoadingUI.setButton(submitButton, true, 'Adding…');

            fetch('../../controllers/scheduleController.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                if (text.trim() === 'success') {
                    modalElement.addEventListener('hidden.bs.modal', () => {
                        if (typeof window.refreshSchedulePage === 'function') window.refreshSchedulePage();
                        else if (typeof loadpage === 'function') loadpage('schedule-content.php');
                    }, { once: true });
                    modalInstance.hide();
                    if (typeof showToast === 'function') showToast('Schedule added successfully!', true);
                } else {
                    const addError = document.getElementById('addError');
                    addError.textContent = text;
                    addError.classList.remove('d-none');
                    if (typeof showToast === 'function') showToast(text, false);
                }
            })
            .catch(err => {
                if (typeof showToast === 'function') showToast('Network error. Please try again.', false);
                console.error(err);
            })
            .finally(() => LoadingUI.setButton(submitButton, false));
        });
    }
})();
</script>
