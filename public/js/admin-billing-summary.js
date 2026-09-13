(function () {
    "use strict";

    let activeRequest = null;
    let collectionChart = null;
    let visibleRecords = [];

    const money = (value) =>
        Number(value || 0).toLocaleString("en-PH", {
            style: "currency",
            currency: "PHP",
            minimumFractionDigits: 2,
        });

    const number = (value) => Number(value || 0).toLocaleString("en-PH");

    const formatDate = (value) => {
        if (!value) return "Not recorded";
        const parts = String(value).slice(0, 10).split("-").map(Number);
        if (parts.length !== 3 || parts.some(Number.isNaN)) return value;
        return new Date(parts[0], parts[1] - 1, parts[2]).toLocaleDateString([], {
            year: "numeric",
            month: "short",
            day: "numeric",
        });
    };

    const formatDateTime = (value) => {
        if (!value) return "Not recorded";
        const date = new Date(String(value).replace(" ", "T"));
        return Number.isNaN(date.getTime())
            ? value
            : date.toLocaleString([], {
                  year: "numeric",
                  month: "short",
                  day: "numeric",
                  hour: "numeric",
                  minute: "2-digit",
              });
    };

    const node = (tag, className = "", text = "") => {
        const element = document.createElement(tag);
        if (className) element.className = className;
        if (text !== "") element.textContent = text;
        return element;
    };

    function addModalDetail(container, label, value) {
        const item = node("div", "vd-appointment-detail-item");
        item.append(node("span", "", label), node("strong", "", value || "Not provided"));
        container.appendChild(item);
    }

    function addBillingRow(container, label, value, emphasized = false) {
        const row = node("div", emphasized ? "vd-final-billing-total" : "");
        row.append(node("span", "", label), node("strong", "", value));
        container.appendChild(row);
    }

    function trendLabel(bucket, granularity) {
        const date = new Date(`${bucket}T00:00:00`);
        if (Number.isNaN(date.getTime())) return bucket;
        return date.toLocaleDateString([], granularity === "month"
            ? { month: "short", year: "numeric" }
            : { month: "short", day: "numeric" });
    }

    function renderChart(data) {
        collectionChart?.destroy();
        collectionChart = null;
        const canvas = document.getElementById("billingCollectionChart");
        const empty = document.getElementById("billingTrendEmpty");
        const hasData = data.trend.length > 0;
        canvas.classList.toggle("d-none", !hasData);
        empty.classList.toggle("d-none", hasData);
        if (!hasData || typeof Chart === "undefined") return;

        collectionChart = new Chart(canvas, {
            type: "bar",
            data: {
                labels: data.trend.map((item) => trendLabel(item.bucket, data.meta.granularity)),
                datasets: [
                    {
                        label: "Deposits applied",
                        data: data.trend.map((item) => item.deposits),
                        backgroundColor: "#b5924c",
                        borderRadius: 3,
                        stack: "collections",
                    },
                    {
                        label: "Balances collected",
                        data: data.trend.map((item) => item.balances),
                        backgroundColor: "#375a67",
                        borderRadius: 3,
                        stack: "collections",
                    },
                    {
                        label: "Refunded deposits",
                        data: data.trend.map((item) => -Number(item.refunds || 0)),
                        backgroundColor: "#b96855",
                        borderRadius: 3,
                        stack: "refunds",
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 360, easing: "easeOutQuart" },
                interaction: { intersect: false, mode: "index" },
                plugins: {
                    legend: {
                        position: "bottom",
                        labels: { color: "#4a4035", boxWidth: 10, usePointStyle: true },
                    },
                    tooltip: {
                        backgroundColor: "#241f1a",
                        padding: 12,
                        cornerRadius: 6,
                        callbacks: {
                            label(context) {
                                return `${context.dataset.label}: ${money(context.raw)}`;
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        stacked: true,
                        grid: { display: false },
                        ticks: { color: "#776b5e", maxRotation: 45, minRotation: 0 },
                    },
                    y: {
                        stacked: true,
                        grid: { color: "rgba(181,146,76,.14)" },
                        ticks: { color: "#776b5e", callback: (value) => money(value) },
                    },
                },
            },
        });
    }

    function renderSummary(data) {
        const summary = data.summary;
        document.getElementById("billingPeriodLabel").textContent = data.meta.period_label;
        document.getElementById("billingNetCollected").textContent = money(summary.net_collected);
        document.getElementById("billingGrossCollected").textContent = money(summary.gross_collected);
        document.getElementById("billingRefunds").textContent = money(summary.refunds);
        document.getElementById("billingSettledVisits").textContent = number(summary.settled_visits);
        document.getElementById("billingAverage").textContent = money(summary.average_per_visit);
        document.getElementById("billingDepositShare").textContent = `${Number(summary.deposit_share || 0).toFixed(1)}%`;
        document.getElementById("billingDeposits").textContent = money(summary.deposits);

        const change = document.getElementById("billingChange");
        change.className = "vd-billing-change";
        if (!summary.has_comparison) {
            change.textContent = "All recorded settlement activity";
            change.classList.add("is-neutral");
        } else if (summary.change_percent === null) {
            change.textContent = "No comparable collections in the previous period";
            change.classList.add("is-neutral");
        } else {
            const increased = Number(summary.change_percent) >= 0;
            change.textContent = `${increased ? "↑" : "↓"} ${Math.abs(Number(summary.change_percent)).toFixed(1)}% from the previous equivalent period`;
            change.classList.add(increased ? "is-positive" : "is-negative");
        }
    }

    function renderClinics(data) {
        const list = document.getElementById("billingClinicComparison");
        const empty = document.getElementById("billingClinicEmpty");
        list.replaceChildren();
        empty.classList.toggle("d-none", data.clinics.length > 0);
        if (!data.clinics.length) return;

        const maximum = Math.max(...data.clinics.map((clinic) => Math.abs(Number(clinic.net_collected))), 1);
        data.clinics.forEach((clinic) => {
            const item = node("div", "vd-billing-clinic-row");
            const heading = node("div", "vd-billing-clinic-heading");
            heading.append(
                node("strong", "", clinic.clinic_name),
                node("span", Number(clinic.net_collected) < 0 ? "is-negative" : "", money(clinic.net_collected)),
            );
            const track = node("div", "vd-billing-clinic-track");
            const fill = node("span", Number(clinic.net_collected) < 0 ? "is-negative" : "");
            fill.style.width = `${Math.max(3, (Math.abs(Number(clinic.net_collected)) / maximum) * 100)}%`;
            track.appendChild(fill);
            const detail = node(
                "small",
                "",
                `${number(clinic.settled_visits)} settled visit${Number(clinic.settled_visits) === 1 ? "" : "s"} · ${money(clinic.average_per_visit)} average`,
            );
            item.append(heading, track, detail);
            list.appendChild(item);
        });
    }

    function renderRecords(data) {
        visibleRecords = data.records.items;
        const body = document.querySelector("#billingRecordsTable tbody");
        const wrap = document.getElementById("billingRecordsWrap");
        const empty = document.getElementById("billingRecordsEmpty");
        const pagination = data.records.pagination;
        body.replaceChildren();

        visibleRecords.forEach((record, index) => {
            const row = document.createElement("tr");
            const patient = document.createElement("td");
            patient.append(node("div", "vd-appt-name", record.patient), node("div", "vd-appt-meta", `Appointment #${record.appointment_id}`));
            const visit = document.createElement("td");
            visit.append(node("div", "vd-appt-name", formatDate(record.appointment_date)), node("div", "vd-appt-meta", record.clinic));
            const settlement = document.createElement("td");
            settlement.append(
                node("div", "vd-appt-name", money(Number(record.deposit_applied) + Number(record.balance_collected))),
                node("div", "vd-appt-meta", `Deposit ${money(record.deposit_applied)} · Balance ${money(record.balance_collected)}`),
            );
            const status = node("span", `vd-status vd-status-${String(record.payment_status).toLowerCase().replace(/[^a-z0-9]+/g, "-")} mt-2`, record.payment_status);
            settlement.appendChild(status);
            const finalized = document.createElement("td");
            finalized.append(node("div", "vd-appt-name", record.recorded_by || "Admin"), node("div", "vd-appt-meta", formatDateTime(record.settled_at)));
            const action = document.createElement("td");
            const button = node("button", "btn vd-btn-outline vd-table-icon-btn");
            button.type = "button";
            button.dataset.billingIndex = String(index);
            button.title = "View billing details";
            button.setAttribute("aria-label", `View billing details for ${record.patient}`);
            button.innerHTML = '<i class="ti ti-eye" aria-hidden="true"></i>';
            action.appendChild(button);
            row.append(patient, visit, settlement, finalized, action);
            body.appendChild(row);
        });

        const hasRecords = visibleRecords.length > 0;
        wrap.classList.toggle("d-none", !hasRecords);
        empty.classList.toggle("d-none", hasRecords);
        document.getElementById("billingRecordCount").textContent = `${number(pagination.total)} record${pagination.total === 1 ? "" : "s"}`;
        document.getElementById("billingPaginationSummary").textContent = `Showing ${number(pagination.from)}–${number(pagination.to)} of ${number(pagination.total)}`;
        document.getElementById("billingPageLabel").textContent = `Page ${number(pagination.page)} of ${number(pagination.total_pages)}`;
        document.getElementById("billingPrevious").disabled = pagination.page <= 1;
        document.getElementById("billingNext").disabled = pagination.page >= pagination.total_pages;
        document.getElementById("billingPagination").classList.toggle("d-none", pagination.total <= 0);
    }

    function showRecord(record) {
        document.getElementById("billingRecordTitle").textContent = `Billing · ${record.patient}`;
        document.getElementById("billingRecordSubtitle").textContent = `Appointment #${record.appointment_id} · Settled ${formatDateTime(record.settled_at)}`;
        const visit = document.getElementById("billingVisitGrid");
        visit.replaceChildren();
        addModalDetail(visit, "Patient", record.patient);
        addModalDetail(visit, "Appointment date", formatDate(record.appointment_date));
        addModalDetail(visit, "Clinic", record.clinic);
        addModalDetail(visit, "Services", record.services);

        const summary = document.getElementById("billingPaymentSummary");
        summary.replaceChildren();
        addBillingRow(summary, "Treatment total", money(record.actual_charge));
        addBillingRow(summary, "Deposit applied", `−${money(record.deposit_applied)}`);
        addBillingRow(summary, "Amount due", money(record.amount_due), true);
        addBillingRow(summary, "Cash tendered", money(record.cash_tendered));
        addBillingRow(summary, "Change", money(record.change));

        const information = document.getElementById("billingRecordGrid");
        information.replaceChildren();
        addModalDetail(information, "Payment status", record.payment_status);
        addModalDetail(information, "Finalized by", record.recorded_by || "Admin");
        addModalDetail(information, "Recorded at", formatDateTime(record.recorded_at));
        const notes = document.getElementById("billingRecordNotes");
        notes.textContent = record.notes || "";
        notes.classList.toggle("d-none", !record.notes);
        bootstrap.Modal.getOrCreateInstance(document.getElementById("billingRecordModal")).show();
    }

    function init(root) {
        if (!root) return;
        activeRequest?.abort();
        collectionChart?.destroy();
        collectionChart = null;

        const form = root.querySelector("#billingFilterForm");
        const period = root.querySelector("#billingPeriod");
        const customDates = root.querySelector("#billingCustomDates");
        const from = root.querySelector("#billingDateFrom");
        const to = root.querySelector("#billingDateTo");
        const loading = root.querySelector("#billingInsightsLoading");
        const results = root.querySelector("#billingInsightsResults");
        const error = root.querySelector("#billingInsightsError");
        let page = 1;

        const syncCustomDates = () => {
            const custom = period.value === "custom";
            customDates.classList.toggle("d-none", !custom);
            from.required = custom;
            to.required = custom;
        };

        const requestParams = () => {
            const params = new URLSearchParams(new FormData(form));
            params.set("page", String(page));
            if (period.value !== "custom") {
                params.delete("date_from");
                params.delete("date_to");
            }
            return params;
        };

        const load = async () => {
            activeRequest?.abort();
            activeRequest = new AbortController();
            loading.classList.remove("d-none");
            results.classList.add("d-none");
            error.classList.add("d-none");
            try {
                const response = await fetch(`${root.dataset.endpoint}?${requestParams()}`, {
                    signal: activeRequest.signal,
                    cache: "no-store",
                    headers: { Accept: "application/json" },
                });
                const payload = await response.json();
                if (!response.ok || !payload.success) throw new Error(payload.message || "Unable to load billing insights.");
                if (!root.isConnected) return;
                renderSummary(payload.data);
                renderChart(payload.data);
                renderClinics(payload.data);
                renderRecords(payload.data);
                results.classList.remove("d-none");
            } catch (requestError) {
                if (requestError.name === "AbortError") return;
                error.textContent = requestError.message || "Unable to load billing insights.";
                error.classList.remove("d-none");
            } finally {
                if (root.isConnected) loading.classList.add("d-none");
            }
        };

        period.addEventListener("change", syncCustomDates);
        form.addEventListener("submit", (event) => {
            event.preventDefault();
            if (!form.reportValidity()) return;
            page = 1;
            load();
        });
        root.querySelector("#billingReset").addEventListener("click", () => {
            form.reset();
            period.value = "month";
            page = 1;
            syncCustomDates();
            load();
        });
        root.querySelector("#billingPrevious").addEventListener("click", () => {
            if (page <= 1) return;
            page--;
            load();
            root.querySelector(".vd-billing-records").scrollIntoView({ behavior: "smooth", block: "start" });
        });
        root.querySelector("#billingNext").addEventListener("click", () => {
            page++;
            load();
            root.querySelector(".vd-billing-records").scrollIntoView({ behavior: "smooth", block: "start" });
        });
        root.querySelector("#billingRecordsTable").addEventListener("click", (event) => {
            const button = event.target.closest("[data-billing-index]");
            if (!button) return;
            const record = visibleRecords[Number(button.dataset.billingIndex)];
            if (record) showRecord(record);
        });

        syncCustomDates();
        load();
    }

    window.AdminBillingSummary = { init };
})();
