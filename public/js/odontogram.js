(function () {
    'use strict';

    const CODE_LABELS = {
        Condition: {
            D: 'Decayed', M: 'Missing due to caries', F: 'Filled', X: 'For extraction',
            RF: 'Root fragment', MO: 'Missing — other cause', IM: 'Impacted tooth'
        },
        Restoration: {
            JC: 'Jacket crown', AM: 'Amalgam filling', CO: 'Composite', AB: 'Abutment',
            P: 'Pontic', IN: 'Inlay', S: 'Sealant', RD: 'Removable denture'
        },
        Surgery: {
            X: 'Extraction due to caries', XO: 'Extraction — other cause',
            CM: 'Congenitally missing', SP: 'Supernumerary', UN: 'Unerupted'
        }
    };
    const SURFACES = ['Whole', 'Occlusal', 'Mesial', 'Distal', 'Buccal', 'Lingual'];

    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
    })[character]);
    const money = value => Number(value || 0).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });
    const displayDate = value => value
        ? new Date(`${value}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
        : '—';

    class OdontogramWorkspace {
        constructor(root) {
            this.root = root;
            this.readOnly = root.dataset.readOnly === '1';
            this.billingContext = root.dataset.billingContext === '1';
            this.patientId = 0;
            this.appointmentId = 0;
            this.selectedTooth = '';
            this.entries = [];
            this.loaded = false;
            this.bind();
            this.renderLegend();
            this.populateCodes();
            this.renderSurfaces();
        }

        bind() {
            this.root.querySelectorAll('[data-dentition]').forEach(button => {
                button.addEventListener('click', () => {
                    this.showDentition(button.dataset.dentition);
                    this.markDirty();
                });
            });
            this.root.querySelectorAll('[data-tooth]').forEach(button => {
                button.addEventListener('click', () => this.selectTooth(button.dataset.tooth));
            });
            this.root.querySelector('[data-finding-category]')?.addEventListener('change', () => this.populateCodes());
            this.root.querySelector('[data-finding-form]')?.addEventListener('submit', event => {
                event.preventDefault();
                this.addFinding();
            });
            this.root.querySelector('[data-save-odontogram]')?.addEventListener('click', () => this.save());
            this.root.querySelectorAll('[data-chart-field]').forEach(input => {
                input.addEventListener('input', () => this.markDirty());
                input.addEventListener('change', () => this.markDirty());
            });
        }

        async load(patientId, appointmentId = 0) {
            this.patientId = Number(patientId) || 0;
            this.appointmentId = Number(appointmentId) || 0;
            this.setBusy(true);
            try {
                const query = new URLSearchParams({
                    action: 'get', patient_id: String(this.patientId), appointment_id: String(this.appointmentId)
                });
                const response = await fetch(`${this.root.dataset.controller}?${query}`, { cache: 'no-store', headers: { Accept: 'application/json' } });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Unable to load the dental chart.');
                this.hydrate(result.data);
                this.root.classList.remove('is-dirty');
                if (!this.billingContext) {
                    const title = this.root.querySelector('[data-savebar-title]');
                    if (title) title.textContent = 'Dental chart · no unsaved changes';
                }
                this.loaded = true;
                return result.data;
            } catch (error) {
                this.root.querySelector('[data-odontogram-patient]').textContent = error.message || 'Unable to load the dental chart.';
                throw error;
            } finally {
                this.setBusy(false);
            }
        }

        hydrate(data) {
            const chart = data.chart || {};
            this.entries = Array.isArray(chart.teeth) ? chart.teeth.map(item => ({ ...item })) : [];
            this.root.querySelector('[data-odontogram-patient]').textContent = `${data.patient.name} · FDI tooth numbering`;
            const updated = this.root.querySelector('[data-odontogram-updated]');
            updated.textContent = chart.updated_at
                ? `Updated ${new Date(chart.updated_at.replace(' ', 'T')).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' })}${chart.updated_by ? ` by ${chart.updated_by}` : ''}`
                : 'No chart recorded';
            this.root.querySelectorAll('[data-chart-field]').forEach(input => {
                input.value = chart[input.dataset.chartField] ?? '';
            });
            this.showDentition(['Permanent', 'Primary', 'Mixed'].includes(chart.dentition_type) ? chart.dentition_type : 'Permanent');
            this.renderTeeth();
            this.renderLedger(data.ledger || []);
            this.setReviewState(Boolean(data.appointment_review), data.appointment_review);
            if (this.selectedTooth) this.renderInspector();
        }

        setBusy(busy) {
            this.root.classList.toggle('is-loading', busy);
            this.root.setAttribute('aria-busy', String(busy));
        }

        showDentition(dentition) {
            const visible = ['Permanent', 'Primary', 'Mixed'].includes(dentition) ? dentition : 'Permanent';
            this.root.querySelectorAll('[data-dentition]').forEach(button => {
                const selected = button.dataset.dentition === visible;
                button.classList.toggle('is-active', selected);
                button.setAttribute('aria-pressed', String(selected));
            });
            this.root.querySelectorAll('[data-dentition-chart]').forEach(chart => { chart.hidden = visible !== 'Mixed' && chart.dataset.dentitionChart !== visible; });
        }

        selectTooth(tooth) {
            this.selectedTooth = tooth;
            this.root.querySelectorAll('[data-tooth]').forEach(button => {
                const selected = button.dataset.tooth === tooth;
                button.classList.toggle('is-selected', selected);
                button.setAttribute('aria-pressed', String(selected));
            });
            this.root.querySelector('[data-inspector-empty]').hidden = true;
            this.root.querySelector('[data-inspector-content]').hidden = false;
            this.root.querySelector('[data-selected-tooth]').textContent = tooth;
            this.renderInspector();
        }

        populateCodes() {
            const category = this.root.querySelector('[data-finding-category]')?.value || 'Condition';
            const select = this.root.querySelector('[data-finding-code]');
            if (!select) return;
            select.replaceChildren(...Object.entries(CODE_LABELS[category]).map(([code, label]) => {
                const option = document.createElement('option');
                option.value = code;
                option.textContent = `${code} — ${label}`;
                return option;
            }));
        }

        renderSurfaces() {
            const container = this.root.querySelector('[data-surface-options]');
            if (!container) return;
            container.innerHTML = SURFACES.map((surface, index) => `
                <label><input type="radio" name="${this.root.id}-surface" value="${surface}" ${index === 0 ? 'checked' : ''}><span>${surface}</span></label>
            `).join('');
        }

        addFinding() {
            if (!this.selectedTooth || this.readOnly) return;
            const category = this.root.querySelector('[data-finding-category]').value;
            const findingCode = this.root.querySelector('[data-finding-code]').value;
            const surface = this.root.querySelector('[data-surface-options] input:checked')?.value || 'Whole';
            const notesInput = this.root.querySelector('[data-finding-notes]');
            const finding = {
                tooth_number: this.selectedTooth,
                surface,
                category,
                finding_code: findingCode,
                notes: notesInput.value.trim()
            };
            const existing = this.entries.findIndex(item => item.tooth_number === finding.tooth_number && item.surface === surface && item.category === category);
            if (existing >= 0) this.entries.splice(existing, 1, finding);
            else this.entries.push(finding);
            notesInput.value = '';
            this.renderTeeth();
            this.renderInspector();
            this.markDirty();
        }

        removeFinding(index) {
            if (this.readOnly) return;
            this.entries.splice(index, 1);
            this.renderTeeth();
            this.renderInspector();
            this.markDirty();
        }

        renderTeeth() {
            this.root.querySelectorAll('[data-tooth]').forEach(button => {
                const findings = this.entries.filter(item => item.tooth_number === button.dataset.tooth);
                button.classList.toggle('has-findings', findings.length > 0);
                button.classList.toggle('has-condition', findings.some(item => item.category === 'Condition'));
                button.classList.toggle('has-restoration', findings.some(item => item.category === 'Restoration'));
                button.classList.toggle('has-surgery', findings.some(item => item.category === 'Surgery'));
                button.querySelector('.vd-tooth-markers').textContent = findings.map(item => item.finding_code).join(' · ');
                button.setAttribute('aria-label', findings.length
                    ? `Tooth ${button.dataset.tooth}, ${findings.length} recorded ${findings.length === 1 ? 'finding' : 'findings'}`
                    : `Tooth ${button.dataset.tooth}, no recorded findings`);
            });
        }

        renderInspector() {
            const container = this.root.querySelector('[data-tooth-findings]');
            if (!container || !this.selectedTooth) return;
            const indexed = this.entries.map((finding, index) => ({ finding, index })).filter(item => item.finding.tooth_number === this.selectedTooth);
            if (!indexed.length) {
                container.innerHTML = '<p class="vd-odontogram-no-findings">No findings recorded for this tooth.</p>';
                return;
            }
            container.innerHTML = indexed.map(({ finding, index }) => `
                <div class="vd-odontogram-finding is-${finding.category.toLowerCase()}">
                    <span>${escapeHtml(finding.finding_code)}</span>
                    <div><strong>${escapeHtml(CODE_LABELS[finding.category]?.[finding.finding_code] || finding.finding_code)}</strong><small>${escapeHtml(finding.surface)}${finding.notes ? ` · ${escapeHtml(finding.notes)}` : ''}</small></div>
                    ${this.readOnly ? '' : `<button type="button" data-remove-finding="${index}" aria-label="Remove ${escapeHtml(finding.finding_code)} finding"><i class="ti ti-x"></i></button>`}
                </div>
            `).join('');
            container.querySelectorAll('[data-remove-finding]').forEach(button => button.addEventListener('click', () => this.removeFinding(Number(button.dataset.removeFinding))));
        }

        renderLegend() {
            const container = this.root.querySelector('[data-odontogram-legend-grid]');
            if (!container) return;
            container.innerHTML = Object.entries(CODE_LABELS).map(([category, codes]) => `
                <section><h4>${category}</h4>${Object.entries(codes).map(([code, label]) => `<div><strong>${code}</strong><span>${label}</span></div>`).join('')}</section>
            `).join('');
        }

        renderLedger(rows) {
            const body = this.root.querySelector('[data-odontogram-ledger]');
            if (!body) return;
            if (!rows.length) {
                body.innerHTML = '<tr><td colspan="8" class="vd-odontogram-ledger-empty">No finalized treatment records yet.</td></tr>';
                return;
            }
            body.innerHTML = rows.map(row => `<tr>
                <td>${displayDate(row.date)}</td><td>${escapeHtml((row.tooth_numbers || []).join(', ') || '—')}</td>
                <td><strong>${escapeHtml(row.procedure_name)}</strong><small>Appointment #${escapeHtml(row.appointment_id)}</small></td>
                <td>${escapeHtml(row.dentist_name || 'Admin / Dentist')}</td><td>${money(row.actual_service_amount)}</td>
                <td>${money(row.amount_paid)}</td><td>${money(row.outstanding_balance)}</td><td>${escapeHtml(row.notes || '—')}</td>
            </tr>`).join('');
        }

        serialize() {
            const chart = { teeth: this.entries.map(item => ({ ...item })) };
            this.root.querySelectorAll('[data-chart-field]').forEach(input => { chart[input.dataset.chartField] = input.value.trim(); });
            const visibleDentition = this.root.querySelector('[data-dentition].is-active')?.dataset.dentition || 'Permanent';
            chart.dentition_type = visibleDentition;
            return chart;
        }

        markDirty() {
            if (this.readOnly) return;
            this.root.classList.add('is-dirty');
            if (this.billingContext) {
                this.setReviewState(false);
                this.root.dispatchEvent(new CustomEvent('odontogram:dirty', { bubbles: true, detail: { appointmentId: this.appointmentId } }));
            }
            const title = this.root.querySelector('[data-savebar-title]');
            if (title) title.textContent = this.billingContext ? 'Dental chart needs review' : 'Unsaved dental chart';
        }

        setReviewState(reviewed, review = null) {
            this.root.dataset.reviewed = reviewed ? '1' : '0';
            if (!this.billingContext) return;
            const title = this.root.querySelector('[data-savebar-title]');
            const message = this.root.querySelector('[data-savebar-message]');
            if (title) title.textContent = reviewed ? 'Reviewed for this visit' : 'Review required for settlement';
            if (message) message.textContent = reviewed
                ? `Recorded${review?.reviewed_by ? ` by ${review.reviewed_by}` : ''}${review?.reviewed_at ? ` on ${new Date(review.reviewed_at.replace(' ', 'T')).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' })}` : ''}.`
                : 'Review the chart and save it before completing final billing.';
            if (reviewed) this.root.dispatchEvent(new CustomEvent('odontogram:reviewed', { bubbles: true, detail: { appointmentId: this.appointmentId } }));
        }

        async save() {
            if (this.readOnly || !this.patientId) return;
            const button = this.root.querySelector('[data-save-odontogram]');
            const originalLabel = button.textContent;
            this.setBusy(true);
            button.disabled = true;
            button.textContent = this.billingContext ? 'Marking reviewed…' : 'Saving…';
            const body = new FormData();
            body.append('action', 'save');
            body.append('csrf_token', this.root.dataset.csrf || '');
            body.append('patient_id', String(this.patientId));
            body.append('appointment_id', String(this.appointmentId));
            body.append('chart', JSON.stringify(this.serialize()));
            try {
                const response = await fetch(this.root.dataset.controller, { method: 'POST', body, headers: { Accept: 'application/json' } });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Unable to save the dental chart.');
                this.root.classList.remove('is-dirty');
                this.setReviewState(Boolean(result.reviewed), result.reviewed ? { reviewed_by: 'you', reviewed_at: new Date().toISOString() } : null);
                window.showToast?.(result.message, true);
                await this.load(this.patientId, this.appointmentId);
            } catch (error) {
                window.showToast?.(error.message || 'Unable to save the dental chart.', false);
            } finally {
                this.setBusy(false);
                button.disabled = false;
                button.textContent = originalLabel;
            }
        }
    }

    window.VdOdontogram = {
        mount(root) {
            if (!root) return null;
            if (root._odontogramWorkspace) return root._odontogramWorkspace;
            root._odontogramWorkspace = new OdontogramWorkspace(root);
            return root._odontogramWorkspace;
        }
    };
})();
