<?php
require_once __DIR__ . '/../config/conn.php';
require_once __DIR__ . '/../apps/models/appointmentModel.php';
require_once __DIR__ . '/../apps/models/patientModel.php';
function expectReschedule($ok, $message) { if (!$ok) throw new RuntimeException($message); echo "PASS: $message\n"; }
$conn = (new Database())->connect();
if (!$conn) throw new RuntimeException('Database unavailable.');
$patientId = $otherPatientId = $userId = $qaClinic = 0; $schedules = [];
try {
    $admin = (int) $conn->query("SELECT id FROM users WHERE user_role='Admin' LIMIT 1")->fetchColumn();
    $assistant = (int) $conn->query("SELECT id FROM users WHERE user_role='Dental Assistant' LIMIT 1")->fetchColumn();
    $clinic = (int) $conn->query('SELECT clinic_id FROM clinics LIMIT 1')->fetchColumn();
    $service = (int) $conn->query('SELECT service_id FROM services LIMIT 1')->fetchColumn();
    expectReschedule($admin && $assistant && $clinic && $service, 'Required fixtures exist.');
    $conn->prepare("INSERT INTO clinics (clinic_name,clinic_address,clinic_contact,embed_url) VALUES ('Postponement QA Clinic','QA only','','')")->execute();
    $qaClinic = $clinic = (int) $conn->lastInsertId();
    $email = 'postpone-reschedule-' . bin2hex(random_bytes(5)) . '@example.invalid';
    $conn->prepare("INSERT INTO users (email,password,user_role) VALUES (?,?,'Patient')")->execute([$email,password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT)]);
    $userId = (int) $conn->lastInsertId();
    $patientId = (int) (new Patient($conn))->createPatient($userId,'Reschedule','QA','','30','Male','09123456781',$email,'1996-01-01');
    $otherPatientId = (int) (new Patient($conn))->createPatient(null,'Capacity','QA','','30','Male','09123456782','capacity@example.invalid','1996-01-01');
    $makeSchedule = function ($days) use ($conn,$clinic,&$schedules) {
        $date = date('Y-m-d',strtotime("+$days days"));
        $conn->prepare("INSERT INTO schedules (clinic_id,sched_date,start_time,end_time,max_appointments) VALUES (?,?,'08:00:00','17:00:00',1)")->execute([$clinic,$date]);
        $id = (int) $conn->lastInsertId(); $schedules[] = $id; return [$id,$date];
    };
    [$today] = $makeSchedule(0); [$target,$targetDate] = $makeSchedule(180); [$full,$fullDate] = $makeSchedule(181);
    [$conflict,$conflictDate] = $makeSchedule(182); [$small,$smallDate] = $makeSchedule(183); [$unpaid,$unpaidDate] = $makeSchedule(184);
    $required = (float) ($conn->query('SELECT deposit_amount FROM site_settings WHERE id=1')->fetchColumn() ?: 400);
    $makeAppointment = function ($schedule,$date,$status,$amount = null,$services = true,$ownerId = null) use ($conn,$patientId,$clinic,$service) {
        $conn->prepare('INSERT INTO appointments (patient_id,clinic_id,date,schedule_id,status) VALUES (?,?,?,?,?)')->execute([$ownerId ?? $patientId,$clinic,$date,$schedule,$status]);
        $id = (int) $conn->lastInsertId();
        if ($services) $conn->prepare('INSERT INTO appointment_services (appointment_id,service_id,quantity) VALUES (?,?,1)')->execute([$id,$service]);
        if ($amount !== null) $conn->prepare("INSERT INTO appointment_deposits (appointment_id,amount,status,receipt_path) VALUES (?,?,'Verified','qa-retained-receipt.jpg')")->execute([$id,$amount]);
        return $id;
    };
    $source = $makeAppointment($today,date('Y-m-d'),'In Progress',$required);
    $model = new Appointment($conn);
    $before = $model->getPostponementSchedules($source);
    expectReschedule(in_array($target,array_column($before,'schedule_id')), 'Future eligible schedule is offered.');
    $makeAppointment($full,$fullDate,'Confirmed',null,true,$otherPatientId);
    $listedFull = array_values(array_filter($model->getPostponementSchedules($source), fn($row)=>(int)$row['schedule_id']===$full));
    expectReschedule(count($listedFull)===1 && (int)$listedFull[0]['available_slots']===0, 'Full schedules remain visible with zero slots.');
    // Emulate another patient taking the slot after availability was displayed.
    $result = $model->postponeTreatment($source,$admin,'High BP at assessment',$full);
    expectReschedule(!$result['success'] && $result['code']==='schedule_full', 'Stale full schedule is rejected.');
    expectReschedule($conn->query('SELECT status FROM appointments WHERE appointment_id='.$source)->fetchColumn()==='In Progress', 'Failed reschedule leaves original visit unchanged.');
    expectReschedule($conn->query('SELECT status FROM appointment_deposits WHERE appointment_id='.$source)->fetchColumn()==='Verified', 'Failed reschedule keeps deposit verified.');
    expectReschedule(!$model->postponeTreatment($source,$assistant,'High BP at assessment',$target)['success'], 'Assistant cannot reschedule treatment.');
    $conflictingBooking = $makeAppointment($conflict,$conflictDate,'Confirmed');
    $conn->prepare('UPDATE schedules SET max_appointments=2 WHERE schedule_id=?')->execute([$conflict]);
    expectReschedule(!in_array($conflict,array_column($model->getPostponementSchedules($source),'schedule_id')), 'Patient conflicting dates are not offered.');
    expectReschedule(!$model->postponeTreatment($source,$admin,'High BP at assessment',$conflict)['success'], 'Server blocks duplicate patient date.');
    $result = $model->postponeTreatment($source,$admin,'High BP at assessment',$target);
    expectReschedule($result['success'], 'Postponement and replacement save together.');
    $replacement = (int) $result['replacement_appointment_id'];
    expectReschedule($replacement > 0 && $conn->query('SELECT status FROM appointments WHERE appointment_id='.$replacement)->fetchColumn()==='Confirmed', 'Transferred deposit confirms replacement.');
    $deposit = $conn->query('SELECT * FROM appointment_deposits WHERE appointment_id='.$replacement)->fetch(PDO::FETCH_ASSOC);
    expectReschedule((int)$deposit['transferred_from_appointment_id']===$source && (float)$deposit['amount']===$required, 'Replacement links the original full deposit.');
    expectReschedule((int)$conn->query('SELECT COUNT(*) FROM appointment_services WHERE appointment_id='.$replacement)->fetchColumn()===1, 'Originally booked services are copied.');
    expectReschedule(!empty($result['notification']['id']) && !empty($result['replacement_notification']['id']), 'Both patient emails are queued transactionally.');
    $snapshot = array_values(array_filter($model->getPatientNotificationSnapshot($userId), fn($row)=>(int)$row['appointment_id']===$source));
    expectReschedule(count($snapshot)===1 && str_contains($snapshot[0]['patient_reason'],'replacement visit'), 'Patient notification points to the replacement, not another rebooking.');
    expectReschedule(!(int)$conn->query('SELECT COUNT(*) FROM appointment_billings WHERE appointment_id IN ('.$source.','.$replacement.')')->fetchColumn(), 'No billing is created.');
    expectReschedule(!$model->postponeTreatment($source,$admin,'Repeated submission',$target)['success'], 'Retry cannot duplicate replacement or spend credit twice.');
    $smallSource = $makeAppointment($today,date('Y-m-d'),'In Progress',$required / 2);
    $countBefore = (int)$conn->query('SELECT COUNT(*) FROM appointments WHERE patient_id='.$patientId)->fetchColumn();
    expectReschedule(!$model->postponeTreatment($smallSource,$admin,'High BP at assessment',$small)['success'], 'Insufficient retained deposit follows existing transfer policy.');
    expectReschedule((int)$conn->query('SELECT COUNT(*) FROM appointments WHERE patient_id='.$patientId)->fetchColumn()===$countBefore, 'Transfer failure rolls back the new booking.');
    expectReschedule($conn->query('SELECT status FROM appointments WHERE appointment_id='.$smallSource)->fetchColumn()==='In Progress', 'Transfer failure rolls back postponement.');
    $noServices = $makeAppointment($today,date('Y-m-d'),'In Progress',$required,false);
    expectReschedule(!$model->postponeTreatment($noServices,$admin,'High BP at assessment',$small)['success'], 'Missing services cannot create an empty replacement.');
    $unpaidSource = $makeAppointment($today,date('Y-m-d'),'In Progress');
    $result = $model->postponeTreatment($unpaidSource,$admin,'High BP at assessment',$unpaid);
    expectReschedule($result['success'] && $conn->query('SELECT status FROM appointments WHERE appointment_id='.(int)$result['replacement_appointment_id'])->fetchColumn()==='Awaiting Deposit', 'No verified deposit means accepted replacement awaits payment.');
    $laterSource = $makeAppointment($today,date('Y-m-d'),'In Progress',$required);
    $result = $model->postponeTreatment($laterSource,$admin,'High BP at assessment');
    expectReschedule($result['success'] && !$result['replacement_appointment_id'], 'Book later creates no replacement.');
} finally {
    if ($conn->inTransaction()) $conn->rollBack();
    if ($patientId || $otherPatientId) {
        $stmt = $conn->prepare('SELECT appointment_id FROM appointments WHERE patient_id IN (?,?)'); $stmt->execute([$patientId,$otherPatientId]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach (array_reverse($ids) as $id) {
            $conn->prepare('DELETE FROM appointment_email_notifications WHERE appointment_id=?')->execute([$id]);
            $conn->prepare("DELETE FROM audit_logs WHERE entity_type='appointment' AND entity_id=?")->execute([$id]);
            foreach (['appointment_deposits','appointment_services','appointments'] as $table) $conn->prepare("DELETE FROM $table WHERE appointment_id=?")->execute([$id]);
        }
        $conn->prepare('DELETE FROM patients WHERE patient_id IN (?,?)')->execute([$patientId,$otherPatientId]);
    }
    if ($userId) $conn->prepare('DELETE FROM users WHERE id=?')->execute([$userId]);
    foreach ($schedules as $id) $conn->prepare('DELETE FROM schedules WHERE schedule_id=?')->execute([$id]);
    if ($qaClinic) $conn->prepare('DELETE FROM clinics WHERE clinic_id=?')->execute([$qaClinic]);
}
