<?php
require_once __DIR__.'/../apps/helpers/cancellationPolicy.php';
$_SESSION['csrf_token']='qa-cancel-token';
if (in_array('--settings',$_SERVER['argv']??[],true)) {
    $settings=['minimum_cancellation_notice_days'=>2];
    $source=file_get_contents(__DIR__.'/../apps/views/admin/partials/siteSettings-content.php');
    $heading=strpos($source,'>Patient Cancellation</span>');
    $start=strrpos(substr($source,0,$heading),'<section');
    $end=strpos($source,'</section>',$heading)+strlen('</section>');
    eval('?>'.substr($source,$start,$end-$start));
    eval('?>'.substr($source,strpos($source,'<!-- Save confirmation modal -->')));
    exit;
}
$cancellationDays=2;
foreach ([
    ['appointment_id'=>101,'status'=>'Confirmed','date'=>date('Y-m-d',strtotime('+10 days')),'start_time'=>'08:00:00'],
    ['appointment_id'=>102,'status'=>'Confirmed','date'=>date('Y-m-d'),'start_time'=>'08:00:00'],
    ['appointment_id'=>103,'status'=>'Pending Review','date'=>date('Y-m-d'),'start_time'=>'08:00:00']
] as $cancelAppointment) {
    echo '<section class="vd-dash-card"><div class="vd-dash-card-body">';
    if ($cancelAppointment['appointment_id']===103) {
        echo '<div class="vd-next-appt-card"><div class="vd-next-appt-label">Next Appointment</div><div class="vd-next-appt-service">Cleaning (Prophylaxis)</div><div class="vd-next-appt-meta"><span>Alcala Branch</span><span>October 12, 2026</span></div><span class="vd-status">Pending Review</span>';
    } else {
        echo '<h3>Appointment #'.$cancelAppointment['appointment_id'].'</h3>';
    }
    include __DIR__.'/../apps/views/shared/patient-cancellation-action.php';
    if ($cancelAppointment['appointment_id']===103) echo '</div>';
    echo '</div></section>';
}
