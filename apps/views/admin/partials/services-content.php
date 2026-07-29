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

$categoryServices = [];

foreach ($categories as $category) {
    $categoryServices[$category['category_name']] = [];
}

foreach ($services as $service) {
    if (!empty($service['category'])) {
        $categoryServices[$service['category']][] = $service;
    }
}

$servicesById = array_column($services, null, 'service_id');

// Curated icon set for the picker — the icons already in use across your
// 13 services, plus a handful of extra dental/medical options to grow into.
$iconOptions = [
    'fa-solid fa-tooth', 'fa-solid fa-teeth', 'fa-solid fa-teeth-open',
    'fa-solid fa-broom', 'fa-solid fa-syringe', 'fa-solid fa-x-ray',
    'fa-solid fa-crown', 'fa-solid fa-link', 'fa-solid fa-star', 'fa-solid fa-gem',
    'fa-solid fa-mask-face', 'fa-solid fa-kit-medical', 'fa-solid fa-notes-medical',
    'fa-solid fa-hand-holding-medical', 'fa-solid fa-microscope',
    'fa-solid fa-vial', 'fa-solid fa-briefcase-medical',
];
?>

<div class="d-flex flex-column gap-4">

    <!-- VIEW TOGGLE -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div class="vd-view-toggle">
            <button type="button" class="vd-view-toggle-btn active" data-view="categories">Categories</button>
            <button type="button" class="vd-view-toggle-btn" data-view="services">Services</button>
        </div>
        <div>
            <button type="button" class="btn vd-btn-outline btn-sm" id="addCategoryBtn" data-view-btn="categories">
                <i class="fa-solid fa-plus"></i> Add Category
            </button>
            <button type="button" class="btn vd-btn-gold btn-sm d-none" id="addServiceBtn" data-view-btn="services">
                <i class="fa-solid fa-plus"></i> Add New Service
            </button>
        </div>
    </div>

    <!-- CATEGORIES VIEW -->
    <div id="categoriesView" class="vd-services-view d-flex flex-column gap-3">
        <?php if (empty($categories)): ?>
            <div class="vd-empty-state">No categories yet.</div>
        <?php endif; ?>
        <?php foreach ($categories as $cat): ?>
        <div class="vd-category-card" data-category-id="<?= $cat['category_id'] ?>">
            <div class="vd-category-card-main">
                <div class="vd-category-card-name"><?= htmlspecialchars($cat['category_name']) ?></div>
                <div class="vd-category-card-desc"><?= htmlspecialchars($cat['category_description']) ?></div>
            </div>
            <div class="vd-category-card-actions">
                <span class="vd-order-badge">#<?= (int)$cat['display_order'] ?></span>
                <button class="btn vd-btn-outline btn-sm vd-edit-category-btn" data-id="<?= $cat['category_id'] ?>">Edit</button>
                <button class="btn vd-btn-outline btn-sm vd-delete-category-btn" data-id="<?= $cat['category_id'] ?>"><i class="fa-solid fa-trash"></i></button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- SERVICES VIEW -->
    <div id="servicesView" class="vd-services-view d-none flex-column gap-4">

        <div class="vd-service-filter-bar">
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

        <?php if (empty($services)): ?>
            <div class="vd-empty-state">No services found.</div>
        <?php endif; ?>

        <?php foreach ($categories as $cat):
            $serviceIdsInCat = array_column($categoryServices[$cat['category_name']], 'service_id');
            if (empty($serviceIdsInCat)) continue;
        ?>
        <div class="vd-service-category-group">
            <div class="vd-service-category-group-title"><?= htmlspecialchars($cat['category_name']) ?></div>
            <div class="vd-service-mini-grid">
                <?php foreach ($serviceIdsInCat as $sid):
                    $service = $services[array_search($sid, array_column($services, 'service_id'))] ?? null;
                    if (!$service) continue;
                    $isActive = (int)$service['is_active'] === 1;
                ?>
                <div class="vd-service-card-mini" data-service-id="<?= $sid ?>"
                     data-category-ids="<?= htmlspecialchars(implode(',', $serviceCategoryIds[$sid] ?? [])) ?>"
                     data-active="<?= $isActive ? '1' : '0' ?>">
                    <div class="vd-service-mini-icon"><i class="<?= htmlspecialchars($service['service_icon']) ?>"></i></div>
                    <div class="vd-service-mini-info">
                        <div class="vd-service-mini-name"><?= htmlspecialchars($service['service_name']) ?></div>
                        <div class="vd-service-mini-desc"><?= htmlspecialchars($service['service_description']) ?></div>
                    </div>
                    <div class="vd-service-mini-actions">
                        <span class="vd-service-status-badge <?= $isActive ? 'vd-status-active' : 'vd-status-inactive' ?>">
                            <?= $isActive ? 'Active' : 'Inactive' ?>
                        </span>
                        <button class="btn vd-btn-outline btn-sm vd-edit-service-btn" data-id="<?= $sid ?>">Edit</button>
                        <button class="btn vd-btn-outline btn-sm vd-delete-service-btn" data-id="<?= $sid ?>"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <?php
        $uncategorized = array_filter($services, fn($s) => empty($serviceCategoryIds[$s['service_id']]));
        if (!empty($uncategorized)):
        ?>
        <div class="vd-service-category-group">
            <div class="vd-service-category-group-title">Uncategorized</div>
            <div class="vd-service-mini-grid">
                <?php foreach ($uncategorized as $service):
                    $sid = $service['service_id'];
                    $isActive = (int)$service['is_active'] === 1;
                ?>
                <div class="vd-service-card-mini" data-service-id="<?= $sid ?>" data-category-ids="" data-active="<?= $isActive ? '1' : '0' ?>">
                    <div class="vd-service-mini-icon"><i class="<?= htmlspecialchars($service['service_icon']) ?>"></i></div>
                    <div class="vd-service-mini-info">
                        <div class="vd-service-mini-name"><?= htmlspecialchars($service['service_name']) ?></div>
                        <div class="vd-service-mini-desc"><?= htmlspecialchars($service['service_description']) ?></div>
                    </div>
                    <div class="vd-service-mini-actions">
                        <span class="vd-service-status-badge <?= $isActive ? 'vd-status-active' : 'vd-status-inactive' ?>">
                            <?= $isActive ? 'Active' : 'Inactive' ?>
                        </span>
                        <button class="btn vd-btn-outline btn-sm vd-edit-service-btn" data-id="<?= $sid ?>">Edit</button>
                        <button class="btn vd-btn-outline btn-sm vd-delete-service-btn" data-id="<?= $sid ?>"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>

