<?php
require_once __DIR__ . '/../config/conn.php';
require_once __DIR__ . '/../apps/models/appointmentModel.php';
require_once __DIR__ . '/../apps/models/depositModel.php';
require_once __DIR__ . '/../apps/models/logbookModel.php';
require_once __DIR__ . '/../apps/models/patientModel.php';
require_once __DIR__ . '/../apps/models/billingModel.php';
require_once __DIR__ . '/../apps/models/clinicModel.php';

function expectTrue($condition, $message) { if (!$condition) throw new RuntimeException($message); echo "PASS: {$message}\n"; }
$conn = (new Database())->connect();
expectTrue($conn->query('SELECT DATABASE()')->fetchColumn() === 'av-clinica-dental-feature', 'Tests are isolated to the feature database.');
$appointments = new Appointment($conn); $deposits = new DepositModel($conn); $logbook = new LogbookModel($conn); $patients = new Patient($conn); $billings = new BillingModel($conn); $clinics = new Clinic($conn);
$createdAppointments = []; $createdSchedules = []; $patientId = null; $createdClinicId = null; $registeredPatientId = null; $registeredUserId = null;
try {
    $clinicId = (int) $conn->query('SELECT clinic_id FROM clinics ORDER BY clinic_id LIMIT 1')->fetchColumn();
    $serviceId = (int) $conn->query('SELECT service_id FROM services WHERE is_active=1 ORDER BY service_id LIMIT 1')->fetchColumn();
    $staffId = (int) $conn->query("SELECT id FROM users WHERE user_role IN ('Admin','Dental Assistant') ORDER BY id LIMIT 1")->fetchColumn();
    expectTrue($clinicId && $serviceId && $staffId, 'Clinic, service, and staff fixtures are available.');
    $testClinicName = 'Workflow Clinic ' . bin2hex(random_bytes(4));
    $createdClinicId = $clinics->createClinic($testClinicName, 'Workflow Test Address', '09123456789', null);
    expectTrue($createdClinicId > 0 && $clinics->getClinicById($createdClinicId)['clinic_name'] === $testClinicName, 'Clinic creation stores the new clinic details.');
    $registrationEmail = 'registration-' . bin2hex(random_bytes(4)) . '@example.invalid';
    $conn->prepare("INSERT INTO users (email,password,email_verified_at,user_role) VALUES (:email,:password,NOW(),'Patient')")
        ->execute([':email'=>$registrationEmail, ':password'=>password_hash('Workflow123', PASSWORD_DEFAULT)]);
    $registeredUserId = (int) $conn->lastInsertId();
    $registeredIdentity = ['firstname'=>'Registered','middlename'=>'','lastname'=>'Patient','suffix'=>'','birthdate'=>'2005-04-09','gender'=>'Female','phone_number'=>'09999999999'];
    $registeredPatientId = $patients->createRegisteredPatient($registeredUserId, $registeredIdentity, $registrationEmail);
    $registeredPatient = $patients->getPatient($registeredPatientId);
    $expectedAge = (new DateTimeImmutable('2005-04-09'))->diff(new DateTimeImmutable('today'))->y;
    expectTrue($registeredPatient['gender'] === 'Female' && (int)$registeredPatient['age'] === $expectedAge, 'Registration stores gender and calculates age from birthdate.');
    $getSchedule = function(string $date) use ($conn, $clinicId, &$createdSchedules) {
        $stmt=$conn->prepare('SELECT schedule_id,clinic_id,sched_date FROM schedules WHERE sched_date=:date LIMIT 1'); $stmt->execute([':date'=>$date]); $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { $stmt=$conn->prepare('INSERT INTO schedules(clinic_id,sched_date,max_appointments) VALUES(:clinic,:date,50)'); $stmt->execute([':clinic'=>$clinicId,':date'=>$date]); $id=(int)$conn->lastInsertId(); $createdSchedules[]=$id; return ['schedule_id'=>$id,'clinic_id'=>$clinicId,'sched_date'=>$date]; }
        return $row;
    };
    $todaySchedule=$getSchedule(date('Y-m-d')); $futureSchedule=$getSchedule(date('Y-m-d', strtotime('+1 day')));
    $identity=['firstname'=>'Workflow','middlename'=>'','lastname'=>'Patient','suffix'=>'','birthdate'=>'2000-01-01','phone_number'=>'09123456789'];
    $patientId=$patients->createPatient(null,'Workflow','Patient','',26,'Prefer not to say','09123456789','workflow-test@example.invalid','2000-01-01');
    $conn->prepare('UPDATE patients SET identity_match_key=:identity WHERE patient_id=:id')->execute([':identity'=>Patient::identityMatchKey($identity),':id'=>$patientId]);
    expectTrue((bool)$patientId, 'Temporary patient created.');

    $booking=$appointments->bookAppointment($patientId,$todaySchedule['clinic_id'],[$serviceId],$todaySchedule['sched_date'],$todaySchedule['schedule_id']);
    expectTrue(($booking['status']??'')==='Pending Review', 'Booking starts in Pending Review without a deposit.'); $createdAppointments[]=(int)$booking['appointment_id'];
    $appointmentServiceDetails=$appointments->getServiceDetailsForAppointments([(int)$booking['appointment_id']]);
    expectTrue(count($appointmentServiceDetails[(int)$booking['appointment_id']]??[])===1,'Appointment details include the selected service card data.');
    $hasDeposit=(int)$conn->query('SELECT COUNT(*) FROM appointment_deposits WHERE appointment_id='.(int)$booking['appointment_id'])->fetchColumn(); expectTrue($hasDeposit===0,'No deposit exists before staff acceptance.');
    $accepted=$appointments->updateAppointmentStatus($booking['appointment_id'],'Awaiting Deposit',$staffId); expectTrue($accepted['success'],'Staff accepts the request for payment.');
    $deposit=$conn->query('SELECT * FROM appointment_deposits WHERE appointment_id='.(int)$booking['appointment_id'])->fetch(PDO::FETCH_ASSOC);
    expectTrue((float)$deposit['amount']===400.0 && $deposit['status']==='Awaiting Submission','Acceptance creates the fixed ₱400 deposit and deadline.');
    $reference='TEST'.date('YmdHis').random_int(100,999);
    $submitted=$deposits->submitReceipt($booking['appointment_id'],$reference,'storage/payment_receipts/test.jpg','image/jpeg'); expectTrue($submitted['success'],'Receipt submission pauses expiry and enters payment review.');
    $staffAppointment=array_values(array_filter($appointments->getAllUpcomingWithStatus(),fn($row)=>(int)$row['appointment_id']===(int)$booking['appointment_id']))[0]??null;
    expectTrue($staffAppointment&&$staffAppointment['deposit_status']==='Under Review'&&!empty($staffAppointment['has_receipt']),'The appointment workspace includes the submitted deposit and receipt context.');
    $depositRecord=array_values(array_filter($deposits->getAllRecords(),fn($row)=>(int)$row['appointment_id']===(int)$booking['appointment_id']))[0]??null;
    expectTrue($depositRecord&&$depositRecord['deposit_status']==='Under Review','The read-only deposit records query includes the pending payment.');
    $verified=$deposits->verify((int)$deposit['deposit_id'],$staffId); expectTrue($verified['success'] && str_starts_with($verified['appointment_code'],'AVC-'),'Verification confirms the appointment and generates its code.');
    $matches=$logbook->lookupToday($verified['appointment_code']); expectTrue(count($matches)===1,'The appointment code finds today’s confirmed appointment.');
    expectTrue(count($logbook->lookupToday('Workflow Patient'))===0,'Patient names cannot be used to search Today’s Logbook for check-in.');
    $checkin=$logbook->checkIn($booking['appointment_id'],$staffId,'Code'); expectTrue($checkin['success'] && $checkin['status']==='Profile Required','First-time patient is checked in with Profile Required.');
    $profile=['firstname'=>'Workflow','lastname'=>'Patient','middlename'=>'','birthdate'=>'2000-01-01','age'=>26,'gender'=>'Prefer not to say','civil_status'=>'Single','phone_number'=>'09123456789','email'=>'workflow-test@example.invalid','home_address'=>'Test','work_address'=>'','occupation'=>'Tester','office_contact'=>'','fb_account'=>'','guardian_name'=>'','guardian_contact'=>'','physician_name'=>'','physician_contact'=>'','physician_address'=>'','previous_dentist'=>'','last_dental_visit'=>'','treatment_done'=>'','reason_for_visit'=>'Checkup','referred_by'=>'','good_health'=>1,'medical_condition'=>0,'medical_condition_detail'=>'','serious_illness'=>0,'serious_illness_detail'=>'','hospitalized'=>0,'hospitalized_detail'=>'','medication'=>0,'medication_detail'=>'','smoke'=>0,'alcohol'=>0,'drugs'=>0,'allergy'=>0,'allergy_detail'=>'','pregnant'=>0,'nursing'=>0,'birth_control'=>0,'blood_type'=>'','blood_pressure'=>'','cond_others'=>'','conditions'=>[],'consent_name'=>'Workflow Patient','consent_for'=>'myself'];
    expectTrue($patients->completeProfileByStaff($patientId,$profile,$staffId)['success'],'Staff completes the entire patient profile.');
    expectTrue($appointments->updateAppointmentStatus($booking['appointment_id'],'In Progress',$staffId)['success'],'Ready checked-in appointment can start treatment.');
    $shortPayment=$billings->settleAndCompleteVisit($booking['appointment_id'],2000,1500,$staffId);
    expectTrue(!$shortPayment['success']&&$conn->query('SELECT status FROM appointments WHERE appointment_id='.(int)$booking['appointment_id'])->fetchColumn()==='In Progress','Insufficient cash leaves the visit in progress and creates no billing.');
    $settled=$billings->settleAndCompleteVisit($booking['appointment_id'],2000,2000,$staffId,'Paid at the front desk.');
    expectTrue($settled['success']&&$settled['payment_status']==='Paid'&&(float)$settled['deposit_applied']===400.0&&(float)$settled['amount_due']===1600.0&&(float)$settled['change']===400.0,'Final billing deducts the deposit, calculates change, and records full payment.');
    expectTrue($conn->query('SELECT status FROM appointments WHERE appointment_id='.(int)$booking['appointment_id'])->fetchColumn()==='Completed','Payment and visit completion are committed together.');
    expectTrue(!$billings->settleAndCompleteVisit($booking['appointment_id'],2000,1600,$staffId)['success'],'A completed visit cannot be billed twice.');
    $billingRecord=array_values(array_filter($billings->getStaffBillings(),fn($row)=>(int)$row['appointment_id']===(int)$booking['appointment_id']))[0]??null;
    expectTrue($billingRecord&&$billingRecord['payment_status']==='Paid','The read-only billing records query includes the completed settlement.');

    $expiring=$appointments->bookAppointment($patientId,$futureSchedule['clinic_id'],[$serviceId],$futureSchedule['sched_date'],$futureSchedule['schedule_id']); $createdAppointments[]=(int)$expiring['appointment_id'];
    $appointments->updateAppointmentStatus($expiring['appointment_id'],'Awaiting Deposit',$staffId);
    $conn->prepare('UPDATE appointments SET payment_deadline_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE appointment_id=:id')->execute([':id'=>$expiring['appointment_id']]);
    expectTrue($deposits->expireUnpaidAppointments()>=1,'Unpaid accepted request expires after its deadline.');
} finally {
    foreach(array_reverse($createdAppointments) as $id){$conn->prepare("DELETE FROM audit_logs WHERE entity_type='appointment' AND entity_id=:id")->execute([':id'=>$id]);foreach(['appointment_billings','appointment_checkins','appointment_deposits','appointment_services'] as $table)$conn->prepare("DELETE FROM {$table} WHERE appointment_id=:id")->execute([':id'=>$id]);$conn->prepare('DELETE FROM appointments WHERE appointment_id=:id')->execute([':id'=>$id]);}
    if($patientId){$conn->prepare("DELETE FROM audit_logs WHERE entity_type='patient' AND entity_id=:id")->execute([':id'=>$patientId]);foreach(['patient_duplicate_reviews','patient_conditions','patient_consent','patient_dental_history','patient_medical_history'] as $table){$column=$table==='patient_duplicate_reviews'?'new_patient_id':'patient_id';$conn->prepare("DELETE FROM {$table} WHERE {$column}=:id")->execute([':id'=>$patientId]);}$conn->prepare('DELETE FROM patients WHERE patient_id=:id')->execute([':id'=>$patientId]);}
    foreach($createdSchedules as $id)$conn->prepare('DELETE FROM schedules WHERE schedule_id=:id')->execute([':id'=>$id]);
    if($createdClinicId)$conn->prepare('DELETE FROM clinics WHERE clinic_id=:id')->execute([':id'=>$createdClinicId]);
    if($registeredPatientId)$conn->prepare('DELETE FROM patients WHERE patient_id=:id')->execute([':id'=>$registeredPatientId]);
    if($registeredUserId)$conn->prepare('DELETE FROM users WHERE id=:id')->execute([':id'=>$registeredUserId]);
}
echo "Feature workflow test completed.\n";
