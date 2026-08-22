(function () {
    'use strict';

    const buttonState = new WeakMap();

    function setButtonLoading(button, isLoading, label = 'Loading…') {
        if (!button) return;

        if (isLoading) {
            if (buttonState.has(button)) return;

            buttonState.set(button, {
                html: button.innerHTML,
                disabled: button.disabled,
                width: button.style.width
            });

            const width = button.getBoundingClientRect().width;
            if (width) button.style.width = `${Math.ceil(width)}px`;

            const content = document.createElement('span');
            content.className = 'vd-button-loading__content';

            const spinner = document.createElement('span');
            spinner.className = 'vd-spinner';
            spinner.setAttribute('aria-hidden', 'true');

            const text = document.createElement('span');
            text.textContent = label;

            content.append(spinner, text);
            button.replaceChildren(content);
            button.disabled = true;
            button.classList.add('vd-button-loading');
            button.setAttribute('aria-busy', 'true');
            return;
        }

        const state = buttonState.get(button);
        if (!state) return;

        button.innerHTML = state.html;
        button.disabled = state.disabled;
        button.style.width = state.width;
        button.classList.remove('vd-button-loading');
        button.removeAttribute('aria-busy');
        buttonState.delete(button);
    }

    const dashboardSkeletonLayouts = {
        'dashboard-content.php': 'overview',
        'home-content.php': 'patient-home',
        'appointment-content.php': 'table',
        'payment-review-content.php': 'table',
        'cash-billing-content.php': 'table',
        'logbook-content.php': 'table',
        'patient-content.php': 'table',
        'den-assist-content.php': 'table',
        'history-content.php': 'list',
        'billing-content.php': 'cards',
        'services-content.php': 'cards',
        'clinic-content.php': 'cards',
        'schedule-content.php': 'schedule',
        'booking-content.php': 'booking',
        'profile-content.php': 'form',
        'change-password-content.php': 'form',
        'siteSettings-content.php': 'form',
        'reports-content.php': 'reports',
        'analytics-content.php': 'analytics'
    };

    function skeletonItem(className = '') {
        return `<span class="vd-skeleton__item ${className}" aria-hidden="true"></span>`;
    }

    function skeletonHeading() {
        return '<div class="vd-skeleton__heading">' +
            '<div>' + skeletonItem('vd-skeleton__eyebrow') + skeletonItem('vd-skeleton__title') + '</div>' +
            skeletonItem('vd-skeleton__button') +
            '</div>';
    }

    function skeletonCard(lines = 3) {
        const rows = Array.from({ length: lines }, (_, index) =>
            skeletonItem(index === lines - 1 ? 'vd-skeleton__line vd-skeleton__line--short' : 'vd-skeleton__line')
        ).join('');
        return `<div class="vd-skeleton__card">${rows}</div>`;
    }

    function skeletonStats(count = 4) {
        return '<div class="vd-skeleton__stats">' + Array.from({ length: count }, () =>
            '<div class="vd-skeleton__stat">' +
                skeletonItem('vd-skeleton__eyebrow') +
                skeletonItem('vd-skeleton__number') +
                skeletonItem('vd-skeleton__line vd-skeleton__line--short') +
            '</div>'
        ).join('') + '</div>';
    }

    function skeletonTable(rows = 5) {
        return '<div class="vd-skeleton__panel">' +
            '<div class="vd-skeleton__panel-head">' + skeletonItem('vd-skeleton__title vd-skeleton__title--small') + skeletonItem('vd-skeleton__badge') + '</div>' +
            '<div class="vd-skeleton__filters">' + Array.from({ length: 4 }, () => skeletonItem('vd-skeleton__control')).join('') + '</div>' +
            '<div class="vd-skeleton__table">' + Array.from({ length: rows }, () =>
                '<div class="vd-skeleton__table-row">' +
                    skeletonItem('vd-skeleton__avatar') +
                    '<div>' + skeletonItem('vd-skeleton__line') + skeletonItem('vd-skeleton__line vd-skeleton__line--short') + '</div>' +
                    skeletonItem('vd-skeleton__line') +
                    skeletonItem('vd-skeleton__badge') +
                    skeletonItem('vd-skeleton__button vd-skeleton__button--small') +
                '</div>'
            ).join('') + '</div>' +
        '</div>';
    }

    function dashboardSkeletonMarkup(layout) {
        switch (layout) {
            case 'overview':
                return skeletonStats() + skeletonTable(4) + '<div class="vd-skeleton__list">' + skeletonCard(2) + skeletonCard(2) + '</div>';
            case 'patient-home':
                return skeletonHeading() + '<div class="vd-skeleton__hero">' + skeletonItem('vd-skeleton__eyebrow') + skeletonItem('vd-skeleton__title') + skeletonItem('vd-skeleton__line vd-skeleton__line--medium') + '</div>' + '<div class="vd-skeleton__list">' + skeletonCard(3) + skeletonCard(3) + '</div>';
            case 'cards':
                return skeletonHeading() + '<div class="vd-skeleton__card-grid">' + Array.from({ length: 4 }, () => skeletonCard(4)).join('') + '</div>';
            case 'schedule':
                return skeletonHeading() + skeletonStats() + '<div class="vd-skeleton__tabs">' + Array.from({ length: 3 }, () => skeletonItem('vd-skeleton__button')).join('') + '</div>' + skeletonTable(4);
            case 'booking':
                return skeletonHeading() + '<div class="vd-skeleton__tabs">' + Array.from({ length: 3 }, () => skeletonItem('vd-skeleton__button')).join('') + '</div>' + '<div class="vd-skeleton__card-grid">' + Array.from({ length: 4 }, () => skeletonCard(3)).join('') + '</div>';
            case 'form':
                return skeletonHeading() + '<div class="vd-skeleton__panel vd-skeleton__form">' + Array.from({ length: 6 }, () => '<div>' + skeletonItem('vd-skeleton__eyebrow') + skeletonItem('vd-skeleton__control') + '</div>').join('') + '</div>';
            case 'reports':
                return skeletonHeading() + '<div class="vd-skeleton__panel">' + '<div class="vd-skeleton__filters">' + Array.from({ length: 4 }, () => skeletonItem('vd-skeleton__control')).join('') + '</div></div>' + skeletonStats(3) + skeletonTable(4);
            case 'analytics':
                return skeletonHeading() + skeletonStats(6) + '<div class="vd-skeleton__chart-grid">' + Array.from({ length: 4 }, () => '<div class="vd-skeleton__chart">' + skeletonItem('vd-skeleton__title vd-skeleton__title--small') + skeletonItem('vd-skeleton__chart-block') + '</div>').join('') + '</div>';
            case 'list':
                return skeletonHeading() + '<div class="vd-skeleton__panel vd-skeleton__list">' + Array.from({ length: 5 }, () => skeletonCard(2)).join('') + '</div>';
            case 'table':
            default:
                return skeletonHeading() + skeletonTable();
        }
    }

    function contentMarkup(type, label, layout) {
        if (type === 'spinner') {
            return `<div class="vd-content-loading__label"><span class="vd-spinner" aria-hidden="true"></span><span>${label}</span></div>`;
        }

        return `<div class="vd-skeleton vd-skeleton--${layout}" aria-hidden="true">${dashboardSkeletonMarkup(layout)}</div>` +
            '<span class="visually-hidden">' + label + '</span>';
    }

    function showContentLoading(container, options = {}) {
        if (!container) return;
        const type = options.type || 'skeleton';
        const label = options.label || 'Loading content…';
        const layout = options.layout || dashboardSkeletonLayouts[options.page] || 'table';
        container.classList.add('vd-content-loading');
        container.setAttribute('aria-busy', 'true');
        container.setAttribute('aria-live', 'polite');
        container.innerHTML = contentMarkup(type, label, layout);
    }

    function finishContentLoading(container) {
        if (!container) return;
        container.classList.remove('vd-content-loading');
        container.removeAttribute('aria-busy');
    }

    window.LoadingUI = {
        setButton: setButtonLoading,
        showContent: showContentLoading,
        finishContent: finishContentLoading
    };
})();
