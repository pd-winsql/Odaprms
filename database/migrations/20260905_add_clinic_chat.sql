CREATE TABLE IF NOT EXISTS clinic_conversations (
    conversation_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    patient_user_id INT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_chat_patient (patient_user_id),
    CONSTRAINT fk_chat_patient FOREIGN KEY (patient_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS clinic_messages (
    message_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    sender_role VARCHAR(30) NOT NULL,
    body TEXT NOT NULL,
    request_key VARCHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    UNIQUE KEY uq_chat_request (sender_id, request_key),
    KEY ix_chat_history (conversation_id, message_id),
    KEY ix_chat_unread (read_at, sender_role, conversation_id),
    CONSTRAINT fk_chat_conversation FOREIGN KEY (conversation_id) REFERENCES clinic_conversations(conversation_id),
    CONSTRAINT fk_chat_sender FOREIGN KEY (sender_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
