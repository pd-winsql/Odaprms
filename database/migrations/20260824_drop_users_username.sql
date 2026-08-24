-- Authentication uses the unique email address; usernames are no longer used.
-- Drop only the username index and column, leaving all other user data intact.
ALTER TABLE users
  DROP INDEX username,
  DROP COLUMN username;
