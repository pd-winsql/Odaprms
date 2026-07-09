<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    echo '<div class="vd-empty-state">Unauthorized. Only admins can manage dental assistants.</div>';
    exit;
}

require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/staffModel.php';

$db   = new Database();
$conn = $db->connect();
$staffModel = new Staff($conn);
$staffList  = $staffModel->getAllStaff();
?>

<div class="d-flex flex-column gap-4">

    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
        <span class="vd-dash-card-title">Dental Assistants</span>
        <button class="btn vd-btn-gold btn-sm"
            data-bs-toggle="modal"
            data-bs-target="#createStaffModal">
            <i class="ti ti-plus me-1"></i> New Account
        </button>
    </div>

    <div class="vd-dash-card-body">
        <?php if (empty($staffList)): ?>
            <div class="vd-empty-state">No dental assistants found.</div>
        <?php else: ?>
            <div class="vd-appt-table-wrap">
            <table class="vd-appt-table w-100" id="staffTable">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Gender</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Date Added</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($staffList as $staff): ?>
                    <tr id="staffRow-<?= $staff['staff_id'] ?>">
                    <td>
                        <div class="vd-appt-name">
                        <?= htmlspecialchars($staff['lastname'] . ', ' . $staff['firstname']) ?>
                        </div>
                        <div class="vd-appt-meta"><?= htmlspecialchars($staff['email']) ?></div>
                    </td>
                    <td class="vd-appt-meta"><?= htmlspecialchars($staff['username']) ?></td>
                    <td class="vd-appt-meta"><?= htmlspecialchars($staff['gender'] ?? '—') ?></td>
                    <td class="vd-appt-meta" id="phone-<?= $staff['staff_id'] ?>">
                        <?= htmlspecialchars($staff['phone_number']) ?>
                    </td>
                    <td>
                        <span class="vd-status <?= $staff['employment_status'] === 'Active' ? 'vd-status-confirmed' : 'vd-status-cancelled' ?>"
                        id="statusPill-<?= $staff['staff_id'] ?>">
                        <?= $staff['employment_status'] ?>
                        </span>
                    </td>
                    <td class="vd-appt-meta"><?= date('M d, Y', strtotime($staff['created_at'])) ?></td>
                    <td>
                        <div class="vd-action-group">
                        <button class="btn btn-sm vd-btn-outline vd-edit-staff-btn"
                            data-id="<?= $staff['staff_id'] ?>"
                            data-phone="<?= htmlspecialchars($staff['phone_number']) ?>"
                            data-email="<?= htmlspecialchars($staff['email']) ?>">
                            <i class="ti ti-pencil"></i>
                        </button>
                        <button class="btn btn-sm vd-btn-outline vd-toggle-status-btn"
                            data-id="<?= $staff['staff_id'] ?>"
                            data-status="<?= $staff['employment_status'] ?>"
                            title="Toggle Status">
                            <i class="ti ti-power"></i>
                        </button>
                        </div>
                    </td>
                    </tr>
                    <!-- Inline edit row — hidden by default -->
                    <tr class="vd-edit-row d-none" id="editRow-<?= $staff['staff_id'] ?>">
                    <td colspan="7">
                        <div class="vd-inline-edit">
                        <div class="vd-filter-group">
                            <label class="vd-label form-label">Phone</label>
                            <input type="tel" class="form-control vd-input vd-edit-phone"
                            value="<?= htmlspecialchars($staff['phone_number']) ?>">
                        </div>
                        <div class="vd-filter-group">
                            <label class="vd-label form-label">Email</label>
                            <input type="email" class="form-control vd-input vd-edit-email"
                            value="<?= htmlspecialchars($staff['email']) ?>">
                        </div>
                        <div class="d-flex gap-2 align-items-end">
                            <button class="btn vd-btn-gold btn-sm vd-save-edit-btn"
                            data-id="<?= $staff['staff_id'] ?>">
                            Save
                            </button>
                            <button class="btn vd-btn-outline btn-sm vd-cancel-edit-btn"
                            data-id="<?= $staff['staff_id'] ?>">
                            Cancel
                            </button>
                        </div>
                        </div>
                    </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
        </div>
    </div>

