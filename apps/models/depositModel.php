<?php

require_once __DIR__ . '/auditLogModel.php';
require_once __DIR__ . '/emailNotificationModel.php';
require_once __DIR__ . '/../helpers/paymentSettings.php';

class DepositModel {
    private $conn;
    private $auditLog;
    private $emailNotifications;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->auditLog = new AuditLog($conn);
        $this->emailNotifications = new EmailNotificationModel($conn);
    }

    private function generateAppointmentCode(): string {
        do {
            $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            $suffix = '';
            for ($i = 0; $i < 6; $i++) $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            $code = 'AVC-' . $suffix;
            $stmt = $this->conn->prepare("SELECT 1 FROM appointments WHERE appointment_code = :code LIMIT 1");
            $stmt->execute([':code' => $code]);
        } while ($stmt->fetchColumn());
        return $code;
    }

    public function expireUnpaidAppointments(): int {
        try {
            $this->conn->beginTransaction();
            $stmt = $this->conn->query("
                SELECT a.appointment_id, a.status
                FROM appointments a
                JOIN appointment_deposits d ON d.appointment_id = a.appointment_id
                WHERE a.status = 'Awaiting Deposit'
                    AND d.status IN ('Awaiting Submission', 'Rejected')
                    AND COALESCE(d.resubmission_deadline_at, a.payment_deadline_at) <= NOW()
                FOR UPDATE
            ");
            $expired = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$expired) {
                $this->conn->commit();
                return 0;
            }

            $appointmentIds = array_map('intval', array_column($expired, 'appointment_id'));
            $placeholders = implode(',', array_fill(0, count($appointmentIds), '?'));

            $depositStmt = $this->conn->prepare("
                UPDATE appointment_deposits
                SET status = 'Expired',
                    rejection_reason = 'Payment submission deadline expired.'
                WHERE appointment_id IN ({$placeholders})
            ");
            $depositStmt->execute($appointmentIds);

            $appointmentStmt = $this->conn->prepare("
                UPDATE appointments
                SET status = 'Cancelled',
                    cancelled_at = NOW(),
                    cancellation_reason = 'Payment submission deadline expired.'
                WHERE appointment_id IN ({$placeholders})
            ");
            $appointmentStmt->execute($appointmentIds);

            $actor = [
                'user_id' => null,
                'name' => 'System',
                'role' => 'System',
                'source' => 'System',
            ];
            foreach ($expired as $row) {
                $this->auditLog->record(
                    'appointment',
                    (int) $row['appointment_id'],
                    'status_changed',
                    "Cancelled appointment #{$row['appointment_id']} after its payment deadline expired.",
                    ['status' => $row['status']],
                    ['status' => 'Cancelled'],
                    $actor
                );
            }

            $this->conn->commit();
            return count($expired);
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('expireUnpaidAppointments error: ' . $e->getMessage());
            return 0;
        }
    }

    public function getPaymentContext($appointmentId, $patientId = null, $token = null) {
        $conditions = ['a.appointment_id = :appointment_id'];
        $params = [':appointment_id' => $appointmentId];

        if ($patientId !== null) {
            $conditions[] = 'a.patient_id = :patient_id';
            $params[':patient_id'] = $patientId;
        } elseif ($token !== null && $token !== '') {
            $conditions[] = 'a.payment_access_token_hash = :token_hash';
            $params[':token_hash'] = hash('sha256', $token);
        } else {
            return false;
        }

        $stmt = $this->conn->prepare("
            SELECT
                a.appointment_id,
                a.patient_id,
                a.date,
                a.status AS appointment_status,
                a.payment_deadline_at,
                overview.firstname,
                overview.lastname,
                overview.email,
                overview.clinic_name,
                payment.deposit_id,
                payment.deposit_amount AS amount,
                payment.gcash_reference,
                payment.receipt_path,
                payment.deposit_status,
                payment.submitted_at,
                payment.verified_at,
                payment.payment_rejection_reason AS rejection_reason,
                payment.resubmission_deadline_at,
                overview.service_name
            FROM appointments a
            JOIN vw_appointment_overview overview
                ON overview.appointment_id = a.appointment_id
            JOIN vw_appointment_payment_summary payment
                ON payment.appointment_id = a.appointment_id
                AND payment.deposit_id IS NOT NULL
            WHERE " . implode(' AND ', $conditions) . "
            LIMIT 1
        ");
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getPatientDeposits($patientId): array {
        $stmt = $this->conn->prepare("
            SELECT
                a.appointment_id,
                a.date,
                a.status AS appointment_status,
                a.payment_deadline_at,
                a.clinic_name,
                payment.deposit_id,
                payment.deposit_amount AS amount,
                payment.gcash_reference,
                payment.deposit_status,
                payment.submitted_at,
                payment.verified_at,
                payment.payment_rejection_reason AS rejection_reason,
                payment.resubmission_deadline_at,
                a.service_name,
                a.created_at
            FROM vw_appointment_overview a
            JOIN vw_appointment_payment_summary payment
                ON payment.appointment_id = a.appointment_id
                AND payment.deposit_id IS NOT NULL
            WHERE a.patient_id = :patient_id
            ORDER BY a.created_at DESC, payment.deposit_id DESC
        ");
        $stmt->execute([':patient_id' => $patientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function submitReceipt($appointmentId, $reference, $receiptPath, $receiptMime): array {
        try {
            $this->conn->beginTransaction();
            $stmt = $this->conn->prepare("
                SELECT d.deposit_id, d.status, d.receipt_path,
                       COALESCE(d.resubmission_deadline_at, a.payment_deadline_at) AS effective_deadline,
                       a.status AS appointment_status
                FROM appointment_deposits d
                JOIN appointments a ON a.appointment_id = d.appointment_id
                WHERE d.appointment_id = :appointment_id
                FOR UPDATE
            ");
            $stmt->execute([':appointment_id' => $appointmentId]);
            $deposit = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$deposit || $deposit['appointment_status'] !== 'Awaiting Deposit') {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'This appointment is not awaiting payment.'];
            }
            if (!in_array($deposit['status'], ['Awaiting Submission', 'Rejected'], true)) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'This payment cannot accept another receipt.'];
            }
            if (strtotime($deposit['effective_deadline']) <= time()) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'The payment deadline has expired.'];
            }

            $duplicate = $this->conn->prepare("
                SELECT deposit_id
                FROM appointment_deposits
                WHERE gcash_reference = :reference
                  AND deposit_id <> :deposit_id
                LIMIT 1
            ");
            $duplicate->execute([
                ':reference' => $reference,
                ':deposit_id' => $deposit['deposit_id'],
            ]);
            if ($duplicate->fetchColumn()) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'This GCash reference number has already been submitted.'];
            }

            $update = $this->conn->prepare("
                UPDATE appointment_deposits
                SET gcash_reference = :reference,
                    receipt_path = :receipt_path,
                    receipt_mime = :receipt_mime,
                    status = 'Under Review',
                    submitted_at = NOW(),
                    rejection_reason = NULL,
                    resubmission_deadline_at = NULL
                WHERE deposit_id = :deposit_id
            ");
            $update->execute([
                ':reference' => $reference,
                ':receipt_path' => $receiptPath,
                ':receipt_mime' => $receiptMime,
                ':deposit_id' => $deposit['deposit_id'],
            ]);
            $this->conn->prepare("UPDATE appointments SET status = 'Payment Under Review' WHERE appointment_id = :appointment_id")
                ->execute([':appointment_id' => $appointmentId]);

            $this->conn->commit();
            return [
                'success' => true,
                'old_receipt_path' => $deposit['receipt_path'],
                'message' => 'Payment proof submitted for verification.',
            ];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('submitReceipt error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to submit the payment proof.'];
        }
    }

    public function getPendingReviews(): array {
        $stmt = $this->conn->query("
            SELECT
                payment.deposit_id,
                a.appointment_id,
                payment.deposit_amount AS amount,
                payment.gcash_reference,
                payment.receipt_mime,
                payment.submitted_at,
                a.firstname,
                a.lastname,
                a.email,
                a.clinic_name,
                a.date,
                a.service_name
            FROM vw_appointment_overview a
            JOIN vw_appointment_payment_summary payment
                ON payment.appointment_id = a.appointment_id
            WHERE payment.deposit_status = 'Under Review'
              AND a.status = 'Payment Under Review'
            ORDER BY payment.submitted_at ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllRecords(): array {
        $stmt = $this->conn->query("
            SELECT
                payment.deposit_id,
                a.appointment_id,
                payment.deposit_amount AS amount,
                payment.gcash_reference,
                payment.deposit_status,
                payment.submitted_at,
                payment.verified_at,
                payment.payment_rejection_reason AS rejection_reason,
                payment.refunded_at,
                payment.has_receipt,
                a.date,
                a.status AS appointment_status,
                a.appointment_code,
                a.firstname,
                a.lastname,
                a.email,
                a.clinic_name,
                payment.payment_verified_by AS verified_by,
                payment.payment_verified_by_role AS verified_by_role,
                a.service_name
            FROM vw_appointment_overview a
            JOIN vw_appointment_payment_summary payment
                ON payment.appointment_id = a.appointment_id
                AND payment.deposit_id IS NOT NULL
            ORDER BY COALESCE(payment.submitted_at, a.created_at) DESC, payment.deposit_id DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPendingReviewCount(): int {
        return (int) $this->conn->query("
            SELECT COUNT(*)
            FROM appointment_deposits d
            JOIN appointments a ON a.appointment_id = d.appointment_id
            WHERE d.status = 'Under Review'
              AND a.status = 'Payment Under Review'
        ")->fetchColumn();
    }

    public function getReceiptForStaff($depositId) {
        $stmt = $this->conn->prepare("
            SELECT receipt_path, receipt_mime
            FROM appointment_deposits
            WHERE deposit_id = :deposit_id
              AND receipt_path IS NOT NULL
        ");
        $stmt->execute([':deposit_id' => $depositId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function verify($depositId, $userId): array {
        try {
            $this->conn->beginTransaction();
            $stmt = $this->conn->prepare("
                SELECT d.appointment_id, d.status AS deposit_status, a.status AS appointment_status
                FROM appointment_deposits d
                JOIN appointments a ON a.appointment_id = d.appointment_id
                WHERE d.deposit_id = :deposit_id
                FOR UPDATE
            ");
            $stmt->execute([':deposit_id' => $depositId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || $row['deposit_status'] !== 'Under Review' || $row['appointment_status'] !== 'Payment Under Review') {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'This payment is no longer awaiting verification.'];
            }

            $actor = $this->auditLog->getUserActor($userId);
            if (!$actor) throw new RuntimeException('Verifier account not found.');

            $depositUpdate = $this->conn->prepare("
                UPDATE appointment_deposits
                SET status = 'Verified', verified_by_user_id = :user_id, verified_at = NOW()
                WHERE deposit_id = :deposit_id
            ");
            $depositUpdate->execute([':user_id' => $userId, ':deposit_id' => $depositId]);

            $appointmentCode = $this->generateAppointmentCode();
            $appointmentUpdate = $this->conn->prepare("
                UPDATE appointments
                SET status = 'Confirmed', confirmed_at = NOW(),
                    appointment_code = :appointment_code, code_generated_at = NOW()
                WHERE appointment_id = :appointment_id
            ");
            $appointmentUpdate->execute([':appointment_id' => $row['appointment_id'], ':appointment_code' => $appointmentCode]);

            $audit = $this->auditLog->record(
                'appointment',
                (int) $row['appointment_id'],
                'status_changed',
                "Verified the deposit and confirmed appointment #{$row['appointment_id']}.",
                ['status' => 'Payment Under Review', 'deposit_status' => 'Under Review'],
                ['status' => 'Confirmed', 'deposit_status' => 'Verified', 'appointment_code' => $appointmentCode],
                $actor
            );

            // Store the confirmation email before commit so a confirmed
            // payment always has a durable notification for browser delivery.
            $notification = $this->emailNotifications->enqueueAppointmentTemplate(
                (int) $row['appointment_id'],
                'appointment_confirmed_code',
                $appointmentCode,
                'audit:' . $audit['audit_log_id'] . ':appointment_confirmed_code'
            );

            $this->conn->commit();
            return [
                'success' => true,
                'message' => 'Payment verified and appointment confirmed.',
                'appointment_code' => $appointmentCode,
                'appointment_id' => (int) $row['appointment_id'],
                'appointment' => ['id' => (int) $row['appointment_id'], 'status' => 'Confirmed'],
                'deposit_status' => 'Verified',
                'notification' => $notification,
                'audit' => [
                    'performed_by_name' => $actor['name'],
                    'performed_by_role' => $actor['role'],
                    'performed_at' => $audit['performed_at'],
                ],
            ];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('verify deposit error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to verify this payment.'];
        }
    }

    public function reject($depositId, $userId, $reason): array {
        try {
            $this->conn->beginTransaction();
            $settings = $this->conn->query("SELECT payment_deadline_minutes FROM site_settings WHERE id = 1")
                ->fetch(PDO::FETCH_ASSOC) ?: [];
            $minutes = max(1, (int) ($settings['payment_deadline_minutes'] ?? 480));

            $stmt = $this->conn->prepare("
                SELECT d.appointment_id, d.status, d.amount, a.status AS appointment_status
                FROM appointment_deposits d
                JOIN appointments a ON a.appointment_id = d.appointment_id
                WHERE d.deposit_id = :deposit_id
                FOR UPDATE
            ");
            $stmt->execute([':deposit_id' => $depositId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || $row['status'] !== 'Under Review' || $row['appointment_status'] !== 'Payment Under Review') {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'This payment is no longer awaiting verification.'];
            }

            $actor = $this->auditLog->getUserActor($userId);
            if (!$actor) throw new RuntimeException('Verifier account not found.');

            $update = $this->conn->prepare("
                UPDATE appointment_deposits
                SET status = 'Rejected',
                    rejection_reason = :reason,
                    resubmission_deadline_at = DATE_ADD(NOW(), INTERVAL {$minutes} MINUTE),
                    verified_by_user_id = :user_id,
                    verified_at = NOW()
                WHERE deposit_id = :deposit_id
            ");
            $update->execute([
                ':reason' => $reason,
                ':user_id' => $userId,
                ':deposit_id' => $depositId,
            ]);
            $this->conn->prepare("
                UPDATE appointments
                SET status = 'Awaiting Deposit',
                    payment_deadline_at = DATE_ADD(NOW(), INTERVAL {$minutes} MINUTE)
                WHERE appointment_id = :appointment_id
            ")->execute([':appointment_id' => $row['appointment_id']]);

            $audit = $this->auditLog->record(
                'appointment',
                (int) $row['appointment_id'],
                'payment_rejected',
                "Rejected the payment proof for appointment #{$row['appointment_id']}.",
                ['deposit_status' => 'Under Review'],
                ['deposit_status' => 'Rejected', 'reason' => $reason],
                $actor
            );

            // A rejected receipt may be submitted again later. Using the audit
            // id gives each genuine review its own deduplicated notification.
            $notification = $this->emailNotifications->enqueueAppointmentTemplate(
                (int) $row['appointment_id'],
                'payment_rejected',
                $reason,
                'audit:' . $audit['audit_log_id'] . ':payment_rejected'
            );

            $this->conn->commit();
            return [
                'success' => true,
                'message' => 'Payment rejected. The patient has ' . vdFormatDurationMinutes($minutes) . ' to resubmit the ' . vdFormatPesoAmount((float) $row['amount']) . ' deposit.',
                'appointment_id' => (int) $row['appointment_id'],
                'appointment' => ['id' => (int) $row['appointment_id'], 'status' => 'Awaiting Deposit'],
                'deposit_status' => 'Rejected',
                'notification' => $notification,
                'audit' => [
                    'performed_by_name' => $actor['name'],
                    'performed_by_role' => $actor['role'],
                    'performed_at' => $audit['performed_at'],
                ],
            ];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('reject deposit error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to reject this payment.'];
        }
    }

    public function extendDeadline(int $appointmentId, int $userId, string $reason): array {
        if (strlen(trim($reason)) < 3) return ['success'=>false,'message'=>'An extension reason is required.'];
        try {
            $this->conn->beginTransaction();
            $minutes=max(1,(int)($this->conn->query('SELECT payment_deadline_minutes FROM site_settings WHERE id=1')->fetchColumn() ?: 480));
            $stmt=$this->conn->prepare("SELECT a.status,d.deposit_id,d.status deposit_status,d.amount FROM appointments a JOIN appointment_deposits d ON d.appointment_id=a.appointment_id WHERE a.appointment_id=:id FOR UPDATE");$stmt->execute([':id'=>$appointmentId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
            if(!$row||$row['status']!=='Awaiting Deposit'||!in_array($row['deposit_status'],['Awaiting Submission','Rejected'],true)){ $this->conn->rollBack(); return ['success'=>false,'message'=>'This payment deadline cannot be extended.']; }
            $this->conn->prepare("UPDATE appointments SET payment_deadline_at=DATE_ADD(NOW(),INTERVAL {$minutes} MINUTE) WHERE appointment_id=:id")->execute([':id'=>$appointmentId]);
            $this->conn->prepare("UPDATE appointment_deposits SET resubmission_deadline_at=NULL,deadline_extended_by_user_id=:user,deadline_extended_at=NOW(),deadline_extension_reason=:reason WHERE deposit_id=:id")->execute([':user'=>$userId,':reason'=>trim($reason),':id'=>$row['deposit_id']]);
            $duration=vdFormatDurationMinutes($minutes);$actor=$this->auditLog->getUserActor($userId);$this->auditLog->record('appointment',$appointmentId,'payment_deadline_extended',"Extended the payment deadline for appointment #{$appointmentId} by {$duration}.",null,['minutes'=>$minutes,'reason'=>trim($reason)],$actor);
            $this->conn->commit();return ['success'=>true,'message'=>'Payment deadline extended by '.$duration.'.'];
        }catch(Throwable $e){if($this->conn->inTransaction())$this->conn->rollBack();error_log('extendDeadline error: '.$e->getMessage());return ['success'=>false,'message'=>'Unable to extend the deadline.'];}
    }

    public function transferDeposit(int $sourceAppointmentId, int $targetAppointmentId, int $userId, string $reason): array {
        if($sourceAppointmentId===$targetAppointmentId)return ['success'=>false,'message'=>'Choose a different original appointment.'];
        try{$this->conn->beginTransaction();
            $stmt=$this->conn->prepare("SELECT d.deposit_id,d.amount,d.status FROM appointment_deposits d WHERE d.appointment_id=:id FOR UPDATE");$stmt->execute([':id'=>$sourceAppointmentId]);$source=$stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->execute([':id'=>$targetAppointmentId]);$target=$stmt->fetch(PDO::FETCH_ASSOC);
            $targetStatus=$this->conn->prepare('SELECT status FROM appointments WHERE appointment_id=:id FOR UPDATE');$targetStatus->execute([':id'=>$targetAppointmentId]);$appointmentStatus=$targetStatus->fetchColumn();
            if(!$source||!in_array($source['status'],['Verified','For Refund'],true)||!$target||$appointmentStatus!=='Awaiting Deposit'){ $this->conn->rollBack();return ['success'=>false,'message'=>'The original deposit or new appointment is not eligible for transfer.']; }
            $code=$this->generateAppointmentCode();
            $this->conn->prepare("UPDATE appointment_deposits SET status='Transferred',transferred_by_user_id=:user,transferred_at=NOW(),transfer_reason=:reason WHERE deposit_id=:id")->execute([':user'=>$userId,':reason'=>trim($reason)?:'Transferred to a newly accepted appointment.',':id'=>$source['deposit_id']]);
            $this->conn->prepare("UPDATE appointment_deposits SET status='Verified',amount=:amount,verified_by_user_id=:user,verified_at=NOW(),transferred_from_appointment_id=:source,transferred_by_user_id=:user2,transferred_at=NOW(),transfer_reason=:reason WHERE deposit_id=:id")->execute([':amount'=>$source['amount'],':user'=>$userId,':source'=>$sourceAppointmentId,':user2'=>$userId,':reason'=>trim($reason)?:'Transferred from prior appointment.',':id'=>$target['deposit_id']]);
            $this->conn->prepare("UPDATE appointments SET status='Confirmed',confirmed_at=NOW(),appointment_code=:code,code_generated_at=NOW() WHERE appointment_id=:id")->execute([':code'=>$code,':id'=>$targetAppointmentId]);
            $actor=$this->auditLog->getUserActor($userId);$audit=$this->auditLog->record('appointment',$targetAppointmentId,'deposit_transferred',"Transferred a verified deposit from appointment #{$sourceAppointmentId}.",null,['source_appointment_id'=>$sourceAppointmentId,'appointment_code'=>$code],$actor);

            // A transferred deposit confirms the target appointment, so its
            // check-in code is queued just like a normally verified payment.
            $notification=$this->emailNotifications->enqueueAppointmentTemplate(
                $targetAppointmentId,
                'appointment_confirmed_code',
                $code,
                'audit:'.$audit['audit_log_id'].':appointment_confirmed_code'
            );

            $this->conn->commit();return ['success'=>true,'message'=>'Deposit transferred and the new appointment confirmed.','appointment_code'=>$code,'deposit_id'=>(int)$target['deposit_id'],'deposit_status'=>'Verified','appointment'=>['id'=>$targetAppointmentId,'status'=>'Confirmed'],'notification'=>$notification,'audit'=>['performed_by_name'=>$actor['name'],'performed_by_role'=>$actor['role'],'performed_at'=>$audit['performed_at']]];
        }catch(Throwable $e){if($this->conn->inTransaction())$this->conn->rollBack();error_log('transferDeposit error: '.$e->getMessage());return ['success'=>false,'message'=>'Unable to transfer the deposit.'];}
    }

    public function markRefunded(int $appointmentId, int $userId, string $notes=''): array {
        $stmt=$this->conn->prepare("UPDATE appointment_deposits SET status='Refunded',refunded_by_user_id=:user,refunded_at=NOW(),refund_notes=:notes WHERE appointment_id=:appointment AND status='For Refund'");
        $stmt->execute([':user'=>$userId,':notes'=>trim($notes)?:null,':appointment'=>$appointmentId]);
        return $stmt->rowCount()?['success'=>true,'message'=>'Manual refund recorded.']:['success'=>false,'message'=>'No deposit is currently marked For Refund.'];
    }
}
