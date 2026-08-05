<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}

require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/siteSettingsModel.php';

$db   = new Database();
$conn = $db->connect();
$settingsModel = new SiteSettingsModel($conn);

$settings = $settingsModel->getSettings();
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

function sv($settings, $key) {
    return htmlspecialchars($settings[$key] ?? '');
}
?>

<div class="d-flex flex-column gap-4">

    <div class="vd-empty-state" style="background: var(--gold-pale); border: 1px solid var(--border); color: var(--mid); text-align: left; padding: 14px 18px;">
        <i class="ti ti-info-circle me-1"></i>
        Changes here update the public homepage (<code>index.php</code>) immediately after saving. Each section below saves independently.
    </div>

    <!-- ── BRAND & LOGO ── -->
    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
            <span class="vd-dash-card-title">Brand & Logo</span>
        </div>
        <div class="vd-dash-card-body">
            <p class="vd-appt-meta mb-3">
                The stylized "VEN✚URA" wordmark itself stays fixed for now — the text below it is editable,
                and uploading a logo image will replace the whole wordmark automatically.
            </p>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="vd-label form-label">Small text above wordmark</label>
                    <input type="text" class="form-control vd-input vd-field" data-field="brand_name_top"
                        maxlength="50" value="<?= sv($settings, 'brand_name_top') ?>">
                </div>
                <div class="col-md-6">
                    <label class="vd-label form-label">Small text below wordmark</label>
                    <input type="text" class="form-control vd-input vd-field" data-field="brand_name_sub"
                        maxlength="50" value="<?= sv($settings, 'brand_name_sub') ?>">
                </div>
            </div>
            <div class="d-flex justify-content-end mb-4">
                <button class="btn vd-btn-gold btn-sm vd-save-group-btn" data-group="brand">Save Brand Text</button>
            </div>

            <hr style="border-color: var(--border);">

            <div class="mt-3">
                <label class="vd-label form-label">Site Logo (optional)</label>
                <?php if (!empty($settings['site_logo'])): ?>
                    <div class="mb-2">
                        <img src="../../../public/assets/<?= htmlspecialchars($settings['site_logo']) ?>"
                                                     alt="Current logo" style="height:32px; border-radius:6px; border:1px solid var(--border);">
                        <span class="vd-appt-meta ms-2">Currently in use</span>
                    </div>
                <?php else: ?>
                    <div class="vd-appt-meta mb-2">No logo uploaded yet — showing the text wordmark.</div>
                <?php endif; ?>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <input type="file" id="logoInput" class="form-control form-control-sm" style="max-width: 280px;" accept="image/jpeg,image/png,image/webp,image/svg+xml">
                    <button class="btn vd-btn-outline btn-sm" id="uploadLogoBtn">Upload Logo</button>
                    <?php if (!empty($settings['site_logo'])): ?>
                        <button class="btn vd-btn-outline btn-sm text-danger" id="removeLogoBtn">Remove Logo</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ── HERO SECTION ── -->
    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
            <span class="vd-dash-card-title">Hero Section</span>
        </div>
        <div class="vd-dash-card-body">
            <div class="mb-3">
                <label class="vd-label form-label">System Tag <span class="vd-appt-meta">(small pill above everything)</span></label>
                <input type="text" class="form-control vd-input vd-field" data-field="hero_system_tag"
                    maxlength="150" value="<?= sv($settings, 'hero_system_tag') ?>">
            </div>
            <div class="mb-3">
                <label class="vd-label form-label">Eyebrow <span class="vd-appt-meta">(small gold caps line)</span></label>
                <input type="text" class="form-control vd-input vd-field" data-field="hero_eyebrow"
                    maxlength="150" value="<?= sv($settings, 'hero_eyebrow') ?>">
            </div>
            <div class="mb-3">
                <label class="vd-label form-label">Headline</label>
                <input type="text" class="form-control vd-input vd-field" data-field="hero_title"
                    maxlength="255" value="<?= sv($settings, 'hero_title') ?>">
            </div>
            <div class="mb-3">
                <label class="vd-label form-label">Subtext</label>
                <textarea class="form-control vd-input vd-field" data-field="hero_subtext" rows="2" maxlength="500"><?= sv($settings, 'hero_subtext') ?></textarea>
            </div>
            <div class="d-flex justify-content-end">
                <button class="btn vd-btn-gold btn-sm vd-save-group-btn" data-group="hero">Save Hero Section</button>
            </div>
        </div>
    </div>

    <!-- ── ABOUT SECTION ── -->
    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
            <span class="vd-dash-card-title">About Section</span>
        </div>
        <div class="vd-dash-card-body">
            <div class="mb-4">
                <label class="vd-label form-label">Intro Paragraph</label>
                <textarea class="form-control vd-input vd-field" data-field="about_intro" rows="4"><?= sv($settings, 'about_intro') ?></textarea>
            </div>

            <?php for ($i = 1; $i <= 3; $i++): ?>
            <div class="row g-3 mb-3 align-items-start">
                <div class="col-md-4">
                    <label class="vd-label form-label">Pillar <?= $i ?> Title</label>
                    <input type="text" class="form-control vd-input vd-field" data-field="pillar<?= $i ?>_title"
                        maxlength="100" value="<?= sv($settings, "pillar{$i}_title") ?>">
                </div>
                <div class="col-md-8">
                    <label class="vd-label form-label">Pillar <?= $i ?> Description</label>
                    <input type="text" class="form-control vd-input vd-field" data-field="pillar<?= $i ?>_desc"
                        maxlength="255" value="<?= sv($settings, "pillar{$i}_desc") ?>">
                </div>
            </div>
            <?php endfor; ?>

            <div class="d-flex justify-content-end">
                <button class="btn vd-btn-gold btn-sm vd-save-group-btn" data-group="about">Save About Section</button>
            </div>
        </div>
    </div>

    <!-- ── CONTACT INFO ── -->
    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
            <span class="vd-dash-card-title">Contact Information</span>
        </div>
        <div class="vd-dash-card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="vd-label form-label">Address</label>
                    <input type="text" class="form-control vd-input vd-field" data-field="contact_address"
                        maxlength="255" value="<?= sv($settings, 'contact_address') ?>">
                </div>
                <div class="col-md-4">
                    <label class="vd-label form-label">Phone</label>
                    <input type="text" class="form-control vd-input vd-field" data-field="contact_phone"
                        maxlength="20" value="<?= sv($settings, 'contact_phone') ?>">
                </div>
                <div class="col-md-4">
                    <label class="vd-label form-label">Email</label>
                    <input type="email" class="form-control vd-input vd-field" data-field="contact_email"
                        maxlength="100" value="<?= sv($settings, 'contact_email') ?>">
                </div>
            </div>
            <div class="d-flex justify-content-end">
                <button class="btn vd-btn-gold btn-sm vd-save-group-btn" data-group="contact">Save Contact Info</button>
            </div>
        </div>
    </div>

    <div class="vd-dash-card">
        <div class="vd-dash-card-header"><span class="vd-dash-card-title">GCash Deposit Settings</span></div>
        <div class="vd-dash-card-body">
            <div class="alert alert-info small">The feature uses a fixed ₱400 deposit and a 30-minute receipt-submission deadline.</div>
            <div class="row g-3 mb-3">
                <div class="col-md-3"><label class="vd-label form-label">Deposit</label><input class="form-control vd-input vd-field" data-field="deposit_amount" value="400.00" readonly></div>
                <div class="col-md-3"><label class="vd-label form-label">Deadline</label><input class="form-control vd-input vd-field" data-field="payment_deadline_minutes" value="30" readonly></div>
                <div class="col-md-3"><label class="vd-label form-label">GCash Account Name</label><input class="form-control vd-input vd-field" data-field="gcash_account_name" value="<?= sv($settings, 'gcash_account_name') ?>" maxlength="100"></div>
                <div class="col-md-3"><label class="vd-label form-label">GCash Number</label><input class="form-control vd-input vd-field" data-field="gcash_account_number" value="<?= sv($settings, 'gcash_account_number') ?>" maxlength="30"></div>
            </div>
            <div class="d-flex justify-content-end mb-4"><button class="btn vd-btn-gold btn-sm vd-save-group-btn" data-group="payment">Save GCash Details</button></div>
            <hr style="border-color: var(--border);">
            <label class="vd-label form-label mt-2">GCash QR Code</label>
            <?php if (!empty($settings['gcash_qr_path'])): ?><div class="mb-3"><img src="../../../public/assets/<?= htmlspecialchars($settings['gcash_qr_path']) ?>" alt="Current GCash QR" style="max-height:180px" class="img-thumbnail"></div><?php endif; ?>
            <div class="d-flex flex-wrap gap-2 align-items-center"><input type="file" id="gcashQrInput" class="form-control form-control-sm" style="max-width:280px" accept="image/jpeg,image/png"><button type="button" class="btn vd-btn-outline btn-sm" id="uploadGcashQrBtn">Upload QR Code</button></div>
        </div>
    </div>

