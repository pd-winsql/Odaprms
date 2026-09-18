'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const read = file => fs.readFileSync(path.join(__dirname, '..', file), 'utf8');
function handler(source, marker) {
    const start = source.indexOf(marker);
    assert.ok(start >= 0, `Missing listener: ${marker}`);
    const bodyStart = source.indexOf('{', start) + 1;
    const end = source.indexOf('});', bodyStart);
    return source.slice(bodyStart, end).trim().replace(/}$/, '');
}
function event(values = {}) {
    return { key: 'Enter', prevented: false, preventDefault() { this.prevented = true; }, ...values };
}
const chat = read('public/js/clinic-chat.js');
const messageKey = new Function('event', 'window', 'send', 'form', handler(chat, "input.addEventListener('keydown'"));
let submissions = 0;
const send = { disabled: false };
const form = { requestSubmit(button) { assert.equal(button, send); submissions++; } };
const desktop = { matchMedia: () => ({ matches: true }) };
const mobile = { matchMedia: () => ({ matches: false }) };
const enter = event();
messageKey(enter, desktop, send, form);
assert.equal(submissions, 1);
assert.equal(enter.prevented, true);
for (const options of [{ shiftKey: true }, { isComposing: true }, { keyCode: 229 }, { ctrlKey: true }, { key: 'a' }]) {
    const e = event(options);
    messageKey(e, desktop, send, form);
    assert.equal(e.prevented, false);
}
const phoneEnter = event();
messageKey(phoneEnter, mobile, send, form);
assert.equal(phoneEnter.prevented, false);
messageKey(event({ repeat: true }), desktop, send, form);
send.disabled = true;
messageKey(event(), desktop, send, form);
assert.equal(submissions, 1, 'Mobile, modified, repeated, composing, or disabled sends must not submit.');
for (const [file, marker] of [
    ['public/js/clinic-chat.js', "search.addEventListener('keydown'"],
    ['apps/views/admin/partials/patient-content.php', "searchInput.addEventListener('keydown'"],
    ['apps/views/admin/partials/services-content.php', "searchInput.addEventListener('keydown'"],
    ['apps/views/admin/partials/activity-logs-content.php', "search.addEventListener('keydown'"]
]) {
    const key = new Function('event', handler(read(file), marker));
    const e = event(); key(e); assert.equal(e.prevented, true);
    const composing = event({ isComposing: true }); key(composing); assert.equal(composing.prevented, false);
}
const lookupKey = new Function('event', 'findAppointment', handler(
    read('apps/views/admin/partials/dashboard-content.php'), "document.getElementById('checkinLookup')?.addEventListener('keydown'"
));
let clicks = 0;
const button = { disabled: false, click() { clicks++; } };
lookupKey(event(), button);
lookupKey(event({ repeat: true }), button);
lookupKey(event({ isComposing: true }), button);
button.disabled = true;
lookupKey(event(), button);
assert.equal(clicks, 1, 'Lookup Enter uses the existing button once.');
console.log('PASS: Desktop chat send, multiline/mobile/IME guards, live searches, and appointment lookup shortcuts.');
