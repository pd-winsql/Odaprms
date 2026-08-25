ALTER TABLE patient_medical_history
    ADD COLUMN no_known_conditions TINYINT(1) DEFAULT NULL AFTER cond_others;