</div>

<!-- Create Staff Modal -->
<div class="modal fade" id="createStaffModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content vd-modal-content p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="vd-modal-title mb-0">New Dental Assistant Account</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="createStaffForm" class="d-flex flex-column gap-3">
            <div class="row g-3">
            <div class="col-12 col-sm-4">
                <label class="vd-label form-label">First Name <span class="text-danger">*</span></label>
                <input type="text" name="firstname" class="form-control vd-input" required>
            </div>
            <div class="col-12 col-sm-4">
                <label class="vd-label form-label">Last Name <span class="text-danger">*</span></label>
                <input type="text" name="lastname" class="form-control vd-input" required>
            </div>
            <div class="col-12 col-sm-4">
                <label class="vd-label form-label">Middle Name</label>
                <input type="text" name="middlename" class="form-control vd-input">
            </div>
            <div class="col-12 col-sm-6">
                <label class="vd-label form-label">Gender <span class="text-danger">*</span></label>
                <select name="gender" class="form-select vd-input" required>
                <option value="" disabled selected>— Select —</option>
                <option>Male</option>
                <option>Female</option>
                <option>Prefer not to say</option>
                </select>
            </div>
            <div class="col-12 col-sm-6">
                <label class="vd-label form-label">Phone Number <span class="text-danger">*</span></label>
                <input type="tel" name="phone" class="form-control vd-input" required>
            </div>
            <div class="col-12">
                <label class="vd-label form-label">Email Address <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control vd-input" required>
            </div>
            <div class="col-12">
                <label class="vd-label form-label">Password <span class="text-danger">*</span></label>
                <input type="text" name="password" class="form-control vd-input"
                placeholder="Set a temporary password" required>
                <div class="mt-1" style="font-size:10px; color: var(--mid);">
                The dental assistant can change this after logging in.
                </div>
            </div>
            </div>
            <div id="createError" class="text-danger small d-none"></div>

            <!-- Success message shown after creation -->
            <div id="createSuccess" class="d-none vd-staff-success-box">
            <div class="vd-staff-success-label">Account Created</div>
            <div class="small mt-1">Username: <strong id="generatedUsername"></strong></div>
            <div class="small">Share these credentials with the dental assistant.</div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-1" id="createFormActions">
            <button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn vd-btn-gold">Create Account</button>
            </div>
            <div class="d-flex justify-content-end d-none" id="createDoneActions">
            <button type="button" class="btn vd-btn-gold" id="createDoneBtn">Done</button>
            </div>
        </form>
        </div>
    </div>
</div>

<!-- Toast -->
<div id="staffToast" class="vd-toast d-none">
    <span id="staffToastMsg"></span>
</div>

