<?php
// Render the production credit controls with fictional data, without a database.
$_SESSION['user_id'] = 1;
$canSubmit = true;
$deposit = ['appointment_id'=>202,'deposit_id'=>20];
$csrfToken = 'qa-credit-token';
$depositModel = new class {
    public function getPatientTransferCredits(int $user, int $target): array {
        $credits = [['appointment_id'=>101,'amount'=>400,'clinic_name'=>'Tuguegarao Branch','date'=>'2026-09-20']];
        if (in_array('--multiple', $_SERVER['argv'] ?? [], true)) {
            $credits[] = ['appointment_id'=>102,'amount'=>500,'clinic_name'=>'Alcala Branch','date'=>'2026-09-18'];
        }
        return $credits;
    }
};
$source = file_get_contents(__DIR__.'/../apps/views/patient/partials/billing-content.php');
$start = strpos($source, '<?php $credits =');
$end = strpos($source, "<?php if (\$deposit['deposit_status'] === 'Rejected')", $start);
eval('?>'.substr($source,$start,$end-$start));
