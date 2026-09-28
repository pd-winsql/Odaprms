# Historical defense data — September 27, 2026

After the verified reset, `seed_historical_demo.php` populated the live application database with **fictional demonstration records**. The seed is transaction based and requires empty patient, schedule, appointment, billing, and rating tables, so it cannot be rerun over these records.

## Scope

- 20 fictional Patient accounts and complete profiles, with dental and medical histories and consent. Emails use `firstnamelastname.av@gmail.com`; passwords are hashed from `password1`.
- 22 schedules and 35 appointments dated August 9–September 26, 2026, across both preserved clinics. Outcomes: 26 Completed, 4 Cancelled, 3 No-show, and 2 Rejected.
- 26 check-ins, 28 deposit records at PHP 150.00, 26 paid billings with service line items, 16 written ratings, and 35 appointment audit entries.
- Unique synthetic GCash references for the historical deposits. They have no receipt image. The supplied marked demo receipt and its printed reference remain reserved for a later demonstration appointment.
- The billing service prices are fictional historical snapshots for demonstration; the preserved service catalog prices were not changed.

## Verification

`C:\xampp\php\php.exe database\defense\verify_historical_demo.php` passed against the live database. It checked counts, preserved staff and catalog records, complete profiles, patient email and password format, unique deposit references, PHP 150 deposit amounts, billing arithmetic, valid ratings/check-ins, and no queued email notifications.

The application models returned 35 appointments and 20 new patients for August 1–September 27 analytics; 26 settled visits with PHP 121,350 net collected for August 9–September 27 billing insights; a 4.3 average from 16 ratings; and 21 historical logbook dates. These figures are fictional demonstration data.

At the time this historical step finished, there were no appointments or schedules on September 28 or later. September 28 bookings were added subsequently; see `september_28_checkin_2026.md`. October 2 and later remain separate steps.
