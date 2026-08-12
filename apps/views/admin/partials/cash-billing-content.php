<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'Dental Assistant'], true)) {
    echo '<div class="vd-empty-state">Unauthorized.</div>'; exit;
}
require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/billingModel.php';
$rows = (new BillingModel((new Database())->connect()))->getStaffBillings();
$clinics = array_values(array_unique(array_filter(array_column($rows, 'clinic_name')))); sort($clinics);
$statuses = array_values(array_unique(array_filter(array_column($rows, 'payment_status')))); sort($statuses);

function billingRecordPayload(array $row): string {
    $amountDue = max(0, (float) ($row['remaining_balance'] ?? 0));
    $cash = (float) ($row['cash_received'] ?? 0);
    return htmlspecialchars(json_encode([
        'appointmentId' => (int) $row['appointment_id'],
        'patient' => trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? '')),
        'date' => $row['date'] ?? '',
        'clinic' => $row['clinic_name'] ?? '',
        'services' => $row['service_name'] ?? '',
        'actualCharge' => (float) ($row['actual_service_amount'] ?? 0),
        'depositApplied' => (float) ($row['deposit_applied'] ?? 0),
        'amountDue' => $amountDue,
        'cashTendered' => $cash,
        'change' => max(0, $cash - $amountDue),
        'paymentStatus' => $row['payment_status'] ?? '',
        'recordedBy' => $row['recorded_by'] ?? '',
        'recordedAt' => $row['recorded_at'] ?? '',
        'notes' => $row['notes'] ?? '',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
}
?>
<div class="d-flex flex-column gap-4">
  <div><div class="vd-welcome-greet">BILLING</div><div class="vd-welcome-name">Billing Records</div><p class="text-muted small mb-0 mt-2">Read-only history of final visit settlements. Active transactions are completed from Today’s Logbook.</p></div>
  <div class="vd-dash-card">
    <div class="vd-dash-card-header"><span class="vd-dash-card-title">Transaction history</span><span class="vd-topbar-date" id="billingRecordCount"><?= count($rows) ?> record<?= count($rows) === 1 ? '' : 's' ?></span></div>
    <div class="vd-filter-bar">
      <div class="vd-filter-group"><label class="vd-label form-label" for="billingClinicFilter">Clinic</label><select id="billingClinicFilter" class="form-select vd-input vd-filter-select"><option value="">All clinics</option><?php foreach ($clinics as $clinic): ?><option value="<?= htmlspecialchars($clinic) ?>"><?= htmlspecialchars($clinic) ?></option><?php endforeach; ?></select></div>
      <div class="vd-filter-group"><label class="vd-label form-label" for="billingStatusFilter">Payment status</label><select id="billingStatusFilter" class="form-select vd-input vd-filter-select"><option value="">All statuses</option><?php foreach ($statuses as $status): ?><option value="<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></option><?php endforeach; ?></select></div>
      <div class="vd-filter-group"><label class="vd-label form-label" for="billingDateFrom">Date from</label><input type="date" id="billingDateFrom" class="form-control vd-input vd-filter-select"></div>
      <div class="vd-filter-group"><label class="vd-label form-label" for="billingDateTo">Date to</label><input type="date" id="billingDateTo" class="form-control vd-input vd-filter-select"></div>
      <div class="vd-filter-group vd-filter-clear"><button type="button" class="btn vd-btn-outline" id="clearBillingFilters">Clear</button></div>
    </div>
    <div class="vd-dash-card-body">
    <?php if (!$rows): ?><div class="vd-empty-state">No completed billing records yet.</div><?php else: ?>
      <div class="vd-appt-table-wrap"><table class="vd-appt-table w-100" id="billingRecordsTable">
        <thead><tr><th>Patient</th><th>Visit</th><th>Settlement</th><th>Status</th><th>Recorded</th><th>Action</th></tr></thead>
        <tbody><?php foreach ($rows as $row): ?>
          <tr data-clinic="<?= htmlspecialchars($row['clinic_name']) ?>" data-status="<?= htmlspecialchars($row['payment_status']) ?>" data-date="<?= htmlspecialchars($row['date']) ?>">
            <td><div class="vd-appt-name"><?= htmlspecialchars($row['lastname'] . ', ' . $row['firstname']) ?></div><div class="vd-appt-meta">Appointment #<?= (int)$row['appointment_id'] ?></div></td>
            <td><div class="vd-appt-name"><?= date('M d, Y', strtotime($row['date'])) ?></div><div class="vd-appt-meta"><?= htmlspecialchars($row['clinic_name']) ?></div></td>
            <td><div class="vd-appt-name">₱<?= number_format((float)$row['actual_service_amount'], 2) ?></div><div class="vd-appt-meta">Deposit: ₱<?= number_format((float)$row['deposit_applied'], 2) ?></div></td>
            <td><span class="<?= htmlspecialchars('vd-status vd-status-' . strtolower(str_replace(' ', '-', $row['payment_status']))) ?>"><?= htmlspecialchars($row['payment_status']) ?></span></td>
            <td><div class="vd-appt-name"><?= htmlspecialchars($row['recorded_by']) ?></div><div class="vd-appt-meta"><?= $row['recorded_at'] ? date('M d, Y g:i A', strtotime($row['recorded_at'])) : 'Not recorded' ?></div></td>
            <td><button type="button" class="btn vd-btn-outline btn-md vd-billing-details-btn" title="View billing details" data-billing-record="<?= billingRecordPayload($row) ?>"><i class="ti ti-eye" aria-hidden="true"></i></button></td>
          </tr>
        <?php endforeach; ?></tbody>
      </table></div>
    <?php endif; ?>
    </div>
  </div>
</div>

<div class="modal fade" id="billingRecordModal" tabindex="-1" aria-labelledby="billingRecordTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable"><div class="modal-content vd-modal-content">
    <div class="modal-header"><div><div class="vd-action-modal-kicker">Transaction record</div><h5 class="modal-title vd-modal-title" id="billingRecordTitle">Billing Details</h5><p class="text-muted small mb-0" id="billingRecordSubtitle"></p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
      <section><h6 class="vd-appointment-details-section-title">Visit information</h6><div class="vd-appointment-detail-grid" id="billingVisitGrid"></div></section>
      <section class="vd-appointment-payment-section"><h6 class="vd-appointment-details-section-title">Payment breakdown</h6><div class="vd-final-billing-summary" id="billingPaymentSummary"></div></section>
      <section class="vd-appointment-activity-section"><h6 class="vd-appointment-details-section-title">Record information</h6><div class="vd-appointment-detail-grid" id="billingRecordGrid"></div><div class="vd-appointment-payment-note d-none" id="billingRecordNotes"></div></section>
    </div>
    <div class="modal-footer"><button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal">Close</button></div>
  </div></div>
</div>

<script>
(function () {
  const money = value => Number(value || 0).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });
  const formatDateTime = value => { if (!value) return 'Not recorded'; const date = new Date(String(value).replace(' ', 'T')); return Number.isNaN(date.getTime()) ? value : date.toLocaleString([], {year:'numeric',month:'short',day:'numeric',hour:'numeric',minute:'2-digit'}); };
  const addDetail = (container, label, value) => { const item=document.createElement('div');item.className='vd-appointment-detail-item';const term=document.createElement('span');term.textContent=label;const detail=document.createElement('strong');detail.textContent=value||'Not provided';item.append(term,detail);container.appendChild(item); };
  const table=document.getElementById('billingRecordsTable'),clinic=document.getElementById('billingClinicFilter'),status=document.getElementById('billingStatusFilter'),from=document.getElementById('billingDateFrom'),to=document.getElementById('billingDateTo'),count=document.getElementById('billingRecordCount');
  const applyFilters=()=>{if(!table)return;let visible=0;table.querySelectorAll('tbody tr').forEach(row=>{const show=(!clinic.value||row.dataset.clinic===clinic.value)&&(!status.value||row.dataset.status===status.value)&&(!from.value||row.dataset.date>=from.value)&&(!to.value||row.dataset.date<=to.value);row.style.display=show?'':'none';if(show)visible++;});count.textContent=`${visible} record${visible===1?'':'s'}`;table.dispatchEvent(new CustomEvent('ventura:table-filtered'));};
  [clinic,status,from,to].forEach(control=>control?.addEventListener('change',applyFilters));
  document.getElementById('clearBillingFilters')?.addEventListener('click',()=>{clinic.value='';status.value='';from.value='';to.value='';applyFilters();});
  document.querySelectorAll('[data-billing-record]').forEach(button=>button.addEventListener('click',()=>{
    const record=JSON.parse(button.dataset.billingRecord);document.getElementById('billingRecordTitle').textContent=`Billing · ${record.patient}`;document.getElementById('billingRecordSubtitle').textContent=`Appointment #${record.appointmentId}`;
    const visit=document.getElementById('billingVisitGrid');visit.replaceChildren();addDetail(visit,'Patient',record.patient);addDetail(visit,'Appointment date',record.date);addDetail(visit,'Clinic',record.clinic);addDetail(visit,'Services',record.services);
    const summary=document.getElementById('billingPaymentSummary');summary.replaceChildren();[['Actual charge',money(record.actualCharge)],['Deposit applied','−'+money(record.depositApplied)],['Amount due',money(record.amountDue)],['Cash tendered',money(record.cashTendered)],['Change',money(record.change)]].forEach(([label,value],index)=>{const row=document.createElement('div');if(index===2)row.className='vd-final-billing-total';const term=document.createElement('span');term.textContent=label;const amount=document.createElement('strong');amount.textContent=value;row.append(term,amount);summary.appendChild(row);});
    const info=document.getElementById('billingRecordGrid');info.replaceChildren();addDetail(info,'Payment status',record.paymentStatus);addDetail(info,'Recorded by',record.recordedBy);addDetail(info,'Recorded at',formatDateTime(record.recordedAt));const notes=document.getElementById('billingRecordNotes');notes.textContent=record.notes||'';notes.classList.toggle('d-none',!record.notes);bootstrap.Modal.getOrCreateInstance(document.getElementById('billingRecordModal')).show();
  }));
})();
</script>
