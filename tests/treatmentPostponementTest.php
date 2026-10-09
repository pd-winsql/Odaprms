<?php
require_once __DIR__ . '/../config/conn.php';
require_once __DIR__ . '/../apps/models/appointmentModel.php';
require_once __DIR__ . '/../apps/models/patientModel.php';
require_once __DIR__ . '/../apps/models/logbookModel.php';
require_once __DIR__ . '/../apps/models/patientPrintModel.php';
function postponeExpect(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
    if (($GLOBALS['argv'][1] ?? '') !== 'render') echo "PASS: $message\n";
}
$conn = (new Database())->connect();
if (!$conn) throw new RuntimeException('Database unavailable.');
$ids = []; $patientId = 0; $scheduleId = 0; $qaUser = 0; $qaClinic = 0;
try {
    $admin = (int) $conn->query("SELECT id FROM users WHERE user_role='Admin' LIMIT 1")->fetchColumn();
    $assistant = (int) $conn->query("SELECT id FROM users WHERE user_role='Dental Assistant' LIMIT 1")->fetchColumn();
    $clinic = (int) $conn->query('SELECT clinic_id FROM clinics LIMIT 1')->fetchColumn();
    $service = (int) $conn->query('SELECT service_id FROM services LIMIT 1')->fetchColumn();
    postponeExpect($admin > 0 && $assistant > 0 && $clinic > 0 && $service > 0, 'Required fixtures exist.');
    $conn->exec("INSERT INTO clinics (clinic_name,clinic_address,clinic_contact,embed_url) VALUES ('Postponement QA Clinic','QA only','','')");
    $qaClinic = $clinic = (int) $conn->lastInsertId();
    $conn->prepare('INSERT INTO schedules (clinic_id,sched_date,max_appointments) VALUES (?,CURDATE(),15)')->execute([$clinic]);
    $scheduleId = (int) $conn->lastInsertId();
    $email = 'postpone-' . bin2hex(random_bytes(5)) . '@example.invalid';
    $conn->prepare("INSERT INTO users (email,password,user_role) VALUES (?,?,'Patient')")->execute([$email,password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT)]);
    $qaUser = (int) $conn->lastInsertId();
    $patientId = (int) (new Patient($conn))->createPatient($qaUser, 'Postponement', 'QA Patient', '', 30, 'Male', '09123456781', $email, '1996-01-01');
    foreach (['In Progress', 'Awaiting Deposit', 'Awaiting Deposit'] as $status) {
        $conn->prepare('INSERT INTO appointments (patient_id,schedule_id,clinic_id,date,status) VALUES (?,?,?,CURDATE(),?)')->execute([$patientId,$scheduleId,$clinic,$status]);
        $id = (int) $conn->lastInsertId(); $ids[] = $id;
        $conn->prepare('INSERT INTO appointment_services (appointment_id,service_id) VALUES (?,?)')->execute([$id,$service]);
        $conn->prepare('INSERT INTO appointment_deposits (appointment_id,amount,status,gcash_reference,receipt_path) VALUES (?,400,?,?,?)')
            ->execute([$id,$status === 'In Progress' ? 'Verified' : 'Awaiting Submission', $status === 'In Progress' ? 'QA-POST-' . $id : null, $status === 'In Progress' ? 'storage/payment_receipts/qa-postponement.jpg' : null]);
    }
    $conn->prepare("INSERT INTO appointment_checkins (appointment_id,arrived_at,checkin_status,checked_in_by_user_id) VALUES (?,NOW(),'Ready',?)")->execute([$ids[0],$assistant]);
    if (($argv[1] ?? '') === 'render') {
        session_start(); $_SESSION = ['user_id'=>$admin,'user_role'=>'Admin','display_name'=>'Demo Dentist','csrf_token'=>'qa-token'];
        include __DIR__ . '/../apps/views/admin/partials/dashboard-content.php';
        include __DIR__ . '/../apps/views/shared/staff-action-modal.php';
    } else {
        $model = new Appointment($conn); $deposit = new DepositModel($conn);
        postponeExpect(!$model->postponeTreatment($ids[0],$assistant,'Elevated blood pressure')['success'], 'Assistant cannot postpone treatment.');
        postponeExpect(!$model->postponeTreatment($ids[0],$admin,'')['success'], 'Reason is required.');
        postponeExpect(!$model->postponeTreatment($ids[1],$admin,'Elevated blood pressure')['success'], 'Only an active treatment visit can be postponed.');
        $result = $model->postponeTreatment($ids[0],$admin,'Elevated blood pressure at assessment.');
        postponeExpect($result['success'], 'Admin postpones the visit.');
        postponeExpect(!empty($result['notification']['id']), 'Patient postponement email is queued in the same transaction.');
        postponeExpect(!$model->postponeTreatment($ids[0],$admin,'Repeated submission')['success'], 'Repeated submission cannot change or duplicate the outcome.');
        $row = $conn->query('SELECT a.status,a.postponement_reason,d.status deposit_status,d.receipt_path FROM appointments a JOIN appointment_deposits d USING(appointment_id) WHERE a.appointment_id='.$ids[0])->fetch(PDO::FETCH_ASSOC);
        postponeExpect($row['status']==='Treatment Postponed' && $row['deposit_status']==='Retained for Rebooking' && $row['receipt_path']==='storage/payment_receipts/qa-postponement.jpg', 'Visit, full deposit, reason and original receipt are preserved.');
        postponeExpect(!(int)$conn->query('SELECT COUNT(*) FROM appointment_billings WHERE appointment_id='.$ids[0])->fetchColumn(), 'No billing is created.');
        postponeExpect(in_array($ids[0],array_column($model->getPatientPastAppointments($patientId),'appointment_id')), 'Postponed visit appears immediately in patient history.');
        postponeExpect(in_array($ids[0],array_column((new LogbookModel($conn))->getToday(),'appointment_id')), 'Postponed visit stays in the logbook.');
        postponeExpect(in_array($ids[0],array_column((new PatientPrintModel($conn))->getRecord($patientId)['ledger'],'appointment_id')), 'Postponed visit appears in the printable record.');
        postponeExpect($deposit->transferDeposit($ids[0],$ids[1],$assistant,'Rebooking after postponed treatment')['success'], 'Retained deposit confirms an accepted replacement booking.');
        postponeExpect(!$deposit->transferDeposit($ids[0],$ids[2],$assistant,'Attempting to reuse the deposit')['success'], 'A transferred deposit cannot be spent twice.');
        $target = $conn->query('SELECT status,amount,transferred_from_appointment_id FROM appointment_deposits WHERE appointment_id='.$ids[1])->fetch(PDO::FETCH_ASSOC);
        postponeExpect($target['status']==='Verified' && (float)$target['amount']===400.0 && (int)$target['transferred_from_appointment_id']===$ids[0], 'Replacement has the full verified deposit and source link.');
    }
} finally {
    if ($conn->inTransaction()) $conn->rollBack();
    foreach (array_reverse($ids) as $id) {
        $conn->prepare('DELETE FROM appointment_email_notifications WHERE appointment_id=?')->execute([$id]);
        $conn->prepare("DELETE FROM audit_logs WHERE entity_type='appointment' AND entity_id=?")->execute([$id]);
        foreach (['appointment_checkins','appointment_deposits','appointment_services','appointments'] as $table) $conn->prepare("DELETE FROM $table WHERE appointment_id=?")->execute([$id]);
    }
    if ($patientId) $conn->prepare('DELETE FROM patients WHERE patient_id=?')->execute([$patientId]);
    if ($qaUser) $conn->prepare('DELETE FROM users WHERE id=?')->execute([$qaUser]);
    if ($scheduleId) $conn->prepare('DELETE FROM schedules WHERE schedule_id=?')->execute([$scheduleId]);
    if ($qaClinic) $conn->prepare('DELETE FROM clinics WHERE clinic_id=?')->execute([$qaClinic]);
}
