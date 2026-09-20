(function () {
    'use strict';

    const meta = document.querySelector('meta[name="vd-app-base-url"]');
    const baseUrl = String(meta?.content || '').replace(/\/+$/, '');
    const partialsMeta = document.querySelector('meta[name="vd-dashboard-partials"]');
    const dashboardPartials = String(partialsMeta?.content || '').replace(/^\/+|\/+$/g, '');

    window.vdAppUrl = function (path) {
        const normalizedPath = String(path || '').replace(/^\/+/, '');
        return `${baseUrl}/${normalizedPath}`;
    };

    window.vdDashboardPartialUrl = function (path) {
        const normalizedPath = String(path || '').replace(/^\/+/, '');
        return window.vdAppUrl(`${dashboardPartials}/${normalizedPath}`);
    };
})();
