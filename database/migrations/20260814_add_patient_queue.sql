ALTER TABLE appointment_checkins
  ADD COLUMN queue_status ENUM('Waiting','On Hold') NOT NULL DEFAULT 'Waiting' AFTER ready_at,
  ADD COLUMN queue_entered_at DATETIME NULL AFTER queue_status,
  ADD COLUMN queue_reason VARCHAR(255) NULL AFTER queue_entered_at,
  ADD COLUMN serve_next_at DATETIME NULL AFTER queue_reason,
  ADD COLUMN serve_next_reason VARCHAR(255) NULL AFTER serve_next_at,
  ADD COLUMN serve_next_by_user_id INT(11) NULL AFTER serve_next_reason,
  ADD COLUMN queue_updated_by_user_id INT(11) NULL AFTER serve_next_by_user_id,
  ADD COLUMN queue_updated_at DATETIME NULL AFTER queue_updated_by_user_id,
  ADD KEY idx_checkins_queue (queue_status, serve_next_at, queue_entered_at),
  ADD KEY idx_checkins_serve_next_actor (serve_next_by_user_id),
  ADD KEY idx_checkins_queue_actor (queue_updated_by_user_id),
  ADD CONSTRAINT fk_checkins_serve_next_actor
    FOREIGN KEY (serve_next_by_user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT fk_checkins_queue_actor
    FOREIGN KEY (queue_updated_by_user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE;

UPDATE appointment_checkins
SET queue_entered_at = arrived_at
WHERE queue_entered_at IS NULL;
