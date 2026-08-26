<?php

require_once __DIR__ . '/auditLogModel.php';

class OdontogramModel
{
    private PDO $conn;
    private AuditLog $auditLog;

    private const PERMANENT_TEETH = [
        '18','17','16','15','14','13','12','11','21','22','23','24','25','26','27','28',
        '48','47','46','45','44','43','42','41','31','32','33','34','35','36','37','38',
    ];
    private const PRIMARY_TEETH = [
        '55','54','53','52','51','61','62','63','64','65',
        '85','84','83','82','81','71','72','73','74','75',
    ];
    private const SURFACES = ['Whole','Occlusal','Mesial','Distal','Buccal','Lingual'];
    private const FINDING_CODES = [
        'Condition' => ['D','M','F','X','RF','MO','IM'],
        'Restoration' => ['JC','AM','CO','AB','P','IN','S','RD'],
        'Surgery' => ['X','XO','CM','SP','UN'],
    ];
    private const DENTITIONS = ['Permanent','Primary','Mixed'];
    private const PERIODONTAL = ['None','Gingivitis','Early Periodontitis','Moderate Periodontitis','Advanced Periodontitis'];

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->auditLog = new AuditLog($conn);
    }

    private function emptyChart(int $patientId): array
    {
        return [
            'patient_id' => $patientId,
            'dentition_type' => 'Permanent',
            'periodontal_status' => 'None',
            'occlusion_class' => '',
            'occlusion_findings' => '',
            'appliances' => '',
            'tmd_findings' => '',
            'clinical_notes' => '',
            'updated_at' => null,
            'updated_by' => null,
            'teeth' => [],
        ];
    }

    public function getChart(int $patientId, int $appointmentId = 0): array
    {
        $patientStmt = $this->conn->prepare("SELECT patient_id, firstname, middlename, lastname, birthdate FROM patients WHERE patient_id = :id");
        $patientStmt->execute([':id' => $patientId]);
        $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);
        if (!$patient) throw new InvalidArgumentException('Patient not found.');

        $chartStmt = $this->conn->prepare("\n            SELECT o.*, COALESCE(NULLIF(TRIM(CONCAT_WS(' ', s.firstname, s.lastname)), ''), u.email) AS updated_by\n            FROM patient_odontograms o\n            LEFT JOIN users u ON u.id = o.updated_by_user_id\n            LEFT JOIN staffs s ON s.user_id = u.id\n            WHERE o.patient_id = :patient_id\n        ");
        $chartStmt->execute([':patient_id' => $patientId]);
        $chart = $chartStmt->fetch(PDO::FETCH_ASSOC) ?: $this->emptyChart($patientId);
        $chart['teeth'] = [];
        if (!empty($chart['odontogram_id'])) {
            $toothStmt = $this->conn->prepare("\n                SELECT tooth_number, surface, category, finding_code, notes\n                FROM patient_odontogram_teeth\n                WHERE odontogram_id = :odontogram_id\n                ORDER BY tooth_number, category, surface\n            ");
            $toothStmt->execute([':odontogram_id' => $chart['odontogram_id']]);
            $chart['teeth'] = $toothStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $review = null;
        if ($appointmentId > 0) {
            $reviewStmt = $this->conn->prepare("\n                SELECT reviewed_at, COALESCE(NULLIF(TRIM(CONCAT_WS(' ', s.firstname, s.lastname)), ''), u.email) AS reviewed_by\n                FROM patient_odontogram_snapshots snap\n                LEFT JOIN users u ON u.id = snap.reviewed_by_user_id\n                LEFT JOIN staffs s ON s.user_id = u.id\n                WHERE snap.appointment_id = :appointment_id AND snap.patient_id = :patient_id\n            ");
            $reviewStmt->execute([':appointment_id' => $appointmentId, ':patient_id' => $patientId]);
            $review = $reviewStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        return [
            'patient' => [
                'patient_id' => (int) $patient['patient_id'],
                'name' => trim(implode(' ', array_filter([$patient['firstname'], $patient['middlename'], $patient['lastname']]))),
                'birthdate' => $patient['birthdate'],
            ],
            'chart' => $chart,
            'appointment_review' => $review,
            'ledger' => $this->getTreatmentLedger($patientId),
        ];
    }

    public function hasReviewedAppointment(int $appointmentId): bool
    {
        $stmt = $this->conn->prepare('SELECT COUNT(*) FROM patient_odontogram_snapshots WHERE appointment_id = :appointment_id');
        $stmt->execute([':appointment_id' => $appointmentId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function normalizedText(mixed $value, int $maxLength): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        return mb_substr($value, 0, $maxLength);
    }

    private function normalizePayload(array $data): array
    {
        $dentition = in_array($data['dentition_type'] ?? '', self::DENTITIONS, true) ? $data['dentition_type'] : 'Permanent';
        $periodontal = in_array($data['periodontal_status'] ?? '', self::PERIODONTAL, true) ? $data['periodontal_status'] : 'None';
        $validTeeth = array_flip(array_merge(self::PERMANENT_TEETH, self::PRIMARY_TEETH));
        $teeth = [];
        foreach ((array) ($data['teeth'] ?? []) as $finding) {
            $tooth = (string) ($finding['tooth_number'] ?? '');
            $surface = (string) ($finding['surface'] ?? 'Whole');
            $category = (string) ($finding['category'] ?? '');
            $code = strtoupper(trim((string) ($finding['finding_code'] ?? '')));
            if (!isset($validTeeth[$tooth]) || !in_array($surface, self::SURFACES, true)) continue;
            if (!isset(self::FINDING_CODES[$category]) || !in_array($code, self::FINDING_CODES[$category], true)) continue;
            $key = implode('|', [$tooth, $surface, $category]);
            $teeth[$key] = [
                'tooth_number' => $tooth,
                'surface' => $surface,
                'category' => $category,
                'finding_code' => $code,
                'notes' => $this->normalizedText($finding['notes'] ?? '', 255),
            ];
        }

        return [
            'dentition_type' => $dentition,
            'periodontal_status' => $periodontal,
            'occlusion_class' => $this->normalizedText($data['occlusion_class'] ?? '', 32),
            'occlusion_findings' => $this->normalizedText($data['occlusion_findings'] ?? '', 255),
            'appliances' => $this->normalizedText($data['appliances'] ?? '', 255),
            'tmd_findings' => $this->normalizedText($data['tmd_findings'] ?? '', 255),
            'clinical_notes' => $this->normalizedText($data['clinical_notes'] ?? '', 2000),
            'teeth' => array_values($teeth),
        ];
    }

    public function saveChart(int $patientId, int $appointmentId, array $data, int $userId): array
    {
        $payload = $this->normalizePayload($data);
        try {
            $this->conn->beginTransaction();
            $patientStmt = $this->conn->prepare('SELECT patient_id FROM patients WHERE patient_id = :patient_id FOR UPDATE');
            $patientStmt->execute([':patient_id' => $patientId]);
            if (!$patientStmt->fetchColumn()) throw new InvalidArgumentException('Patient not found.');

            if ($appointmentId > 0) {
                $appointmentStmt = $this->conn->prepare('SELECT status FROM appointments WHERE appointment_id = :appointment_id AND patient_id = :patient_id FOR UPDATE');
                $appointmentStmt->execute([':appointment_id' => $appointmentId, ':patient_id' => $patientId]);
                $status = $appointmentStmt->fetchColumn();
                if ($status === false) throw new InvalidArgumentException('Appointment not found for this patient.');
                if ($status !== 'In Progress') throw new InvalidArgumentException('The dental chart can be reviewed for billing only while treatment is in progress.');
            }

            $chartStmt = $this->conn->prepare("
                INSERT INTO patient_odontograms
                    (patient_id, dentition_type, periodontal_status, occlusion_class, occlusion_findings, appliances, tmd_findings, clinical_notes, updated_by_user_id)
                VALUES
                    (:patient_id, :dentition, :periodontal, :occlusion_class, :occlusion_findings, :appliances, :tmd, :notes, :user_id)
                ON DUPLICATE KEY UPDATE
                    odontogram_id = LAST_INSERT_ID(odontogram_id), dentition_type = VALUES(dentition_type),
                    periodontal_status = VALUES(periodontal_status), occlusion_class = VALUES(occlusion_class),
                    occlusion_findings = VALUES(occlusion_findings), appliances = VALUES(appliances),
                    tmd_findings = VALUES(tmd_findings), clinical_notes = VALUES(clinical_notes),
                    updated_by_user_id = VALUES(updated_by_user_id), updated_at = CURRENT_TIMESTAMP
            ");
            $chartStmt->execute([
                ':patient_id' => $patientId,
                ':dentition' => $payload['dentition_type'],
                ':periodontal' => $payload['periodontal_status'],
                ':occlusion_class' => $payload['occlusion_class'],
                ':occlusion_findings' => $payload['occlusion_findings'],
                ':appliances' => $payload['appliances'],
                ':tmd' => $payload['tmd_findings'],
                ':notes' => $payload['clinical_notes'],
                ':user_id' => $userId,
            ]);
            $odontogramId = (int) $this->conn->lastInsertId();
            $this->conn->prepare('DELETE FROM patient_odontogram_teeth WHERE odontogram_id = :odontogram_id')
                ->execute([':odontogram_id' => $odontogramId]);
            $toothStmt = $this->conn->prepare("
                INSERT INTO patient_odontogram_teeth
                    (odontogram_id, tooth_number, surface, category, finding_code, notes)
                VALUES (:odontogram_id, :tooth, :surface, :category, :code, :notes)
            ");
            foreach ($payload['teeth'] as $finding) {
                $toothStmt->execute([
                    ':odontogram_id' => $odontogramId,
                    ':tooth' => $finding['tooth_number'],
                    ':surface' => $finding['surface'],
                    ':category' => $finding['category'],
                    ':code' => $finding['finding_code'],
                    ':notes' => $finding['notes'],
                ]);
            }

            if ($appointmentId > 0) {
                $snapshotStmt = $this->conn->prepare("
                    INSERT INTO patient_odontogram_snapshots
                        (patient_id, appointment_id, chart_payload, reviewed_by_user_id, reviewed_at)
                    VALUES (:patient_id, :appointment_id, :payload, :user_id, NOW())
                    ON DUPLICATE KEY UPDATE chart_payload = VALUES(chart_payload),
                        reviewed_by_user_id = VALUES(reviewed_by_user_id), reviewed_at = NOW()
                ");
                $snapshotStmt->execute([
                    ':patient_id' => $patientId,
                    ':appointment_id' => $appointmentId,
                    ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ':user_id' => $userId,
                ]);
            }

            $actor = $this->auditLog->getUserActor($userId);
            if (!$actor) throw new RuntimeException('Staff account not found.');
            $this->auditLog->record(
                'patient', $patientId,
                $appointmentId > 0 ? 'odontogram_reviewed' : 'odontogram_updated',
                $appointmentId > 0
                    ? "Reviewed the odontogram for appointment #{$appointmentId}."
                    : "Updated the current odontogram for patient #{$patientId}.",
                null,
                ['appointment_id' => $appointmentId ?: null, 'findings' => count($payload['teeth'])],
                $actor
            );
            $this->conn->commit();
            return [
                'success' => true,
                'message' => $appointmentId > 0 ? 'Dental chart reviewed for this visit.' : 'Dental chart saved.',
                'reviewed' => $appointmentId > 0,
            ];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            if ($e instanceof InvalidArgumentException) return ['success' => false, 'message' => $e->getMessage()];
            error_log('save odontogram error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to save the dental chart.'];
        }
    }

    private function getTreatmentLedger(int $patientId): array
    {
        $stmt = $this->conn->prepare("
            SELECT a.appointment_id, a.date,
                   COALESCE(GROUP_CONCAT(DISTINCT s.service_name ORDER BY s.service_name SEPARATOR ', '), 'Service not listed') AS procedure_name,
                   b.actual_service_amount,
                   LEAST(b.actual_service_amount, b.deposit_applied + b.cash_received) AS amount_paid,
                   GREATEST(b.actual_service_amount - b.deposit_applied - b.cash_received, 0) AS outstanding_balance,
                   b.notes,
                   COALESCE(NULLIF(TRIM(CONCAT_WS(' ', staff.firstname, staff.lastname)), ''), u.email) AS dentist_name
            FROM appointments a
            JOIN appointment_billings b ON b.appointment_id = a.appointment_id
            LEFT JOIN appointment_services aps ON aps.appointment_id = a.appointment_id
            LEFT JOIN services s ON s.service_id = aps.service_id
            LEFT JOIN users u ON u.id = b.recorded_by_user_id
            LEFT JOIN staffs staff ON staff.user_id = u.id
            WHERE a.patient_id = :patient_id AND a.status = 'Completed'
            GROUP BY a.appointment_id, a.date, b.actual_service_amount, b.deposit_applied, b.cash_received, b.notes, dentist_name
            ORDER BY a.date DESC, a.appointment_id DESC
        ");
        $stmt->execute([':patient_id' => $patientId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) return [];

        $appointmentIds = array_map('intval', array_column($rows, 'appointment_id'));
        $placeholders = implode(',', array_fill(0, count($appointmentIds), '?'));
        $snapshotStmt = $this->conn->prepare("SELECT appointment_id, chart_payload FROM patient_odontogram_snapshots WHERE appointment_id IN ({$placeholders})");
        $snapshotStmt->execute($appointmentIds);
        $teethByAppointment = [];
        foreach ($snapshotStmt->fetchAll(PDO::FETCH_ASSOC) as $snapshot) {
            $payload = json_decode($snapshot['chart_payload'], true);
            $teeth = array_values(array_unique(array_column((array) ($payload['teeth'] ?? []), 'tooth_number')));
            sort($teeth, SORT_NATURAL);
            $teethByAppointment[(int) $snapshot['appointment_id']] = $teeth;
        }
        foreach ($rows as &$row) {
            $row['tooth_numbers'] = $teethByAppointment[(int) $row['appointment_id']] ?? [];
        }
        unset($row);
        return $rows;
    }
}
