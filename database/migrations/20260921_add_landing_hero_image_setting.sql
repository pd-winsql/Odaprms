ALTER TABLE `site_settings`
    ADD COLUMN `hero_image` varchar(255) DEFAULT 'landing_hero_default.jpg' AFTER `site_logo`,
    MODIFY COLUMN `hero_system_tag` varchar(150) DEFAULT 'Online Appointment with Records Management System';

UPDATE `site_settings`
SET `hero_image` = 'landing_hero_default.jpg'
WHERE `hero_image` IS NULL OR TRIM(`hero_image`) = '';

UPDATE `site_settings`
SET `hero_system_tag` = 'Online Appointment with Records Management System'
WHERE `hero_system_tag` IS NULL
   OR TRIM(`hero_system_tag`) = ''
   OR `hero_system_tag` = 'Online Dental Appointment & Patient Records Management System';
