<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}

require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/serviceModel.php';

$db   = new Database();
$conn = $db->connect();
$serviceModel = new ServiceModel($conn);

$categories = $serviceModel->getAllCategories();
$services   = $serviceModel->getAllServices();

// Build lookup from services.category_id:
// category_id => [service_id, ...] and service_id => [category_id]
$categoryServiceIds = [];
$serviceCategoryIds = [];
foreach ($services as $service) {
    $serviceId = (int)($service['service_id'] ?? 0);
    $categoryId = (int)($service['category_id'] ?? 0);

    if ($serviceId <= 0 || $categoryId <= 0) {
        continue;
    }

    $categoryServiceIds[$categoryId][] = $serviceId;
    $serviceCategoryIds[$serviceId] = [$categoryId];
}

$servicesById = array_column($services, null, 'service_id');

// Renders one read-only service summary card. Editing happens in the modal —
// this just displays what's already saved, plus Edit/Delete actions.
function renderServiceCard($service, $categories, $assignedCategoryIds) {
    $id       = $service['service_id'];
    $catCsv   = implode(',', $assignedCategoryIds);
    $catNames = array_map(fn($c) => $c['category_name'], array_filter($categories, fn($c) => in_array($c['category_id'], $assignedCategoryIds)));
    $isActive = (int)$service['is_active'] === 1;
    ?>
    <div class="vd-dash-card vd-service-card"
         data-service-id="<?= $id ?>"
         data-name="<?= htmlspecialchars($service['service_name'], ENT_QUOTES) ?>"
         data-description="<?= htmlspecialchars($service['service_description'], ENT_QUOTES) ?>"
         data-icon="<?= htmlspecialchars($service['service_icon'], ENT_QUOTES) ?>"
         data-order="<?= (int)$service['display_order'] ?>"
         data-category-ids="<?= htmlspecialchars($catCsv) ?>"
         data-active="<?= $isActive ? '1' : '0' ?>">
        <div class="vd-dash-card-header vd-service-card-header">
            <span class="vd-dash-card-title vd-service-card-title"><?= htmlspecialchars($service['service_name']) ?></span>
            <span class="vd-service-status-badge <?= $isActive ? 'vd-status-active' : 'vd-status-inactive' ?>">
                <?= $isActive ? 'Active' : 'Inactive' ?>
            </span>
        </div>
        <div class="vd-dash-card-body">
            <div class="d-flex gap-3 align-items-start">
                <div class="vd-service-summary-icon">
                    <i class="<?= htmlspecialchars($service['service_icon']) ?>"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="vd-service-summary-desc"><?= htmlspecialchars($service['service_description']) ?></div>
                    <div class="vd-chip-group mt-2">
                        <?php if (empty($catNames)): ?>
                            <span class="vd-order-badge">No category assigned</span>
                        <?php else: foreach ($catNames as $name): ?>
                            <span class="vd-chip vd-chip-selected" style="cursor:default;"><?= htmlspecialchars($name) ?></span>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
                <div class="d-flex flex-column gap-2">
                    <button class="btn vd-btn-outline btn-sm vd-edit-service-btn" title="Edit"><i class="ti ti-pencil"></i></button>
                    <button class="btn vd-btn-outline btn-sm vd-delete-service-btn" data-id="<?= $id ?>" title="Delete"><i class="ti ti-trash"></i></button>
                </div>
            </div>
        </div>
    </div>
    <?php
}
?>

