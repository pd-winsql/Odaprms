<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo '<div class="vd-empty-state">Activity logs are available to administrators only.</div>';
    exit;
}
require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/auditLogModel.php';

$activityRows = (new AuditLog((new Database())->connect()))->getRecent();
$activityEntities = array_values(array_unique(array_filter(array_column($activityRows, 'entity_type'))));
$activityRoles = array_values(array_unique(array_filter(array_column($activityRows, 'performed_by_role'))));
sort($activityEntities);
sort($activityRoles);

function activityValueSummary(?string $json): string
{
    if (!$json) return '';
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) return $json;
    $parts = [];
    foreach ($decoded as $key => $value) {
        if (is_array($value)) $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_bool($value)) $value = $value ? 'Yes' : 'No';
        if ($value === null) $value = 'None';
        $parts[] = ucwords(str_replace('_', ' ', (string) $key)) . ': ' . (string) $value;
    }
    return implode(' · ', $parts);
}
?>

<div class="d-flex flex-column gap-4" id="activityLogsPage">
    <div>
        <div class="vd-welcome-greet">ACCOUNTABILITY</div>
        <div class="vd-welcome-name">Activity Logs</div>
        <p class="text-muted small mb-0 mt-2">Read-only history of recorded changes and clinic actions.</p>
    </div>
    <div class="vd-dash-card">
        <div class="vd-filter-bar">
            <div class="vd-filter-group flex-grow-1">
                <label class="vd-label form-label" for="activitySearch">Search</label>
                <input type="search" id="activitySearch" class="form-control vd-input" placeholder="Person, action, description, or record number">
            </div>
            <div class="vd-filter-group">
                <label class="vd-label form-label" for="activityEntity">Record type</label>
                <select id="activityEntity" class="form-select vd-input vd-filter-select"><option value="">All record types</option><?php foreach ($activityEntities as $entity): ?><option value="<?= htmlspecialchars(strtolower($entity)) ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $entity))) ?></option><?php endforeach; ?></select>
            </div>
            <div class="vd-filter-group">
                <label class="vd-label form-label" for="activityRole">Role</label>
                <select id="activityRole" class="form-select vd-input vd-filter-select"><option value="">All roles</option><?php foreach ($activityRoles as $role): ?><option value="<?= htmlspecialchars(strtolower($role)) ?>"><?= htmlspecialchars($role === 'Admin' ? 'Admin / Dentist' : $role) ?></option><?php endforeach; ?></select>
            </div>
            <div class="vd-filter-group"><label class="vd-label form-label" for="activityDate">Date</label><input type="date" id="activityDate" class="form-control vd-input vd-filter-select"></div>
            <div class="vd-filter-group vd-filter-clear"><button type="button" class="btn vd-btn-outline" id="clearActivityFilters">Clear</button></div>
        </div>
        <div class="vd-dash-card-body">
            <div class="vd-dash-card-header px-0 pt-0"><span class="vd-dash-card-title">Recorded actions</span><span class="vd-topbar-date" id="activityCount"><?= count($activityRows) ?> records</span></div>
            <?php if (!$activityRows): ?>
                <div class="vd-empty-state">No activity has been recorded yet.</div>
            <?php else: ?>
                <div class="vd-appt-table-wrap"><table class="vd-appt-table w-100" id="activityTable">
                    <thead><tr><th>Date and time</th><th>Performed by</th><th>Record</th><th>Action</th><th>Description</th><th>Changes</th></tr></thead>
                    <tbody><?php foreach ($activityRows as $row):
                        $searchText = strtolower(implode(' ', [$row['performed_by_name'], $row['performed_by_role'], $row['entity_type'], $row['entity_id'], $row['action'], $row['description']]));
                        $oldSummary = activityValueSummary($row['old_values']);
                        $newSummary = activityValueSummary($row['new_values']);
                    ?>
                        <tr data-search="<?= htmlspecialchars($searchText, ENT_QUOTES) ?>" data-entity="<?= htmlspecialchars(strtolower($row['entity_type'])) ?>" data-role="<?= htmlspecialchars(strtolower($row['performed_by_role'])) ?>" data-date="<?= htmlspecialchars(substr($row['performed_at'], 0, 10)) ?>">
                            <td><div class="vd-appt-name"><?= date('M d, Y', strtotime($row['performed_at'])) ?></div><div class="vd-appt-meta"><?= date('g:i A', strtotime($row['performed_at'])) ?></div></td>
                            <td><div class="vd-appt-name"><?= htmlspecialchars($row['performed_by_name']) ?></div><div class="vd-appt-meta"><?= htmlspecialchars($row['performed_by_role'] === 'Admin' ? 'Admin / Dentist' : $row['performed_by_role']) ?></div></td>
                            <td><div class="vd-appt-name"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $row['entity_type']))) ?></div><div class="vd-appt-meta"><?= $row['entity_id'] === null ? 'System-wide' : '#' . (int) $row['entity_id'] ?></div></td>
                            <td><span class="vd-status vd-status-confirmed"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $row['action']))) ?></span></td>
                            <td><?= htmlspecialchars($row['description']) ?></td>
                            <td><?php if ($oldSummary || $newSummary): ?><details><summary>View changes</summary><?php if ($oldSummary): ?><div class="vd-appt-meta mt-2"><strong>Before:</strong> <?= htmlspecialchars($oldSummary) ?></div><?php endif; ?><?php if ($newSummary): ?><div class="vd-appt-meta mt-1"><strong>After:</strong> <?= htmlspecialchars($newSummary) ?></div><?php endif; ?></details><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?></tbody>
                </table></div>
                <div class="vd-empty-state d-none" id="activityEmpty">No activity matches the selected filters.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function () {
    const table = document.getElementById('activityTable');
    if (!table) return;
    const search = document.getElementById('activitySearch');
    const entity = document.getElementById('activityEntity');
    const role = document.getElementById('activityRole');
    const date = document.getElementById('activityDate');
    const count = document.getElementById('activityCount');
    const empty = document.getElementById('activityEmpty');
    const apply = () => {
        const term = search.value.trim().toLowerCase();
        let visible = 0;
        table.querySelectorAll('tbody tr').forEach(row => {
            const show = (!term || row.dataset.search.includes(term)) && (!entity.value || row.dataset.entity === entity.value) && (!role.value || row.dataset.role === role.value) && (!date.value || row.dataset.date === date.value);
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        count.textContent = `${visible} record${visible === 1 ? '' : 's'}`;
        empty.classList.toggle('d-none', visible !== 0);
    };
    search.addEventListener('input', apply);
    [entity, role, date].forEach(control => control.addEventListener('change', apply));
    document.getElementById('clearActivityFilters').addEventListener('click', () => { search.value = ''; entity.value = ''; role.value = ''; date.value = ''; apply(); });
})();
</script>
