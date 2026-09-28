-- Patient accounts are now created only through verified registration.
-- Live data was checked before this retirement: no unlinked patients and no
-- account-link authorization records were present.
DROP TABLE IF EXISTS `patient_account_link_authorizations`;
