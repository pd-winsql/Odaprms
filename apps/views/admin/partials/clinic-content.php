<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}

require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/clinicModel.php';

$db   = new Database();
$conn = $db->connect();
$clinicModel = new Clinic($conn);

$clinics = $clinicModel->getAllClinics();
?>

<div class="d-flex flex-column gap-4">

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

<!-- Toast -->
<div id="clinicToast" class="vd-toast d-none">
    <span id="clinicToastMsg"></span>
</div>

<script>
(function () {
    const CONTROLLER = '../../../apps/controllers/clinicController.php';

    function showToast(msg, success) {
        const toast = document.getElementById('clinicToast');
        const msgEl = document.getElementById('clinicToastMsg');
        msgEl.textContent = msg;
        toast.classList.remove('d-none', 'vd-toast-success', 'vd-toast-error');
        toast.classList.add(success ? 'vd-toast-success' : 'vd-toast-error');
        setTimeout(() => toast.classList.add('d-none'), 3000);
    }

    document.querySelectorAll('.vd-save-clinic-btn').forEach(btn => {
        btn.addEventListener('click', async function () {
            const card        = this.closest('.vd-dash-card');
            const id           = this.dataset.id;
            const nameInput    = card.querySelector('[data-field="name"]');
            const phoneInput   = card.querySelector('[data-field="phone"]');
            const addressInput = card.querySelector('[data-field="address"]');
            const fileInput    = card.querySelector('.vd-clinic-image-input');

            const formData = new FormData();
            formData.append('action', 'updateInline');
            formData.append('clinic_id', id);
            formData.append('name', nameInput.value.trim());
            formData.append('phone', phoneInput.value.trim());
            formData.append('address', addressInput.value.trim());

            if (fileInput.files[0]) {
                formData.append('image', fileInput.files[0]);
            }

            const originalText = this.textContent;
            this.disabled = true;
            this.textContent = 'Saving…';

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
                this.disabled = false;
                this.textContent = originalText;
            }
        });
    });
})();
</script>