</div>

<!-- CATEGORY FORM MODAL -->
<div class="modal fade" id="categoryFormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content vd-modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title vd-modal-title" id="categoryFormTitle">Add Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body d-flex flex-column gap-3">
                <input type="hidden" id="catFormId" value="">
                <div>
                    <label class="vd-label form-label">Category Name</label>
                    <input type="text" class="form-control vd-input" id="catFormName">
                </div>
                <div>
                    <label class="vd-label form-label">Description</label>
                    <textarea class="form-control vd-input" id="catFormDescription" rows="2"></textarea>
                </div>
                <div>
                    <label class="vd-label form-label">Display Order</label>
                    <input type="number" class="form-control vd-input" id="catFormOrder" value="0" min="0">
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn vd-btn-gold" id="catReviewBtn">Review &amp; Save</button>
            </div>
        </div>
    </div>
</div>

<!-- SERVICE FORM MODAL -->
<div class="modal fade" id="serviceFormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content vd-modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title vd-modal-title" id="serviceFormTitle">Add New Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="svcFormId" value="">
                <div class="mb-3">
                    <label class="vd-label form-label">Choose an Icon</label>
                    <div class="vd-icon-picker" id="iconPicker">
                        <?php foreach ($iconOptions as $icon): ?>
                        <button type="button" class="vd-icon-swatch" data-icon="<?= htmlspecialchars($icon) ?>" title="<?= htmlspecialchars($icon) ?>">
                            <i class="<?= htmlspecialchars($icon) ?>"></i>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="vd-label form-label">Service Name</label>
                        <input type="text" class="form-control vd-input" id="svcFormName">
                    </div>
                    <div class="col-md-4">
                        <label class="vd-label form-label">Display Order</label>
                        <input type="number" class="form-control vd-input" id="svcFormOrder" value="0" min="0">
                    </div>
                    <div class="col-12">
                        <label class="vd-label form-label">Description</label>
                        <textarea class="form-control vd-input" id="svcFormDescription" rows="2"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="vd-label form-label">Categories</label>
                        <div class="vd-chip-group" id="svcFormCategories">
                            <?php foreach ($categories as $cat): ?>
                            <span class="vd-chip" data-category-id="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="d-flex align-items-center gap-2 vd-label mb-0">
                            <input type="checkbox" id="svcFormActive" checked>
                            Active
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn vd-btn-gold" id="svcReviewBtn">Review &amp; Save</button>
            </div>
        </div>
    </div>
