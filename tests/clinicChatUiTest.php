<?php

function chatUiExpect(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

$script = file_get_contents(dirname(__DIR__) . '/public/js/clinic-chat.js');
$controller = file_get_contents(dirname(__DIR__) . '/apps/controllers/chatController.php');
$model = file_get_contents(dirname(__DIR__) . '/apps/models/chatModel.php');

chatUiExpect(
    str_contains($script, 'send.disabled = sending || id === null || !input.value.trim()')
        && !str_contains($script, 'send.disabled = busy')
        && !str_contains($script, 'send.disabled = fetchingMessages'),
    'Silent message polling does not change the Send button state.'
);

chatUiExpect(
    str_contains($script, 'older.disabled = fetchingMessages')
        && str_contains($script, 'if (fetchingMessages || sending) return;'),
    'Message history loading and conversation selection retain independent request guards.'
);

chatUiExpect(
    str_contains($script, 'remember(); count(); resizeComposer(); enable();'),
    'The Send button follows the current draft state immediately.'
);

chatUiExpect(
    str_contains($script, "api('sync'")
        && str_contains($controller, "\$action === 'sync'")
        && str_contains($model, 'public function sync('),
    'Active chat refreshes use one combined incremental sync request.'
);

chatUiExpect(
    str_contains($script, 'idleCycles <= 2 ? 5000')
        && str_contains($script, 'idleCycles <= 6 ? 15000 : 30000')
        && str_contains($script, 'Math.random()')
        && !str_contains($script, '}, 5000);'),
    'Polling backs off from 5 to 15 and 30 seconds with timing jitter.'
);

chatUiExpect(
    str_contains($script, "if (document.hidden) stopSync()")
        && str_contains($script, 'const syncConversation = patient || (active && id !== null)')
        && str_contains($model, "if ((int) \$messages['markReadThrough'] > 0)"),
    'Hidden pages stop syncing and read-state writes require newly loaded unread messages.'
);

echo "Clinic chat UI test completed.\n";
