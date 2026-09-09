<?php
require_once __DIR__ . '/../config/conn.php';
require_once __DIR__ . '/../apps/models/chatModel.php';

function chatExpect(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}
function chatReject(callable $action, string $message): void {
    try { $action(); } catch (DomainException | InvalidArgumentException $e) { chatExpect(true, $message); return; }
    throw new RuntimeException($message);
}
$db = (new Database())->connect();
if (!$db) exit(1);
$db->exec('SET NAMES utf8mb4');
$users = [];
try {
    foreach (['Patient', 'Patient', 'Admin', 'Dental Assistant', 'Dental Assistant'] as $role) {
        $stmt = $db->prepare('INSERT INTO users (email, password, user_role) VALUES (?, ?, ?)');
        $stmt->execute(['chat-test-' . bin2hex(random_bytes(8)) . '@example.invalid', password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), $role]);
        $users[] = (int) $db->lastInsertId();
        if ($role === 'Patient') {
            $stmt = $db->prepare("INSERT INTO patients (user_id, firstname, lastname) VALUES (?, 'ChatTest', 'Patient')");
            $stmt->execute([end($users)]);
        }
    }
    [$patientOneId, $patientTwoId, $adminId, $assistantOneId, $assistantTwoId] = $users;
    $p1 = new ChatModel($db, $patientOneId);
    $p2 = new ChatModel($db, $patientTwoId);
    chatReject(fn() => new ChatModel($db, $adminId), 'Admin oversight cannot access daily clinic messages.');
    $assistant = new ChatModel($db, $assistantOneId);
    $secondAssistant = new ChatModel($db, $assistantTwoId);
    chatExpect($p1->messages(0)['conversationId'] === 0, 'Opening an empty patient panel does not create a conversation.');
    chatReject(fn() => $p1->inbox('', 0), 'Patients cannot browse the clinic inbox.');
    chatReject(fn() => $p1->send(0, ' ', str_repeat('a', 32)), 'Blank messages are rejected.');
    chatReject(fn() => $p1->send(0, str_repeat('x', 2001), str_repeat('a', 32)), 'Messages exceeding the limit are rejected.');
    $key = bin2hex(random_bytes(16));
    $id = $p1->send(0, 'Appointment question 🦷 <script>example</script>', $key);
    chatExpect($p1->send($id, 'Appointment question 🦷 <script>example</script>', $key) === $id, 'A retried send reuses the original conversation.');
    chatExpect(count($p1->messages($id)['messages']) === 1, 'A retried send does not duplicate the message.');
    chatReject(fn() => $p2->messages($id), 'Another patient cannot read the conversation.');
    chatReject(fn() => $p2->send($id, 'Intrusion', bin2hex(random_bytes(16))), 'Another patient cannot send to the conversation.');
    chatReject(fn() => $p2->markRead($id, PHP_INT_MAX), 'Another patient cannot mark the conversation read.');
    $rows = $assistant->inbox('ChatTest', 0)['conversations'];
    chatExpect(count(array_filter($rows, fn($r) => (int) $r['conversation_id'] === $id && (int) $r['unread'] === 1)) === 1, 'The staff inbox can find a patient and count unread messages.');
    $incoming = $assistant->messages($id)['messages'][0];
    $assistant->markRead($id, (int) $incoming['message_id']);
    $rows = $secondAssistant->inbox('ChatTest', 0)['conversations'];
    chatExpect(count(array_filter($rows, fn($r) => (int) $r['conversation_id'] === $id && (int) $r['unread'] === 0)) === 1, 'Read state is shared across clinic staff.');
    $assistant->send($id, 'Please bring your appointment details.', bin2hex(random_bytes(16)));
    chatExpect($p1->unread() === 1 && $p2->unread() === 0, 'Only the intended patient receives an unread reply.');
    $reply = $p1->messages($id, (int) $incoming['message_id'])['messages'][0];
    chatExpect($reply['sender_role'] === 'Dental Assistant' && !$reply['mine'], 'Replies identify the staff role.');
    $p1->markRead($id, (int) $incoming['message_id']);
    chatExpect($p1->unread() === 1, 'A read cursor does not mark newer messages read.');
    $p1->markRead($id, (int) $reply['message_id']);
    chatExpect($p1->unread() === 0, 'Opening the reply clears its unread state.');
    for ($i = 0; $i < 103; $i++) $p1->send($id, 'History ' . $i, bin2hex(random_bytes(16)));
    $recent = $p1->messages($id);
    chatExpect(count($recent['messages']) === 100 && $recent['hasMore'], 'Long histories use bounded pages.');
    $older = $p1->messages($id, 0, (int) $recent['messages'][0]['message_id']);
    chatExpect(count($older['messages']) === 5, 'Older messages remain accessible.');
    $incremental = $p1->messages($id, (int) $incoming['message_id']);
    chatExpect(count($incremental['messages']) === 100 && $incremental['hasMore'], 'Incremental polling reports remaining messages for catch-up.');
} finally {
    if ($users) {
        $marks = implode(',', array_fill(0, count($users), '?'));
        $db->prepare("DELETE FROM clinic_messages WHERE sender_id IN ({$marks})")->execute($users);
        $db->prepare("DELETE FROM clinic_conversations WHERE patient_user_id IN ({$marks})")->execute($users);
        $db->prepare("DELETE FROM patients WHERE user_id IN ({$marks})")->execute($users);
        $db->prepare("DELETE FROM users WHERE id IN ({$marks})")->execute($users);
    }
}
echo "Clinic chat tests completed; temporary fixtures removed.\n";
