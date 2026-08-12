-- Remove usernames and use unique email addresses for every login.
-- Target database only: db-oaprms-system

USE `db-oaprms-system`;

ALTER TABLE users
    DROP INDEX username,
    DROP COLUMN username;
