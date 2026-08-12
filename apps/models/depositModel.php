<?php

require_once __DIR__ . '/auditLogModel.php';

class DepositModel {
    private $conn;
    private $auditLog;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->auditLog = new AuditLog($conn);
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
                p.firstname,
                p.lastname,
                p.email,
                c.clinic_name,
                d.deposit_id,
                d.amount,
                d.gcash_reference,
                d.receipt_path,
                d.status AS deposit_status,
                d.submitted_at,
                d.verified_at,
                d.rejection_reason,
                d.resubmission_deadline_at,
                (
                    SELECT GROUP_CONCAT(s.service_name ORDER BY s.display_order, s.service_name SEPARATOR ', ')
                    FROM appointment_services aps
                    JOIN services s ON s.service_id = aps.service_id
                    WHERE aps.appointment_id = a.appointment_id
                ) AS service_name
            FROM appointments a
            JOIN patients p ON p.patient_id = a.patient_id
            JOIN clinics c ON c.clinic_id = a.clinic_id
            JOIN appointment_deposits d ON d.appointment_id = a.appointment_id
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
                c.clinic_name,
                d.deposit_id,
                d.amount,
                d.gcash_reference,
                d.status AS deposit_status,
                d.submitted_at,
                d.verified_at,
                d.rejection_reason,
                d.resubmission_deadline_at,
                (
                    SELECT GROUP_CONCAT(s.service_name ORDER BY s.display_order, s.service_name SEPARATOR ', ')
                    FROM appointment_services aps
                    JOIN services s ON s.service_id = aps.service_id
                    WHERE aps.appointment_id = a.appointment_id
                ) AS service_name
            FROM appointments a
            JOIN appointment_deposits d ON d.appointment_id = a.appointment_id
            JOIN clinics c ON c.clinic_id = a.clinic_id
            WHERE a.patient_id = :patient_id
            ORDER BY a.created_at DESC
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
                d.deposit_id,
                d.appointment_id,
                d.amount,
                d.gcash_reference,
                d.receipt_mime,
                d.submitted_at,
                p.firstname,
                p.lastname,
                p.email,
                c.clinic_name,
                a.date,
                (
                    SELECT GROUP_CONCAT(s.service_name ORDER BY s.display_order, s.service_name SEPARATOR ', ')
                    FROM appointment_services aps
                    JOIN services s ON s.service_id = aps.service_id
                    WHERE aps.appointment_id = a.appointment_id
                ) AS service_name
            FROM appointment_deposits d
            JOIN appointments a ON a.appointment_id = d.appointment_id
            JOIN patients p ON p.patient_id = a.patient_id
            JOIN clinics c ON c.clinic_id = a.clinic_id
            WHERE d.status = 'Under Review'
              AND a.status = 'Payment Under Review'
            ORDER BY d.submitted_at ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllRecords(): array {
        $stmt = $this->conn->query("
            SELECT
                d.deposit_id,
                d.appointment_id,
                d.amount,
                d.gcash_reference,
                d.status AS deposit_status,
                d.submitted_at,
                d.verified_at,
                d.rejection_reason,
                d.refunded_at,
                CASE WHEN d.receipt_path IS NULL THEN 0 ELSE 1 END AS has_receipt,
                a.date,
                a.status AS appointment_status,
                a.appointment_code,
                p.firstname,
                p.lastname,
                p.email,
                c.clinic_name,
                verifier.email AS verified_by,
                verifier.user_role AS verified_by_role,
                (
                    SELECT GROUP_CONCAT(s.service_name ORDER BY s.display_order, s.service_name SEPARATOR ', ')
                    FROM appointment_services aps
                    JOIN services s ON s.service_id = aps.service_id
                    WHERE aps.appointment_id = a.appointment_id
                ) AS service_name
            FROM appointment_deposits d
            JOIN appointments a ON a.appointment_id = d.appointment_id
            JOIN patients p ON p.patient_id = a.patient_id
            JOIN clinics c ON c.clinic_id = a.clinic_id
            LEFT JOIN users verifier ON verifier.id = d.verified_by_user_id
            ORDER BY COALESCE(d.submitted_at, d.created_at) DESC, d.deposit_id DESC
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

    public function getNotificationDetails(int $depositId) {
        $stmt = $this->conn->prepare("
            SELECT p.email, p.firstname, p.lastname, a.appointment_code
            FROM appointment_deposits d
            JOIN appointments a ON a.appointment_id = d.appointment_id
            JOIN patients p ON p.patient_id = a.patient_id
            WHERE d.deposit_id = :deposit_id
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

            $this->auditLog->record(
                'appointment',
                (int) $row['appointment_id'],
                'status_changed',
                "Verified the deposit and confirmed appointment #{$row['appointment_id']}.",
                ['status' => 'Payment Under Review', 'deposit_status' => 'Under Review'],
                ['status' => 'Confirmed', 'deposit_status' => 'Verified', 'appointment_code' => $appointmentCode],
                $actor
            );

            $this->conn->commit();
            return ['success' => true, 'message' => 'Payment verified and appointment confirmed.', 'appointment_code' => $appointmentCode, 'appointment_id' => (int) $row['appointment_id']];
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
            $minutes = max(1, (int) ($settings['payment_deadline_minutes'] ?? 30));

            $stmt = $this->conn->prepare("
                SELECT d.appointment_id, d.status, a.status AS appointment_status
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

            $this->auditLog->record(
                'appointment',
                (int) $row['appointment_id'],
                'payment_rejected',
                "Rejected the payment proof for appointment #{$row['appointment_id']}.",
                ['deposit_status' => 'Under Review'],
                ['deposit_status' => 'Rejected', 'reason' => $reason],
                $actor
            );

            $this->conn->commit();
            return ['success' => true, 'message' => 'Payment rejected. The patient has eight hours to resubmit.'];
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
            $minutes=(int)($this->conn->query('SELECT payment_deadline_minutes FROM site_settings WHERE id=1')->fetchColumn() ?: 480);
            $stmt=$this->conn->prepare("SELECT a.status,d.deposit_id,d.status deposit_status FROM appointments a JOIN appointment_deposits d ON d.appointment_id=a.appointment_id WHERE a.appointment_id=:id FOR UPDATE");$stmt->execute([':id'=>$appointmentId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
            if(!$row||$row['status']!=='Awaiting Deposit'||!in_array($row['deposit_status'],['Awaiting Submission','Rejected'],true)){ $this->conn->rollBack(); return ['success'=>false,'message'=>'This payment deadline cannot be extended.']; }
            $this->conn->prepare("UPDATE appointments SET payment_deadline_at=DATE_ADD(NOW(),INTERVAL {$minutes} MINUTE) WHERE appointment_id=:id")->execute([':id'=>$appointmentId]);
            $this->conn->prepare("UPDATE appointment_deposits SET resubmission_deadline_at=NULL,deadline_extended_by_user_id=:user,deadline_extended_at=NOW(),deadline_extension_reason=:reason WHERE deposit_id=:id")->execute([':user'=>$userId,':reason'=>trim($reason),':id'=>$row['deposit_id']]);
            $actor=$this->auditLog->getUserActor($userId);$this->auditLog->record('appointment',$appointmentId,'payment_deadline_extended',"Extended the payment deadline for appointment #{$appointmentId} by eight hours.",null,['minutes'=>$minutes,'reason'=>trim($reason)],$actor);
            $this->conn->commit();return ['success'=>true,'message'=>'Payment deadline extended by eight hours.'];
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
            $actor=$this->auditLog->getUserActor($userId);$this->auditLog->record('appointment',$targetAppointmentId,'deposit_transferred',"Transferred a verified deposit from appointment #{$sourceAppointmentId}.",null,['source_appointment_id'=>$sourceAppointmentId,'appointment_code'=>$code],$actor);
            $this->conn->commit();return ['success'=>true,'message'=>'Deposit transferred and the new appointment confirmed.','appointment_code'=>$code,'deposit_id'=>(int)$target['deposit_id']];
        }catch(Throwable $e){if($this->conn->inTransaction())$this->conn->rollBack();error_log('transferDeposit error: '.$e->getMessage());return ['success'=>false,'message'=>'Unable to transfer the deposit.'];}
    }

    public function markRefunded(int $appointmentId, int $userId, string $notes=''): array {
        $stmt=$this->conn->prepare("UPDATE appointment_deposits SET status='Refunded',refunded_by_user_id=:user,refunded_at=NOW(),refund_notes=:notes WHERE appointment_id=:appointment AND status='For Refund'");
        $stmt->execute([':user'=>$userId,':notes'=>trim($notes)?:null,':appointment'=>$appointmentId]);
        return $stmt->rowCount()?['success'=>true,'message'=>'Manual refund recorded.']:['success'=>false,'message'=>'No deposit is currently marked For Refund.'];
    }
}
