<?php

function chatUiExpect(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

$script = file_get_contents(dirname(__DIR__) . '/public/js/clinic-chat.js');

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

echo "Clinic chat UI test completed.\n";
