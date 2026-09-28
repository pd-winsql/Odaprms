# September 28 client-visit check-in data

Four fictional bookings are ready for a **live check-in on September 28, 2026**. They use existing complete, verified patient accounts and the PHP 150.00 deposit setting. None has been checked in yet.

| Clinic | Schedule window | Patient | Patient login | Check-in code |
| --- | --- | --- | --- | --- |
| Alcala Branch | 8:00 AM–12:00 PM | Ana Mendoza | `anamendoza.av@gmail.com` | `AVC-HU5EUK` |
| Alcala Branch | 8:00 AM–12:00 PM | Miguel Ramos | `miguelramos.av@gmail.com` | `AVC-NWVESW` |
| Tuguegarao Branch | 2:00 PM–5:00 PM | Leah Navarro | `leahnavarro.av@gmail.com` | `AVC-TE69AQ` |
| Tuguegarao Branch | 2:00 PM–5:00 PM | Noel Santos | `noelsantos.av@gmail.com` | `AVC-XWLW7C` |

The two clinic windows have a two-hour separation, satisfying the site's 90-minute clinic transition setting. Each window has capacity four. All four appointments were booked at least seven days before the visit, are Confirmed, have verified PHP 150.00 deposits and unique payment references, and appear in the staff logbook. Ana's deposit uses the supplied **DEMO RECEIPT** with its printed reference `9384210766513092`; the other deposits have unique synthetic references and no receipt image. No emails were queued by the seed.

For the demonstration, staff can find a patient by appointment code or name on September 28, record their arrival, review/confirm the profile, and continue the queue/treatment flow. The app restricts the check-in action and search to the current date, so these actions become available on September 28 in the site's Asia/Manila timezone. Keep at least one appointment untouched if a second live check-in demonstration is needed.

`C:\xampp\php\php.exe database\defense\verify_september_28_checkin.php` passed: four Confirmed appointments, no existing check-ins, both clinics, four logbook entries, and one matching demo receipt. The historical seed verifier also still passes.

October 2 defense check-in bookings were added later; see `october_2_checkin_2026.md`. Additional appointment-flow states and schedules with a seven-day gap from October 2 remain for later steps.
