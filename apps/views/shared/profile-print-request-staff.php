<section id="profileRequestsPanel" hidden>
    <div id="profileRequestsUI" data-mode="staff" class="vd-dash-card">
        <div class="vd-dash-card-header vd-request-list-header"><span class="vd-dash-card-title">Profile requests</span><label class="d-flex align-items-center gap-2">Status <select class="form-select vd-input" id="profileRequestFilter"><option value="active">Active</option><option value="all">All</option><option>Pending</option><option>Ready for pickup</option><option>Collected</option><option>Cancelled</option></select></label></div>
        <p id="profileRequestError" class="text-danger px-3" role="alert" hidden></p>
        <div class="vd-appt-table-wrap"><table class="w-100 vd-report-table"><thead><tr><th>Patient</th><th>Pickup clinic</th><th>Purpose</th><th>Billing history</th><th>Requested on</th><th>Status</th><th>Actions</th></tr></thead><tbody id="profileRequestRows"></tbody></table></div>
        <div id="profileRequestEmpty" class="vd-empty-state" hidden>No requests found.</div>
    </div>
</section>
<?php require __DIR__ . '/profile-print-request-script.php'; ?>
