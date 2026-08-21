ALTER TABLE clinics
  ADD COLUMN IF NOT EXISTS embed_url TEXT NULL AFTER clinic_contact;
