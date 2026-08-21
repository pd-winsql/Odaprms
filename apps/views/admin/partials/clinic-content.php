<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Admin', 'Dental Assistant'])) {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}

require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/clinicModel.php';

$db   = new Database();
$conn = $db->connect();
$clinicModel = new Clinic($conn);

$clinics = $clinicModel->getAllClinics();
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
?>

<div class="d-flex flex-column gap-4">

    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
        <div>
            <div class="vd-welcome-greet">CLINIC MANAGEMENT</div>
            <div class="vd-welcome-name">Clinic Locations</div>
            <p class="text-muted small mb-0 mt-2">Add a clinic or update its contact details, address, and display image.</p>
        </div>
        <button type="button" class="btn vd-btn-gold align-self-start" data-bs-toggle="modal" data-bs-target="#addClinicModal">
            <i class="ti ti-plus me-1"></i> Add Clinic
        </button>
    </div>

    <?php if (empty($clinics)): ?>
        <div class="vd-empty-state">No clinics found.</div>
    <?php endif; ?>

    <?php foreach ($clinics as $clinic):
        $imagePath = $clinic['clinic_image']
            ? '/Capstone System/public/assets/clinic-images/' . $clinic['clinic_image']
            : null;
    ?>
    <div class="vd-dash-card" data-clinic-id="<?= $clinic['clinic_id'] ?>">
        <div class="vd-dash-card-header">
            <span class="vd-dash-card-title"><?= htmlspecialchars($clinic['clinic_name']) ?></span>
        </div>

        <div class="vd-dash-card-body">
            <div class="d-flex flex-column flex-md-row gap-4">

                <!-- Clinic image -->
                <div class="d-flex flex-column align-items-center gap-2" style="width:140px; flex-shrink:0;">
                    <?php if ($imagePath): ?>
                        <img src="<?= htmlspecialchars($imagePath) ?>"
                             alt="<?= htmlspecialchars($clinic['clinic_name']) ?>"
                             class="vd-clinic-image-preview"
                             style="width:120px; height:120px; object-fit:cover; border-radius:8px; border:1px solid var(--border);">
                    <?php else: ?>
                        <div class="vd-clinic-image-preview d-flex align-items-center justify-content-center"
                             style="width:120px; height:120px; border-radius:8px; border:1px dashed var(--border); color:var(--mid); font-size:11px; text-align:center;">
                            No Image
                        </div>
                    <?php endif; ?>
                    <input type="file" class="form-control form-control-sm vd-clinic-image-input" accept="image/jpeg,image/png,image/webp">
                </div>

                <!-- Editable fields -->
                <div class="flex-grow-1 row g-3">
                    <div class="col-md-6">
                        <label class="vd-label form-label">Clinic Name</label>
                        <input type="text" class="form-control vd-input vd-clinic-field" data-field="name"
                               value="<?= htmlspecialchars($clinic['clinic_name']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="vd-label form-label">Contact Number</label>
                        <input type="text" class="form-control vd-input vd-clinic-field" data-field="phone"
                               value="<?= htmlspecialchars($clinic['clinic_contact']) ?>">
                    </div>
                    <div class="col-12">
                        <label class="vd-label form-label">Address</label>
                        <input type="text" class="form-control vd-input vd-clinic-field" data-field="address"
                               value="<?= htmlspecialchars($clinic['clinic_address']) ?>">
                    </div>
                    <div class="col-12">
                        <label class="vd-label form-label">Google Maps Embed URL</label>
                        <textarea class="form-control vd-input vd-clinic-field" data-field="embed_url" rows="2" placeholder="Paste Google Maps embed URL or full iframe"><?= htmlspecialchars($clinic['embed_url'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn vd-btn-gold btn-sm vd-save-clinic-btn" data-id="<?= $clinic['clinic_id'] ?>">
                            Save Changes
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <?php endforeach; ?>

</div>

<div class="modal fade" id="addClinicModal" tabindex="-1" aria-labelledby="addClinicModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content vd-modal-content">
            <form id="addClinicForm" enctype="multipart/form-data" novalidate>
                <div class="modal-header">
                    <div>
                        <div class="vd-action-modal-kicker">Clinic management</div>
                        <h5 class="modal-title vd-modal-title mb-0" id="addClinicModalTitle">Add New Clinic</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="addClinicError" class="alert alert-danger d-none" role="alert"></div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="vd-label form-label" for="newClinicImage">Clinic Image <span class="text-muted">(optional)</span></label>
                            <input type="file" class="form-control vd-input" id="newClinicImage" name="image" accept="image/jpeg,image/png,image/webp">
                            <div class="form-text">JPG, PNG, or WEBP, up to 5 MB.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="vd-label form-label" for="newClinicName">Clinic Name</label>
                            <input type="text" class="form-control vd-input" id="newClinicName" name="name" maxlength="100" required>
                        </div>
                        <div class="col-md-6">
                            <label class="vd-label form-label" for="newClinicPhone">Contact Number</label>
                            <input type="tel" class="form-control vd-input" id="newClinicPhone" name="phone" maxlength="15" required>
                        </div>
                        <div class="col-12">
                            <label class="vd-label form-label" for="newClinicAddress">Address</label>
                            <input type="text" class="form-control vd-input" id="newClinicAddress" name="address" maxlength="100" required>
                        </div>
                        <div class="col-12">
                            <label class="vd-label form-label" for="newClinicEmbedUrl">Google Maps Embed URL <span class="text-muted">(optional)</span></label>
                            <textarea class="form-control vd-input" id="newClinicEmbedUrl" name="embed_url" rows="2" placeholder="Paste Google Maps embed URL or full iframe"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn vd-btn-gold" id="addClinicSubmit">Add Clinic</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const CONTROLLER = '../../../apps/controllers/clinicController.php';
    const csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;

    function showToast(msg, success) {
        if (typeof window.showToast === 'function') { window.showToast(msg, success); return; }
        console.warn('showToast not available:', msg);
    }

    document.querySelectorAll('.vd-save-clinic-btn').forEach(btn => {
        btn.addEventListener('click', async function () {
            const card        = this.closest('.vd-dash-card');
            const id           = this.dataset.id;
            const nameInput    = card.querySelector('[data-field="name"]');
            const phoneInput   = card.querySelector('[data-field="phone"]');
            const addressInput = card.querySelector('[data-field="address"]');
            const embedInput   = card.querySelector('[data-field="embed_url"]');
            const fileInput    = card.querySelector('.vd-clinic-image-input');

            const formData = new FormData();
            formData.append('action', 'updateInline');
            formData.append('csrf_token', csrfToken);
            formData.append('clinic_id', id);
            formData.append('name', nameInput.value.trim());
            formData.append('phone', phoneInput.value.trim());
            formData.append('address', addressInput.value.trim());
            formData.append('embed_url', embedInput.value.trim());

            if (fileInput.files[0]) {
                formData.append('image', fileInput.files[0]);
            }

            const originalText = this.textContent;
            this.disabled = true;
            this.textContent = 'Saving…';

            LoadingUI.setButton(this, true, 'Saving…');
            try {
                const response = await fetch(CONTROLLER, { method: 'POST', body: formData });
                const result   = await response.json();

                if (result.success) {
                    showToast(result.message || 'Clinic updated.', true);

                    // Keep the card header title in sync with the name field
                    const titleEl = card.querySelector('.vd-dash-card-title');
                    if (titleEl) titleEl.textContent = nameInput.value.trim();

                    // Refresh the image preview if a new one was uploaded
                    if (fileInput.files[0] && result.image) {
                        const newSrc = '/Capstone System/public/assets/clinic-images/' + result.image + '?t=' + Date.now();
                        let img = card.querySelector('.vd-clinic-image-preview');

                        if (img.tagName === 'IMG') {
                            img.src = newSrc;
                        } else {
                            const newImg = document.createElement('img');
                            newImg.src = newSrc;
                            newImg.alt = nameInput.value.trim();
                            newImg.className = 'vd-clinic-image-preview';
                            newImg.style.cssText = 'width:120px; height:120px; object-fit:cover; border-radius:8px; border:1px solid var(--border);';
                            img.replaceWith(newImg);
                        }
                        fileInput.value = '';
                    }
                } else {
                    showToast(result.message || 'Failed to update clinic.', false);
                }
            } catch (err) {
                showToast('Network error. Please try again.', false);
                console.error(err);
            } finally {
                LoadingUI.setButton(this, false);
                this.disabled = false;
                this.textContent = originalText;
            }
        });
    });

    const addClinicModalElement = document.getElementById('addClinicModal');
    const addClinicModal = bootstrap.Modal.getOrCreateInstance(addClinicModalElement);
    const addClinicForm = document.getElementById('addClinicForm');
    const addClinicError = document.getElementById('addClinicError');

    addClinicModalElement.addEventListener('hidden.bs.modal', () => {
        addClinicForm.reset();
        addClinicError.classList.add('d-none');
        addClinicError.textContent = '';
    });

    addClinicForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        const submitButton = document.getElementById('addClinicSubmit');
        addClinicError.classList.add('d-none');

        if (!this.checkValidity()) {
            this.reportValidity();
            return;
        }

        const formData = new FormData(this);
        formData.append('action', 'add');
        formData.append('csrf_token', csrfToken);
        LoadingUI.setButton(submitButton, true, 'Adding…');

        try {
            const response = await fetch(CONTROLLER, { method: 'POST', body: formData });
            const result = await response.json();
            if (!result.success) throw new Error(result.message || 'Failed to add clinic.');

            showToast(result.message, true);
            addClinicModalElement.addEventListener('hidden.bs.modal', () => {
                document.querySelector('[data-page="clinic-content.php"]')?.click();
            }, { once: true });
            addClinicModal.hide();
        } catch (error) {
            addClinicError.textContent = error.message || 'Unable to add clinic.';
            addClinicError.classList.remove('d-none');
            LoadingUI.setButton(submitButton, false);
        }
    });
})();
</script>
