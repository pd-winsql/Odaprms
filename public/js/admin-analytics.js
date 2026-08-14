(function () {
    const COLORS = ['#b5924c', '#375a67', '#7b8f6a', '#c47b63', '#86729b', '#5f7798', '#9a6b5c', '#6f7779', '#c3a35d', '#8b4e4e'];
    let charts = [];
    let requestController = null;
    let drillController = null;
    let drillState = null;

    const destroyCharts = () => {
        charts.forEach(chartInstance => chartInstance.destroy());
        charts = [];
    };

    const localDate = date => {
        const [year, month, day] = date.split('-').map(Number);
        return new Date(year, month - 1, day);
    };

    const isoDate = date => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    const number = value => Number(value || 0).toLocaleString('en-PH');
    const percent = value => `${Number(value || 0).toFixed(1)}%`;
    const share = (value, total) => total > 0 ? `${((Number(value) / Number(total)) * 100).toFixed(1)}%` : '0.0%';

    function commonOptions(overrides = {}) {
        const base = {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            animation: { duration: 420, easing: 'easeOutQuart' },
            onHover(event, elements) {
                if (event.native?.target) event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
            },
            plugins: {
                legend: { labels: { color: '#4a4035', boxWidth: 12, usePointStyle: true } },
                tooltip: { backgroundColor: '#241f1a', padding: 12, cornerRadius: 6 }
            },
            scales: {
                x: { ticks: { color: '#776b5e', maxRotation: 45, minRotation: 0 }, grid: { display: false } },
                y: { beginAtZero: true, ticks: { color: '#776b5e', precision: 0 }, grid: { color: 'rgba(181,146,76,.14)' } }
            }
        };
        return {
            ...base,
            ...overrides,
            plugins: { ...base.plugins, ...(overrides.plugins || {}) },
            scales: overrides.scales === undefined ? base.scales : overrides.scales
        };
    }

    function createChart(canvasId, config) {
        const canvas = document.getElementById(canvasId);
        if (!canvas || typeof Chart === 'undefined') return null;
        const instance = new Chart(canvas, config);
        charts.push(instance);
        return instance;
    }

    function selectedIndex(elements) {
        return elements?.[0]?.index ?? -1;
    }

    function renderCharts(root, data) {
        destroyCharts();

        createChart('appointmentTrendChart', {
            type: 'line',
            data: {
                labels: data.appointment_trend.map(item => item.label),
                datasets: [{ label: 'Appointments', data: data.appointment_trend.map(item => item.value), borderColor: COLORS[0], backgroundColor: 'rgba(181,146,76,.14)', fill: true, tension: .28, pointRadius: 3, pointHoverRadius: 6 }]
            },
            options: commonOptions({
                onClick(event, elements) {
                    const index = selectedIndex(elements);
                    if (index >= 0) openDrilldown(root, 'date', data.appointment_trend[index].bucket);
                },
                plugins: { legend: { display: false }, tooltip: { backgroundColor: '#241f1a', padding: 12, callbacks: {
                    title: items => data.appointment_trend[items[0].dataIndex]?.bucket
                        ? localDate(data.appointment_trend[items[0].dataIndex].bucket).toLocaleDateString('en-PH', data.meta.granularity === 'month' ? { month: 'long', year: 'numeric' } : { month: 'long', day: 'numeric', year: 'numeric' })
                        : '',
                    label: item => `${number(item.raw)} appointment${Number(item.raw) === 1 ? '' : 's'} · click to view`
                } } }
            })
        });

        const statuses = data.status_distribution.filter(item => item.value > 0);
        const statusTotal = statuses.reduce((sum, item) => sum + item.value, 0);
        createChart('statusDistributionChart', {
            type: 'doughnut',
            data: {
                labels: statuses.map(item => item.label),
                datasets: [{ data: statuses.map(item => item.value), backgroundColor: COLORS, borderColor: '#fffdf9', borderWidth: 3 }]
            },
            options: commonOptions({
                cutout: '62%', scales: {},
                onClick(event, elements) {
                    const index = selectedIndex(elements);
                    if (index >= 0) openDrilldown(root, 'status', statuses[index].label);
                },
                plugins: {
                    legend: { position: 'bottom', labels: { color: '#4a4035', boxWidth: 10, usePointStyle: true } },
                    tooltip: { backgroundColor: '#241f1a', padding: 12, callbacks: {
                        label: item => `${item.label}: ${number(item.raw)} (${share(item.raw, statusTotal)}) · click to view`
                    } }
                }
            })
        });

        createChart('topServicesChart', {
            type: 'bar',
            data: {
                labels: data.top_services.map(item => item.label),
                datasets: [{ label: 'Appointments', data: data.top_services.map(item => item.value), backgroundColor: COLORS[1], borderRadius: 5 }]
            },
            options: commonOptions({
                indexAxis: 'y',
                interaction: { mode: 'nearest', intersect: true, axis: 'y' },
                onClick(event, elements) {
                    const index = selectedIndex(elements);
                    if (index >= 0) openDrilldown(root, 'service', String(data.top_services[index].id));
                },
                plugins: { legend: { display: false }, tooltip: { backgroundColor: '#241f1a', padding: 12, callbacks: {
                    title: items => data.top_services[items[0].dataIndex]?.label || '',
                    label: item => {
                        const count = Number(item.parsed.x || 0);
                        return [
                            `${number(count)} appointment${count === 1 ? '' : 's'}`,
                            `${share(count, data.kpis.appointments)} of ${number(data.kpis.appointments)} total requests`,
                            'Click to view matching records'
                        ];
                    }
                } }, legend: { display: false } }
            })
        });

        createChart('clinicComparisonChart', {
            type: 'bar',
            data: {
                labels: data.clinic_comparison.map(item => item.label),
                datasets: [
                    { label: 'Appointments', data: data.clinic_comparison.map(item => item.appointments), backgroundColor: COLORS[0], borderRadius: 5 },
                    { label: 'Completed', data: data.clinic_comparison.map(item => item.completed), backgroundColor: COLORS[2], borderRadius: 5 },
                    { type: 'line', label: 'Utilization %', data: data.clinic_comparison.map(item => item.utilization_rate), borderColor: COLORS[1], backgroundColor: COLORS[1], yAxisID: 'y1', tension: .2, pointRadius: 4, pointHoverRadius: 7 }
                ]
            },
            options: commonOptions({
                onClick(event, elements) {
                    const index = selectedIndex(elements);
                    if (index >= 0) openDrilldown(root, 'clinic', String(data.clinic_comparison[index].id));
                },
                plugins: { tooltip: { backgroundColor: '#241f1a', padding: 12, callbacks: {
                    label: item => item.dataset.label === 'Utilization %'
                        ? `Utilization: ${percent(item.raw)} · click to view clinic records`
                        : `${item.dataset.label}: ${number(item.raw)} · click to view clinic records`
                } } },
                scales: {
                    x: { ticks: { color: '#776b5e' }, grid: { display: false } },
                    y: { beginAtZero: true, ticks: { color: '#776b5e', precision: 0 }, grid: { color: 'rgba(181,146,76,.14)' }, title: { display: true, text: 'Appointments' } },
                    y1: { beginAtZero: true, max: 100, position: 'right', ticks: { color: '#375a67', callback: value => `${value}%` }, grid: { drawOnChartArea: false }, title: { display: true, text: 'Utilization' } }
                }
            })
        });

        createChart('patientGrowthChart', {
            type: 'line',
            data: {
                labels: data.patient_growth.map(item => item.label),
                datasets: [{ label: 'New patients', data: data.patient_growth.map(item => item.value), borderColor: COLORS[2], backgroundColor: 'rgba(123,143,106,.14)', fill: true, tension: .28, pointRadius: 3, pointHoverRadius: 6 }]
            },
            options: commonOptions({
                onClick(event, elements) {
                    const index = selectedIndex(elements);
                    if (index >= 0) openDrilldown(root, 'patient_bucket', data.patient_growth[index].bucket);
                },
                plugins: { legend: { display: false }, tooltip: { backgroundColor: '#241f1a', padding: 12, callbacks: {
                    title: items => data.patient_growth[items[0].dataIndex]?.bucket
                        ? localDate(data.patient_growth[items[0].dataIndex].bucket).toLocaleDateString('en-PH', data.meta.granularity === 'month' ? { month: 'long', year: 'numeric' } : { month: 'long', day: 'numeric', year: 'numeric' })
                        : '',
                    label: item => `${number(item.raw)} new patient${Number(item.raw) === 1 ? '' : 's'} · click to view`
                } } }
            })
        });
    }

    function setGrouping(root, value, refresh = false) {
        root.querySelector('#analyticsGroupBy').value = value;
        root.querySelectorAll('[data-group-by]').forEach(button => {
            const active = button.dataset.groupBy === value;
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        if (refresh) load(root);
    }

    function setPreset(root, preset) {
        const from = root.querySelector('#analyticsDateFrom');
        const to = root.querySelector('#analyticsDateTo');
        const today = new Date();
        let start = new Date(today);
        let end = new Date(today);

        if (preset === 'month') {
            start = new Date(today.getFullYear(), today.getMonth(), 1);
            end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            setGrouping(root, 'day');
        } else if (preset === '30days') {
            start.setDate(today.getDate() - 29);
            setGrouping(root, 'day');
        } else if (preset === 'year') {
            start = new Date(today.getFullYear(), 0, 1);
            end = new Date(today.getFullYear(), 11, 31);
            setGrouping(root, 'month');
        } else {
            return;
        }

        from.value = isoDate(start);
        to.value = isoDate(end);
    }

    function toggleCustomDates(root, preset) {
        root.querySelector('#analyticsCustomDates').classList.toggle('d-none', preset !== 'custom');
    }

    function filterParams(root) {
        return new URLSearchParams(new FormData(root.querySelector('#analyticsFilterForm')));
    }

    function periodLabel(root) {
        const from = localDate(root.querySelector('#analyticsDateFrom').value).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
        const to = localDate(root.querySelector('#analyticsDateTo').value).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
        const clinicSelect = root.querySelector('#analyticsClinic');
        const clinic = clinicSelect.options[clinicSelect.selectedIndex]?.text || 'All clinics';
        return `${from} – ${to} · ${clinic}`;
    }

    function render(root, data) {
        const kpis = data.kpis;
        ['appointments', 'completed', 'new_patients'].forEach(key => {
            root.querySelector(`[data-kpi="${key}"]`).textContent = number(kpis[key]);
        });
        ['utilization_rate', 'cancellation_rate', 'no_show_rate'].forEach(key => {
            root.querySelector(`[data-kpi="${key}"]`).textContent = percent(kpis[key]);
        });
        root.querySelector('[data-kpi-sub="utilization"]').textContent = `${number(kpis.booked)} of ${number(kpis.capacity)} capacity used`;
        root.querySelector('#analyticsPeriod').textContent = `${periodLabel(root)} · ${data.meta.granularity === 'month' ? 'Monthly' : 'Daily'} grouping`;
        setGrouping(root, data.meta.granularity);

        renderCharts(root, data);
        root.querySelector('#analyticsResults').classList.remove('d-none');
        root.dataset.hasData = 'true';
    }

    function renderDrilldown(data) {
        const table = document.getElementById('analyticsDrilldownTable');
        const wrap = document.getElementById('analyticsDrilldownTableWrap');
        const empty = document.getElementById('analyticsDrilldownEmpty');
        const headRow = document.createElement('tr');
        table.tHead.replaceChildren(headRow);
        table.tBodies[0].replaceChildren();

        data.columns.forEach(column => {
            const cell = document.createElement('th');
            cell.textContent = column;
            headRow.appendChild(cell);
        });
        data.rows.forEach(row => {
            const tableRow = document.createElement('tr');
            row.forEach(value => {
                const cell = document.createElement('td');
                cell.textContent = value;
                tableRow.appendChild(cell);
            });
            table.tBodies[0].appendChild(tableRow);
        });

        const pagination = data.pagination;
        const paginationNav = document.getElementById('analyticsPagination');
        document.getElementById('analyticsDrilldownTitle').textContent = data.title;
        document.getElementById('analyticsDrilldownCount').textContent = pagination.total > 0
            ? `Showing ${number(pagination.from)}–${number(pagination.to)} of ${number(pagination.total)} records`
            : '0 matching records';
        document.getElementById('analyticsPageLabel').textContent = `Page ${number(pagination.page)} of ${number(pagination.total_pages)}`;
        document.getElementById('analyticsPagePrevious').disabled = pagination.page <= 1;
        document.getElementById('analyticsPageNext').disabled = pagination.page >= pagination.total_pages;
        paginationNav.classList.toggle('d-none', pagination.total_pages <= 1);
        wrap.classList.toggle('d-none', data.rows.length === 0);
        empty.classList.toggle('d-none', data.rows.length !== 0);
    }

    async function openDrilldown(root, dimension, value = '', page = 1) {
        const modalElement = document.getElementById('analyticsDrilldownModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
        const loading = document.getElementById('analyticsDrilldownLoading');
        const error = document.getElementById('analyticsDrilldownError');
        document.getElementById('analyticsDrilldownTableWrap').classList.add('d-none');
        document.getElementById('analyticsDrilldownEmpty').classList.add('d-none');
        document.getElementById('analyticsPagination').classList.add('d-none');
        document.getElementById('analyticsDrilldownCount').textContent = '';
        document.getElementById('analyticsDrilldownPeriod').textContent = periodLabel(root);
        document.getElementById('analyticsDrilldownTitle').textContent = 'Loading records...';
        error.classList.add('d-none');
        loading.classList.remove('d-none');
        modal.show();
        drillState = { root, dimension, value, page };

        if (drillController) drillController.abort();
        drillController = new AbortController();
        const params = filterParams(root);
        params.set('action', 'drilldown');
        params.set('dimension', dimension);
        params.set('page', String(page));
        if (value !== '') params.set('value', value);

        try {
            const response = await fetch(`${root.dataset.endpoint}?${params}`, { signal: drillController.signal, headers: { Accept: 'application/json' } });
            const payload = await response.json();
            if (!response.ok || !payload.success) throw new Error(payload.message || 'The matching records could not be loaded.');
            renderDrilldown(payload.data);
        } catch (exception) {
            if (exception.name === 'AbortError') return;
            error.textContent = exception.message;
            error.classList.remove('d-none');
            document.getElementById('analyticsDrilldownTitle').textContent = 'Unable to load records';
        } finally {
            loading.classList.add('d-none');
        }
    }

    async function load(root) {
        const error = root.querySelector('#analyticsError');
        const loading = root.querySelector('#analyticsLoading');
        const apply = root.querySelector('#analyticsApply');
        const params = filterParams(root);
        const endpoint = root.dataset.endpoint;

        if (requestController) requestController.abort();
        requestController = new AbortController();
        error.classList.add('d-none');
        loading.classList.remove('d-none');
        root.querySelector('#analyticsResults').classList.remove('d-none');
        root.classList.add('is-loading');
        apply.disabled = true;
        root.querySelector('#analyticsExport').href = `${endpoint}?action=export_csv&${params}`;

        try {
            if (typeof Chart === 'undefined') throw new Error('The chart library did not load. Check the internet connection and refresh the page.');
            const response = await fetch(`${endpoint}?${params}`, { signal: requestController.signal, headers: { Accept: 'application/json' } });
            const payload = await response.json();
            if (!response.ok || !payload.success) throw new Error(payload.message || 'Analytics could not be loaded.');
            render(root, payload.data);
        } catch (exception) {
            if (exception.name === 'AbortError') return;
            error.textContent = exception.message;
            error.classList.remove('d-none');
            if (root.dataset.hasData !== 'true') root.querySelector('#analyticsResults').classList.add('d-none');
        } finally {
            root.classList.remove('is-loading');
            loading.classList.add('d-none');
            apply.disabled = false;
        }
    }

    window.AdminAnalytics = {
        init(root) {
            if (!root || root.dataset.initialized === 'true') return;
            root.dataset.initialized = 'true';
            destroyCharts();

            const preset = root.querySelector('#analyticsPreset');
            preset.addEventListener('change', () => {
                toggleCustomDates(root, preset.value);
                setPreset(root, preset.value);
            });
            ['analyticsDateFrom', 'analyticsDateTo'].forEach(id => {
                root.querySelector(`#${id}`).addEventListener('change', () => { preset.value = 'custom'; });
            });
            root.querySelectorAll('[data-group-by]').forEach(button => {
                button.addEventListener('click', () => setGrouping(root, button.dataset.groupBy, true));
            });
            root.querySelectorAll('[data-drilldown]').forEach(button => {
                button.addEventListener('click', () => openDrilldown(root, button.dataset.drilldown));
            });
            root.querySelector('#analyticsFilterForm').addEventListener('submit', event => {
                event.preventDefault();
                load(root);
            });
            root.querySelector('#analyticsReset').addEventListener('click', () => {
                preset.value = 'month';
                root.querySelector('#analyticsClinic').value = '';
                toggleCustomDates(root, 'month');
                setPreset(root, 'month');
                load(root);
            });
            document.getElementById('analyticsPagePrevious')?.addEventListener('click', () => {
                if (drillState && drillState.page > 1) openDrilldown(drillState.root, drillState.dimension, drillState.value, drillState.page - 1);
            });
            document.getElementById('analyticsPageNext')?.addEventListener('click', () => {
                if (drillState) openDrilldown(drillState.root, drillState.dimension, drillState.value, drillState.page + 1);
            });
            toggleCustomDates(root, preset.value);
            setGrouping(root, 'day');
            load(root);
        }
    };
})();
