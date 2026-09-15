(() => {
    'use strict';

    const desktop = window.matchMedia('(min-width: 768px)');

    document.querySelectorAll('[data-service-explorer]').forEach(explorer => {
        const tabs = Array.from(explorer.querySelectorAll('[role="tab"]'));
        const panels = Array.from(explorer.querySelectorAll('[data-service-panel]'));
        if (!panels.length) return;

        let hashTarget = null;
        try {
            hashTarget = location.hash ? document.getElementById(decodeURIComponent(location.hash.slice(1))) : null;
        } catch (_) {
            hashTarget = null;
        }
        const hashPanel = hashTarget?.matches('[data-service-panel]')
            ? hashTarget
            : hashTarget?.closest('[data-service-panel]');
        let activeId = hashPanel?.id || tabs.find(tab => tab.getAttribute('aria-selected') === 'true')?.getAttribute('aria-controls') || panels[0].id;
        let arranging = false;

        function selectPanel(id, updateUrl = false) {
            if (!panels.some(panel => panel.id === id)) return;
            activeId = id;

            tabs.forEach(tab => {
                const selected = tab.getAttribute('aria-controls') === activeId;
                tab.classList.toggle('is-active', selected);
                tab.setAttribute('aria-selected', String(selected));
                tab.tabIndex = selected ? 0 : -1;
            });

            if (desktop.matches) {
                panels.forEach(panel => {
                    panel.open = true;
                    panel.hidden = panel.id !== activeId;
                    panel.setAttribute('role', 'tabpanel');
                    panel.setAttribute('aria-labelledby', panel.dataset.tab);
                });
            }

            if (updateUrl) history.replaceState(null, '', `#${activeId}`);
        }

        function arrange() {
            arranging = true;
            if (desktop.matches) {
                selectPanel(activeId);
            } else {
                panels.forEach(panel => {
                    panel.hidden = false;
                    panel.removeAttribute('role');
                    panel.removeAttribute('aria-labelledby');
                    panel.open = panel.id === activeId;
                });
            }
            explorer.classList.add('is-enhanced');
            arranging = false;
        }

        tabs.forEach((tab, index) => {
            tab.addEventListener('click', event => {
                if (!desktop.matches) return;
                event.preventDefault();
                selectPanel(tab.getAttribute('aria-controls'), true);
            });

            tab.addEventListener('keydown', event => {
                if (!desktop.matches || !['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
                event.preventDefault();
                let nextIndex = index;
                if (event.key === 'ArrowDown') nextIndex = (index + 1) % tabs.length;
                if (event.key === 'ArrowUp') nextIndex = (index - 1 + tabs.length) % tabs.length;
                if (event.key === 'Home') nextIndex = 0;
                if (event.key === 'End') nextIndex = tabs.length - 1;
                const nextTab = tabs[nextIndex];
                selectPanel(nextTab.getAttribute('aria-controls'), true);
                nextTab.focus();
            });
        });

        panels.forEach(panel => {
            panel.addEventListener('toggle', () => {
                if (arranging || desktop.matches || !panel.open) return;
                activeId = panel.id;
                arranging = true;
                panels.forEach(other => {
                    if (other !== panel) other.open = false;
                });
                arranging = false;
            });
        });

        const serviceTarget = hashTarget?.matches('.vd-service-item') ? hashTarget : hashTarget?.closest('.vd-service-item');
        if (serviceTarget) serviceTarget.open = true;

        desktop.addEventListener('change', arrange);
        arrange();
    });
})();
