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
    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
            <div>
                <span class="vd-dash-card-title">Recorded actions</span>
                <p class="text-muted small mb-0 mt-1">Read-only history of recorded changes and clinic actions.</p>
            </div>
            <span class="vd-topbar-date" id="activityCount"><?= count($activityRows) ?> records</span>
        </div>
        <div class="vd-filter-bar">
            <div class="vd-filter-group flex-grow-1">
                <label class="vd-label form-label" for="activitySearch">Search</label>
                <div class="vd-activity-search-field">
                    <i class="ti ti-search" aria-hidden="true"></i>
                    <input type="search" id="activitySearch" class="form-control vd-input" placeholder="Person, action, description, or record number">
                </div>
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
            <?php if (!$activityRows): ?>
                <div class="vd-empty-state">No activity has been recorded yet.</div>
            <?php else: ?>
                <div class="vd-appt-table-wrap"><table class="vd-appt-table vd-activity-log-table w-100" id="activityTable" data-page-size="20">
                    <colgroup><col class="vd-activity-col-date"><col class="vd-activity-col-person"><col class="vd-activity-col-record"><col class="vd-activity-col-action"><col class="vd-activity-col-description"><col class="vd-activity-col-changes"></colgroup>
                    <thead><tr><th>Date and time</th><th>Performed by</th><th>Record</th><th>Action</th><th>Description</th><th>Changes</th></tr></thead>
                    <tbody><?php foreach ($activityRows as $row):
                        $searchText = strtolower(implode(' ', [$row['performed_by_name'], $row['performed_by_role'], $row['entity_type'], $row['entity_id'], $row['action'], $row['description']]));
                        $oldSummary = activityValueSummary($row['old_values']);
                        $newSummary = activityValueSummary($row['new_values']);
                        $recordLabel = ucwords(str_replace('_', ' ', $row['entity_type'])) . ($row['entity_id'] === null ? ' · System-wide' : ' #' . (int) $row['entity_id']);
                        $changePayload = json_encode([
                            'action' => ucwords(str_replace('_', ' ', $row['action'])),
                            'record' => $recordLabel,
                            'performedBy' => $row['performed_by_name'],
                            'performedAt' => date('M d, Y · g:i A', strtotime($row['performed_at'])),
                            'before' => $oldSummary,
                            'after' => $newSummary,
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    ?>
                        <tr data-search="<?= htmlspecialchars($searchText, ENT_QUOTES) ?>" data-entity="<?= htmlspecialchars(strtolower($row['entity_type'])) ?>" data-role="<?= htmlspecialchars(strtolower($row['performed_by_role'])) ?>" data-date="<?= htmlspecialchars(substr($row['performed_at'], 0, 10)) ?>">
                            <td><div class="vd-appt-name"><?= date('M d, Y', strtotime($row['performed_at'])) ?></div><div class="vd-appt-meta"><?= date('g:i A', strtotime($row['performed_at'])) ?></div></td>
                            <td><div class="vd-appt-name"><?= htmlspecialchars($row['performed_by_name']) ?></div><div class="vd-appt-meta"><?= htmlspecialchars($row['performed_by_role'] === 'Admin' ? 'Admin / Dentist' : $row['performed_by_role']) ?></div></td>
                            <td><div class="vd-appt-name"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $row['entity_type']))) ?></div><div class="vd-appt-meta"><?= $row['entity_id'] === null ? 'System-wide' : '#' . (int) $row['entity_id'] ?></div></td>
                            <td><span class="vd-status vd-status-confirmed"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $row['action']))) ?></span></td>
                            <td><div class="vd-activity-description" title="<?= htmlspecialchars($row['description'], ENT_QUOTES) ?>"><?= htmlspecialchars($row['description']) ?></div></td>
                            <td><?php if ($oldSummary || $newSummary): ?><button type="button" class="vd-activity-change-trigger" data-activity-changes="<?= htmlspecialchars($changePayload, ENT_QUOTES) ?>"><i class="ti ti-eye" aria-hidden="true"></i><span>View changes</span></button><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?></tbody>
                </table></div>
                <div class="vd-empty-state d-none" id="activityEmpty">No activity matches the selected filters.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="activityChangesModal" tabindex="-1" aria-labelledby="activityChangesTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content vd-modal-content">
            <div class="modal-header">
                <div>
                    <div class="vd-appointment-details-kicker">Activity log</div>
                    <h5 class="modal-title vd-modal-title" id="activityChangesTitle">Recorded changes</h5>
                    <p class="vd-appointment-details-subtitle mb-0" id="activityChangesContext"></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="vd-activity-change-meta" id="activityChangesMeta"></p>
                <div class="vd-activity-change-grid">
                    <section class="vd-activity-change-panel" id="activityBeforePanel">
                        <span class="vd-activity-change-label">Before</span>
                        <p id="activityBeforeValue"></p>
                    </section>
                    <section class="vd-activity-change-panel is-after" id="activityAfterPanel">
                        <span class="vd-activity-change-label">After</span>
                        <p id="activityAfterValue"></p>
                    </section>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal">Close</button>
            </div>
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
    search.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.isComposing && event.keyCode !== 229) event.preventDefault();
    });
    [entity, role, date].forEach(control => control.addEventListener('change', apply));
    document.getElementById('clearActivityFilters').addEventListener('click', () => { search.value = ''; entity.value = ''; role.value = ''; date.value = ''; apply(); });

    const changesModalElement = document.getElementById('activityChangesModal');
    const changesModal = bootstrap.Modal.getOrCreateInstance(changesModalElement);
    const beforePanel = document.getElementById('activityBeforePanel');
    const afterPanel = document.getElementById('activityAfterPanel');
    document.querySelectorAll('[data-activity-changes]').forEach(button => {
        button.addEventListener('click', () => {
            const details = JSON.parse(button.dataset.activityChanges);
            document.getElementById('activityChangesTitle').textContent = details.action || 'Recorded changes';
            document.getElementById('activityChangesContext').textContent = details.record || '';
            document.getElementById('activityChangesMeta').textContent = `${details.performedBy || 'System'} · ${details.performedAt || ''}`;
            document.getElementById('activityBeforeValue').textContent = details.before || '';
            document.getElementById('activityAfterValue').textContent = details.after || '';
            beforePanel.hidden = !details.before;
            afterPanel.hidden = !details.after;
            changesModal.show();
        });
    });
})();
</script>
