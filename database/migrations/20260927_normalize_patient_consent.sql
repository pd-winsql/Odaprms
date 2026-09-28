-- Normalize the historical demo value that described the procedure instead of
-- the person to whom consent applies. Restrict the repair to self-consenting
-- records whose consent name matches the patient name.
START TRANSACTION;

UPDATE patient_consent AS consent
JOIN patients AS patient ON patient.patient_id = consent.patient_id
SET consent.consent_for = 'myself'
WHERE LOWER(TRIM(consent.consent_for)) = 'treatment'
  AND LOWER(TRIM(consent.consent_name)) = LOWER(TRIM(CONCAT_WS(' ', patient.firstname, patient.lastname)));

COMMIT;
