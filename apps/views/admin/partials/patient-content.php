<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Admin', 'Dental Assistant'])) {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}

require_once '../../../../config/conn.php';
require_once '../../../models/patientModel.php';

$db   = new Database();
$conn = $db->connect();
$patientModel = new Patient($conn);

$patients = $patientModel->getAllPatients();

// Build unique months from patient records for the dropdown
$months = [];
foreach ($patients as $p) {
    $key   = date('Y-m', strtotime($p['created_at']));
    $label = date('F Y', strtotime($p['created_at']));
    $months[$key] = $label;
}
krsort($months); // latest first
?>

<div class="d-flex flex-column gap-4">

	<div class="vd-dash-card">
		<div class="vd-dash-card-header">
		<span class="vd-dash-card-title">Patients</span>
		<span class="vd-topbar-date"><?= count($patients) ?> total</span>
		</div>

		<!-- Filter bar -->
		<div class="vd-filter-bar">
		<div class="vd-filter-group">
			<label class="vd-label form-label">Search</label>
			<div class="vd-search-wrap">
			<i class="ti ti-search vd-search-icon" aria-hidden="true"></i>
			<input type="text" id="searchInput" class="form-control vd-input vd-search-input"
				placeholder="Name or email…">
			</div>
		</div>
		<div class="vd-filter-group">
			<label class="vd-label form-label">Clinic</label>
			<select id="filterClinic" class="form-select vd-input vd-filter-select">
			<option value="">All Clinics</option>
			<option value="1">Alcala Branch</option>
			<option value="2">Tuguegarao Branch</option>
			</select>
		</div>
		<div class="vd-filter-group">
			<label class="vd-label form-label">Form Status</label>
			<select id="filterForm" class="form-select vd-input vd-filter-select">
			<option value="">All</option>
			<option value="complete">Complete</option>
			<option value="incomplete">Incomplete</option>
			</select>
		</div>
		<div class="vd-filter-group">
			<label class="vd-label form-label">Month Registered</label>
			<select id="filterMonth" class="form-select vd-input vd-filter-select">
			<option value="">All Months</option>
			<?php foreach ($months as $key => $label): ?>
				<option value="<?= $key ?>"><?= $label ?></option>
			<?php endforeach; ?>
			</select>
		</div>
		<div class="vd-filter-group vd-filter-clear">
			<button id="clearFilters" class="btn vd-btn-outline">Clear</button>
		</div>
		</div>

		<!-- Table -->
		<div class="vd-dash-card-body">
		<?php if (empty($patients)): ?>
			<div class="vd-empty-state">No patients found.</div>
		<?php else: ?>
			<div class="vd-appt-table-wrap">
			<table class="vd-appt-table w-100" id="patientsTable">
				<thead>
				<tr>
					<th>Patient</th>
					<th>Age</th>
					<th>Gender</th>
					<th>Phone</th>
					<th>Registered</th>
					<th>Form</th>
					<th>Action</th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ($patients as $p):
					$formComplete = !empty($p['birthdate']);
					$monthKey     = date('Y-m', strtotime($p['created_at']));
					$patientId    = $p['patient_id'] ?? $p['id'] ?? null;
					$profilePage  = $patientId ? '_patient-profie.php?id=' . $patientId : '#';
				?>
				<tr
					data-name="<?= strtolower($p['lastname'] . ' ' . $p['firstname']) ?>"
					data-email="<?= strtolower($p['email'] ?? '') ?>"
					data-clinic="<?= $p['clinic_id'] ?? '' ?>"
					data-form="<?= $formComplete ? 'complete' : 'incomplete' ?>"
					data-month="<?= $monthKey ?>">
					<td>
					<div class="vd-appt-name">
						<?= htmlspecialchars($p['lastname'] . ', ' . $p['firstname']) ?>
					</div>
					<div class="vd-appt-meta"><?= htmlspecialchars($p['email'] ?? '—') ?></div>
					</td>
					<td class="vd-appt-meta"><?= htmlspecialchars($p['age'] ?? '—') ?></td>
					<td class="vd-appt-meta"><?= htmlspecialchars($p['gender'] ?? '—') ?></td>
					<td class="vd-appt-meta"><?= htmlspecialchars($p['phone_number'] ?? '—') ?></td>
					<td class="vd-appt-meta"><?= date('M d, Y', strtotime($p['created_at'])) ?></td>
					<td>
					<?php if ($formComplete): ?>
						<span class="vd-status vd-status-confirmed">Complete</span>
					<?php else: ?>
						<span class="vd-status vd-status-pending">Incomplete</span>
					<?php endif; ?>
					</td>
					<td>
						<button class="btn btn-sm vd-btn-outline vd-view-profile-btn"
							data-id="<?= $p['patient_id'] ?>">
							View Profile
						</button>
					</td>
				</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<div id="noResults" class="vd-empty-state d-none">
				No patients match your search or filters.
			</div>
			</div>
		<?php endif; ?>
		</div>

	</div>

