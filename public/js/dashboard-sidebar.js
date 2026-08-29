(function () {
    const sidebar = document.getElementById('sidebar');
    const nav = sidebar?.querySelector('.vd-sidebar-nav');
    if (!nav || nav.dataset.grouped === 'true') return;
    nav.dataset.grouped = 'true';

    const sections = Array.from(nav.querySelectorAll(':scope > .vd-nav-section'));
    sections.forEach((section, index) => {
        const label = section.textContent.trim();
        const group = document.createElement('div');
        const groupId = `sidebar-nav-group-${index}`;
        group.id = groupId;
        group.className = 'vd-nav-group';

        let sibling = section.nextElementSibling;
        while (sibling && !sibling.classList.contains('vd-nav-section')) {
            const next = sibling.nextElementSibling;
            group.appendChild(sibling);
            sibling = next;
        }

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'vd-nav-section-toggle';
        toggle.setAttribute('aria-controls', groupId);
        toggle.innerHTML = `<span>${label}</span><i class="ti ti-chevron-down" aria-hidden="true"></i>`;

        const openByDefault = index === 0 || Boolean(group.querySelector('.vd-nav-item.active'));
        group.hidden = !openByDefault;
        toggle.setAttribute('aria-expanded', String(openByDefault));

        toggle.addEventListener('click', () => {
            const opening = group.hidden;
            group.hidden = !opening;
            toggle.setAttribute('aria-expanded', String(opening));
        });

        section.replaceWith(toggle, group);
    });

    const revealActiveGroup = () => {
        const active = nav.querySelector('.vd-nav-item.active');
        const group = active?.closest('.vd-nav-group');
        if (!group || !group.hidden) return;
        group.hidden = false;
        nav.querySelector(`[aria-controls="${group.id}"]`)?.setAttribute('aria-expanded', 'true');
    };

    new MutationObserver(revealActiveGroup).observe(nav, {
        subtree: true,
        attributes: true,
        attributeFilter: ['class']
    });
})();
