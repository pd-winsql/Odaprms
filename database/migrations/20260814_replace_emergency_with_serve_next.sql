/*
  This migration preserves existing check-ins while replacing Emergency/Deferred
  queue mechanics with the audited Serve Next and On Hold workflow.
*/
ALTER TABLE appointment_checkins
  MODIFY queue_status ENUM('Waiting','Deferred','On Hold') NOT NULL DEFAULT 'Waiting';

UPDATE appointment_checkins SET queue_status = 'On Hold' WHERE queue_status = 'Deferred';
UPDATE appointment_checkins SET queue_priority = 'Normal' WHERE queue_priority = 'Emergency';

ALTER TABLE appointment_checkins
  DROP INDEX idx_checkins_queue,
  MODIFY queue_status ENUM('Waiting','On Hold') NOT NULL DEFAULT 'Waiting',
  DROP COLUMN queue_priority,
  ADD COLUMN serve_next_at DATETIME NULL AFTER queue_reason,
  ADD COLUMN serve_next_reason VARCHAR(255) NULL AFTER serve_next_at,
  ADD COLUMN serve_next_by_user_id INT(11) NULL AFTER serve_next_reason,
  ADD KEY idx_checkins_queue (queue_status, serve_next_at, queue_entered_at),
  ADD KEY idx_checkins_serve_next_actor (serve_next_by_user_id),
  ADD CONSTRAINT fk_checkins_serve_next_actor
    FOREIGN KEY (serve_next_by_user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE;