</div>

<!-- CONFIRMATION RECEIPT MODAL -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content vd-modal-content">
            <div class="modal-header border-0 pb-0 justify-content-center">
                <h5 class="modal-title vd-modal-title" id="confirmModalTitle">Confirm Details</h5>
            </div>
            <div class="modal-body">
                <div id="confirmBody" class="d-flex flex-column"></div>
            </div>
            <div class="modal-footer border-0 justify-content-between">
                <button type="button" class="btn vd-btn-outline" id="confirmEditBtn">&larr; Edit</button>
                <button type="button" class="btn vd-btn-gold" id="confirmSaveBtn">Confirm &amp; Save</button>
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
    const CATEGORIES = <?= json_encode(array_map(fn($c) => [
        'id' => $c['category_id'], 'name' => $c['category_name'],
        'description' => $c['category_description'], 'order' => (int)$c['display_order'],
    ], $categories)) ?>;
    const SERVICES = <?= json_encode(array_map(fn($s) => [
        'id' => $s['service_id'], 'name' => $s['service_name'], 'description' => $s['service_description'],
        'icon' => $s['service_icon'], 'active' => (int)$s['is_active'] === 1,
        'order' => (int)$s['display_order'], 'categoryIds' => $serviceCategoryIds[$s['service_id']] ?? [],
    ], $services)) ?>;

    const categoryFormModal = new bootstrap.Modal(document.getElementById('categoryFormModal'));
    const serviceFormModal  = new bootstrap.Modal(document.getElementById('serviceFormModal'));
    const confirmModal      = new bootstrap.Modal(document.getElementById('confirmModal'));

    let pending = null; // { type: 'category'|'service', payload: {...}, summary: [...] }

    function showToast(msg, success) {
        const toast = document.getElementById('serviceToast');
        const msgEl = document.getElementById('serviceToastMsg');
        msgEl.textContent = msg;
        toast.classList.remove('d-none', 'vd-toast-success', 'vd-toast-error');
        toast.classList.add(success ? 'vd-toast-success' : 'vd-toast-error');
        setTimeout(() => toast.classList.add('d-none'), 3000);
    }

    function refreshPage() {
        if (typeof loadpage === 'function') loadpage('services-content.php');
    }

    // ---------------------------------------------------------------
    // View toggle
    // ---------------------------------------------------------------
    document.querySelectorAll('.vd-view-toggle-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const view = this.dataset.view;
            document.querySelectorAll('.vd-view-toggle-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            document.getElementById('categoriesView').classList.toggle('d-none', view !== 'categories');
            document.getElementById('servicesView').classList.toggle('d-none', view !== 'services');
            document.getElementById('servicesView').classList.toggle('d-flex', view === 'services');

            document.querySelectorAll('[data-view-btn]').forEach(b => {
                b.classList.toggle('d-none', b.dataset.viewBtn !== view);
            });

            if (view === 'services') applyFilters();
        });
    });

    // ---------------------------------------------------------------
    // Category modal
    // ---------------------------------------------------------------
    function openCategoryModal(category) {
        document.getElementById('categoryFormTitle').textContent = category ? 'Edit Category' : 'Add Category';
        document.getElementById('catFormId').value = category ? category.id : '';
        document.getElementById('catFormName').value = category ? category.name : '';
        document.getElementById('catFormDescription').value = category ? category.description : '';
        document.getElementById('catFormOrder').value = category ? category.order : 0;
        categoryFormModal.show();
    }

    document.getElementById('addCategoryBtn').addEventListener('click', () => openCategoryModal(null));

    document.querySelectorAll('.vd-edit-category-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const cat = CATEGORIES.find(c => String(c.id) === this.dataset.id);
            if (cat) openCategoryModal(cat);
        });
    });

    document.querySelectorAll('.vd-delete-category-btn').forEach(btn => {
        btn.addEventListener('click', async function () {
            if (!confirm('Delete this category? Services in it will become uncategorized.')) return;
            this.disabled = true;
            try {
                const response = await fetch(`${CONTROLLER}?action=deleteCategory&id=${this.dataset.id}`);
                const result = await response.json();
                if (result.success) { showToast(result.message, true); refreshPage(); }
                else { showToast(result.message || 'Failed to delete category.', false); this.disabled = false; }
            } catch (err) {
                showToast('Network error. Please try again.', false);
                this.disabled = false;
            }
        });
    });

    document.getElementById('catReviewBtn').addEventListener('click', function () {
        const id          = document.getElementById('catFormId').value;
        const name        = document.getElementById('catFormName').value.trim();
        const description = document.getElementById('catFormDescription').value.trim();
        const order       = document.getElementById('catFormOrder').value;

        if (!name) { showToast('Category name is required.', false); return; }

        pending = {
            type: 'category',
            payload: { id, name, description, order },
            summary: [
                ['Name', name],
                ['Description', description || '—'],
                ['Display Order', order],
            ],
        };

        categoryFormModal.hide();
        renderConfirm('Confirm Category Details');
    });

    // ---------------------------------------------------------------
    // Service modal
    // ---------------------------------------------------------------
    function selectIcon(iconClass) {
        document.querySelectorAll('.vd-icon-swatch').forEach(sw => {
            sw.classList.toggle('vd-icon-swatch-selected', sw.dataset.icon === iconClass);
        });
    }

    function getSelectedIcon() {
        const el = document.querySelector('.vd-icon-swatch-selected');
        return el ? el.dataset.icon : '';
    }

    document.querySelectorAll('.vd-icon-swatch').forEach(sw => {
        sw.addEventListener('click', () => selectIcon(sw.dataset.icon));
    });

    document.querySelectorAll('#svcFormCategories .vd-chip').forEach(chip => {
        chip.addEventListener('click', () => chip.classList.toggle('vd-chip-selected'));
    });

    function getSelectedCategoryChips() {
        return Array.from(document.querySelectorAll('#svcFormCategories .vd-chip-selected'));
    }

    function openServiceModal(service) {
        document.getElementById('serviceFormTitle').textContent = service ? 'Edit Service' : 'Add New Service';
        document.getElementById('svcFormId').value = service ? service.id : '';
        document.getElementById('svcFormName').value = service ? service.name : '';
        document.getElementById('svcFormDescription').value = service ? service.description : '';
        document.getElementById('svcFormOrder').value = service ? service.order : 0;
        document.getElementById('svcFormActive').checked = service ? service.active : true;

        selectIcon(service ? service.icon : '');

        document.querySelectorAll('#svcFormCategories .vd-chip').forEach(chip => {
            const catId = parseInt(chip.dataset.categoryId, 10);
            const checked = service ? service.categoryIds.includes(catId) : false;
            chip.classList.toggle('vd-chip-selected', checked);
        });

        serviceFormModal.show();
    }

    document.getElementById('addServiceBtn').addEventListener('click', () => openServiceModal(null));

    document.querySelectorAll('.vd-edit-service-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const svc = SERVICES.find(s => String(s.id) === this.dataset.id);
            if (svc) openServiceModal(svc);
        });
    });

    document.querySelectorAll('.vd-delete-service-btn').forEach(btn => {
        btn.addEventListener('click', async function () {
            if (!confirm('Delete this service? This cannot be undone.')) return;
            this.disabled = true;
            try {
                const response = await fetch(`${CONTROLLER}?action=deleteService&id=${this.dataset.id}`);
                const result = await response.json();
                if (result.success) { showToast(result.message, true); refreshPage(); }
                else { showToast(result.message || 'Failed to delete service.', false); this.disabled = false; }
            } catch (err) {
                showToast('Network error. Please try again.', false);
                this.disabled = false;
            }
        });
    });

    document.getElementById('svcReviewBtn').addEventListener('click', function () {
        const id          = document.getElementById('svcFormId').value;
        const name        = document.getElementById('svcFormName').value.trim();
        const description = document.getElementById('svcFormDescription').value.trim();
        const order       = document.getElementById('svcFormOrder').value;
        const active      = document.getElementById('svcFormActive').checked;
        const icon        = getSelectedIcon();
        const catChips    = getSelectedCategoryChips();
        const categoryIds = catChips.map(c => c.dataset.categoryId);
        const categoryNames = catChips.map(c => c.textContent.trim()).join(', ') || '—';

        if (!name)  { showToast('Service name is required.', false); return; }
        if (!icon)  { showToast('Please choose an icon.', false); return; }

        pending = {
            type: 'service',
            payload: { id, name, description, order, active, icon, categoryIds },
            summary: [
                ['Icon', icon],
                ['Name', name],
                ['Description', description || '—'],
                ['Categories', categoryNames],
                ['Display Order', order],
                ['Status', active ? 'Active' : 'Inactive'],
            ],
        };

        serviceFormModal.hide();
        renderConfirm('Confirm Service Details');
    });

    // ---------------------------------------------------------------
    // Confirmation receipt
    // ---------------------------------------------------------------
    function renderConfirm(title) {
        document.getElementById('confirmModalTitle').textContent = title;
        const body = document.getElementById('confirmBody');
        body.innerHTML = pending.summary.map(([label, value]) => {
            const isIconRow = label === 'Icon';
            const valueHtml = isIconRow
                ? `<span class="vd-confirm-icon-preview"><i class="${value}"></i></span>`
                : `<span>${value.replace(/</g, '&lt;')}</span>`;
            return `<div class="vd-confirm-row"><span class="vd-confirm-label">${label}</span>${valueHtml}</div>`;
        }).join('');
        confirmModal.show();
    }

    document.getElementById('confirmEditBtn').addEventListener('click', function () {
        confirmModal.hide();
        if (!pending) return;
        if (pending.type === 'category') categoryFormModal.show();
        else serviceFormModal.show();
    });

    document.getElementById('confirmSaveBtn').addEventListener('click', async function () {
        if (!pending) return;
        this.disabled = true;
        this.textContent = 'Saving…';

        const formData = new FormData();
        const p = pending.payload;

        if (pending.type === 'category') {
            formData.append('action', p.id ? 'updateCategory' : 'addCategory');
            if (p.id) formData.append('category_id', p.id);
            formData.append('name', p.name);
            formData.append('description', p.description);
            formData.append('order', p.order);
        } else {
            formData.append('action', p.id ? 'updateService' : 'addService');
            if (p.id) formData.append('service_id', p.id);
            formData.append('name', p.name);
            formData.append('description', p.description);
            formData.append('icon', p.icon);
            formData.append('order', p.order);
            if (p.active) formData.append('is_active', '1');
            p.categoryIds.forEach(cid => formData.append('category_ids[]', cid));
        }

        try {
            const response = await fetch(CONTROLLER, { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                confirmModal.hide();
                showToast(result.message, true);
                refreshPage();
            } else {
                showToast(result.message || 'Failed to save.', false);
                this.disabled = false;
                this.textContent = 'Confirm & Save';
            }
        } catch (err) {
            showToast('Network error. Please try again.', false);
            this.disabled = false;
            this.textContent = 'Confirm & Save';
        }
    });

    // ---------------------------------------------------------------
    // Search / filter (services view only)
    // ---------------------------------------------------------------
    const searchInput    = document.getElementById('serviceSearch');
    const categoryFilter = document.getElementById('categoryFilter');
    const statusFilter   = document.getElementById('statusFilter');
    const countEl        = document.getElementById('serviceCount');
    const allCards       = () => Array.from(document.querySelectorAll('.vd-service-card-mini'));

    function applyFilters() {
        const q      = searchInput.value.trim().toLowerCase();
        const cat    = categoryFilter.value;
        const status = statusFilter.value;

        let visible = 0;
        const total = allCards().length;

        allCards().forEach(card => {
            const name   = card.querySelector('.vd-service-mini-name').textContent.toLowerCase();
            const cats   = (card.dataset.categoryIds || '').split(',').filter(Boolean);
            const active = card.dataset.active;

            const show = (!q || name.includes(q)) && (!cat || cats.includes(cat)) && (!status || active === status);
            card.classList.toggle('d-none', !show);
            if (show) visible++;
        });

        document.querySelectorAll('#servicesView .vd-service-category-group').forEach(group => {
            const hasVisible = group.querySelectorAll('.vd-service-card-mini:not(.d-none)').length > 0;
            group.classList.toggle('d-none', !hasVisible);
        });

        countEl.textContent = `${visible} of ${total} services shown`;
    }

    [searchInput, categoryFilter, statusFilter].forEach(el => el.addEventListener('input', applyFilters));
    applyFilters();
})();
</script>