<script>
(function () {
    const CONTROLLER = '../../../apps/controllers/staffController.php';

    function showToast(msg, success) {
        const toast = document.getElementById('staffToast');
        const msgEl = document.getElementById('staffToastMsg');
        msgEl.textContent = msg;
        toast.classList.remove('d-none', 'vd-toast-success', 'vd-toast-error');
        toast.classList.add(success ? 'vd-toast-success' : 'vd-toast-error');
        setTimeout(() => toast.classList.add('d-none'), 3000);
    }

    // ── Create account ──
    document.getElementById('createStaffForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'create');
        const errorEl = document.getElementById('createError');
        errorEl.classList.add('d-none');

        try {
        const res    = await fetch(CONTROLLER, { method: 'POST', body: formData });
        const result = await res.json();

        if (result.success) {
            // Show success block with generated username
            document.getElementById('generatedUsername').textContent = result.username;
            document.getElementById('createSuccess').classList.remove('d-none');
            document.getElementById('createFormActions').classList.add('d-none');
            document.getElementById('createDoneActions').classList.remove('d-none');
        } else {
            errorEl.textContent = result.message;
            errorEl.classList.remove('d-none');
        }
        } catch (err) {
        errorEl.textContent = 'Network error. Please try again.';
        errorEl.classList.remove('d-none');
        console.error(err);
        }
    });

    // Done button — reload to show new staff in list
    document.getElementById('createDoneBtn').addEventListener('click', () => {
        location.reload();
    });

    // Reset modal on close
    document.getElementById('createStaffModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('createStaffForm').reset();
        document.getElementById('createSuccess').classList.add('d-none');
        document.getElementById('createError').classList.add('d-none');
        document.getElementById('createFormActions').classList.remove('d-none');
        document.getElementById('createDoneActions').classList.add('d-none');
    });

    // ── Inline edit — show ──
    document.querySelectorAll('.vd-edit-staff-btn').forEach(btn => {
        btn.addEventListener('click', () => {
        const id = btn.dataset.id;
        // Hide all other open edit rows first
        document.querySelectorAll('.vd-edit-row').forEach(r => r.classList.add('d-none'));
        document.getElementById('editRow-' + id).classList.remove('d-none');
        });
    });

    // ── Inline edit — cancel ──
    document.querySelectorAll('.vd-cancel-edit-btn').forEach(btn => {
        btn.addEventListener('click', () => {
        document.getElementById('editRow-' + btn.dataset.id).classList.add('d-none');
        });
    });

    // ── Inline edit — save ──
    document.querySelectorAll('.vd-save-edit-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
        const id      = btn.dataset.id;
        const editRow = document.getElementById('editRow-' + id);
        const phone   = editRow.querySelector('.vd-edit-phone').value.trim();
        const email   = editRow.querySelector('.vd-edit-email').value.trim();

        if (!phone || !email) {
            showToast('Phone and email are required.', false);
            return;
        }

        const fd = new FormData();
        fd.append('action', 'update');
        fd.append('staff_id', id);
        fd.append('phone', phone);
        fd.append('email', email);

        try {
            const res    = await fetch(CONTROLLER, { method: 'POST', body: fd });
            const result = await res.json();

            if (result.success) {
            // Update displayed values in the row
            document.getElementById('phone-' + id).textContent = phone;
            const nameCell = document.querySelector('#staffRow-' + id + ' .vd-appt-meta');
            if (nameCell) nameCell.textContent = email;
            editRow.classList.add('d-none');
            showToast('Updated successfully.', true);
            } else {
            showToast(result.message || 'Failed to update.', false);
            }
        } catch (err) {
            showToast('Network error.', false);
            console.error(err);
        }
        });
    });

    // ── Toggle status ──
    document.querySelectorAll('.vd-toggle-status-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
        const id         = btn.dataset.id;
        const current    = btn.dataset.status;
        const newStatus  = current === 'Active' ? 'Inactive' : 'Active';

        if (!confirm(`Set this dental assistant to ${newStatus}?`)) return;

        const fd = new FormData();
        fd.append('action', 'toggleStatus');
        fd.append('staff_id', id);

        try {
            const res    = await fetch(CONTROLLER, { method: 'POST', body: fd });
            const result = await res.json();

            if (result.success) {
            const pill = document.getElementById('statusPill-' + id);
            pill.textContent  = newStatus;
            pill.className    = 'vd-status ' + (newStatus === 'Active' ? 'vd-status-confirmed' : 'vd-status-cancelled');
            btn.dataset.status = newStatus;
            showToast('Status updated to ' + newStatus + '.', true);
            } else {
            showToast(result.message || 'Failed to update status.', false);
            }
        } catch (err) {
            showToast('Network error.', false);
            console.error(err);
        }
        });
    });

})();
</script>