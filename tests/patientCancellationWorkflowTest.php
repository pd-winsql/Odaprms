<?php
require_once __DIR__.'/../config/conn.php';
require_once __DIR__.'/../apps/models/appointmentModel.php';
require_once __DIR__.'/../apps/models/patientModel.php';
function cancelExpect(bool $ok,string $label): void { if (!$ok) throw new RuntimeException($label); echo "PASS: $label\n"; }
$conn=(new Database())->connect();
if (!$conn) throw new RuntimeException('Database unavailable.');
$clinic=$user=$patient=$other=$schedule=$targetSchedule=0; $ids=[];
try {
    $settings=new SiteSettingsModel($conn);
    $original=$settings->getSettings();
    $conn->beginTransaction();
    try {
        cancelExpect($settings->updateGroup('cancellation',['minimum_cancellation_notice_days'=>'3'],'Admin'),'Cancellation setting saves independently');
        cancelExpect(CancellationPolicy::days($conn)===3,'Cancellation policy reads the saved setting immediately');
        cancelExpect($settings->getSettings()['minimum_booking_lead_days']===$original['minimum_booking_lead_days'],'Cancellation setting does not change booking notice');
    } finally { $conn->rollBack(); }
    $staff=(int)$conn->query("SELECT id FROM users WHERE user_role='Dental Assistant' LIMIT 1")->fetchColumn();
    $conn->exec("INSERT INTO clinics(clinic_name,clinic_address,clinic_contact,embed_url) VALUES('Cancellation QA','QA only','','')"); $clinic=(int)$conn->lastInsertId();
    $email='cancel-'.bin2hex(random_bytes(5)).'@example.invalid';
    $conn->prepare("INSERT INTO users(email,password,user_role) VALUES(?,?,'Patient')")->execute([$email,password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT)]); $user=(int)$conn->lastInsertId();
    $patients=new Patient($conn);
    $patient=(int)$patients->createPatient($user,'Cancellation','QA','',30,'Male','09123456781',$email,'1996-01-01');
    $other=(int)$patients->createPatient(null,'Other','QA','',30,'Male','09123456782','other@example.invalid','1996-01-01');
    $date=date('Y-m-d',strtotime('+90 days'));
    $conn->prepare("INSERT INTO schedules(clinic_id,sched_date,start_time,end_time,max_appointments) VALUES(?,?,'08:00:00','17:00:00',20)")->execute([$clinic,$date]); $schedule=(int)$conn->lastInsertId();
    $make=function(string $status,int $owner=0) use ($conn,$patient,$clinic,$date,$schedule,&$ids): int {
        $conn->prepare('INSERT INTO appointments(patient_id,clinic_id,date,schedule_id,status) VALUES(?,?,?,?,?)')->execute([$owner?:$patient,$clinic,$date,$schedule,$status]);
        $ids[]=$id=(int)$conn->lastInsertId(); return $id;
    };
    $model=new Appointment($conn);
    $foreign=$make('Confirmed',$other);
    cancelExpect(!$model->updateAppointmentStatus($foreign,'Cancelled',$user,'QA cancellation',true)['success'],'Patient cannot cancel another patient appointment');
    $id=$make('Confirmed');
    cancelExpect(!$model->updateAppointmentStatus($id,'Cancelled',$staff,'QA cancellation',true)['success'],'Staff cannot masquerade as patient cancellation');
    cancelExpect(!$model->updateAppointmentStatus($id,'Completed',$user,'QA cancellation',true)['success'],'Patient cancellation path cannot set another status');
    cancelExpect(!$model->updateAppointmentStatus($id,'Cancelled',$user,'',true)['success'],'Cancellation reason required');
    $conn->prepare("UPDATE appointments SET date=? WHERE appointment_id=?")->execute([date('Y-m-d',strtotime('-1 day')),$id]);
    cancelExpect(!$model->updateAppointmentStatus($id,'Cancelled',$user,'QA cancellation',true)['success'],'Server blocks confirmed appointment after cancellation cutoff');
    cancelExpect($conn->query('SELECT status FROM appointments WHERE appointment_id='.$id)->fetchColumn()==='Confirmed','Rejected cancellation leaves appointment unchanged');
    $conn->prepare('UPDATE appointments SET date=? WHERE appointment_id=?')->execute([$date,$id]);
    $targetDate=date('Y-m-d',strtotime('+91 days'));
    $conn->prepare("INSERT INTO schedules(clinic_id,sched_date,start_time,end_time,max_appointments) VALUES(?,?,'08:00:00','17:00:00',20)")->execute([$clinic,$targetDate]); $targetSchedule=(int)$conn->lastInsertId();
    $conn->prepare("INSERT INTO appointment_reschedule_requests(appointment_id,requested_by_user_id,original_schedule_id,original_clinic_id,original_date,target_schedule_id,target_clinic_id,target_date,reason,lead_days_snapshot,expires_at) VALUES(?,?,?,?,?,?,?,?,?,3,DATE_ADD(NOW(),INTERVAL 1 DAY))")->execute([$id,$user,$schedule,$clinic,$date,$targetSchedule,$clinic,$targetDate,'QA date change']);
    $conn->prepare("INSERT INTO appointment_deposits(appointment_id,amount,status,verified_by_user_id,verified_at) VALUES(?,400,'Verified',?,NOW())")->execute([$id,$staff]);
    cancelExpect($model->updateAppointmentStatus($id,'Cancelled',$user,'Patient cannot attend',true)['success'],'Eligible confirmed cancellation succeeds');
    cancelExpect($conn->query('SELECT status FROM appointment_reschedule_requests WHERE appointment_id='.$id)->fetchColumn()==='Withdrawn','Cancellation releases pending reschedule hold');
    cancelExpect($conn->query('SELECT status FROM appointment_deposits WHERE appointment_id='.$id)->fetchColumn()==='For Refund','Verified deposit remains eligible for staff refund or transfer');
    cancelExpect($conn->query("SELECT performed_by_role FROM audit_logs WHERE entity_id=$id AND entity_type='appointment' ORDER BY audit_log_id DESC LIMIT 1")->fetchColumn()==='Patient','Cancellation audit identifies patient');
    cancelExpect(!$model->updateAppointmentStatus($id,'Cancelled',$user,'Repeated cancellation',true)['success'],'Repeated patient cancellation is rejected');
    foreach (['Pending Review','Awaiting Deposit','Payment Under Review'] as $status) {
        $request=$make($status);
        $conn->prepare('UPDATE appointments SET date=? WHERE appointment_id=?')->execute([date('Y-m-d'),$request]);
        if ($status==='Payment Under Review') $conn->prepare("INSERT INTO appointment_deposits(appointment_id,amount,status) VALUES(?,400,'Under Review')")->execute([$request]);
        cancelExpect($model->updateAppointmentStatus($request,'Cancelled',$user,'Withdraw unconfirmed request',true)['success'],"$status withdrawal allowed without confirmed cutoff");
        if ($status==='Payment Under Review') cancelExpect($conn->query('SELECT status FROM appointment_deposits WHERE appointment_id='.$request)->fetchColumn()==='Expired','Under-review deposit cannot remain active after withdrawal');
    }
    $exception=$make('Confirmed');
    $conn->prepare('UPDATE appointments SET date=? WHERE appointment_id=?')->execute([date('Y-m-d'),$exception]);
    cancelExpect($model->updateAppointmentStatus($exception,'Cancelled',$staff,'Staff exception')['success'],'Staff can still cancel within patient cutoff');
} finally {
    if ($conn->inTransaction()) $conn->rollBack();
    foreach ($ids as $id) {
        $conn->prepare('DELETE FROM appointment_email_notifications WHERE appointment_id=?')->execute([$id]);
        $conn->prepare("DELETE FROM audit_logs WHERE entity_type='appointment' AND entity_id=?")->execute([$id]);
        $conn->prepare('DELETE FROM appointment_deposits WHERE appointment_id=?')->execute([$id]);
        $conn->prepare('DELETE FROM appointment_reschedule_requests WHERE appointment_id=?')->execute([$id]);
        $conn->prepare('DELETE FROM appointments WHERE appointment_id=?')->execute([$id]);
    }
    foreach ([$patient,$other] as $id) if ($id) $conn->prepare('DELETE FROM patients WHERE patient_id=?')->execute([$id]);
    if ($schedule) $conn->prepare('DELETE FROM schedules WHERE schedule_id=?')->execute([$schedule]);
    if ($targetSchedule) $conn->prepare('DELETE FROM schedules WHERE schedule_id=?')->execute([$targetSchedule]);
    if ($user) $conn->prepare('DELETE FROM users WHERE id=?')->execute([$user]);
    if ($clinic) $conn->prepare('DELETE FROM clinics WHERE clinic_id=?')->execute([$clinic]);
}
