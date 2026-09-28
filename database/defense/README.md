# Final defense payment fixture

`demo-gcash-receipt-150.png` is the supplied, clearly marked demo receipt. Its printed amount is PHP 150.00, reference is `9384210766513092`, and transaction time is 2026-09-18 18:05 (Asia/Manila).

Use this image for one demo deposit only. When the defense data is seeded, copy it into `storage/payment_receipts/` and store that private relative path in the matching `appointment_deposits.receipt_path`; use `image/png` as `receipt_mime`. Give every other demo deposit a different reference number, and do not point multiple deposits at this same receipt because the printed reference would conflict.

`set_demo_deposit_amount.sql` has been applied to the active database, and `site_settings.id = 1` was verified at PHP 150.00. The historical seed uses synthetic unique references and does not use this image. Keep its printed reference available for one later demo deposit.

The historical data is documented in `historical_seed_2026-09-27.md`. September 28 and October 2 check-in bookings are documented in `september_28_checkin_2026.md` and `october_2_checkin_2026.md`. The other appointment-flow scenarios and schedules beginning October 9 remain to be prepared.
