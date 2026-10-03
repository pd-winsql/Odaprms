<?php
require_once __DIR__ . '/auditLogModel.php';

class ProfilePrintRequestModel {
    public function __construct(private PDO $conn) {}

    public function listRequests(?int $userId = null): array {
        $sql = "SELECT r.*, CONCAT(p.firstname, ' ', p.lastname) AS patient_name, c.clinic_name
            FROM patient_profile_print_requests r JOIN patients p ON p.patient_id=r.patient_id
            JOIN clinics c ON c.clinic_id=r.clinic_id";
        $stmt = $this->conn->prepare($sql . ($userId === null ? '' : ' WHERE p.user_id=?') . ' ORDER BY r.request_id DESC LIMIT 100');
        $stmt->execute($userId === null ? [] : [$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRequest(int $id): ?array {
        $stmt = $this->conn->prepare('SELECT * FROM patient_profile_print_requests WHERE request_id=?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(int $userId, int $clinicId, string $purpose, string $reason, bool $billing): int {
        $reason = trim($reason);
        if (!in_array($purpose, ['Personal copy', 'Transfer to another clinic', 'Other'], true)) throw new InvalidArgumentException('Choose a purpose.');
        if ($purpose === 'Other' && $reason === '') throw new InvalidArgumentException('Enter a reason for your request.');
        if (mb_strlen($reason) > 500) throw new InvalidArgumentException('Keep the reason within 500 characters.');
        $this->conn->beginTransaction();
        try {
            $stmt = $this->conn->prepare('SELECT patient_id FROM patients WHERE user_id=? FOR UPDATE');
            $stmt->execute([$userId]);
            $patientId = (int) $stmt->fetchColumn();
            if (!$patientId) throw new InvalidArgumentException('Patient profile not found.');
            $stmt = $this->conn->prepare("SELECT request_id FROM patient_profile_print_requests WHERE patient_id=? AND status IN ('Pending','Ready for pickup')");
            $stmt->execute([$patientId]);
            if ($stmt->fetchColumn()) throw new InvalidArgumentException('You already have an active request.');
            $stmt = $this->conn->prepare('SELECT clinic_id FROM clinics WHERE clinic_id=?');
            $stmt->execute([$clinicId]);
            if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Choose a pickup clinic.');
            $stmt = $this->conn->prepare('INSERT INTO patient_profile_print_requests(patient_id,clinic_id,purpose,reason,include_billing) VALUES(?,?,?,?,?)');
            $stmt->execute([$patientId, $clinicId, $purpose, $purpose === 'Other' ? $reason : '', (int) $billing]);
            $id = (int) $this->conn->lastInsertId();
            $this->audit($userId, $id, 'profile_print_requested', 'Requested a printed patient profile.', null, $this->getRequest($id));
            $this->conn->commit();
            return $id;
        } catch (Throwable $e) { $this->conn->rollBack(); throw $e; }
    }

    public function changeStatus(int $id, int $userId, string $status): void {
        $this->conn->beginTransaction();
        try {
            $stmt = $this->conn->prepare('SELECT * FROM patient_profile_print_requests WHERE request_id=? FOR UPDATE');
            $stmt->execute([$id]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$request) throw new InvalidArgumentException('Request not found.');
            $audit = new AuditLog($this->conn);
            $actor = $audit->getUserActor($userId);
            if (!$actor) throw new InvalidArgumentException('Account not found.');
            if ($actor['role'] === 'Patient') {
                $stmt = $this->conn->prepare('SELECT user_id FROM patients WHERE patient_id=?');
                $stmt->execute([$request['patient_id']]);
                if ((int) $stmt->fetchColumn() !== $userId || $status !== 'Cancelled' || $request['status'] !== 'Pending') throw new InvalidArgumentException('This request cannot be cancelled.');
            } elseif (in_array($actor['role'], ['Admin','Dental Assistant'], true)) {
                if (!(($request['status'] === 'Pending' && $status === 'Ready for pickup') || ($request['status'] === 'Ready for pickup' && $status === 'Collected'))) throw new InvalidArgumentException('Refresh the request and try again.');
            } else { throw new InvalidArgumentException('Unauthorized.'); }
            $stmt = $this->conn->prepare('UPDATE patient_profile_print_requests SET status=?,handled_by_user_id=?,updated_at=NOW() WHERE request_id=?');
            $stmt->execute([$status, $actor['role'] === 'Patient' ? null : $userId, $id]);
            $this->audit($userId, $id, 'profile_print_' . match ($status) { 'Ready for pickup'=>'ready', 'Collected'=>'collected', default=>'cancelled' },
                'Printed profile request marked ' . strtolower($status) . '.', ['status'=>$request['status']], ['status'=>$status]);
            $this->conn->commit();
        } catch (Throwable $e) { $this->conn->rollBack(); throw $e; }
    }

    private function audit(int $userId, int $id, string $action, string $description, ?array $old, ?array $new): void {
        $audit = new AuditLog($this->conn);
        $audit->record('Profile print request', $id, $action, $description, $old, $new, $audit->getUserActor($userId));
    }

    public function staffEvents(): array {
        $events = [];
        foreach ($this->listRequests() as $r) {
            if ($r['status'] !== 'Pending') continue;
            $events[] = ['id'=>'profile-print-'.$r['request_id'], 'type'=>'profile_print_requested',
                'message'=>$r['patient_name'].' requested a printed profile for pickup at '.$r['clinic_name'].'.',
                'destination'=>'patient-content.php', 'created_at'=>$r['requested_at']];
        }
        return array_slice($events, 0, 12);
    }
}
