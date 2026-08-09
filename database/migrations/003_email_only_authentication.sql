-- Remove usernames and use unique email addresses for every login.
-- Target database only: av-clinica-dental-feature

USE `av-clinica-dental-feature`;

ALTER TABLE users
    DROP INDEX username,
    DROP COLUMN username;
