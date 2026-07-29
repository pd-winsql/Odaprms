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

// Renders one inline-editable service card. Kept as a local function so both
// the category-grouped sections and the "Uncategorized" section can reuse it.
function renderServiceCard($service, $categories, $assignedCategoryIds) {
    $id       = $service['service_id'];
    $catCsv   = implode(',', $assignedCategoryIds);
    $isActive = (int)$service['is_active'] === 1;
    ?>
    <div class="vd-dash-card vd-service-card" data-service-id="<?= $id ?>" data-category-ids="<?= htmlspecialchars($catCsv) ?>" data-active="<?= $isActive ? '1' : '0' ?>">
        <div class="vd-dash-card-header vd-service-card-header">
            <span class="vd-dash-card-title vd-service-card-title"><?= htmlspecialchars($service['service_name']) ?></span>
            <span class="vd-service-status-badge <?= $isActive ? 'vd-status-active' : 'vd-status-inactive' ?>">
                <?= $isActive ? 'Active' : 'Inactive' ?>
            </span>
        </div>
        <div class="vd-dash-card-body">
            <div class="d-flex flex-column flex-md-row gap-4">

                <div class="d-flex flex-column align-items-center gap-2 vd-service-icon-col">
                    <div class="vd-service-icon-preview">
                        <i class="<?= htmlspecialchars($service['service_icon']) ?>"></i>
                    </div>
                    <input type="text" class="form-control form-control-sm vd-input vd-service-field" data-field="icon"
                           value="<?= htmlspecialchars($service['service_icon']) ?>" placeholder="fa-solid fa-tooth">
                </div>

                <div class="flex-grow-1 row g-3">
                    <div class="col-md-6">
                        <label class="vd-label form-label">Service Name</label>
                        <input type="text" class="form-control vd-input vd-service-field" data-field="name"
                               value="<?= htmlspecialchars($service['service_name']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="vd-label form-label">Display Order</label>
                        <input type="number" class="form-control vd-input vd-service-field" data-field="order"
                               value="<?= (int)$service['display_order'] ?>" min="0">
                    </div>
                    <div class="col-12">
                        <label class="vd-label form-label">Description</label>
                        <textarea class="form-control vd-input vd-service-field" data-field="description" rows="2"><?= htmlspecialchars($service['service_description']) ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="vd-label form-label">Categories</label>
                        <div class="vd-chip-group">
                            <?php foreach ($categories as $cat):
                                $checked = in_array($cat['category_id'], $assignedCategoryIds);
                            ?>
                            <span class="vd-chip <?= $checked ? 'vd-chip-selected' : '' ?>" data-category-id="<?= $cat['category_id'] ?>">
                                <?= htmlspecialchars($cat['category_name']) ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-12 d-flex justify-content-between align-items-center">
                        <label class="d-flex align-items-center gap-2 vd-label mb-0">
                            <input type="checkbox" class="vd-service-active-toggle" <?= $isActive ? 'checked' : '' ?>>
                            Active
                        </label>
                        <div class="d-flex gap-2">
                            <button class="btn vd-btn-outline btn-sm vd-delete-service-btn" data-id="<?= $id ?>">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                            <button class="btn vd-btn-gold btn-sm vd-save-service-btn" data-id="<?= $id ?>">
                                Save Changes
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <?php
}
?>

