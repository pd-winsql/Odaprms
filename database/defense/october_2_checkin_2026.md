# October 2 final-defense check-in data

Four fictional appointments are ready for **live check-in on October 2, 2026**. The seed reused complete, verified patient accounts and did not alter the September 28 bookings or historical data.

| Clinic | Schedule window | Patient | Patient login | Check-in code |
| --- | --- | --- | --- | --- |
| Alcala Branch | 8:00 AM–12:00 PM | Bianca Reyes | `biancareyes.av@gmail.com` | `AVC-G2QXHV` |
| Alcala Branch | 8:00 AM–12:00 PM | Carlo Bautista | `carlobautista.av@gmail.com` | `AVC-KWB7DN` |
| Tuguegarao Branch | 2:00 PM–5:00 PM | Ella Rivera | `ellarivera.av@gmail.com` | `AVC-QF24N3` |
| Tuguegarao Branch | 2:00 PM–5:00 PM | Isabel Lopez | `isabellopez.av@gmail.com` | `AVC-UJEUPL` |

The clinic windows have a two-hour separation, above the 90-minute site setting. Each has capacity four. All bookings are dated at least seven days before October 2. Each appointment is Confirmed, has a verified PHP 150.00 deposit with a unique synthetic GCash reference, and has no check-in yet. No receipt image was attached to these four deposits; the supplied marked demo receipt remains linked only to Ana Mendoza's September 28 appointment. No email notifications were queued.

On October 2 in the site's Asia/Manila timezone, staff can search by code or patient name, record arrival, review/confirm the profile, then demonstrate queue and treatment actions. Check-in search and action are limited by the app to the current date.

`C:\xampp\php\php.exe database\defense\verify_october_2_checkin.php` passed: four ready appointments, four staff-logbook entries, two clinics, unique codes and deposit references across the database, and no existing check-ins. The September 28 and historical verifiers also passed after this seed.

Additional pending/payment-review and other appointment-flow scenarios, plus schedules beginning October 9 for the seven-day booking-gap demonstration, remain separate next steps.
