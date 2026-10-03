<?php
if (PHP_SAPI !== 'cli') exit;
require __DIR__.'/../apps/helpers/appUrl.php';
$source=file_get_contents(__DIR__.'/../apps/views/admin/partials/appointment-content.php');
$start=strpos($source,'function statusClass');
eval(substr($source,$start,strpos($source,'?>',$start)-$start));
$_SESSION=['user_role'=>'Dental Assistant','csrf_token'=>'test-token'];
$configuredDeadlineLabel='8 hours'; $configuredDepositLabel='₱400.00';
$statusFilterOrder=['Pending Review','Awaiting Deposit','Payment Under Review','Confirmed','Checked In','In Progress','Completed','Cancelled','No-show','Rejected'];
$base=['appointment_id'=>1,'firstname'=>'Sample','lastname'=>'Patient','email'=>'patient@example.invalid','phone_number'=>'09123456789','date'=>'2026-10-10','start_time'=>'08:00:00','end_time'=>'12:00:00','status'=>'Pending Review','clinic_name'=>'Alcala Branch','status_changed_by'=>'','status_changed_at'=>'','status_changed_by_role'=>''];
$upcoming=[$base,array_replace($base,['appointment_id'=>2,'firstname'=>'Julian','lastname'=>'Torres','status'=>'Confirmed','date'=>'2026-10-19','status_changed_by'=>'Julie Ann Mae Graham','status_changed_by_role'=>'Dental Assistant','status_changed_at'=>'2026-10-01 22:27:00'])];
$past=[array_replace($base,['appointment_id'=>3,'date'=>'2026-09-20','status'=>'Completed'])];
$upcomingFilters=['minDate'=>'2026-10-10','maxDate'=>'2026-10-19'];$pastFilters=['minDate'=>'2026-09-20','maxDate'=>'2026-09-20'];
$overduePaymentCount=0;$servicesByAppointment=[];
eval('?>'.substr($source,strpos($source,'<!-- VIEW TOGGLE')));
