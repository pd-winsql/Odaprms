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
            <p class="text-muted small mb-0">Choose an appointment date and set the maximum number of patients the clinic can accommodate.</p>
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
    document.getElementById('addScheduleModal').addEventListener('show.bs.modal', function(e) {
        const btn = e.relatedTarget; // the button that triggered the modal
        document.getElementById('modalClinicName').textContent = btn.dataset.clinicName;
        document.getElementById('modalClinicId').value = btn.dataset.clinicId;
    });

    document.getElementById('add-schedule-form').addEventListener('submit', function(e) {
        e.preventDefault();
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
                const modalElement = document.getElementById('addScheduleModal');
                const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
                modalElement.addEventListener('hidden.bs.modal', () => {
                    if (typeof window.refreshSchedulePage === 'function') window.refreshSchedulePage();
                    else if (typeof loadpage === 'function') loadpage('schedule-content.php');
                }, { once: true });
                modal.hide();
                if (typeof showToast === 'function') showToast('Schedule added successfully!', true);
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