<div class="d-flex flex-column gap-4">

    <!-- VIEW TOGGLE -->
    <div class="vd-view-toggle">
        <button type="button" class="vd-toggle-btn active" data-view="services">Services</button>
        <button type="button" class="vd-toggle-btn" data-view="categories">Categories</button>
    </div>

    <!-- CATEGORIES VIEW -->
    <div id="categoriesView" class="d-none">
        <div class="vd-dash-card">
            <div class="vd-dash-card-header">
                <span class="vd-dash-card-title">Manage Categories</span>
                <button class="btn vd-btn-gold btn-sm" id="addCategoryBtn">+ Add Category</button>
            </div>
            <div class="vd-dash-card-body p-0">
                <?php if (empty($categories)): ?>
                    <div class="vd-empty-state">No categories yet.</div>
                <?php endif; ?>
                <?php foreach ($categories as $cat): ?>
                <div class="vd-category-list-row"
                     data-id="<?= $cat['category_id'] ?>"
                     data-name="<?= htmlspecialchars($cat['category_name'], ENT_QUOTES) ?>"
                     data-description="<?= htmlspecialchars($cat['category_description'], ENT_QUOTES) ?>"
                     data-order="<?= (int)$cat['display_order'] ?>">
                    <div>
                        <div class="vd-category-list-name"><?= htmlspecialchars($cat['category_name']) ?></div>
                        <div class="vd-category-list-desc"><?= htmlspecialchars($cat['category_description']) ?></div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="vd-order-badge">Order <?= (int)$cat['display_order'] ?></span>
                        <button class="btn vd-btn-outline btn-sm vd-edit-category-btn" title="Edit"><i class="ti ti-pencil"></i></button>
                        <button class="btn vd-btn-outline btn-sm vd-delete-category-btn" data-id="<?= $cat['category_id'] ?>" title="Delete"><i class="ti ti-trash"></i></button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- SERVICES VIEW -->
    <div id="servicesView">

        <div class="vd-service-filter-bar mb-3">
            <input type="text" id="serviceSearch" class="form-control vd-input" placeholder="Search services...">
            <select id="categoryFilter" class="form-select vd-input">
                <option value="">All categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="statusFilter" class="form-select vd-input">
                <option value="">All statuses</option>
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
            <span class="vd-service-count" id="serviceCount"></span>
        </div>

        <div class="d-flex justify-content-end mb-3">
            <button class="btn vd-btn-gold btn-sm" id="addServiceBtn">+ Add New Service</button>
        </div>

        <?php if (empty($services)): ?>
            <div class="vd-empty-state">No services found.</div>
        <?php endif; ?>

        <?php foreach ($categories as $cat):
            $serviceIdsInCat = $categoryServiceIds[$cat['category_id']] ?? [];
            if (empty($serviceIdsInCat)) continue;
        ?>
        <div class="vd-service-category-group mb-4">
            <div class="vd-service-category-group-title"><?= htmlspecialchars($cat['category_name']) ?></div>
            <div class="d-flex flex-column gap-3">
                <?php foreach ($serviceIdsInCat as $sid):
                    if (!isset($servicesById[$sid])) continue;
                    renderServiceCard($servicesById[$sid], $categories, $serviceCategoryIds[$sid] ?? []);
                endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <?php
        $uncategorized = array_filter($services, fn($s) => empty($serviceCategoryIds[$s['service_id']]));
        if (!empty($uncategorized)):
        ?>
        <div class="vd-service-category-group mb-4">
            <div class="vd-service-category-group-title">Uncategorized</div>
            <div class="d-flex flex-column gap-3">
                <?php foreach ($uncategorized as $service): renderServiceCard($service, $categories, []); endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>

</div>

<!-- ============================================================
     SERVICE MODAL (Add / Edit) — two internal steps: form, then
     a receipt-style confirmation before it actually saves
     ============================================================ -->
