<?php

class ChatModel {
    private PDO $db;
    private int $userId;
    private string $role;

    public function __construct(PDO $db, int $userId) {
        $this->db = $db;
        $this->userId = $userId;
        $stmt = $db->prepare('SELECT user_role FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $this->role = (string) $stmt->fetchColumn();
        if (!in_array($this->role, ['Patient', 'Dental Assistant'], true)) {
            throw new DomainException('Your account cannot access clinic messages.');
        }
    }

    public function isPatient(): bool { return $this->role === 'Patient'; }

    private function conversation(int $id): int {
        if ($this->isPatient()) {
            $stmt = $this->db->prepare('SELECT conversation_id FROM clinic_conversations WHERE patient_user_id = ?');
            $stmt->execute([$this->userId]);
            $own = (int) $stmt->fetchColumn();
            if ($id && $id !== $own) throw new DomainException('Conversation not available.');
            return $own;
        }
        $stmt = $this->db->prepare('SELECT conversation_id FROM clinic_conversations WHERE conversation_id = ?');
        $stmt->execute([$id]);
        if (!$stmt->fetchColumn()) throw new DomainException('Conversation not available.');
        return $id;
    }

    private function incoming(): string {
        return $this->isPatient() ? "sender_role <> 'Patient'" : "sender_role = 'Patient'";
    }

    public function unread(): int {
        $sql = 'SELECT COUNT(*) FROM clinic_messages m JOIN clinic_conversations c USING (conversation_id) WHERE read_at IS NULL AND ' . $this->incoming();
        $stmt = $this->db->prepare($sql . ($this->isPatient() ? ' AND c.patient_user_id = ?' : ''));
        $stmt->execute($this->isPatient() ? [$this->userId] : []);
        return (int) $stmt->fetchColumn();
    }

    public function inbox(string $search, int $offset): array {
        if ($this->isPatient()) throw new DomainException('Staff access required.');
        $offset = max(0, $offset);
        $stmt = $this->db->prepare("SELECT c.conversation_id, c.updated_at,
            COALESCE((SELECT CONCAT(p.firstname, ' ', p.lastname) FROM patients p WHERE p.user_id = c.patient_user_id LIMIT 1), 'Patient') AS patient_name,
            (SELECT body FROM clinic_messages WHERE conversation_id = c.conversation_id ORDER BY message_id DESC LIMIT 1) AS preview,
            (SELECT COUNT(*) FROM clinic_messages WHERE conversation_id = c.conversation_id AND sender_role = 'Patient' AND read_at IS NULL) AS unread
            FROM clinic_conversations c
            WHERE EXISTS (SELECT 1 FROM patients p WHERE p.user_id = c.patient_user_id AND CONCAT(p.firstname, ' ', p.lastname) LIKE ?)
            ORDER BY c.updated_at DESC, c.conversation_id DESC LIMIT 51 OFFSET {$offset}");
        $stmt->execute(['%' . mb_substr($search, 0, 100) . '%']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['conversations' => array_slice($rows, 0, 50), 'hasMore' => count($rows) > 50];
    }

    public function messages(int $id, int $after = 0, int $before = 0): array {
        $id = $this->conversation($id);
        if (!$id) return ['conversationId' => 0, 'messages' => [], 'hasMore' => false];
        $params = [$id];
        $where = '';
        if ($before > 0) { $where = ' AND message_id < ?'; $params[] = $before; }
        elseif ($after > 0) { $where = ' AND message_id > ?'; $params[] = $after; }
        $ascending = $after > 0 && !$before;
        $stmt = $this->db->prepare('SELECT message_id, sender_id, sender_role, body, created_at FROM clinic_messages WHERE conversation_id = ?' . $where . ' ORDER BY message_id ' . ($ascending ? 'ASC' : 'DESC') . ' LIMIT 101');
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $hasMore = count($rows) > 100;
        $rows = array_slice($rows, 0, 100);
        if (!$ascending) $rows = array_reverse($rows);
        foreach ($rows as &$row) $row['mine'] = (int) $row['sender_id'] === $this->userId;
        return ['conversationId' => $id, 'messages' => $rows, 'hasMore' => $hasMore];
    }

    public function markRead(int $id, int $through): void {
        $id = $this->conversation($id);
        $stmt = $this->db->prepare('UPDATE clinic_messages SET read_at = NOW() WHERE conversation_id = ? AND message_id <= ? AND read_at IS NULL AND ' . $this->incoming());
        $stmt->execute([$id, max(0, $through)]);
    }

    public function send(int $id, string $body, string $key): int {
        $body = trim($body);
        if ($body === '' || !mb_check_encoding($body, 'UTF-8') || mb_strlen($body) > 2000) {
            throw new InvalidArgumentException('Enter a message of 1–2,000 characters.');
        }
        if (!preg_match('/^[a-zA-Z0-9-]{16,64}$/D', $key)) throw new InvalidArgumentException('Invalid message request. Refresh and try again.');
        $id = $this->conversation($id);
        $this->db->beginTransaction();
        try {
            if (!$id) {
                $stmt = $this->db->prepare('INSERT INTO clinic_conversations (patient_user_id) VALUES (?) ON DUPLICATE KEY UPDATE patient_user_id = VALUES(patient_user_id)');
                $stmt->execute([$this->userId]);
                $id = $this->conversation(0);
            }
            // Serialize sends in a conversation so ID cursors cannot skip a late commit.
            $stmt = $this->db->prepare('SELECT conversation_id FROM clinic_conversations WHERE conversation_id = ? FOR UPDATE');
            $stmt->execute([$id]);
            $stmt = $this->db->prepare('SELECT message_id, conversation_id, body FROM clinic_messages WHERE sender_id = ? AND request_key = ?');
            $stmt->execute([$this->userId, $key]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                if ((int) $existing['conversation_id'] !== $id || $existing['body'] !== $body) throw new InvalidArgumentException('This request was already used for another message.');
            } else {
                $stmt = $this->db->prepare('INSERT INTO clinic_messages (conversation_id, sender_id, sender_role, body, request_key) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$id, $this->userId, $this->role, $body, $key]);
                $stmt = $this->db->prepare('UPDATE clinic_conversations SET updated_at = NOW() WHERE conversation_id = ?');
                $stmt->execute([$id]);
            }
            $this->db->commit();
            return $id;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
