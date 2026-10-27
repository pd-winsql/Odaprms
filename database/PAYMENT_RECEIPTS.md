# Final settlement PNG receipts

Only successful Admin final settlement (`settleAndCompleteVisit`) creates a payment-receipt snapshot and email outbox row. Both commit with the Paid billing/Completed appointment transaction. Appointment approval, deposits, failed payments and DA actions do not issue final receipts. No schema migration or additional table is needed.

The immutable receipt is stored in `appointment_email_notifications.payload.receipt`, keyed uniquely by `payment-receipt:<billing_id>`. It preserves item names, quantities, rates, payment amounts, patient/branch/recorder names and the raster clinic logo at settlement. Do not purge these rows as disposable email logs: they also contain issued receipt records. Existing pre-feature settlements are not backfilled or emailed automatically.

Patient History payment details provide a protected preview with zoom and a PNG download. Every request validates the Patient role and appointment ownership; no image is exposed in a public directory. The SMTP sender renders the same snapshot and attaches its PNG. Internal billing notes and medical history are excluded. Outstanding balance is zero for a fully Paid settlement; `remaining_balance` is the final amount collected, not an outstanding debt.

Admin settlement starts delivery using the existing keepalive email delivery request. Admin dashboard startup retries only payment receipts; existing DA delivery retains its behavior. Retry timing and attempt limits use the existing outbox policy (three attempts, then Failed). SMTP/render failures do not undo payment. Delivery depends on an authorized dashboard visit, as with the existing queue; this is not an unattended server scheduler or a guarantee of exactly-once SMTP delivery after an interrupted connection.

Requirements: PHP GD with FreeType enabled, write-independent in-memory PNG generation. Windows uses Georgia for receipt text and Segoe UI for Unicode peso amounts. Other hosts default to DejaVu Serif; configure `RECEIPT_FONT_PATH` and `RECEIPT_AMOUNT_FONT_PATH` for installed, Unicode-capable TTF fonts. Raster logos (PNG/JPG/WEBP) are embedded; SVG logos use the text branding fallback. XAMPP Apache must restart after GD is enabled in php.ini.

Verification: `php tests/paymentReceiptTest.php` creates temporary QA records and removes them, without SMTP. `node tests/paymentReceiptBrowserTest.js` verifies desktop/mobile preview, zoom and downloads. Generated sample images are in ignored `test-results/`.
