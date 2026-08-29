(function () {
    'use strict';

    const TABLE_SELECTOR = '.vd-appt-table';
    const PRIORITY_LABELS = ['status', 'form', 'action', 'actions'];
    const PAGE_SIZE = 10;

    function normaliseLabel(value) {
        return value.replace(/\s+/g, ' ').trim();
    }

    function ensureDetailsModal() {
        let modal = document.getElementById('vdTableDetailsModal');
        if (modal) return modal;

        const wrapper = document.createElement('div');
        wrapper.innerHTML = `
            <div class="modal fade vd-table-details-modal" id="vdTableDetailsModal" tabindex="-1"
                aria-labelledby="vdTableDetailsTitle" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content vd-modal-content">
                        <div class="modal-header">
                            <div>
                                <div class="vd-table-details-eyebrow">Record details</div>
                                <h5 class="modal-title vd-modal-title" id="vdTableDetailsTitle">Details</h5>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <dl class="vd-table-details-list" id="vdTableDetailsList"></dl>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn vd-btn-gold" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>`;

        modal = wrapper.firstElementChild;
        document.body.appendChild(modal);
        return modal;
    }

    function showDetails(table, row) {
        const headers = Array.from(table.querySelectorAll('thead th'));
        const cells = Array.from(row.children);
        const modal = ensureDetailsModal();
        const list = modal.querySelector('#vdTableDetailsList');
        const title = modal.querySelector('#vdTableDetailsTitle');
        const primaryName = row.querySelector('.vd-appt-name');

        title.textContent = normaliseLabel(primaryName ? primaryName.textContent : cells[0]?.textContent || 'Details');
        list.replaceChildren();

        cells.forEach((cell, index) => {
            if (cell.classList.contains('vd-generated-details-cell')) return;

            const label = normaliseLabel(headers[index]?.textContent || `Field ${index + 1}`);
            if (!label || /^(action|actions)$/i.test(label)) return;

            const item = document.createElement('div');
            item.className = 'vd-table-details-item';

            const term = document.createElement('dt');
            term.textContent = label;

            const description = document.createElement('dd');
            const activity = label === 'Latest Activity'
                ? cell.querySelector('.vd-activity-card')
                : null;

            if (activity) {
                description.classList.add('vd-table-details-activity');
                description.appendChild(activity.cloneNode(true));
            } else {
                description.textContent = normaliseLabel(cell.textContent) || '—';
            }

            item.append(term, description);
            list.appendChild(item);
        });

        bootstrap.Modal.getOrCreateInstance(modal).show();
    }

    function createDetailsButton(table, row) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn vd-btn-outline vd-table-icon-btn vd-row-details-btn';
        button.setAttribute('aria-label', 'View complete details');
        button.setAttribute('title', 'View details');
        button.innerHTML = '<i class="ti ti-eye" aria-hidden="true"></i>';
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            showDetails(table, row);
        });
        return button;
    }

    function createPagination(table, rows) {
        const tableWrap = table.closest('.vd-appt-table-wrap');
        if (!tableWrap || tableWrap.nextElementSibling?.classList.contains('vd-table-pagination')) return;

        let currentPage = 1;
        const pagination = document.createElement('nav');
        pagination.className = 'vd-table-pagination';
        pagination.setAttribute('aria-label', 'Table pagination');

        const summary = document.createElement('span');
        summary.className = 'vd-table-pagination-summary';

        const controls = document.createElement('div');
        controls.className = 'vd-table-pagination-controls';

        const previous = document.createElement('button');
        previous.type = 'button';
        previous.className = 'btn vd-table-page-btn';
        previous.innerHTML = '<i class="ti ti-chevron-left" aria-hidden="true"></i><span>Previous</span>';

        const pageLabel = document.createElement('span');
        pageLabel.className = 'vd-table-page-label';

        const next = document.createElement('button');
        next.type = 'button';
        next.className = 'btn vd-table-page-btn';
        next.innerHTML = '<span>Next</span><i class="ti ti-chevron-right" aria-hidden="true"></i>';

        controls.append(previous, pageLabel, next);
        pagination.append(summary, controls);
        tableWrap.insertAdjacentElement('afterend', pagination);

        function filteredRows() {
            return rows.filter((row) => row.style.display !== 'none');
        }

        function setRowOnPage(row, isOnPage) {
            row.classList.toggle('vd-page-hidden', !isOnPage);
            const editRow = row.nextElementSibling;
            if (editRow?.classList.contains('vd-edit-row')) {
                editRow.classList.toggle('vd-page-hidden', !isOnPage);
            }
        }

        function render(resetPage = false) {
            const availableRows = filteredRows();
            const totalRows = availableRows.length;
            const totalPages = Math.max(1, Math.ceil(totalRows / PAGE_SIZE));

            if (resetPage) currentPage = 1;
            currentPage = Math.min(currentPage, totalPages);

            rows.forEach((row) => {
                setRowOnPage(row, false);
                row.classList.remove('vd-last-visible-row');
            });

            const startIndex = (currentPage - 1) * PAGE_SIZE;
            const endIndex = Math.min(startIndex + PAGE_SIZE, totalRows);
            const pageRows = availableRows.slice(startIndex, endIndex);
            pageRows.forEach((row) => setRowOnPage(row, true));
            pageRows.at(-1)?.classList.add('vd-last-visible-row');

            pagination.classList.toggle('d-none', totalRows === 0);
            summary.textContent = `Showing ${totalRows ? startIndex + 1 : 0}–${endIndex} of ${totalRows}`;
            pageLabel.textContent = `Page ${currentPage} of ${totalPages}`;
            previous.disabled = currentPage === 1;
            next.disabled = currentPage === totalPages;
        }

        previous.addEventListener('click', () => {
            if (currentPage <= 1) return;
            currentPage--;
            render();
            tableWrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });

        next.addEventListener('click', () => {
            const totalPages = Math.max(1, Math.ceil(filteredRows().length / PAGE_SIZE));
            if (currentPage >= totalPages) return;
            currentPage++;
            render();
            tableWrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });

        table.addEventListener('ventura:table-filtered', () => render(true));

        const card = table.closest('.vd-dash-card');
        if (card) {
            const refreshAfterFilter = (event) => {
                if (!event.target.closest?.('.vd-filter-bar, .vd-status-filter-wrap')) return;
                queueMicrotask(() => render(true));
            };
            card.addEventListener('input', refreshAfterFilter);
            card.addEventListener('change', refreshAfterFilter);
            card.addEventListener('click', refreshAfterFilter);
        }

        render();
    }

    function trackHorizontalOverflow(table) {
        const tableWrap = table.closest('.vd-appt-table-wrap');
        if (!tableWrap) return;

        const update = () => {
            tableWrap.classList.toggle(
                'vd-table-has-horizontal-overflow',
                table.scrollWidth > tableWrap.clientWidth + 1
            );
        };

        if ('ResizeObserver' in window) {
            const observer = new ResizeObserver(update);
            observer.observe(tableWrap);
            observer.observe(table);
        } else {
            window.addEventListener('resize', update, { passive: true });
        }

        requestAnimationFrame(update);
    }

    function ensureTableFrame(table) {
        const tableWrap = table.closest('.vd-appt-table-wrap');
        if (!tableWrap || tableWrap.parentElement?.classList.contains('vd-table-frame')) return;

        const frame = document.createElement('div');
        frame.className = 'vd-table-frame';
        tableWrap.insertAdjacentElement('beforebegin', frame);
        frame.appendChild(tableWrap);
    }

    function enhanceTable(table) {
        if (table.dataset.vdResponsive === 'true') return;

        const headers = Array.from(table.querySelectorAll('thead th'));
        const rows = Array.from(table.querySelectorAll('tbody tr:not(.vd-edit-row)'));
        if (!headers.length || !rows.length) return;

        table.dataset.vdResponsive = 'true';
        table.classList.add('vd-responsive-table');

        let actionIndex = -1;
        headers.forEach((header, index) => {
            const label = normaliseLabel(header.textContent).toLowerCase();
            header.dataset.label = label;

            if (/^actions?$/.test(label)) {
                actionIndex = index;
                header.classList.add('vd-table-actions-column');
            } else if (index === 0 || PRIORITY_LABELS.includes(label)) {
                header.classList.add('vd-table-priority');
            } else {
                header.classList.add('vd-table-secondary');
            }
        });

        if (actionIndex === -1) {
            const detailsHeader = document.createElement('th');
            detailsHeader.className = 'vd-generated-details-cell vd-table-actions-column';
            detailsHeader.textContent = 'Details';
            headers[0].parentElement.appendChild(detailsHeader);
        }

        rows.forEach((row) => {
            Array.from(row.children).forEach((cell, index) => {
                const header = headers[index];
                if (!header) return;

                const label = normaliseLabel(header.textContent);
                cell.dataset.label = label;
                if (header.classList.contains('vd-table-secondary')) cell.classList.add('vd-table-secondary');
                if (header.classList.contains('vd-table-priority')) cell.classList.add('vd-table-priority');
                if (header.classList.contains('vd-table-actions-column')) cell.classList.add('vd-table-actions-column');
            });

            // Tables with an explicit Action(s) column own their actions. Do not
            // inject a second eye button beside purpose-built appointment,
            // billing, receipt, or workflow controls. Generated row details are
            // reserved for read-only tables that do not have an action column.
            if (actionIndex === -1) {
                const detailsButton = createDetailsButton(table, row);
                const detailsCell = document.createElement('td');
                detailsCell.className = 'vd-generated-details-cell vd-table-actions-column';
                detailsCell.dataset.label = 'Details';
                detailsCell.appendChild(detailsButton);
                row.appendChild(detailsCell);
            }
        });

        trackHorizontalOverflow(table);
        createPagination(table, rows);
        ensureTableFrame(table);
    }

    function enhance(root) {
        if (root.matches?.(TABLE_SELECTOR)) enhanceTable(root);
        root.querySelectorAll?.(TABLE_SELECTOR).forEach(enhanceTable);
    }

    function initialise() {
        enhance(document);
        const content = document.querySelector('.vd-dash-content');
        if (!content) return;

        new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === Node.ELEMENT_NODE) enhance(node);
                });
            });
        }).observe(content, { childList: true, subtree: true });
    }

    window.VenturaTables = { enhance };
    document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', initialise)
        : initialise();
})();
