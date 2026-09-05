# Deploying clinic messages

Upload the updated application files, then run `database/migrations/20260905_add_clinic_chat.sql` against the application's MySQL/MariaDB database (for example, using phpMyAdmin's Import tab). Run it after the existing `users` table is installed. The migration is additive and safe to rerun; it creates two tables and does not modify existing appointment data.

This migration is required for both existing installations and fresh installations imported from `db-oaprms-system.sql`.

Patients open **Message clinic** from their dashboard. Admin and Dental Assistant users open **Messages** in the sidebar. A patient's first message creates their single ongoing conversation. Read state is shared by the clinic team; replies identify the sender's role. Messages do not change appointments or billing records.

The implementation uses the existing PHP/PDO connection and session/CSRF helpers. No new package, queue worker, cron job, WebSocket server, or external messaging provider is needed. Message text supports UTF-8 and is limited to 2,000 characters. Older messages and the inbox are paginated. Open conversations refresh every 12 seconds; the unread badge refreshes every 30 seconds. Hidden browser tabs pause polling.

Verify the installation with `php tests/clinicChatTest.php`. This creates isolated test accounts/conversations and removes them when finished. It tests ownership, staff permissions, duplicate-send prevention, unread cursors, Unicode, and pagination.
