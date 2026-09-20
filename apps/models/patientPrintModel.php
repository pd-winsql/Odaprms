<?php

require_once __DIR__ . '/patientModel.php';
require_once __DIR__ . '/odontogramModel.php';
require_once __DIR__ . '/clinicModel.php';

final class PatientPrintModel
{
    private Patient $patients;
    private OdontogramModel $odontograms;
    private Clinic $clinics;

    public function __construct(PDO $conn)
    {
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

        return [
            'patient' => $patient,
            'chart' => $dentalRecord['chart'],
            'ledger' => $dentalRecord['ledger'],
            'clinics' => $this->clinics->getAllClinics(),
        ];
    }
}
