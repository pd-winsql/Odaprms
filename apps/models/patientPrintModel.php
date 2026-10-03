<?php

require_once __DIR__ . '/patientModel.php';
require_once __DIR__ . '/odontogramModel.php';
require_once __DIR__ . '/clinicModel.php';

final class PatientPrintModel
{
    private Patient $patients;
    private OdontogramModel $odontograms;
    private Clinic $clinics;
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->patients = new Patient($conn);
        $this->odontograms = new OdontogramModel($conn);
        $this->clinics = new Clinic($conn);
    }

    public function getRecord(int $patientId): array
    {
        if ($patientId < 1) {
            throw new InvalidArgumentException('Choose a valid patient record.');
        }

        $patient = $this->patients->getPatientFull($patientId);
        if (!$patient) {
            throw new InvalidArgumentException('Patient record not found.');
        }

        $dentalRecord = $this->odontograms->getChart($patientId);
        $postponed = $this->conn->prepare("SELECT a.appointment_id, a.date, 'Treatment postponed — no treatment performed' AS procedure_name,
            a.postponement_reason AS notes, 0 AS actual_service_amount, 0 AS amount_paid, 0 AS outstanding_balance
            FROM appointments a WHERE a.patient_id = ? AND a.status = 'Treatment Postponed'");
        $postponed->execute([$patientId]);
        $ledger = array_merge($dentalRecord['ledger'], $postponed->fetchAll(PDO::FETCH_ASSOC));
        usort($ledger, static fn($a, $b) => strcmp($b['date'], $a['date']) ?: $b['appointment_id'] <=> $a['appointment_id']);

        return [
            'patient' => $patient,
            'chart' => $dentalRecord['chart'],
            'ledger' => $ledger,
            'clinics' => $this->clinics->getAllClinics(),
        ];
    }
}
