TYPE=VIEW
query=select `current_log`.`entity_id` AS `appointment_id`,`current_log`.`audit_log_id` AS `audit_log_id`,`current_log`.`description` AS `status_change_description`,`current_log`.`old_values` AS `old_values`,`current_log`.`new_values` AS `new_values`,`current_log`.`performed_by_user_id` AS `performed_by_user_id`,`current_log`.`performed_by_name` AS `status_changed_by`,`current_log`.`performed_by_role` AS `status_changed_by_role`,`current_log`.`source` AS `source`,`current_log`.`performed_at` AS `status_changed_at` from (`db-oaprms-system`.`audit_logs` `current_log` left join `db-oaprms-system`.`audit_logs` `newer_log` on(`newer_log`.`entity_type` = `current_log`.`entity_type` and `newer_log`.`entity_id` = `current_log`.`entity_id` and `newer_log`.`action` = `current_log`.`action` and `newer_log`.`audit_log_id` > `current_log`.`audit_log_id`)) where `current_log`.`entity_type` = \'appointment\' and `current_log`.`action` = \'status_changed\' and `newer_log`.`audit_log_id` is null
md5=9d86355124969d547bd8aff0796ae157
updatable=0
algorithm=0
definer_user=root
definer_host=localhost
suid=0
with_check_option=0
timestamp=0001786585195231487
create-version=2
source=SELECT\n    current_log.entity_id AS appointment_id,\n    current_log.audit_log_id,\n    current_log.description AS status_change_description,\n    current_log.old_values,\n    current_log.new_values,\n    current_log.performed_by_user_id,\n    current_log.performed_by_name AS status_changed_by,\n    current_log.performed_by_role AS status_changed_by_role,\n    current_log.source,\n    current_log.performed_at AS status_changed_at\nFROM audit_logs current_log\nLEFT JOIN audit_logs newer_log\n    ON newer_log.entity_type = current_log.entity_type\n    AND newer_log.entity_id = current_log.entity_id\n    AND newer_log.action = current_log.action\n    AND newer_log.audit_log_id > current_log.audit_log_id\nWHERE current_log.entity_type = \'appointment\'\n  AND current_log.action = \'status_changed\'\n  AND newer_log.audit_log_id IS NULL
client_cs_name=cp850
connection_cl_name=cp850_general_ci
view_body_utf8=select `current_log`.`entity_id` AS `appointment_id`,`current_log`.`audit_log_id` AS `audit_log_id`,`current_log`.`description` AS `status_change_description`,`current_log`.`old_values` AS `old_values`,`current_log`.`new_values` AS `new_values`,`current_log`.`performed_by_user_id` AS `performed_by_user_id`,`current_log`.`performed_by_name` AS `status_changed_by`,`current_log`.`performed_by_role` AS `status_changed_by_role`,`current_log`.`source` AS `source`,`current_log`.`performed_at` AS `status_changed_at` from (`db-oaprms-system`.`audit_logs` `current_log` left join `db-oaprms-system`.`audit_logs` `newer_log` on(`newer_log`.`entity_type` = `current_log`.`entity_type` and `newer_log`.`entity_id` = `current_log`.`entity_id` and `newer_log`.`action` = `current_log`.`action` and `newer_log`.`audit_log_id` > `current_log`.`audit_log_id`)) where `current_log`.`entity_type` = \'appointment\' and `current_log`.`action` = \'status_changed\' and `newer_log`.`audit_log_id` is null
mariadb-version=100432
