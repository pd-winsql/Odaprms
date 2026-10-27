<?php
require_once __DIR__.'/../config/conn.php';
require_once __DIR__.'/../apps/models/billingModel.php';
require_once __DIR__.'/../apps/models/patientModel.php';
require_once __DIR__.'/../apps/helpers/paymentReceiptImage.php';
function receiptExpect(bool $ok,string $label): void { if (!$ok) throw new RuntimeException($label); echo "PASS: $label\n"; }
function receiptSample(): array {
    return ['version'=>1,'billing_id'=>1,'number'=>'SAMPLE-0001','appointment_id'=>990,
        'patient'=>'Juan Dela Cruz','clinic'=>'Alcala Branch','settled_at'=>'2026-10-04 11:30:00',
        'recorded_by'=>'Dr. Aprille Ventura','brand'=>'Dr. Aprille Ventura Clinica Dental',
        'logo'=>base64_encode(file_get_contents(__DIR__.'/../public/assets/site_logo_1785381335.png')),
        'items'=>[['name'=>'Restoration (Fillings)','quantity'=>2,'unit_price'=>700],['name'=>'Cleaning (Prophylaxis)','quantity'=>1,'unit_price'=>500]],
        'total'=>1900,'deposit'=>400,'payment'=>1500,'tendered'=>2000,'change'=>500];
}
if (in_array('--preview',$_SERVER['argv'],true)) {
    $receipt=receiptSample(); $base='http://receipt.test/apps/controllers/paymentReceiptController.php?billing_id=1';
    include __DIR__.'/../apps/views/shared/payment-receipt-preview.php'; exit;
}
if (in_array('--sample',$_SERVER['argv'],true)) {
    $bytes=vdPaymentReceiptPng(receiptSample());
    $output=__DIR__.'/../test-results'; if (!is_dir($output)) mkdir($output,0700,true);
    file_put_contents($output.'/payment-receipt-sample.png',$bytes);
    echo base64_encode($bytes); exit;
}
$conn=(new Database())->connect();
if (!$conn) throw new RuntimeException('Database unavailable.');
$clinic=$user=$patient=$schedule=0; $ids=[];
try {
    $admin=(int)$conn->query("SELECT id FROM users WHERE user_role='Admin' LIMIT 1")->fetchColumn();
    $assistant=(int)$conn->query("SELECT id FROM users WHERE user_role='Dental Assistant' LIMIT 1")->fetchColumn();
    $service=(int)$conn->query('SELECT service_id FROM services WHERE is_active=1 ORDER BY service_id LIMIT 1')->fetchColumn();
    receiptExpect($admin>0 && $assistant>0 && $service>0,'Admin, assistant and service fixtures available');
    $conn->exec("INSERT INTO clinics(clinic_name,clinic_address,clinic_contact,embed_url) VALUES('Receipt QA','QA only','','')"); $clinic=(int)$conn->lastInsertId();
    $email='receipt-'.bin2hex(random_bytes(5)).'@example.invalid';
    $conn->prepare("INSERT INTO users(email,password,user_role) VALUES(?,?,'Patient')")->execute([$email,password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT)]); $user=(int)$conn->lastInsertId();
    $patient=(int)(new Patient($conn))->createPatient($user,'Receipt','QA','',30,'Male','09123456781',$email,'1996-01-01');
    $conn->prepare("INSERT INTO schedules(clinic_id,sched_date,start_time,end_time,max_appointments) VALUES(?,'1997-04-06','08:00:00','17:00:00',20)")->execute([$clinic]); $schedule=(int)$conn->lastInsertId();
    $conn->prepare("INSERT INTO appointments(patient_id,clinic_id,date,schedule_id,status) VALUES(?,?,'1997-04-06',?,'In Progress')")->execute([$patient,$clinic,$schedule]); $ids[]=$id=(int)$conn->lastInsertId();
    $conn->prepare('INSERT INTO appointment_services(appointment_id,service_id,quantity) VALUES(?,?,2)')->execute([$id,$service]);
    $conn->prepare("INSERT INTO appointment_deposits(appointment_id,amount,status,verified_by_user_id,verified_at) VALUES(?,400,'Verified',?,NOW())")->execute([$id,$assistant]);
    $model=new BillingModel($conn); $line=[$service=>['quantity'=>2,'unit_price'=>700]];
    receiptExpect(!$model->settleAndCompleteVisit($id,1400,0,$admin,'',[$service],'',$line)['success'],'Insufficient payment does not settle');
    receiptExpect(!$model->settleAndCompleteVisit($id,1400,2000,$assistant,'',[$service],'',$line)['success'],'Dental assistant cannot settle or issue receipt');
    receiptExpect((int)$conn->query("SELECT COUNT(*) FROM appointment_email_notifications WHERE appointment_id=$id")->fetchColumn()===0,'No email queued before successful Admin settlement');
    $result=$model->settleAndCompleteVisit($id,1400,2000,$admin,'Internal notes excluded',[$service],'',$line);
    receiptExpect($result['success'] && !empty($result['receipt_notification_id']),'Admin final settlement atomically queues receipt');
    $notification=$conn->query('SELECT * FROM appointment_email_notifications WHERE notification_id='.(int)$result['receipt_notification_id'])->fetch(PDO::FETCH_ASSOC);
    $snapshot=json_decode($notification['payload'],true,512,JSON_THROW_ON_ERROR)['receipt'];
    receiptExpect($notification['delivery_status']==='Pending' && $notification['recipient_email']===$email,'Email addressed to patient and awaiting delivery, no SMTP in test');
    receiptExpect($snapshot['total']===1400 && $snapshot['deposit']===400 && $snapshot['payment']===1000 && $snapshot['change']===1000,'Receipt totals, applied deposit, cash and change match settled billing');
    receiptExpect(count($snapshot['items'])===1 && (float)$snapshot['items'][0]['quantity']===2.0 && (float)$snapshot['items'][0]['unit_price']===700.0,'Actual treatment quantity/rate snapshot is itemized');
    receiptExpect(!str_contains(json_encode($snapshot),'Internal notes'),'Internal billing notes not exposed');
    $receipts=new PaymentReceiptModel($conn);
    receiptExpect($receipts->forPatient($snapshot['billing_id'],$user)!==null,'Owner can retrieve receipt');
    receiptExpect($receipts->forPatient($snapshot['billing_id'],$admin)===null,'Other user cannot retrieve patient receipt');
    receiptExpect(!$model->settleAndCompleteVisit($id,1400,2000,$admin,'',[$service],'',$line)['success'],'Repeated settlement rejected');
    receiptExpect((int)$conn->query("SELECT COUNT(*) FROM appointment_email_notifications WHERE appointment_id=$id")->fetchColumn()===1,'Repeated settlement does not duplicate receipt email');
    $bytes=vdPaymentReceiptPng($snapshot);
    receiptExpect(substr($bytes,0,8)==="\x89PNG\r\n\x1a\n",'Real PNG generated');
    receiptExpect($bytes===vdPaymentReceiptPng($snapshot),'Same immutable snapshot generates identical download/email image');
    $endpoint=static function(string $role,int $owner,string $mode='') use($snapshot): string {
        $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/paymentReceiptEndpointFixture.php').' '.escapeshellarg($role).' '.$owner.' '.$snapshot['billing_id'].' '.escapeshellarg($mode);
        return (string)base64_decode(trim((string)shell_exec($command)),true);
    };
    receiptExpect($endpoint('Patient',$user,'download')===$bytes,'Real download controller returns matching PNG to owner');
    receiptExpect(str_contains($endpoint('Patient',$user,'preview'),'receiptZoomIn'),'Real preview controller renders zoom controls');
    receiptExpect($endpoint('Patient',$admin)==='Receipt not found.','Real download controller denies another patient');
    receiptExpect(str_contains($endpoint('Dental Assistant',$assistant),'Forbidden'),'Real download controller denies assistant');
    require_once __DIR__.'/../config/mailer.php';
    $mail=new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->setFrom('clinic@example.invalid'); $mail->addAddress('patient@example.invalid');
    $mail->Subject='QA receipt'; $mail->Body='Payment settled';
    vdAttachPaymentReceipt($mail,['bytes'=>$bytes,'filename'=>$snapshot['number'].'.png']);
    $mail->preSend(); $mime=$mail->getSentMIMEMessage();
    receiptExpect(str_contains($mime,'Content-Type: image/png') && str_contains($mime,$snapshot['number'].'.png'),'Mailer prepares PNG attachment, without SMTP');
    receiptExpect(str_contains(preg_replace('/\s+/','',$mime),base64_encode($bytes)),'Email attachment contains the exact downloadable PNG');
    $long=receiptSample(); $long['patient']=str_repeat('Long patient name ',8); $long['items']=array_fill(0,5,['name'=>str_repeat('Long service name ',8),'quantity'=>2,'unit_price'=>700]);
    $large=vdPaymentReceiptPng($long);
    receiptExpect(getimagesizefromstring($large)[1]>getimagesizefromstring($bytes)[1],'Long names and multiple treatments expand receipt height');
} finally {
    if ($conn->inTransaction()) $conn->rollBack();
    foreach ($ids as $id) {
        $conn->prepare('DELETE FROM appointment_email_notifications WHERE appointment_id=?')->execute([$id]);
        $conn->prepare("DELETE FROM audit_logs WHERE entity_type='appointment' AND entity_id=?")->execute([$id]);
        $conn->prepare('DELETE FROM appointment_billing_items WHERE billing_id IN (SELECT billing_id FROM appointment_billings WHERE appointment_id=?)')->execute([$id]);
        $conn->prepare('DELETE FROM appointment_billings WHERE appointment_id=?')->execute([$id]);
        $conn->prepare('DELETE FROM appointment_services WHERE appointment_id=?')->execute([$id]);
        $conn->prepare('DELETE FROM appointment_deposits WHERE appointment_id=?')->execute([$id]);
        $conn->prepare('DELETE FROM appointments WHERE appointment_id=?')->execute([$id]);
    }
    if ($patient) $conn->prepare('DELETE FROM patients WHERE patient_id=?')->execute([$patient]);
    if ($schedule) $conn->prepare('DELETE FROM schedules WHERE schedule_id=?')->execute([$schedule]);
    if ($user) $conn->prepare('DELETE FROM users WHERE id=?')->execute([$user]);
    if ($clinic) $conn->prepare('DELETE FROM clinics WHERE clinic_id=?')->execute([$clinic]);
}
