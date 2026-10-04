<?php
require_once __DIR__.'/../apps/helpers/cancellationPolicy.php';
function cancellationExpect(bool $condition,string $label): void {
    if (!$condition) throw new RuntimeException($label);
    echo "PASS: $label\n";
}
$zone=new DateTimeZone('Asia/Manila');
$now=new DateTimeImmutable('2026-10-04 08:00:00',$zone);
$base=['status'=>'Confirmed','date'=>'2026-10-06','start_time'=>'08:00:00'];
cancellationExpect(!CancellationPolicy::eligibility($base,2,$now)['allowed'],'Exactly 48 hours is blocked');
cancellationExpect(CancellationPolicy::eligibility($base,2,$now->modify('-1 second'))['allowed'],'More than 48 hours is allowed');
cancellationExpect(!CancellationPolicy::eligibility($base,2,$now->modify('+1 second'))['allowed'],'Less than 48 hours is blocked');
cancellationExpect(CancellationPolicy::eligibility($base,1,$now)['allowed'],'Immediate notice setting change changes eligibility');
cancellationExpect(CancellationPolicy::eligibility($base,0,$now)['allowed'],'Zero notice allows before start');
cancellationExpect(!CancellationPolicy::eligibility($base,0,$now->modify('+2 days'))['allowed'],'Zero notice still blocks at start');
foreach (['Pending Review','Awaiting Deposit','Payment Under Review'] as $status) {
    cancellationExpect(CancellationPolicy::eligibility(array_replace($base,['status'=>$status]),30,$now)['allowed'],"$status can be withdrawn without confirmed cutoff");
}
foreach (['Checked In','In Progress','Completed','Cancelled','No-show','Rejected','Treatment Postponed'] as $status) {
    cancellationExpect(!CancellationPolicy::eligibility(array_replace($base,['status'=>$status]),0,$now)['allowed'],"$status cannot be cancelled by patient");
}
cancellationExpect(!CancellationPolicy::eligibility($base+['has_checkin'=>1],0,$now)['allowed'],'Check-in record prevents patient cancellation');
foreach (['-1','31','1.5','abc',''] as $value) cancellationExpect(!SiteSettingsModel::validateCancellationSettings(['minimum_cancellation_notice_days'=>$value])['success'],"Invalid notice '$value' rejected");
cancellationExpect(SiteSettingsModel::validateCancellationSettings(['minimum_cancellation_notice_days'=>'2'])['success'],'Valid notice accepted');
