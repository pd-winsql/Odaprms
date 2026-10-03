<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
session_save_path(sys_get_temp_dir());
session_start();
require __DIR__ . '/../apps/helpers/appUrl.php';
$conn = new class {
    public function query($sql) { return new class {
        public function fetchAll($mode) { return [['clinic_id'=>1,'clinic_name'=>'Alcala Branch'],['clinic_id'=>2,'clinic_name'=>'Tuguegarao Branch']]; }
    }; }
};
if (($argv[1] ?? 'patient') === 'patient') {
    $source=file_get_contents(__DIR__.'/../apps/views/patient/partials/profile-content.php');
    preg_match('/<header class="vd-profile-overview-header">.*?<\/header>/s', $source, $header);
    echo '<section class="vd-profile-overview">'.$header[0];
    require __DIR__.'/../apps/views/shared/profile-print-request-patient.php';
    echo '<div class="vd-dash-card p-4">Personal information</div></section>';
} else {
    $source=file_get_contents(__DIR__.'/../apps/views/admin/partials/patient-content.php');
    preg_match('/<div class="vd-patient-record-tabs".*?<\/div>/s', $source, $tabs);
    echo $tabs[0].'<div id="patientRecordsPanel">Patient records</div>';
    require __DIR__.'/../apps/views/shared/profile-print-request-staff.php';
}
require __DIR__.'/../apps/views/shared/staff-action-modal.php';
session_destroy();
