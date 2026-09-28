-- Final-defense demo setting. Match the supplied DEMO RECEIPT amount.
-- Run against the active application database after confirming its backup.
UPDATE `site_settings`
SET `deposit_amount` = 150.00,
    `last_updated_by` = 'Defense preparation',
    `last_updated_at` = NOW()
WHERE `id` = 1;