<div class="modal fade" id="serviceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content vd-modal-content">

      <!-- STEP 1: form -->
      <div id="serviceModalFormStep">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title vd-modal-title" id="serviceModalTitle">Add New Service</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body d-flex flex-column gap-3">
          <input type="hidden" id="serviceModalId" value="">

          <div>
            <label class="vd-label form-label">Choose an Icon</label>
            <div class="vd-icon-picker" id="iconPicker"></div>
          </div>

          <div>
            <label class="vd-label form-label">Service Name</label>
            <input type="text" class="form-control vd-input" id="serviceModalName">
          </div>

          <div>
            <label class="vd-label form-label">Description</label>
            <textarea class="form-control vd-input" id="serviceModalDescription" rows="2"></textarea>
          </div>

          <div class="row g-3 align-items-center">
            <div class="col-6">
              <label class="vd-label form-label">Display Order</label>
              <input type="number" class="form-control vd-input" id="serviceModalOrder" value="0" min="0">
            </div>
            <div class="col-6">
              <label class="d-flex align-items-center gap-2 vd-label mb-0 mt-3">
                <input type="checkbox" id="serviceModalActive" checked>
                Active
              </label>
            </div>
          </div>

          <div>
            <label class="vd-label form-label">Categories</label>
            <div class="vd-chip-group" id="serviceModalCategories">
              <?php foreach ($categories as $cat): ?>
              <span class="vd-chip" data-category-id="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></span>
              <?php endforeach; ?>
            </div>
          </div>

          <div id="serviceModalError" class="text-danger small d-none"></div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="vd-btn-outline btn" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="vd-btn-gold btn" id="serviceModalReviewBtn">Review &amp; Save</button>
        </div>
      </div>

      <!-- STEP 2: confirm (receipt) -->
      <div id="serviceModalConfirmStep" class="d-none">
        <div class="modal-header border-0 pb-0 justify-content-center">
          <h5 class="modal-title vd-modal-title">Confirm Service Details</h5>
        </div>
        <div class="modal-body">
          <div class="vd-receipt" id="serviceReceiptBody"></div>
        </div>
        <div class="modal-footer border-0 pt-0 justify-content-between">
          <button type="button" class="vd-btn-outline btn" id="serviceModalBackBtn">&larr; Edit</button>
          <button type="button" class="vd-btn-gold btn" id="serviceModalConfirmBtn">Confirm &amp; Save</button>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- ============================================================
     CATEGORY MODAL (Add / Edit) — same two-step pattern
     ============================================================ -->
<div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content vd-modal-content">

      <div id="categoryModalFormStep">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title vd-modal-title" id="categoryModalTitle">Add Category</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body d-flex flex-column gap-3">
          <input type="hidden" id="categoryModalId" value="">
          <div>
            <label class="vd-label form-label">Category Name</label>
            <input type="text" class="form-control vd-input" id="categoryModalName">
          </div>
          <div>
            <label class="vd-label form-label">Description</label>
            <input type="text" class="form-control vd-input" id="categoryModalDescription">
          </div>
          <div>
            <label class="vd-label form-label">Display Order</label>
            <input type="number" class="form-control vd-input" id="categoryModalOrder" value="0" min="0">
          </div>
          <div id="categoryModalError" class="text-danger small d-none"></div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="vd-btn-outline btn" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="vd-btn-gold btn" id="categoryModalReviewBtn">Review &amp; Save</button>
        </div>
      </div>

      <div id="categoryModalConfirmStep" class="d-none">
        <div class="modal-header border-0 pb-0 justify-content-center">
          <h5 class="modal-title vd-modal-title">Confirm Category Details</h5>
        </div>
        <div class="modal-body">
          <div class="vd-receipt" id="categoryReceiptBody"></div>
        </div>
        <div class="modal-footer border-0 pt-0 justify-content-between">
          <button type="button" class="vd-btn-outline btn" id="categoryModalBackBtn">&larr; Edit</button>
          <button type="button" class="vd-btn-gold btn" id="categoryModalConfirmBtn">Confirm &amp; Save</button>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- Toast -->
<div id="serviceToast" class="vd-toast d-none">
    <span id="serviceToastMsg"></span>
</div>

