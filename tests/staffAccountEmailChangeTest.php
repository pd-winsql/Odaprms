<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/conn.php';
require_once __DIR__ . '/../apps/models/accountEmailChangeModel.php';
require_once __DIR__ . '/../apps/models/staffModel.php';
require_once __DIR__ . '/../apps/models/patientModel.php';
function accountExpect(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}
$db=(new Database())->connect();
$ids=[];
$password='TestPassword123';
$token=bin2hex(random_bytes(5));
$changes=new AccountEmailChange($db);
try {
    foreach (['Admin','Dental Assistant','Patient'] as $index=>$role) {
        $email="account-test-{$token}-{$index}@example.invalid";
        $db->prepare('INSERT INTO users(email,password,email_verified_at,user_role) VALUES(?,?,NOW(),?)')->execute([$email,password_hash($password,PASSWORD_DEFAULT),$role]);
        $ids[]=$id=(int)$db->lastInsertId();
        $fields=['firstname'=>'Account','middlename'=>'','lastname'=>'Fixture','phone_number'=>'09123456789','email'=>'bypass@example.invalid'];
        $staff=new Staff($db);
        if ($role === 'Patient') {
            $patient = new Patient($db);
            $patient->createRegisteredPatient($id, [
                'firstname'=>'Account','middlename'=>'','lastname'=>'Patient','suffix'=>'',
                'birthdate'=>'2000-01-01','gender'=>'Prefer not to say','phone_number'=>'09123456789',
            ], $email);
            accountExpect($patient->updateMyAccount($id,$fields)['success'],'Basic details save for Patient.');
            accountExpect($patient->getPatientByUserId($id)['email']===$email,'Patient details cannot bypass email verification.');
        } else {
            accountExpect($staff->updateMyAccount($id,$fields)['success'],'Basic details save for '.$role.'.');
            accountExpect($staff->getMyAccount($id)['login_email']===$email,'Basic details cannot bypass email verification.');
        }
        $new="new-{$email}"; $code='';
        $send=static function($recipient,$otp) use (&$code,$new) { accountExpect($recipient===$new,'Code is addressed to the new email.'); $code=$otp; return true; };
        accountExpect(!$changes->request($id,$new,'wrong',$send)['success'],'Wrong current password is rejected.');
        accountExpect(!$changes->request($id,'invalid',$password,$send)['success'],'Invalid email is rejected.');
        accountExpect($changes->request($id,$new,$password,$send)['success'],'Verification request succeeds for '.$role.'.');
        $emailBeforeVerify = $role === 'Patient' ? (new Patient($db))->getPatientByUserId($id)['email'] : $staff->getMyAccount($id)['login_email'];
        accountExpect($emailBeforeVerify===$email,'Old email remains active before verification.');
        accountExpect(!$changes->request($id,$new,$password,$send)['success'],'Immediate resend is rate-limited.');
        $wrongCode=$code==='111111'?'222222':'111111';
        accountExpect(!$changes->verify($id,$wrongCode,$password)['success'],'Wrong code is rejected.');
        accountExpect($changes->verify($id,$code,$password)['success'],'Correct code updates the account.');
        $savedEmail = $role === 'Patient' ? (new Patient($db))->getPatientByUserId($id)['email'] : $staff->getMyAccount($id)['login_email'];
        accountExpect($savedEmail===$new,'New login email is saved.');
        if ($role !== 'Patient') {
            $stmt=$db->prepare('SELECT email FROM staffs WHERE user_id=?'); $stmt->execute([$id]);
            accountExpect($stmt->fetchColumn()===$new,'Staff contact email stays synchronized.');
        }
        accountExpect(!$changes->verify($id,$code,$password)['success'],'Used code cannot be replayed.');
        $audit=$db->prepare("SELECT old_values,new_values FROM audit_logs WHERE entity_type='user' AND entity_id=? AND action='account_email_changed'"); $audit->execute([$id]);
        $entry=$audit->fetch(PDO::FETCH_ASSOC);
        accountExpect($entry && !str_contains(json_encode($entry),$password) && !str_contains(json_encode($entry),$code),'Audit entry excludes passwords and codes.');
        $next="next-{$email}";
        accountExpect($changes->request($id,$next,$password,static function($e,$c)use(&$code){$code=$c;return true;})['success'],'A later email change can be requested.');
        for($i=0;$i<5;$i++) $changes->verify($id,$code==='111111'?'222222':'111111',$password);
        accountExpect(!$changes->verify($id,$code,$password)['success'],'Five wrong attempts invalidate the code.');
        $db->prepare('DELETE FROM account_email_changes WHERE user_id=?')->execute([$id]);
        $changes->request($id,$next,$password,static function($e,$c)use(&$code){$code=$c;return true;});
        $db->prepare('UPDATE account_email_changes SET expires_at=NOW()-INTERVAL 1 SECOND WHERE user_id=?')->execute([$id]);
        accountExpect(!$changes->verify($id,$code,$password)['success'],'Expired codes are rejected.');
        $db->prepare('DELETE FROM account_email_changes WHERE user_id=?')->execute([$id]);
        $changes->request($id,$next,$password,static function($e,$c)use(&$code){$code=$c;return true;});
        $db->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash('OtherPassword123',PASSWORD_DEFAULT),$id]);
        accountExpect(!$changes->verify($id,$code,'OtherPassword123')['success'],'Password changes invalidate pending email codes.');
        $db->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$id]);
        $db->prepare('DELETE FROM account_email_changes WHERE user_id=?')->execute([$id]);
        accountExpect(!$changes->request($id,$next,$password,fn()=>false)['success'],'Delivery failure leaves email unchanged.');
        accountExpect($changes->pending($id)===null,'Undelivered code is invalidated.');
    }
    $existing=$db->prepare('SELECT email FROM users WHERE id=?'); $existing->execute([$ids[1]]);
    accountExpect(!$changes->request($ids[0],$existing->fetchColumn(),$password,fn()=>true)['success'],'Another account email cannot be claimed.');
} finally {
    foreach($ids as $id) {
        $db->prepare("DELETE FROM audit_logs WHERE entity_type IN ('user','staff','patient') AND performed_by_user_id=?")->execute([$id]);
        $db->prepare('DELETE FROM patients WHERE user_id=?')->execute([$id]);
        $db->prepare('DELETE FROM staffs WHERE user_id=?')->execute([$id]);
        $db->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
    }
}
