# Role and ownership integration evidence

Verified locally on 2026-09-18: **21 checks passed; zero failures**.

Final isolated database: `vd_auth_test_f64774fd9c8e`.
It contains schema copied from the application database and synthetic data only.
No live records, credentials, receipt files, or settings were copied. The live
database, `.env`, Apache configuration, and application authorization code were
not modified.

## Demonstrated controls

| Surface | Negative test | Positive control |
| --- | --- | --- |
| Profile display | Patient A supplies B's patient/user/appointment IDs; only A's profile renders | B reads B's profile |
| Profile save | A supplies B's IDs; B's patient row remains unchanged | A's own profile updates |
| Staff profile editing | Patient request is denied with database unchanged | Own-profile route tested separately |
| Appointment notifications | A supplies B's user ID; feed contains only A's appointment | B's feed contains B's records |
| Deposit display | Tampered IDs do not expose B's payment card | B sees B's card |
| Deposit submission | A cannot submit B's deposit; database unchanged | B passes ownership validation to receipt-field validation |
| Receipt access | Patient cannot access the staff-only receipt route using B's deposit ID | Authorized receipt retrieval was covered by the separate web-access regression test |
| Reschedule | A cannot submit for B or withdraw B's real pending request; database unchanged | B successfully creates a pending request |
| Conversation | A cannot read, send into, or mark B's conversation read; write attempts leave database unchanged | A reads A's messages |
| Final settlement | Assistant receives HTTP 403; patient is also denied; billing, appointments, services, audit and notification state unchanged | Admin settles the same in-progress appointment, producing Completed status and an admin-attributed billing record |
| Completed history | A cannot see B's completed appointment or billing via tampered IDs | B's history includes the completed visit and actual final charge |

Mutating negative requests use valid session credentials and CSRF tokens.
State comparisons hash all rows of relevant patient, appointment, deposit,
billing, reschedule, conversation, audit and notification tables before and after
the request. HTTP/JSON responses are checked as well. Some ownership denials
currently use HTTP 200 with `success: false`; the tests do not mislabel those as
HTTP 403.

## Reproduce

```powershell
C:\xampp\php\php.exe tests/authorizationIntegrationTest.php
```

Each run creates a uniquely named `vd_auth_test_*` database and retains it for
inspection. The runner reads live schema metadata only, then closes that
connection. All fixtures and endpoint mutations target the new database.
The PHP test server binds to loopback on an ephemeral port; its router accepts
only listed application endpoints and a strict test database name. Temporary
session files are removed and the server is stopped after execution. Server logs
are retained in the printed temporary directory. There is no HTTP login bypass.
Apache continues denying public access to the tests directory.

Earlier fixture-development databases were also retained:
`vd_auth_test_b39c2f31f17d` (one positive-control fixture collision) and
`vd_auth_test_f0f0b1c4e73f` (20 checks passed before strengthening feed assertions
and adding the receipt restriction check).

## Scope and limitations

This is targeted endpoint evidence, not a complete security audit. Sessions are
created by the CLI runner, so login/session hardening is not being tested.
No receipt is uploaded and no SMTP delivery occurs. Tests use XAMPP's local
database credentials; they require schema-read and database-create permissions.
Production routing, TLS, proxy and cookie behavior remain unverified.

Final settlement role enforcement currently lives in the billing controller;
the billing model's settlement method does not independently verify the actor's
role. The tested HTTP route rejects assistants, but any future caller of that
model must preserve the same authorization boundary. No authorization fixes were
made as part of this demonstration.
