ALTER TABLE services
    ADD COLUMN IF NOT EXISTS service_image VARCHAR(255) NULL AFTER service_description;

UPDATE services SET service_image = CASE service_id
    WHEN 1 THEN 'public/uploads/services/cleaning-prophylaxis.webp'
    WHEN 2 THEN 'public/uploads/services/scaling.webp'
    WHEN 3 THEN 'public/uploads/services/periapical-xray.webp'
    WHEN 4 THEN 'public/uploads/services/restoration-fillings.webp'
    WHEN 5 THEN 'public/uploads/services/crown-jackets.webp'
    WHEN 6 THEN 'public/uploads/services/bridge.webp'
    WHEN 7 THEN 'public/uploads/services/root-canal.webp'
    WHEN 8 THEN 'public/uploads/services/dentures.webp'
    WHEN 9 THEN 'public/uploads/services/extraction.webp'
    WHEN 10 THEN 'public/uploads/services/wisdom-tooth-removal.webp'
    WHEN 11 THEN 'public/uploads/services/braces.webp'
    WHEN 12 THEN 'public/uploads/services/whitening.webp'
    WHEN 13 THEN 'public/uploads/services/veneer.webp'
    ELSE NULL
END;

ALTER TABLE services DROP COLUMN IF EXISTS service_icon;