<div class="d-flex flex-column gap-4">

    <!-- CATEGORY MANAGEMENT -->
    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
            <span class="vd-dash-card-title">Manage Categories</span>
            <button class="btn vd-btn-outline btn-sm" id="addCategoryBtn">+ Add Category</button>
        </div>
        <div class="vd-dash-card-body">
            <div class="d-flex flex-column" id="categoryList">
                <?php foreach ($categories as $i => $cat): ?>
                <div class="row g-3 align-items-end vd-category-row <?= $i > 0 ? 'pt-3 mt-3' : '' ?>"
                     data-category-id="<?= $cat['category_id'] ?>"
                     <?= $i > 0 ? 'style="border-top:1px solid var(--border);"' : '' ?>>
                    <div class="col-md-3">
                        <label class="vd-label form-label">Category Name</label>
                        <input type="text" class="form-control vd-input vd-category-field" data-field="name"
                               value="<?= htmlspecialchars($cat['category_name']) ?>" placeholder="Category name">
                    </div>
                    <div class="col-md-6">
                        <label class="vd-label form-label">Description</label>
                        <input type="text" class="form-control vd-input vd-category-field" data-field="description"
                               value="<?= htmlspecialchars($cat['category_description']) ?>" placeholder="Short description">
                    </div>
                    <div class="col-md-1">
                        <label class="vd-label form-label">Order</label>
                        <input type="number" class="form-control vd-input vd-category-field" data-field="order"
                               value="<?= (int)$cat['display_order'] ?>" min="0" title="Display order">
                    </div>
                    <div class="col-md-2 d-flex justify-content-end">
                        <button class="btn vd-btn-gold btn-sm vd-save-category-btn" data-id="<?= $cat['category_id'] ?>">Save</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- SEARCH / FILTER BAR -->
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

    <div class="d-flex justify-content-end">
        <button class="btn vd-btn-gold btn-sm" id="addServiceBtn">+ Add New Service</button>
    </div>

    <!-- NEW SERVICE CARDS GET PREPENDED HERE -->
    <div id="newServiceSlot"></div>

    <?php if (empty($services)): ?>
        <div class="vd-empty-state">No services found.</div>
    <?php endif; ?>

    <?php foreach ($categories as $cat):
        $serviceIdsInCat = $categoryServiceIds[$cat['category_id']] ?? [];
        if (empty($serviceIdsInCat)) continue;
    ?>
    <div class="vd-service-category-group">
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
    <div class="vd-service-category-group">
        <div class="vd-service-category-group-title">Uncategorized</div>
        <div class="d-flex flex-column gap-3">
            <?php foreach ($uncategorized as $service): renderServiceCard($service, $categories, []); endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Toast -->
<div id="serviceToast" class="vd-toast d-none">
    <span id="serviceToastMsg"></span>
</div>

