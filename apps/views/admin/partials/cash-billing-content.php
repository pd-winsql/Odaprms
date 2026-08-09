<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'Dental Assistant'], true)) { echo '<div class="vd-empty-state">Unauthorized.</div>'; exit; }
require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/billingModel.php';
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$rows = (new BillingModel((new Database())->connect()))->getStaffBillings();
?>
<div class="d-flex flex-column gap-4">
  <div><div class="vd-welcome-greet">CASH BILLING</div><div class="vd-welcome-name">Final treatment settlement</div><p class="text-muted small">Enter the actual service amount. The verified ₱400 deposit is deducted automatically; the remaining payment is cash only.</p></div>
  <div class="vd-dash-card"><div class="vd-dash-card-body">
  <?php if (!$rows): ?><div class="vd-empty-state">No checked-in appointments are ready for cash billing.</div><?php else: ?>
  <div class="vd-appt-table-wrap"><table class="vd-appt-table w-100"><thead><tr><th>Patient</th><th>Service</th><th>Deposit</th><th>Actual Charge</th><th>Cash Received</th><th>Status</th><th>Action</th></tr></thead><tbody>
  <?php foreach ($rows as $row): ?><tr data-billing-row>
    <td><div class="vd-appt-name"><?= htmlspecialchars($row['lastname'] . ', ' . $row['firstname']) ?></div><div class="vd-appt-meta"><?= htmlspecialchars($row['appointment_status']) ?></div></td>
    <td class="vd-appt-meta"><?= htmlspecialchars($row['service_name'] ?: '—') ?></td>
    <td>₱<?= number_format((float) $row['verified_deposit'], 2) ?></td>
    <td><input type="number" min="0" step="0.01" class="form-control vd-input" data-service-amount value="<?= htmlspecialchars($row['actual_service_amount'] ?? '') ?>"></td>
    <td><input type="number" min="0" step="0.01" class="form-control vd-input" data-cash-received value="<?= htmlspecialchars($row['cash_received'] ?? '0') ?>"></td>
    <td><span class="vd-status vd-status-<?= strtolower(str_replace(' ', '-', $row['payment_status'])) ?>"><?= htmlspecialchars($row['payment_status']) ?></span><?php if ($row['remaining_balance'] !== null): ?><div class="vd-appt-meta">Balance: ₱<?= number_format((float) $row['remaining_balance'], 2) ?></div><?php endif; ?></td>
    <td><button type="button" class="btn vd-btn-gold btn-sm" data-save-billing="<?= (int) $row['appointment_id'] ?>">Record Cash</button></td>
  </tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
  </div></div>
</div>
<script>(function(){document.querySelectorAll('[data-save-billing]').forEach(button=>button.addEventListener('click',async()=>{const row=button.closest('[data-billing-row]');const body=new FormData();body.append('action','recordCash');body.append('csrf_token',<?= json_encode($_SESSION['csrf_token']) ?>);body.append('appointment_id',button.dataset.saveBilling);body.append('service_amount',row.querySelector('[data-service-amount]').value);body.append('cash_received',row.querySelector('[data-cash-received]').value);button.disabled=true;try{const response=await fetch('../../controllers/billingController.php',{method:'POST',body});const result=await response.json();if(!result.success)throw new Error(result.message);window.showToast(result.message,true);document.querySelector('[data-page="cash-billing-content.php"]')?.click();}catch(error){window.showToast(error.message||'Unable to save billing.',false);button.disabled=false;}}));})();</script>