<script>
(function () {
    const CONTROLLER = '../../../apps/controllers/serviceController.php';

    // The dashboard shell only loads Tabler icons. Service icons are stored
    // as Font Awesome classes (matching the public landing page), so load
    // Font Awesome once here for the picker/preview/receipt to render correctly.
    if (!document.getElementById('vdFontAwesomeCdn')) {
        const link = document.createElement('link');
        link.id = 'vdFontAwesomeCdn';
        link.rel = 'stylesheet';
        link.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';
        document.head.appendChild(link);
    }

    const ICONS = [
        'fa-solid fa-tooth', 'fa-solid fa-broom', 'fa-solid fa-teeth', 'fa-solid fa-teeth-open',
        'fa-solid fa-x-ray', 'fa-solid fa-crown', 'fa-solid fa-link', 'fa-solid fa-syringe',
        'fa-solid fa-star', 'fa-solid fa-gem', 'fa-solid fa-briefcase-medical', 'fa-solid fa-notes-medical',
        'fa-solid fa-hand-holding-medical', 'fa-solid fa-stethoscope', 'fa-solid fa-band-aid',
        'fa-solid fa-shield-heart', 'fa-solid fa-microscope', 'fa-solid fa-vial',
        'fa-solid fa-mortar-pestle', 'fa-solid fa-kit-medical',
    ];

    function showToast(msg, success) {
        const toast = document.getElementById('serviceToast');
        const msgEl = document.getElementById('serviceToastMsg');
        msgEl.textContent = msg;
        toast.classList.remove('d-none', 'vd-toast-success', 'vd-toast-error');
        toast.classList.add(success ? 'vd-toast-success' : 'vd-toast-error');
        setTimeout(() => toast.classList.add('d-none'), 3000);
    }

    function refreshPage() {
        if (typeof loadpage === 'function') loadpage('services-content');
    }

    // ---------------------------------------------------------------
    // VIEW TOGGLE
    // ---------------------------------------------------------------
    const toggleBtns     = document.querySelectorAll('.vd-toggle-btn');
    const categoriesView = document.getElementById('categoriesView');
    const servicesView   = document.getElementById('servicesView');

    toggleBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            toggleBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const view = this.dataset.view;
            categoriesView.classList.toggle('d-none', view !== 'categories');
            servicesView.classList.toggle('d-none', view !== 'services');
        });
    });

    // ---------------------------------------------------------------
    // SERVICE MODAL
    // ---------------------------------------------------------------
    const serviceModalEl = document.getElementById('serviceModal');
    const serviceModal   = new bootstrap.Modal(serviceModalEl);
    const formStep       = document.getElementById('serviceModalFormStep');
    const confirmStep    = document.getElementById('serviceModalConfirmStep');
    const iconPicker     = document.getElementById('iconPicker');
    const nameInput      = document.getElementById('serviceModalName');
    const descInput      = document.getElementById('serviceModalDescription');
    const orderInput     = document.getElementById('serviceModalOrder');
    const activeInput    = document.getElementById('serviceModalActive');
    const idInput        = document.getElementById('serviceModalId');
    const errorBox       = document.getElementById('serviceModalError');
    const categoriesWrap = document.getElementById('serviceModalCategories');

    let selectedIcon = '';

    // Build the icon picker grid once
    iconPicker.innerHTML = ICONS.map(icon =>
        `<span class="vd-icon-swatch" data-icon="${icon}" title="${icon}"><i class="${icon}"></i></span>`
    ).join('');

    iconPicker.addEventListener('click', function (e) {
        const swatch = e.target.closest('.vd-icon-swatch');
        if (!swatch) return;
        iconPicker.querySelectorAll('.vd-icon-swatch').forEach(s => s.classList.remove('vd-icon-swatch-selected'));
        swatch.classList.add('vd-icon-swatch-selected');
        selectedIcon = swatch.dataset.icon;
    });

    categoriesWrap.addEventListener('click', function (e) {
        const chip = e.target.closest('.vd-chip');
        if (!chip) return;

        const wasSelected = chip.classList.contains('vd-chip-selected');
        categoriesWrap.querySelectorAll('.vd-chip').forEach(c => c.classList.remove('vd-chip-selected'));
        if (!wasSelected) chip.classList.add('vd-chip-selected');
    });

    function resetServiceForm() {
        idInput.value = '';
        nameInput.value = '';
        descInput.value = '';
        orderInput.value = '0';
        activeInput.checked = true;
        selectedIcon = '';
        errorBox.classList.add('d-none');
        iconPicker.querySelectorAll('.vd-icon-swatch').forEach(s => s.classList.remove('vd-icon-swatch-selected'));
        categoriesWrap.querySelectorAll('.vd-chip').forEach(c => c.classList.remove('vd-chip-selected'));
        formStep.classList.remove('d-none');
        confirmStep.classList.add('d-none');
    }

    function openServiceModal(data) {
        resetServiceForm();
        document.getElementById('serviceModalTitle').textContent = data ? 'Edit Service' : 'Add New Service';

        if (data) {
            idInput.value = data.serviceId;
            nameInput.value = data.name;
            descInput.value = data.description;
            orderInput.value = data.order;
            activeInput.checked = data.active === '1';
            selectedIcon = data.icon;

            const swatch = iconPicker.querySelector(`[data-icon="${data.icon}"]`);
            if (swatch) swatch.classList.add('vd-icon-swatch-selected');

            const assignedIds = (data.categoryIds || '').split(',').filter(Boolean);
            assignedIds.forEach(cid => {
                const chip = categoriesWrap.querySelector(`[data-category-id="${cid}"]`);
                if (chip) chip.classList.add('vd-chip-selected');
            });
        }

        serviceModal.show();
    }

    document.getElementById('addServiceBtn').addEventListener('click', () => openServiceModal(null));

    document.querySelectorAll('.vd-edit-service-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const card = this.closest('.vd-service-card');
            openServiceModal({
                serviceId: card.dataset.serviceId,
                name: card.dataset.name,
                description: card.dataset.description,
                icon: card.dataset.icon,
                order: card.dataset.order,
                active: card.dataset.active,
                categoryIds: card.dataset.categoryIds,
            });
        });
    });

    document.querySelectorAll('.vd-delete-service-btn').forEach(btn => {
        btn.addEventListener('click', async function () {
            const id = this.dataset.id;
            if (!confirm('Delete this service? This cannot be undone.')) return;
            this.disabled = true;
            try {
                const response = await fetch(`${CONTROLLER}?action=deleteService&id=${id}`);
                const result = await response.json();
                if (result.success) { showToast(result.message, true); refreshPage(); }
                else { showToast(result.message || 'Failed to delete service.', false); this.disabled = false; }
            } catch (err) {
                showToast('Network error. Please try again.', false);
                console.error(err);
                this.disabled = false;
            }
        });
    });

    document.getElementById('serviceModalReviewBtn').addEventListener('click', function () {
        const name = nameInput.value.trim();

        if (!name) { errorBox.textContent = 'Service name is required.'; errorBox.classList.remove('d-none'); return; }
        if (!selectedIcon) { errorBox.textContent = 'Please choose an icon.'; errorBox.classList.remove('d-none'); return; }
        errorBox.classList.add('d-none');

        const selectedCats = Array.from(categoriesWrap.querySelectorAll('.vd-chip-selected')).map(c => c.textContent.trim());
        const isActive = activeInput.checked;

        document.getElementById('serviceReceiptBody').innerHTML = `
            <div class="vd-receipt-row"><span class="vd-receipt-row-label">Icon</span><span class="vd-receipt-icon"><i class="${selectedIcon}"></i></span></div>
            <div class="vd-receipt-row"><span class="vd-receipt-row-label">Name</span><span>${name}</span></div>
            <div class="vd-receipt-row"><span class="vd-receipt-row-label">Description</span><span>${descInput.value.trim() || '—'}</span></div>
            <div class="vd-receipt-row"><span class="vd-receipt-row-label">Categories</span><span>${selectedCats.length ? selectedCats.join(', ') : '—'}</span></div>
            <div class="vd-receipt-row"><span class="vd-receipt-row-label">Status</span><span>${isActive ? 'Active' : 'Inactive'}</span></div>
        `;

        formStep.classList.add('d-none');
        confirmStep.classList.remove('d-none');
    });

    document.getElementById('serviceModalBackBtn').addEventListener('click', function () {
        confirmStep.classList.add('d-none');
        formStep.classList.remove('d-none');
    });

    document.getElementById('serviceModalConfirmBtn').addEventListener('click', async function () {
        const id = idInput.value;
        const selectedCategoryChip = categoriesWrap.querySelector('.vd-chip-selected');
        const selectedCategoryId = selectedCategoryChip ? selectedCategoryChip.dataset.categoryId : '0';

        const formData = new FormData();
        formData.append('action', id ? 'updateService' : 'addService');
        if (id) formData.append('service_id', id);
        formData.append('name', nameInput.value.trim());
        formData.append('description', descInput.value.trim());
        formData.append('icon', selectedIcon);
        formData.append('order', orderInput.value);
        if (activeInput.checked) formData.append('is_active', '1');
        formData.append('category_id', selectedCategoryId);

        this.disabled = true;
        this.textContent = 'Saving…';

        try {
            const response = await fetch(CONTROLLER, { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                serviceModal.hide();
                showToast(result.message, true);
                refreshPage();
            } else {
                showToast(result.message || 'Failed to save service.', false);
                this.disabled = false;
                this.textContent = 'Confirm & Save';
            }
        } catch (err) {
            showToast('Network error. Please try again.', false);
            console.error(err);
            this.disabled = false;
            this.textContent = 'Confirm & Save';
        }
    });

    // ---------------------------------------------------------------
    // CATEGORY MODAL
    // ---------------------------------------------------------------
    const categoryModalEl = document.getElementById('categoryModal');
    const categoryModal   = new bootstrap.Modal(categoryModalEl);
    const catFormStep     = document.getElementById('categoryModalFormStep');
    const catConfirmStep  = document.getElementById('categoryModalConfirmStep');
    const catIdInput      = document.getElementById('categoryModalId');
    const catNameInput    = document.getElementById('categoryModalName');
    const catDescInput    = document.getElementById('categoryModalDescription');
    const catOrderInput   = document.getElementById('categoryModalOrder');
    const catErrorBox     = document.getElementById('categoryModalError');

    function resetCategoryForm() {
        catIdInput.value = '';
        catNameInput.value = '';
        catDescInput.value = '';
        catOrderInput.value = '0';
        catErrorBox.classList.add('d-none');
        catFormStep.classList.remove('d-none');
        catConfirmStep.classList.add('d-none');
    }

    function openCategoryModal(data) {
        resetCategoryForm();
        document.getElementById('categoryModalTitle').textContent = data ? 'Edit Category' : 'Add Category';
        if (data) {
            catIdInput.value = data.id;
            catNameInput.value = data.name;
            catDescInput.value = data.description;
            catOrderInput.value = data.order;
        }
        categoryModal.show();
    }

    document.getElementById('addCategoryBtn').addEventListener('click', () => openCategoryModal(null));

    document.querySelectorAll('.vd-edit-category-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const row = this.closest('.vd-category-list-row');
            openCategoryModal({
                id: row.dataset.id,
                name: row.dataset.name,
                description: row.dataset.description,
                order: row.dataset.order,
            });
        });
    });

    document.querySelectorAll('.vd-delete-category-btn').forEach(btn => {
        btn.addEventListener('click', async function () {
            const id = this.dataset.id;
            if (!confirm('Delete this category? Services assigned to it will keep their other categories.')) return;
            this.disabled = true;
            try {
                const response = await fetch(`${CONTROLLER}?action=deleteCategory&id=${id}`);
                const result = await response.json();
                if (result.success) { showToast(result.message, true); refreshPage(); }
                else { showToast(result.message || 'Failed to delete category.', false); this.disabled = false; }
            } catch (err) {
                showToast('Network error. Please try again.', false);
                console.error(err);
                this.disabled = false;
            }
        });
    });

    document.getElementById('categoryModalReviewBtn').addEventListener('click', function () {
        const name = catNameInput.value.trim();
        if (!name) { catErrorBox.textContent = 'Category name is required.'; catErrorBox.classList.remove('d-none'); return; }
        catErrorBox.classList.add('d-none');

        document.getElementById('categoryReceiptBody').innerHTML = `
            <div class="vd-receipt-row"><span class="vd-receipt-row-label">Name</span><span>${name}</span></div>
            <div class="vd-receipt-row"><span class="vd-receipt-row-label">Description</span><span>${catDescInput.value.trim() || '—'}</span></div>
            <div class="vd-receipt-row"><span class="vd-receipt-row-label">Display Order</span><span>${catOrderInput.value}</span></div>
        `;

        catFormStep.classList.add('d-none');
        catConfirmStep.classList.remove('d-none');
    });

    document.getElementById('categoryModalBackBtn').addEventListener('click', function () {
        catConfirmStep.classList.add('d-none');
        catFormStep.classList.remove('d-none');
    });

    document.getElementById('categoryModalConfirmBtn').addEventListener('click', async function () {
        const id = catIdInput.value;
        const formData = new FormData();
        formData.append('action', id ? 'updateCategory' : 'addCategory');
        if (id) formData.append('category_id', id);
        formData.append('name', catNameInput.value.trim());
        formData.append('description', catDescInput.value.trim());
        formData.append('order', catOrderInput.value);

        this.disabled = true;
        this.textContent = 'Saving…';

        try {
            const response = await fetch(CONTROLLER, { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                categoryModal.hide();
                showToast(result.message, true);
                refreshPage();
            } else {
                showToast(result.message || 'Failed to save category.', false);
                this.disabled = false;
                this.textContent = 'Confirm & Save';
            }
        } catch (err) {
            showToast('Network error. Please try again.', false);
            console.error(err);
            this.disabled = false;
            this.textContent = 'Confirm & Save';
        }
    });

    // ---------------------------------------------------------------
    // SEARCH / FILTER (unchanged from before)
    // ---------------------------------------------------------------
    const searchInput    = document.getElementById('serviceSearch');
    const categoryFilter = document.getElementById('categoryFilter');
    const statusFilter   = document.getElementById('statusFilter');
    const countEl        = document.getElementById('serviceCount');
    const allCards       = () => Array.from(document.querySelectorAll('.vd-service-card'));

    function applyFilters() {
        const q      = searchInput.value.trim().toLowerCase();
        const cat    = categoryFilter.value;
        const status = statusFilter.value;

        let visible = 0;
        const total = allCards().length;

        allCards().forEach(card => {
            const name   = card.dataset.name.toLowerCase();
            const cats   = (card.dataset.categoryIds || '').split(',').filter(Boolean);
            const active = card.dataset.active;

            const show = (!q || name.includes(q)) && (!cat || cats.includes(cat)) && (!status || active === status);
            card.classList.toggle('d-none', !show);
            if (show) visible++;
        });

        document.querySelectorAll('.vd-service-category-group').forEach(group => {
            const hasVisible = group.querySelectorAll('.vd-service-card:not(.d-none)').length > 0;
            group.classList.toggle('d-none', !hasVisible);
        });

        countEl.textContent = `${visible} of ${total} services shown`;
    }

    [searchInput, categoryFilter, statusFilter].forEach(el => el.addEventListener('input', applyFilters));
    applyFilters();
})();
</script>