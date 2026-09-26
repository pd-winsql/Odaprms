document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('completeVisitPage')) return;
    const money = value => Number(value || 0).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });
    const activeBillingAppointment = JSON.parse(document.getElementById("completeVisitData").textContent);
const csrfToken = activeBillingAppointment.csrfToken;
        const serviceAmountInput = document.getElementById('finalServiceAmount');
        const cashTenderedInput = document.getElementById('finalCashTendered');
        const completeBillingButton = document.getElementById('recordPaymentAndComplete');
        const chargeLinesContainer = document.getElementById('finalChargeLines');
        const maxServicesPerVisit = activeBillingAppointment.maxServices;
        const performedServiceInputs = Array.from(document.querySelectorAll('[data-final-service]'));
        const serviceChangeReasonInput = document.getElementById('finalServiceChangeReason');
        const serviceLineState = new Map();
        const finalOdontogramRoot = document.getElementById('completeVisitOdontogram');
        let finalOdontogramWorkspace = null;
        const ensureFinalOdontogramWorkspace = () => {
            finalOdontogramWorkspace ??= window.VdOdontogram?.mount(finalOdontogramRoot) || null;
            return finalOdontogramWorkspace;
        };

        const selectedServiceInputs = () => performedServiceInputs.filter(input => input.checked);
        const sortedServiceIds = inputs => inputs.map(input => Number(input.value)).sort((a, b) => a - b);
        const selectionsMatch = (left, right) => left.length === right.length && left.every((value, index) => value === right[index]);

        function startingLineFor(input) {
            const serviceId = String(input.value);
            const original = activeBillingAppointment.originalServicePricing?.[serviceId];
            const defaultPrice = input.dataset.defaultPrice === '' ? '' : Number(input.dataset.defaultPrice);
            return {
                quantity: Math.max(1, Number(original?.quantity || 1)),
                unitPrice: original?.unitPrice !== null && original?.unitPrice !== undefined
                    ? Number(original.unitPrice)
                    : defaultPrice,
                billingUnit: original?.billingUnit || input.dataset.billingUnit || 'service'
            };
        }

        function ensureSelectedLineState() {
            selectedServiceInputs().forEach(input => {
                const serviceId = Number(input.value);
                if (!serviceLineState.has(serviceId)) serviceLineState.set(serviceId, startingLineFor(input));
            });
        }

        function renderChargeLines() {
            ensureSelectedLineState();
            chargeLinesContainer.replaceChildren();
            selectedServiceInputs().forEach(input => {
                const serviceId = Number(input.value);
                const state = serviceLineState.get(serviceId);
                const row = document.createElement('div');
                row.className = 'vd-final-charge-line';
                row.dataset.serviceId = String(serviceId);

                const heading = document.createElement('div');
                heading.className = 'vd-final-charge-line-heading';
                const title = document.createElement('strong');
                title.textContent = input.dataset.serviceName;
                const basis = document.createElement('span');
                basis.textContent = state.billingUnit === 'tooth' ? 'Charged per tooth' : 'Charged per service';
                heading.append(title, basis);

                const fields = document.createElement('div');
                fields.className = 'vd-final-charge-line-fields';
                const priceLabel = document.createElement('label');
                priceLabel.innerHTML = '<span>Rate</span>';
                const priceInput = document.createElement('input');
                priceInput.type = 'number';
                priceInput.min = '0';
                priceInput.max = '99999999.99';
                priceInput.step = '0.01';
                priceInput.inputMode = 'decimal';
                priceInput.className = 'form-control vd-input';
                priceInput.placeholder = 'Enter rate';
                priceInput.value = state.unitPrice === '' ? '' : String(state.unitPrice);
                priceInput.dataset.chargePrice = '';
                priceLabel.appendChild(priceInput);

                const quantityLabel = document.createElement('label');
                const quantityText = document.createElement('span');
                quantityText.textContent = state.billingUnit === 'tooth' ? 'Number of teeth' : 'Quantity';
                const quantityInput = document.createElement('input');
                quantityInput.type = 'number';
                quantityInput.min = '1';
                quantityInput.max = '99';
                quantityInput.step = '1';
                quantityInput.inputMode = 'numeric';
                quantityInput.className = 'form-control vd-input';
                quantityInput.value = String(state.quantity);
                quantityInput.dataset.chargeQuantity = '';
                quantityLabel.append(quantityText, quantityInput);

                const lineTotal = document.createElement('div');
                lineTotal.className = 'vd-final-charge-line-total';
                lineTotal.innerHTML = '<span>Line total</span><strong data-charge-line-total>—</strong>';
                fields.append(priceLabel, quantityLabel, lineTotal);
                row.append(heading, fields);
                chargeLinesContainer.appendChild(row);

                const updateState = () => {
                    state.unitPrice = priceInput.value === '' ? '' : Number(priceInput.value);
                    state.quantity = Number(quantityInput.value);
                    updateFinalBillingSummary();
                };
                priceInput.addEventListener('input', updateState);
                quantityInput.addEventListener('input', updateState);
            });
        }

        function calculateServiceCharges() {
            let total = 0;
            let valid = selectedServiceInputs().length > 0;
            chargeLinesContainer.querySelectorAll('.vd-final-charge-line').forEach(row => {
                const serviceId = Number(row.dataset.serviceId);
                const state = serviceLineState.get(serviceId);
                const priceValid = state.unitPrice !== '' && Number.isFinite(Number(state.unitPrice)) && Number(state.unitPrice) >= 0;
                const quantityValid = Number.isInteger(Number(state.quantity)) && Number(state.quantity) >= 1 && Number(state.quantity) <= 99;
                const lineTotal = priceValid && quantityValid ? Number(state.unitPrice) * Number(state.quantity) : null;
                row.classList.toggle('is-invalid', !priceValid || !quantityValid);
                row.querySelector('[data-charge-line-total]').textContent = lineTotal === null ? 'Needs rate' : money(lineTotal);
                if (lineTotal === null) valid = false;
                else total += lineTotal;
            });
            serviceAmountInput.value = valid ? total.toFixed(2) : '';
            return { valid, total };
        }

        function serviceLineSignature() {
            return selectedServiceInputs().map(input => {
                const state = serviceLineState.get(Number(input.value)) || startingLineFor(input);
                return `${input.value}:${state.quantity}:${state.unitPrice}`;
            }).sort().join('|');
        }

        function serviceSelectionState() {
            const selectedInputs = selectedServiceInputs();
            const selectedIds = sortedServiceIds(selectedInputs);
            const originalIds = [...(activeBillingAppointment?.originalServiceIds || [])].sort((a, b) => a - b);
            return {
                selectedInputs,
                selectedIds,
                changed: !selectionsMatch(selectedIds, originalIds)
            };
        }

        function updateServiceSelection() {
            const state = serviceSelectionState();
            const selectedCount = state.selectedInputs.length;
            const originalIds = activeBillingAppointment?.originalServiceIds || [];
            const feedback = document.getElementById('finalServiceSelectionFeedback');
            const counter = document.getElementById('finalServiceSelectionCount');
            const reasonGroup = document.getElementById('finalServiceChangeReasonGroup');
            const overLimit = state.changed && selectedCount > maxServicesPerVisit;

            performedServiceInputs.forEach(input => {
                const isOriginal = originalIds.includes(Number(input.value));
                const isInactiveAddition = input.dataset.serviceActive !== '1' && !isOriginal;
                const selectionIsFull = selectedCount >= maxServicesPerVisit && !input.checked;
                input.disabled = !activeBillingAppointment || isInactiveAddition || selectionIsFull;
            });

            counter.textContent = originalIds.length > maxServicesPerVisit && !state.changed
                ? `${selectedCount} selected · existing booking retained`
                : `${selectedCount} of ${maxServicesPerVisit} selected`;
            reasonGroup.classList.toggle('d-none', !state.changed);
            serviceChangeReasonInput.required = state.changed;

            feedback.className = 'vd-billing-service-feedback';
            if (!selectedCount) {
                feedback.textContent = 'Select at least one service performed.';
                feedback.classList.add('is-error');
            } else if (overLimit) {
                feedback.textContent = `Reduce the edited selection to ${maxServicesPerVisit} services or fewer.`;
                feedback.classList.add('is-error');
            } else if (state.changed) {
                feedback.textContent = 'The performed services differ from the booking. Add a short reason below.';
                feedback.classList.add('is-changed');
            } else {
                feedback.textContent = 'The performed services match the original booking.';
            }

            return selectedCount > 0 && !overLimit;
        }

        function updateFinalBillingSummary() {
            const chargeState = calculateServiceCharges();
            const charge = chargeState.total;
            const deposit = Math.min(Number(activeBillingAppointment?.deposit || 0), charge);
            const due = Math.max(0, charge - deposit);
            const cash = Math.max(0, Number(cashTenderedInput.value) || 0);
            const change = Math.max(0, cash - due);
            document.getElementById('finalChargeDisplay').textContent = money(charge);
            document.getElementById('finalDepositDisplay').textContent = '−' + money(deposit);
            document.getElementById('finalAmountDueDisplay').textContent = money(due);
            document.getElementById('finalCashDisplay').textContent = money(cash);
            document.getElementById('finalChangeDisplay').textContent = money(change);
            const servicesValid = updateServiceSelection();
            const selection = serviceSelectionState();
            const changeReasonValid = !selection.changed || serviceChangeReasonInput.value.trim().length >= 3;
            completeBillingButton.disabled = !chargeState.valid || cash < due || !servicesValid || !changeReasonValid || !cashTenderedInput.checkValidity();
            const error = document.getElementById('finalBillingError');
            if (!chargeState.valid && selection.selectedInputs.length) {
                error.textContent = 'Enter a valid rate and quantity for every selected service.';
                error.classList.remove('d-none');
            } else if (cash < due) {
                error.textContent = `Cash tendered is ${money(due - cash)} short of the amount due.`;
                error.classList.remove('d-none');
            } else {
                error.classList.add('d-none');
                error.textContent = '';
            }
        }

        performedServiceInputs.forEach(input => {
            input.checked = activeBillingAppointment.originalServiceIds.includes(Number(input.value));
            if (input.checked) serviceLineState.set(Number(input.value), startingLineFor(input));
        });
        renderChargeLines();
        const initialLineSignature = serviceLineSignature();
        const chartDetails = document.getElementById('completeVisitChart');
        chartDetails.addEventListener('toggle', async () => {
            if (!chartDetails.open || finalOdontogramWorkspace?.loaded) return;
            try { await ensureFinalOdontogramWorkspace().load(activeBillingAppointment.patientId); }
            catch (error) { window.showToast(error.message, false); }
        });
        let settled = false;
        window.addEventListener('beforeunload', event => {
            if (settled) return;
            const paymentDirty = cashTenderedInput.value !== '0' || serviceLineSignature() !== initialLineSignature
                || serviceSelectionState().changed || document.getElementById('finalBillingNotes').value !== ''
                || finalOdontogramRoot.classList.contains('is-dirty');
            if (paymentDirty) { event.preventDefault(); event.returnValue = ''; }
        });
        updateFinalBillingSummary();
        [cashTenderedInput, serviceChangeReasonInput].forEach(input => input?.addEventListener('input', updateFinalBillingSummary));
        performedServiceInputs.forEach(input => input.addEventListener('change', () => {
            renderChargeLines();
            updateFinalBillingSummary();
        }));

        completeBillingButton?.addEventListener('click', async function() {
            if (!activeBillingAppointment) return;
            updateFinalBillingSummary();
            if (this.disabled) return;
            if (finalOdontogramRoot.classList.contains('is-loading')) return;
            if (finalOdontogramRoot.classList.contains('is-dirty')) {
                const discard = await window.showActionModal({
                    title: 'Unsaved dental chart', message: 'Cancel to save the chart, or discard its unsaved edits before completing payment.',
                    confirmText: 'Discard chart edits', tone: 'warning', icon: 'ti-dental'
                });
                if (!discard.confirmed) return;
                try {
                    await finalOdontogramWorkspace.load(activeBillingAppointment.patientId);
                    finalOdontogramRoot.classList.remove('is-dirty');
                } catch (error) { window.showToast(error.message, false); return; }
            }
            const selection = serviceSelectionState();
            const performedServiceCharges = selection.selectedInputs.map(input => {
                const line = serviceLineState.get(Number(input.value));
                const unit = line.billingUnit === 'tooth' ? (line.quantity === 1 ? 'tooth' : 'teeth') : (line.quantity === 1 ? 'service' : 'services');
                return `${input.dataset.serviceName}: ${line.quantity} ${unit} × ${money(line.unitPrice)}`;
            });
            const confirmationDetails = [{
                    label: 'Patient',
                    value: activeBillingAppointment.patient
                },
                {
                    label: 'Services performed',
                    value: performedServiceCharges.join(' · ')
                },
                {
                    label: 'Amount due',
                    value: document.getElementById('finalAmountDueDisplay').textContent
                },
                {
                    label: 'Cash tendered',
                    value: document.getElementById('finalCashDisplay').textContent
                },
                {
                    label: 'Change',
                    value: document.getElementById('finalChangeDisplay').textContent
                }
            ];
            if (selection.changed) {
                confirmationDetails.push({
                    label: 'Service change reason',
                    value: serviceChangeReasonInput.value.trim()
                });
            }
            const confirmation = await window.showActionModal({
                title: 'Confirm Final Payment',
                kicker: 'Complete transaction',
                message: 'This records the cash payment and completes the visit. The transaction cannot be edited from Today’s Logbook afterward.',
                confirmText: 'Confirm & Complete',
                icon: 'ti-cash-check',
                tone: 'success',
                details: confirmationDetails
            });
            if (!confirmation.confirmed) return;
            LoadingUI.setButton(this, true, 'Completing...');
            const body = new FormData();
            body.append('action', 'settleAndComplete');
            body.append('csrf_token', csrfToken);
            body.append('appointment_id', activeBillingAppointment.id);
            body.append('service_amount', serviceAmountInput.value);
            body.append('cash_received', cashTenderedInput.value);
            body.append('notes', document.getElementById('finalBillingNotes').value);
            selection.selectedIds.forEach(serviceId => body.append('service_ids[]', String(serviceId)));
            selection.selectedIds.forEach(serviceId => {
                const line = serviceLineState.get(serviceId);
                body.append(`service_quantities[${serviceId}]`, String(line.quantity));
                body.append(`service_unit_prices[${serviceId}]`, String(line.unitPrice));
            });
            body.append('service_change_reason', serviceChangeReasonInput.value.trim());
            try {
                const response = await fetch(window.vdAppUrl('apps/controllers/billingController.php'), {
                    method: 'POST',
                    body
                });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Unable to complete the transaction.');
                settled = true;
                window.location.assign('dashboard.php');
            } catch (error) {
                LoadingUI.setButton(this, false);
                const errorBox = document.getElementById('finalBillingError');
                errorBox.textContent = error.message || 'Unable to complete the transaction.';
                errorBox.classList.remove('d-none');
                window.showToast(error.message, false);
            }
        });


});