<script>
(function () {
    const CONTROLLER = '../../../apps/controllers/serviceController.php';

    function showToast(msg, success) {
        const toast = document.getElementById('serviceToast');
        const msgEl = document.getElementById('serviceToastMsg');
        msgEl.textContent = msg;
        toast.classList.remove('d-none', 'vd-toast-success', 'vd-toast-error');
        toast.classList.add(success ? 'vd-toast-success' : 'vd-toast-error');
        setTimeout(() => toast.classList.add('d-none'), 3000);
    }

    function refreshPage() {
        if (typeof loadpage === 'function') {
            loadpage('services-content');
        }
    }

    // ---- Chip toggling (category assignment on a service card) ----
    document.addEventListener('click', function (e) {
        const chip = e.target.closest('.vd-chip');
        if (chip) chip.classList.toggle('vd-chip-selected');
    });

    function getSelectedCategoryIds(card) {
        return Array.from(card.querySelectorAll('.vd-chip-selected')).map(c => c.dataset.categoryId);
    }

    // ---- Category management ----

    function attachCategoryRowHandlers(row) {
        const saveBtn = row.querySelector('.vd-save-category-btn');
        saveBtn.addEventListener('click', async function () {
            const id          = this.dataset.id;
            const name        = row.querySelector('[data-field="name"]').value.trim();
            const description = row.querySelector('[data-field="description"]').value.trim();
            const order       = row.querySelector('[data-field="order"]').value;

            if (!name) { showToast('Category name is required.', false); return; }

            const formData = new FormData();
            formData.append('action', id ? 'updateCategory' : 'addCategory');
            if (id) formData.append('category_id', id);
            formData.append('name', name);
            formData.append('description', description);
            formData.append('order', order);

            this.disabled = true;
            try {
                const response = await fetch(CONTROLLER, { method: 'POST', body: formData });
                const result   = await response.json();
                if (result.success) {
                    showToast(result.message, true);
                    if (!id) refreshPage();
                } else {
                    showToast(result.message || 'Failed to save category.', false);
                }
            } catch (err) {
                showToast('Network error. Please try again.', false);
                console.error(err);
            } finally {
                this.disabled = false;
            }
        });
    }

    document.querySelectorAll('.vd-category-row').forEach(attachCategoryRowHandlers);

    document.getElementById('addCategoryBtn').addEventListener('click', function () {
        const list = document.getElementById('categoryList');
        const row = document.createElement('div');
        row.className = 'vd-category-row';
        row.innerHTML = `
            <input type="text" class="form-control form-control-sm vd-input vd-category-field" data-field="name" placeholder="Category name">
            <input type="text" class="form-control form-control-sm vd-input vd-category-field" data-field="description" placeholder="Short description">
            <input type="number" class="form-control form-control-sm vd-input vd-category-field vd-category-order" data-field="order" value="0" min="0" title="Display order">
            <button class="btn vd-btn-gold btn-sm vd-save-category-btn" data-id="">Save</button>
        `;
        list.appendChild(row);
        attachCategoryRowHandlers(row);
    });

    // ---- Service card save / delete ----

    function attachServiceCardHandlers(card) {
        const saveBtn   = card.querySelector('.vd-save-service-btn');
        const deleteBtn = card.querySelector('.vd-delete-service-btn');

        saveBtn.addEventListener('click', async function () {
            const id          = this.dataset.id;
            const name        = card.querySelector('[data-field="name"]').value.trim();
            const description = card.querySelector('[data-field="description"]').value.trim();
            const icon        = card.querySelector('[data-field="icon"]').value.trim();
            const order       = card.querySelector('[data-field="order"]').value;
            const isActive    = card.querySelector('.vd-service-active-toggle').checked;
            const categoryIds = getSelectedCategoryIds(card);

            if (!name) { showToast('Service name is required.', false); return; }

            const formData = new FormData();
            formData.append('action', id ? 'updateService' : 'addService');
            if (id) formData.append('service_id', id);
            formData.append('name', name);
            formData.append('description', description);
            formData.append('icon', icon);
            formData.append('order', order);
            if (isActive) formData.append('is_active', '1');
            categoryIds.forEach(cid => formData.append('category_ids[]', cid));

            this.disabled = true;
            this.textContent = 'Saving…';

            try {
                const response = await fetch(CONTROLLER, { method: 'POST', body: formData });
                const result   = await response.json();
                if (result.success) {
                    showToast(result.message, true);
                    refreshPage();
                } else {
                    showToast(result.message || 'Failed to save service.', false);
                    this.disabled = false;
                    this.textContent = 'Save Changes';
                }
            } catch (err) {
                showToast('Network error. Please try again.', false);
                console.error(err);
                this.disabled = false;
                this.textContent = 'Save Changes';
            }
        });

        if (deleteBtn) {
            deleteBtn.addEventListener('click', async function () {
                const id = this.dataset.id;
                if (!confirm('Delete this service? This cannot be undone.')) return;

                this.disabled = true;
                try {
                    const response = await fetch(`${CONTROLLER}?action=deleteService&id=${id}`);
                    const result   = await response.json();
                    if (result.success) {
                        showToast(result.message, true);
                        refreshPage();
                    } else {
                        showToast(result.message || 'Failed to delete service.', false);
                        this.disabled = false;
                    }
                } catch (err) {
                    showToast('Network error. Please try again.', false);
                    console.error(err);
                    this.disabled = false;
                }
            });
        }

        // Collapse/expand on header click (ignore clicks on the status badge)
        const header = card.querySelector('.vd-service-card-header');
        header.addEventListener('click', function (e) {
            if (e.target.closest('.vd-service-status-badge')) return;
            card.classList.toggle('vd-service-collapsed');
        });
    }

    document.querySelectorAll('.vd-service-card').forEach(attachServiceCardHandlers);

    document.getElementById('addServiceBtn').addEventListener('click', function () {
        const slot = document.getElementById('newServiceSlot');
        const card = document.createElement('div');
        card.className = 'vd-dash-card vd-service-card mb-3';
        card.dataset.serviceId = '';
        card.dataset.categoryIds = '';
        card.dataset.active = '1';

        const chipsHtml = <?= json_encode(array_map(fn($c) => ['id' => $c['category_id'], 'name' => $c['category_name']], $categories)) ?>
            .map(c => `<span class="vd-chip" data-category-id="${c.id}">${c.name}</span>`).join('');

        card.innerHTML = `
            <div class="vd-dash-card-header vd-service-card-header">
                <span class="vd-dash-card-title vd-service-card-title">New Service</span>
                <span class="vd-service-status-badge vd-status-active">Active</span>
            </div>
            <div class="vd-dash-card-body">
                <div class="d-flex flex-column flex-md-row gap-4">
                    <div class="d-flex flex-column align-items-center gap-2 vd-service-icon-col">
                        <div class="vd-service-icon-preview"><i class="fa-solid fa-tooth"></i></div>
                        <input type="text" class="form-control form-control-sm vd-input vd-service-field" data-field="icon" placeholder="fa-solid fa-tooth">
                    </div>
                    <div class="flex-grow-1 row g-3">
                        <div class="col-md-6">
                            <label class="vd-label form-label">Service Name</label>
                            <input type="text" class="form-control vd-input vd-service-field" data-field="name">
                        </div>
                        <div class="col-md-6">
                            <label class="vd-label form-label">Display Order</label>
                            <input type="number" class="form-control vd-input vd-service-field" data-field="order" value="0" min="0">
                        </div>
                        <div class="col-12">
                            <label class="vd-label form-label">Description</label>
                            <textarea class="form-control vd-input vd-service-field" data-field="description" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="vd-label form-label">Categories</label>
                            <div class="vd-chip-group">${chipsHtml}</div>
                        </div>
                        <div class="col-12 d-flex justify-content-between align-items-center">
                            <label class="d-flex align-items-center gap-2 vd-label mb-0">
                                <input type="checkbox" class="vd-service-active-toggle" checked>
                                Active
                            </label>
                            <div class="d-flex gap-2">
                                <button class="btn vd-btn-gold btn-sm vd-save-service-btn" data-id="">Save Changes</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        slot.appendChild(card);
        attachServiceCardHandlers(card);
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    // ---- Search / filter (client-side, matches existing live-count pattern) ----

    const searchInput    = document.getElementById('serviceSearch');
    const categoryFilter = document.getElementById('categoryFilter');
    const statusFilter   = document.getElementById('statusFilter');
    const countEl        = document.getElementById('serviceCount');
    const allCards        = () => Array.from(document.querySelectorAll('.vd-service-card'));

    function applyFilters() {
        const q   = searchInput.value.trim().toLowerCase();
        const cat = categoryFilter.value;
        const status = statusFilter.value;

        let visible = 0;
        const total = allCards().length;

        allCards().forEach(card => {
            const name  = card.querySelector('.vd-service-card-title').textContent.toLowerCase();
            const cats  = (card.dataset.categoryIds || '').split(',').filter(Boolean);
            const active = card.dataset.active;

            const matchesSearch = !q || name.includes(q);
            const matchesCategory = !cat || cats.includes(cat);
            const matchesStatus = !status || active === status;

            const show = matchesSearch && matchesCategory && matchesStatus;
            card.classList.toggle('d-none', !show);
            if (show) visible++;
        });

        // Hide category group headers with no visible cards
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