</div>


<!-- Save confirmation modal -->
<div class="modal fade" id="settingsConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content vd-modal-content vd-confirm-modal">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title vd-modal-title">Confirm Save</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="settingsConfirmMessage">Are you sure you want to save these changes?</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="vd-btn-outline btn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="vd-btn-gold btn" id="settingsConfirmBtn">Confirm &amp; Save</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const CONTROLLER = '../../../apps/controllers/siteSettingsController.php';
    const settingsCsrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;

    const groupLabels = {
        brand: 'Brand Text',
        hero: 'Hero Section',
        about: 'About Section',
        contact: 'Contact Information',
        payment: 'GCash Deposit Settings',
    };

    function showToast(msg, success) {
        if (typeof window.showToast === 'function') { window.showToast(msg, success); return; }
        console.warn('showToast not available:', msg);
    }

    function refreshPage() {
        if (typeof loadpage === 'function') loadpage('siteSettings-content.php');
    }

    const confirmModalEl = document.getElementById('settingsConfirmModal');
    const confirmMessageEl = document.getElementById('settingsConfirmMessage');
    const confirmBtn = document.getElementById('settingsConfirmBtn');
    const confirmModal = new bootstrap.Modal(confirmModalEl);
    let pendingConfirmAction = null;

    function askForSaveConfirmation(message, onConfirm) {
        pendingConfirmAction = onConfirm;
        confirmMessageEl.textContent = message;
        confirmBtn.disabled = false;
        confirmBtn.textContent = 'Confirm & Save';
        confirmModal.show();
    }

    confirmBtn.addEventListener('click', async function () {
        if (!pendingConfirmAction) return;

        this.disabled = true;
        this.textContent = 'Saving…';

        LoadingUI.setButton(this, true, 'Saving…');
        try {
            const shouldRefresh = await pendingConfirmAction();
            if (shouldRefresh) {
                confirmModalEl.addEventListener('hidden.bs.modal', refreshPage, { once: true });
            }
            confirmModal.hide();
        } catch (err) {
            console.error(err);
            this.disabled = false;
            this.textContent = 'Confirm & Save';
        } finally {
            LoadingUI.setButton(this, false);
            this.disabled = false;
            this.textContent = 'Confirm & Save';
            pendingConfirmAction = null;
        }
    });

    // ── Save a text/textarea group ──
    document.querySelectorAll('.vd-save-group-btn').forEach(btn => {
        btn.addEventListener('click', async function () {
            const group = this.dataset.group;
            const label = groupLabels[group] || group;
            const saveButton = this;

            askForSaveConfirmation(
                `Save changes to the "${label}" section? This will update what visitors see on the homepage right away.`,
                async function () {
                    const card   = saveButton.closest('.vd-dash-card');
                    const fields = card.querySelectorAll('.vd-field');

                    const formData = new FormData();
                    formData.append('action', 'updateGroup');
                    formData.append('csrf_token', settingsCsrfToken);
                    formData.append('group', group);
                    fields.forEach(field => {
                        formData.append(field.dataset.field, field.value.trim());
                    });

                    const originalText = saveButton.textContent;
                    saveButton.disabled = true;
                    saveButton.textContent = 'Saving…';

                    LoadingUI.setButton(saveButton, true, 'Saving…');
                    try {
                        const response = await fetch(CONTROLLER, { method: 'POST', body: formData });
                        const result   = await response.json();
                        showToast(result.message || (result.success ? 'Saved.' : 'Failed to save.'), result.success);
                    } catch (err) {
                        showToast('Network error. Please try again.', false);
                        console.error(err);
                    } finally {
                        LoadingUI.setButton(saveButton, false);
                        saveButton.disabled = false;
                        saveButton.textContent = originalText;
                    }
                }
            );
        });
    });

    // ── Upload logo ──
    const uploadLogoBtn = document.getElementById('uploadLogoBtn');
    const logoInput     = document.getElementById('logoInput');

    if (uploadLogoBtn) {
        uploadLogoBtn.addEventListener('click', async function () {
            if (!logoInput.files[0]) {
                showToast('Please choose an image first.', false);
                return;
            }

            const uploadButton = this;
            askForSaveConfirmation(
                'Upload this logo and replace the current wordmark on the homepage?',
                async function () {
                    const formData = new FormData();
                    formData.append('action', 'updateLogo');
                    formData.append('csrf_token', settingsCsrfToken);
                    formData.append('logo', logoInput.files[0]);

                    const originalText = uploadButton.textContent;
                    uploadButton.disabled = true;
                    uploadButton.textContent = 'Uploading…';

                    LoadingUI.setButton(uploadButton, true, 'Uploading…');
                    try {
                        const response = await fetch(CONTROLLER, { method: 'POST', body: formData });
                        const result   = await response.json();
                        showToast(result.message || (result.success ? 'Logo updated.' : 'Failed to upload.'), result.success);
                        return result.success;
                    } catch (err) {
                        showToast('Network error. Please try again.', false);
                        console.error(err);
                    } finally {
                        LoadingUI.setButton(uploadButton, false);
                        uploadButton.disabled = false;
                        uploadButton.textContent = originalText;
                    }
                }
            );
        });
    }
    // ── Remove logo ──
    const removeLogoBtn = document.getElementById('removeLogoBtn');
    if (removeLogoBtn) {
        removeLogoBtn.addEventListener('click', function () {
            const btn = this;
            askForSaveConfirmation('Remove the current logo and revert to the text wordmark?', async function () {
                const formData = new FormData();
                formData.append('action', 'removeLogo');
                formData.append('csrf_token', settingsCsrfToken);

                const originalText = btn.textContent;
                btn.disabled = true;
                btn.textContent = 'Removing…';

                LoadingUI.setButton(btn, true, 'Removing…');
                try {
                    const response = await fetch(CONTROLLER, { method: 'POST', body: formData });
                    const result   = await response.json();
                    showToast(result.message || (result.success ? 'Logo removed.' : 'Failed to remove.'), result.success);
                    return result.success;
                } catch (err) {
                    showToast('Network error. Please try again.', false);
                    console.error(err);
                } finally {
                    LoadingUI.setButton(btn, false);
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            });
        });
    }

    const uploadGcashQrBtn = document.getElementById('uploadGcashQrBtn');
    const gcashQrInput = document.getElementById('gcashQrInput');
    uploadGcashQrBtn?.addEventListener('click', function () {
        if (!gcashQrInput.files[0]) { showToast('Choose a QR image first.', false); return; }
        const button = this;
        askForSaveConfirmation('Upload this GCash QR code for patient deposit payments?', async function () {
            const formData = new FormData();
            formData.append('action', 'updateGcashQr');
            formData.append('csrf_token', settingsCsrfToken);
            formData.append('gcash_qr', gcashQrInput.files[0]);
            LoadingUI.setButton(button, true, 'Uploading…');
            try {
                const response = await fetch(CONTROLLER, { method: 'POST', body: formData });
                const result = await response.json();
                showToast(result.message, result.success);
                return result.success;
            } catch (error) { showToast('Unable to upload the QR code.', false); }
            finally { LoadingUI.setButton(button, false); }
        });
    });
})();
</script>
