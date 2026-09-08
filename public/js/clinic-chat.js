(() => {
    'use strict';
    const config = document.getElementById('clinicChatConfig');
    if (!config || window.ClinicChat) return;
    const patient = config.dataset.patient === '1';
    const drafts = new Map();
    const mounts = new WeakMap();
    const notice = 'For appointment and clinic inquiries. Replies are available during clinic hours. Not for emergencies.';
    const node = (tag, cls, text) => {
        const el = document.createElement(tag);
        if (cls) el.className = cls;
        if (text !== undefined) el.textContent = text;
        return el;
    };
    async function api(action, values = {}, write = false) {
        const url = new URL(config.dataset.endpoint, location.href);
        const params = new URLSearchParams({ action, ...values });
        if (!write) url.search = params.toString();
        const response = await fetch(url, {
            method: write ? 'POST' : 'GET', cache: 'no-store',
            headers: write ? { 'X-CSRF-Token': config.dataset.csrf } : {},
            body: write ? params : undefined,
            signal: AbortSignal.timeout(20000)
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.success) {
            const error = new Error(data.message || 'Unable to load messages. Try again.');
            error.chatConflict = response.status === 409 && data.conflict === true;
            throw error;
        }
        return data;
    }
    let badgeBusy = false;
    async function badge() {
        if (document.hidden || badgeBusy) return;
        badgeBusy = true;
        try {
            const data = await api('unread');
            document.querySelectorAll('[data-chat-unread]').forEach(el => {
                el.hidden = !data.unread;
                el.textContent = data.unread > 99 ? '99+' : String(data.unread);
                el.setAttribute('aria-label', `${data.unread} unread messages`);
            });
        } catch (_) { /* The conversation panel provides actionable errors. */ }
        finally { badgeBusy = false; }
    }
    function mount(root) {
        if (!root || mounts.has(root)) return mounts.get(root);
        root.className = patient ? 'vd-chat-single' : 'vd-chat-inbox';
        let id = patient ? 0 : null;
        let last = 0, first = 0, busy = false, sending = false, listBusy = false, offset = 0;
        let active = !patient, disposed = false, query = '', listVersion = 0, listSignature = '';
        let pendingKey = null, pendingText = null, composeBaseMessageId = null;
        const seen = new Set();
        const pane = node('section', 'vd-chat-thread');
        pane.setAttribute('aria-label', 'Conversation');
        const title = node('h2', '', patient ? 'Clinic support' : 'Choose a conversation');
        const back = node('button', 'vd-chat-back', '← Inbox');
        back.type = 'button';
        const threadMeta = node('p', 'vd-chat-thread-meta', patient ? 'Private conversation with the clinic team' : 'Select a patient to begin');
        const headingCopy = node('div', 'vd-chat-thread-heading-copy');
        headingCopy.append(title, threadMeta);
        const heading = node('header', 'vd-chat-thread-heading');
        if (!patient) heading.append(back);
        heading.append(headingCopy);
        const info = node('p', 'vd-chat-notice', notice);
        const older = node('button', 'vd-chat-older', 'Load earlier messages');
        older.type = 'button'; older.hidden = true;
        const log = node('div', 'vd-chat-log');
        log.setAttribute('role', 'log'); log.setAttribute('aria-label', 'Messages');
        log.setAttribute('aria-live', 'polite'); log.tabIndex = 0;
        const empty = node('p', 'vd-chat-empty', patient ? 'How can we help? Send your appointment or clinic question below.' : 'Select a patient from the inbox to read and reply.');
        log.append(empty);
        const status = node('p', 'vd-chat-status'); status.setAttribute('role', 'status');
        const retry = node('button', 'vd-chat-retry', 'Retry'); retry.type = 'button'; retry.hidden = true;
        const form = node('form', 'vd-chat-compose');
        const label = node('label', '', 'Your message');
        const input = node('textarea'); input.rows = 1; input.maxLength = 2000; input.required = true;
        input.placeholder = patient ? 'Ask about your appointment…' : 'Write a reply…';
        label.append(input);
        const foot = node('div', 'vd-chat-compose-footer');
        const counter = node('span', '', '0 / 2,000');
        const send = node('button', 'vd-chat-send', 'Send message'); send.type = 'submit';
        foot.append(counter, send); form.append(label, foot);
        pane.append(heading, info, older, log, status, retry, form);
        let list, search, previous, next, listStatus, listRetry;
        if (!patient) {
            const sidebar = node('aside', 'vd-chat-conversations');
            const sidebarHeading = node('header', 'vd-chat-conversations-heading');
            sidebarHeading.append(node('h2', '', 'Conversations'), node('p', '', 'Shared patient inbox'));
            const label = node('label', 'vd-chat-search', 'Find a patient');
            search = node('input'); search.type = 'search'; search.placeholder = 'Search by patient name';
            label.append(search);
            list = node('div', 'vd-chat-conversation-list'); list.setAttribute('aria-label', 'Patient conversations');
            listStatus = node('p', 'vd-chat-status'); listStatus.setAttribute('role', 'status');
            listRetry = node('button', 'vd-chat-retry', 'Retry loading inbox'); listRetry.type = 'button'; listRetry.hidden = true;
            listRetry.onclick = inbox;
            const paging = node('div', 'vd-chat-paging');
            previous = node('button', '', 'Previous'); next = node('button', '', 'Next');
            previous.type = next.type = 'button'; previous.disabled = next.disabled = true;
            paging.append(previous, next); sidebar.append(sidebarHeading, label, listStatus, listRetry, list, paging); root.append(sidebar);
            let debounce;
            search.addEventListener('input', () => {
                query = search.value.trim(); offset = 0; listVersion++;
                clearTimeout(debounce); debounce = setTimeout(inbox, 250);
            });
            previous.onclick = () => { offset = Math.max(0, offset - 50); listVersion++; inbox(); };
            next.onclick = () => { offset += 50; listVersion++; inbox(); };
        }
        root.append(pane);
        function enable() { input.disabled = sending || id === null; send.disabled = busy || id === null; older.disabled = busy; }
        function failure(error) {
            status.textContent = error.name === 'TimeoutError' ? 'The request timed out. Your draft is still here; try again.' : error.message;
            retry.hidden = false;
        }
        function remember() {
            if (id === null) return;
            const key = `${patient ? 'patient' : id}`;
            if (input.value) drafts.set(key, { body: input.value, baseMessageId: composeBaseMessageId });
            else drafts.delete(key);
        }
        function count() { counter.textContent = `${input.value.length.toLocaleString()} / 2,000`; }
        function resizeComposer() {
            const styles = window.getComputedStyle(input);
            const lineHeight = Number.parseFloat(styles.lineHeight) || 21;
            const verticalChrome = (Number.parseFloat(styles.paddingTop) || 0)
                + (Number.parseFloat(styles.paddingBottom) || 0)
                + (Number.parseFloat(styles.borderTopWidth) || 0)
                + (Number.parseFloat(styles.borderBottomWidth) || 0);
            const minHeight = lineHeight + verticalChrome;
            const maxHeight = (lineHeight * 3) + verticalChrome;
            input.style.height = 'auto';
            input.style.height = `${Math.max(minHeight, Math.min(input.scrollHeight, maxHeight))}px`;
            input.style.overflowY = input.scrollHeight > maxHeight ? 'auto' : 'hidden';
        }
        input.addEventListener('input', () => {
            if (input.value.trim() && composeBaseMessageId === null) composeBaseMessageId = last;
            if (!input.value.trim()) composeBaseMessageId = null;
            remember(); count(); resizeComposer();
        });
        function parsedDate(value) { return new Date(value.replace(' ', 'T')); }
        function messageDay(value) {
            const date = parsedDate(value);
            const today = new Date();
            const yesterday = new Date();
            yesterday.setDate(today.getDate() - 1);
            if (date.toDateString() === today.toDateString()) return 'Today';
            if (date.toDateString() === yesterday.toDateString()) return 'Yesterday';
            return date.toLocaleDateString([], { month: 'long', day: 'numeric', year: date.getFullYear() === today.getFullYear() ? undefined : 'numeric' });
        }
        function listTime(value) {
            const date = parsedDate(value);
            const today = new Date();
            if (date.toDateString() === today.toDateString()) return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
            return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
        }
        function initials(name) {
            return name.split(/\s+/).filter(Boolean).slice(0, 2).map(part => part[0]).join('').toUpperCase() || 'CS';
        }
        function rebuildDaySeparators() {
            log.querySelectorAll('.vd-chat-day').forEach(separator => separator.remove());
            let previousDay = '';
            log.querySelectorAll('.vd-chat-message').forEach(message => {
                if (message.dataset.day === previousDay) return;
                previousDay = message.dataset.day;
                message.before(node('div', 'vd-chat-day', previousDay));
            });
        }
        function draw(messages, prepend = false) {
            const fragment = document.createDocumentFragment();
            for (const m of messages) {
                const key = Number(m.message_id);
                if (seen.has(key)) continue;
                seen.add(key); first = first ? Math.min(first, key) : key; last = Math.max(last, key);
                const outgoing = patient ? m.sender_role === 'Patient' : m.sender_role !== 'Patient';
                const item = node('article', `vd-chat-message${outgoing ? ' is-mine' : ''}`);
                item.dataset.day = messageDay(m.created_at);
                const by = m.mine ? 'You' : (m.sender_name || (m.sender_role === 'Patient' ? 'Patient' : 'Clinic staff'));
                const avatar = node('span', 'vd-chat-avatar', initials(by));
                avatar.setAttribute('aria-hidden', 'true');
                const bubble = node('div', 'vd-chat-bubble');
                const meta = node('div', 'vd-chat-message-meta');
                const time = node('time', '', parsedDate(m.created_at).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }));
                time.dateTime = m.created_at.replace(' ', 'T');
                meta.append(node('span', 'vd-chat-author', by), time);
                bubble.append(meta, node('p', '', m.body));
                item.append(avatar, bubble); fragment.append(item);
            }
            empty.hidden = seen.size > 0;
            if (prepend) log.prepend(fragment); else log.append(fragment);
            rebuildDaySeparators();
        }
        async function fetchMessages(before = 0) {
            const nearBottom = log.scrollHeight - log.scrollTop - log.clientHeight < 80;
            const oldHeight = log.scrollHeight, oldTop = log.scrollTop;
            let more;
            do {
                const data = await api('messages', { conversation_id: id, after: before ? 0 : last, before });
                if (disposed || !root.isConnected) return;
                id = Number(data.conversationId);
                const wasFirst = last === 0;
                draw(data.messages, !!before);
                if (before || wasFirst) older.hidden = !data.hasMore;
                more = !before && !wasFirst && data.hasMore;
            } while (more);
            if (before) log.scrollTop = oldTop + log.scrollHeight - oldHeight;
            else if (nearBottom) log.scrollTop = log.scrollHeight;
            if (active && !document.hidden && last) {
                await api('read', { conversation_id: id, through: last }, true);
                badge();
            }
        }
        async function refresh(before = 0) {
            if (disposed || !root.isConnected || !active || document.hidden || busy || id === null) return;
            busy = true; enable();
            try { await fetchMessages(before); status.textContent = ''; retry.hidden = true; }
            catch (e) { failure(e); }
            finally { busy = false; enable(); }
        }
        async function inbox() {
            if (patient || disposed || !root.isConnected || document.hidden) return;
            if (listBusy) return;
            listBusy = true;
            const version = listVersion;
            try {
                const result = await api('inbox', { search: query, offset });
                if (version !== listVersion || !root.isConnected) return;
                listStatus.textContent = ''; listRetry.hidden = true;
                const signature = JSON.stringify([result, id, offset, query]);
                if (signature === listSignature) return;
                listSignature = signature;
                const focusedId = list.contains(document.activeElement) ? document.activeElement.dataset.conversation : null;
                list.replaceChildren();
                if (!result.conversations.length) list.append(node('p', 'vd-chat-empty', query ? 'No matching patients.' : 'No conversations yet. Patient messages will appear here.'));
                result.conversations.forEach(c => {
                    const button = node('button', 'vd-chat-conversation'); button.type = 'button';
                    button.dataset.conversation = c.conversation_id;
                    button.setAttribute('aria-pressed', String(Number(c.conversation_id) === id));
                    const row = node('div', 'vd-chat-conversation-name'); row.append(node('strong', '', c.patient_name));
                    const activity = node('span', 'vd-chat-conversation-time', listTime(c.updated_at));
                    row.append(activity);
                    const preview = node('span', 'vd-chat-preview', c.preview || 'No messages');
                    const details = node('div', 'vd-chat-conversation-details');
                    details.append(preview);
                    if (Number(c.unread)) details.append(node('span', 'vd-chat-count', String(c.unread)));
                    button.append(row, details);
                    button.onclick = () => select(Number(c.conversation_id), c.patient_name, c.updated_at);
                    list.append(button);
                });
                if (focusedId) list.querySelector(`[data-conversation="${Number(focusedId)}"]`)?.focus();
                previous.disabled = offset === 0; next.disabled = !result.hasMore;
            } catch (e) { listStatus.textContent = e.message; listRetry.hidden = false; }
            finally { listBusy = false; if (version !== listVersion) inbox(); }
        }
        async function select(nextId, name, updatedAt) {
            if (busy) return;
            active = true;
            remember(); id = nextId; last = first = 0; seen.clear(); pendingKey = pendingText = composeBaseMessageId = null;
            log.replaceChildren(empty); empty.hidden = false; empty.textContent = 'Loading conversation…';
            title.textContent = name;
            threadMeta.textContent = `Patient conversation · Last activity ${listTime(updatedAt)}`;
            older.hidden = true;
            const draft = drafts.get(String(id));
            input.value = draft?.body || '';
            composeBaseMessageId = draft?.baseMessageId ?? null;
            count(); resizeComposer();
            root.classList.add('has-conversation');
            list.querySelectorAll('button').forEach(b => b.setAttribute('aria-pressed', String(Number(b.dataset.conversation) === id)));
            await refresh();
            empty.textContent = 'No messages yet.';
            title.tabIndex = -1; title.focus();
            inbox();
        }
        back.onclick = () => { active = false; root.classList.remove('has-conversation'); search.focus(); inbox(); };
        older.onclick = () => refresh(first);
        retry.onclick = () => { refresh(); inbox(); };
        form.addEventListener('submit', async e => {
            e.preventDefault();
            if (busy || id === null || !input.value.trim()) return;
            busy = sending = true; enable(); status.textContent = 'Sending…'; retry.hidden = true;
            const body = input.value.trim();
            if (pendingText !== body) {
                pendingText = body;
                pendingKey = Array.from(crypto.getRandomValues(new Uint8Array(16)), n => n.toString(16).padStart(2, '0')).join('');
            }
            try {
                const result = await api('send', {
                    conversation_id: id,
                    body,
                    request_key: pendingKey,
                    last_seen_message_id: composeBaseMessageId ?? last
                }, true);
                id = Number(result.conversationId); input.value = ''; composeBaseMessageId = null; remember(); count(); pendingKey = pendingText = null;
                resizeComposer(); send.textContent = 'Send message';
                await fetchMessages(); log.scrollTop = log.scrollHeight;
                status.textContent = ''; inbox();
            } catch (e) {
                if (e.chatConflict) {
                    try { await fetchMessages(); } catch (_) { /* The conflict message remains actionable. */ }
                    composeBaseMessageId = last;
                    remember();
                    status.textContent = `${e.message} Your draft is preserved; send again after reviewing.`;
                    send.textContent = 'Send after review';
                    retry.hidden = true;
                } else {
                    failure(e);
                }
            }
            finally { busy = sending = false; enable(); if (active && root.isConnected) input.focus(); }
        });
        const timer = setInterval(() => {
            if (!root.isConnected) { disposed = true; clearInterval(timer); return; }
            if (!document.hidden) { if (active) refresh(); inbox(); }
        }, 5000);
        const instance = { open() { active = true; refresh(); }, close() { active = false; remember(); } };
        mounts.set(root, instance);
        const initialDraft = drafts.get(patient ? 'patient' : String(id));
        input.value = initialDraft?.body || '';
        composeBaseMessageId = initialDraft?.baseMessageId ?? null;
        count(); resizeComposer(); enable();
        if (!patient) inbox();
        return instance;
    }
    window.ClinicChat = { mount };
    if (patient) {
        const modal = document.getElementById('clinicChatModal');
        const instance = mount(modal.querySelector('[data-clinic-chat]'));
        modal.addEventListener('shown.bs.modal', () => instance.open());
        modal.addEventListener('hidden.bs.modal', () => instance.close());
    }
    badge();
    setInterval(badge, 30000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) badge(); });
})();
