<?php
// Caller supplies $cancelAppointment, $cancellationDays and the session CSRF token.
$cancelState=CancellationPolicy::eligibility($cancelAppointment,$cancellationDays);
if (in_array($cancelAppointment['status'],['Pending Review','Awaiting Deposit','Payment Under Review','Confirmed'],true)):
$isConfirmed=$cancelAppointment['status']==='Confirmed';
?>
<div class="vd-patient-cancellation" data-csrf="<?= htmlspecialchars($_SESSION['csrf_token']??'',ENT_QUOTES) ?>">
    <button type="button" class="btn vd-patient-cancellation-button" data-patient-cancel="<?= (int)$cancelAppointment['appointment_id'] ?>" data-cancel-confirmed="<?= $isConfirmed ? 'true':'false' ?>" <?= $cancelState['allowed']?'':'disabled' ?>
        aria-describedby="cancelNotice<?= (int)$cancelAppointment['appointment_id'] ?>">
        <i class="ti ti-calendar-x" aria-hidden="true"></i>
        <span data-cancel-label><?= $isConfirmed?'Cancel appointment':'Withdraw request' ?></span>
    </button>
    <p id="cancelNotice<?= (int)$cancelAppointment['appointment_id'] ?>">
        <?php if (!$cancelState['allowed']): ?>Online cancellation is closed. Contact the clinic.
        <?php elseif ($isConfirmed): ?>Cancel before <?= htmlspecialchars($cancelState['deadline']->format('M j, Y · g:i A')) ?> (Philippine time).
        <?php else: ?>This request has not yet been confirmed.<?php endif; ?>
    </p>
    <div data-cancel-error role="alert" hidden></div>
</div>
<?php endif; ?>
