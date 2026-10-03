<?php
declare(strict_types=1);
require_once __DIR__ . '/auditLogModel.php';

class AccountEmailChange {
    public function __construct(private PDO $db) {}

    public function pending(int $userId): ?array {
        $stmt = $this->db->prepare('SELECT new_email, expires_at FROM account_email_changes WHERE user_id=? AND expires_at>NOW() AND attempts<5');
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function fail(string $message, string $field = ''): array {
        return ['success'=>false, 'message'=>$message, 'field'=>$field];
    }

    public function request(int $userId, string $email, string $password, callable $send): array {
        $email = trim($email);
        if (strlen($email)>255 || !filter_var($email,FILTER_VALIDATE_EMAIL)) return $this->fail('Enter a valid email address.','email');
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("SELECT * FROM users WHERE id=? AND user_role IN ('Admin','Dental Assistant','Patient') FOR UPDATE");
            $stmt->execute([$userId]); $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user || !password_verify($password,$user['password'])) {
                $this->db->rollBack(); return $this->fail('Current password is incorrect.','current_password');
            }
            if (strcasecmp($email,$user['email'])===0) {
                $this->db->rollBack(); return $this->fail('Choose an email different from your current address.','email');
            }
            $stmt = $this->db->prepare('SELECT id FROM users WHERE LOWER(email)=LOWER(?) AND id<>?');
            $stmt->execute([$email,$userId]);
            if ($stmt->fetchColumn()) { $this->db->rollBack(); return $this->fail('This email is already in use.','email'); }
            $stmt = $this->db->prepare('SELECT sent_at > NOW() - INTERVAL 60 SECOND FROM account_email_changes WHERE user_id=?');
            $stmt->execute([$userId]);
            if ($stmt->fetchColumn()) { $this->db->rollBack(); return $this->fail('Wait 60 seconds before requesting another code.'); }
            $code = (string) random_int(100000,999999);
            $token = bin2hex(random_bytes(32));
            $stmt = $this->db->prepare('INSERT INTO account_email_changes(user_id,new_email,old_email,code_hash,password_fingerprint,request_token,expires_at,sent_at,attempts)
                VALUES(?,?,?,?,?,?,NOW()+INTERVAL 10 MINUTE,NOW(),0)
                ON DUPLICATE KEY UPDATE new_email=VALUES(new_email),old_email=VALUES(old_email),code_hash=VALUES(code_hash),password_fingerprint=VALUES(password_fingerprint),request_token=VALUES(request_token),expires_at=VALUES(expires_at),sent_at=NOW(),attempts=0');
            $stmt->execute([$userId,$email,$user['email'],password_hash($code,PASSWORD_DEFAULT),hash('sha256',$user['password']),$token]);
            $this->db->commit();
            try { $delivered = $send($email,$code); } catch (Throwable $e) { $delivered = false; error_log('Account email code delivery failed.'); }
            if (!$delivered) {
                // Keep the cooldown, but invalidate the undelivered challenge.
                $this->db->prepare('UPDATE account_email_changes SET expires_at=NOW() WHERE user_id=? AND request_token=?')->execute([$userId,$token]);
                return $this->fail('Unable to send the verification email. Your current email is unchanged. Try again shortly.');
            }
            return ['success'=>true,'message'=>'Verification code sent. Your current email stays active until verified.','pending_email'=>$email];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('Account email request error: ' . $e->getMessage());
            return $this->fail('Unable to request an email change. Please try again.');
        }
    }

    public function verify(int $userId, string $code, string $password): array {
        if (!preg_match('/^[0-9]{6}$/',$code)) return $this->fail('Enter the six-digit verification code.','code');
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("SELECT * FROM users WHERE id=? AND user_role IN ('Admin','Dental Assistant','Patient') FOR UPDATE");
            $stmt->execute([$userId]); $user=$stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = $this->db->prepare('SELECT *, expires_at>NOW() AS active FROM account_email_changes WHERE user_id=? FOR UPDATE');
            $stmt->execute([$userId]); $change=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user || !$change || !$change['active'] || $change['attempts']>=5
                || !hash_equals($change['password_fingerprint'],hash('sha256',$user['password'])) || $change['old_email']!==$user['email']) {
                $this->db->rollBack(); return $this->fail('This verification request is expired or no longer valid. Request a new code.','code');
            }
            if (!password_verify($password,$user['password']) || !password_verify($code,$change['code_hash'])) {
                $this->db->prepare('UPDATE account_email_changes SET attempts=attempts+1 WHERE user_id=?')->execute([$userId]);
                $this->db->commit();
                return $this->fail('Incorrect password or verification code. After five failed attempts, request a new code.','code');
            }
            $stmt = $this->db->prepare('SELECT id FROM users WHERE LOWER(email)=LOWER(?) AND id<>?');
            $stmt->execute([$change['new_email'],$userId]);
            if ($stmt->fetchColumn()) { $this->db->rollBack(); return $this->fail('This email is already in use. Request a code for another address.','email'); }
            $this->db->prepare('UPDATE users SET email=?,email_verified_at=NOW() WHERE id=?')->execute([$change['new_email'],$userId]);
            if (in_array($user['user_role'], ['Admin', 'Dental Assistant'], true)) {
                $this->db->prepare('UPDATE staffs SET email=? WHERE user_id=?')->execute([$change['new_email'],$userId]);
            }
            $this->db->prepare('DELETE FROM account_email_changes WHERE user_id=?')->execute([$userId]);
            (new AuditLog($this->db))->recordForUser('user',$userId,'account_email_changed','Changed own verified account email.', ['email'=>$user['email']], ['email'=>$change['new_email']],$userId);
            $this->db->commit();
            return ['success'=>true,'message'=>'Email updated. Use the new address the next time you sign in.','email'=>$change['new_email']];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('Account email verification error: ' . $e->getMessage());
            return $this->fail('Unable to update your email. Please try again.');
        }
    }
}