</div>

<script>
(function () {
	const searchInput  = document.getElementById('searchInput');
	const filterClinic = document.getElementById('filterClinic');
	const filterForm   = document.getElementById('filterForm');
	const filterMonth  = document.getElementById('filterMonth');
	const clearBtn     = document.getElementById('clearFilters');
	const rows         = document.querySelectorAll('#patientsTable tbody tr');
	const noResults    = document.getElementById('noResults');

	function filterTable() {
		const search = searchInput.value.toLowerCase().trim();
		const clinic = filterClinic.value;
		const form   = filterForm.value;
		const month  = filterMonth.value;
		let visible  = 0;

		rows.forEach(row => {
		const matchSearch = !search || row.dataset.name.includes(search) || row.dataset.email.includes(search);
		const matchClinic = !clinic || row.dataset.clinic === clinic;
		const matchForm   = !form   || row.dataset.form   === form;
		const matchMonth  = !month  || row.dataset.month  === month;

		if (matchSearch && matchClinic && matchForm && matchMonth) {
			row.style.display = '';
			visible++;
		} else {
			row.style.display = 'none';
		}
		});

		noResults.classList.toggle('d-none', visible > 0);
	}

	searchInput.addEventListener('input', filterTable);
	filterClinic.addEventListener('change', filterTable);
	filterForm.addEventListener('change', filterTable);
	filterMonth.addEventListener('change', filterTable);

	document.querySelectorAll('a[data-page]').forEach(link => {
		link.addEventListener('click', async (e) => {
			e.preventDefault();
			const page = link.getAttribute('data-page');
			if (page) await loadpage(page);
		});
	});

	clearBtn.addEventListener('click', () => {
		searchInput.value  = '';
		filterClinic.value = '';
		filterForm.value   = '';
		filterMonth.value  = '';
		filterTable();
	});

	document.querySelectorAll('.vd-view-profile-btn').forEach(btn => {
		btn.addEventListener('click', async function () {
			const patientId = this.dataset.id;
			const dashContent = document.querySelector('.vd-dash-content');

			try {
const response = await fetch(`partials/_patient-profie.php?id=${patientId}`);
			if (!response.ok) throw new Error('Failed to load profile');
			const html = await response.text();
			dashContent.innerHTML = html;

			// Re-execute scripts in the loaded partial
			dashContent.querySelectorAll('script').forEach(oldScript => {
				const newScript = document.createElement('script');
				newScript.textContent = oldScript.textContent;
				document.body.appendChild(newScript);
				oldScript.remove();
			});

			} catch (err) {
			dashContent.innerHTML = '<div class="vd-empty-state">Error loading patient profile.</div>';
			console.error(err);
			}
		});
});

})();

    async function loadpage(page) {
        const dashContent = document.querySelector('.vd-dash-content');
        try {
            const response = await fetch(`partials/${page}`);
            if (!response.ok) throw new Error('Network response was not ok');
            const html = await response.text();
            dashContent.innerHTML = html;

            dashContent.querySelectorAll('script').forEach(oldScript => {
            const newScript = document.createElement('script');
            newScript.textContent = oldScript.textContent;
            document.body.appendChild(newScript);
            oldScript.remove();
            });
        
            closeSidebar();
            } catch (error) {
            if (dashContent) {
                dashContent.innerHTML = `<div class="vd-empty-state">Error loading content.</div>`;
            }
            console.error('Error fetching page:', error);
            }
        }
</script>