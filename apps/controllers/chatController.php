<?php
session_start();
require_once __DIR__ . '/../../config/conn.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../models/chatModel.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    if (empty($_SESSION['user_id'])) { http_response_code(401); throw new DomainException('Sign in again to access messages.'); }
    $method = $_SERVER['REQUEST_METHOD'];
    if (!in_array($method, ['GET', 'POST'], true)) { http_response_code(405); throw new DomainException('Method not allowed.'); }
    if ($method === 'POST' && !validate_csrf()) { http_response_code(403); throw new DomainException('Your session expired. Refresh and try again.'); }
    $userId = (int) $_SESSION['user_id'];
    session_write_close();
    $db = (new Database())->connect();
    if (!$db) throw new RuntimeException('Database unavailable');
    $db->exec('SET NAMES utf8mb4');
    $chat = new ChatModel($db, $userId);
    $input = $method === 'POST' ? $_POST : $_GET;
    $action = (string) ($input['action'] ?? '');
    $id = max(0, (int) ($input['conversation_id'] ?? 0));
    $result = [];
    if ($method === 'GET' && $action === 'unread') $result = ['unread' => $chat->unread()];
    elseif ($method === 'GET' && $action === 'inbox') $result = $chat->inbox((string) ($input['search'] ?? ''), (int) ($input['offset'] ?? 0));
    elseif ($method === 'GET' && $action === 'messages') $result = $chat->messages($id, max(0, (int) ($input['after'] ?? 0)), max(0, (int) ($input['before'] ?? 0)));
    elseif ($method === 'POST' && $action === 'send') $result = ['conversationId' => $chat->send($id, (string) ($input['body'] ?? ''), (string) ($input['request_key'] ?? ''))];
    elseif ($method === 'POST' && $action === 'read') $chat->markRead($id, (int) ($input['through'] ?? 0));
    else throw new InvalidArgumentException('Unknown message action.');
    echo json_encode(['success' => true] + $result, JSON_UNESCAPED_UNICODE);
} catch (DomainException $e) {
    if (http_response_code() < 400) http_response_code(403);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Clinic chat: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Messages are temporarily unavailable. Please try again.']);
}
