document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('completeVisitPage')) return;
    const money = value => Number(value || 0).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });
    const activeBillingAppointment = JSON.parse(document.getElementById("completeVisitData").textContent);
const csrfToken = activeBillingAppointment.csrfToken;
        const serviceAmountInput = document.getElementById('finalServiceAmount');
        const cashTenderedInput = document.getElementById('finalCashTendered');
        const completeBillingButton = document.getElementById('recordPaymentAndComplete');
        const maxServicesPerVisit = activeBillingAppointment.maxServices;
        const performedServiceInputs = Array.from(document.querySelectorAll('[data-final-service]'));
        const serviceChangeReasonInput = document.getElementById('finalServiceChangeReason');
        const finalOdontogramRoot = document.getElementById('completeVisitOdontogram');
        let finalOdontogramWorkspace = null;
        const ensureFinalOdontogramWorkspace = () => {
            finalOdontogramWorkspace ??= window.VdOdontogram?.mount(finalOdontogramRoot) || null;
            return finalOdontogramWorkspace;
        };

        const selectedServiceInputs = () => performedServiceInputs.filter(input => input.checked);
        const sortedServiceIds = inputs => inputs.map(input => Number(input.value)).sort((a, b) => a - b);
        const selectionsMatch = (left, right) => left.length === right.length && left.every((value, index) => value === right[index]);

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
            const charge = Math.max(0, Number(serviceAmountInput.value) || 0);
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
            completeBillingButton.disabled = serviceAmountInput.value === '' || cash < due || !servicesValid || !changeReasonValid || !serviceAmountInput.checkValidity() || !cashTenderedInput.checkValidity();
            const error = document.getElementById('finalBillingError');
            if (serviceAmountInput.value !== '' && cash < due) {
                error.textContent = `Cash tendered is ${money(due - cash)} short of the amount due.`;
                error.classList.remove('d-none');
            } else {
                error.classList.add('d-none');
                error.textContent = '';
            }
        }


        performedServiceInputs.forEach(input => { input.checked = activeBillingAppointment.originalServiceIds.includes(Number(input.value)); });
        const chartDetails = document.getElementById('completeVisitChart');
        chartDetails.addEventListener('toggle', async () => {
            if (!chartDetails.open || finalOdontogramWorkspace?.loaded) return;
            try { await ensureFinalOdontogramWorkspace().load(activeBillingAppointment.patientId); }
            catch (error) { window.showToast(error.message, false); }
        });
        let settled = false;
        window.addEventListener('beforeunload', event => {
            if (settled) return;
            const paymentDirty = serviceAmountInput.value !== '' || cashTenderedInput.value !== '0'
                || serviceSelectionState().changed || document.getElementById('finalBillingNotes').value !== ''
                || finalOdontogramRoot.classList.contains('is-dirty');
            if (paymentDirty) { event.preventDefault(); event.returnValue = ''; }
        });
        updateFinalBillingSummary();
        [serviceAmountInput, cashTenderedInput, serviceChangeReasonInput].forEach(input => input?.addEventListener('input', updateFinalBillingSummary));
        performedServiceInputs.forEach(input => input.addEventListener('change', updateFinalBillingSummary));

        completeBillingButton?.addEventListener('click', async function() {
            if (!activeBillingAppointment) return;
            updateFinalBillingSummary();
            if (this.disabled) return;
            if (finalOdontogramRoot.classList.contains('is-loading')) return;
            if (finalOdontogramRoot.classList.contains('is-dirty')) {
                const discard = await window.showActionModal({
                    title: 'Unsaved dental chart', message: 'Cancel to save the chart, or discard its unsaved edits before completing payment.',
                    confirmText: 'Discard chart edits', tone: 'warning', icon: 'ti-tooth'
                });
                if (!discard.confirmed) return;
                try {
                    await finalOdontogramWorkspace.load(activeBillingAppointment.patientId);
                    finalOdontogramRoot.classList.remove('is-dirty');
                } catch (error) { window.showToast(error.message, false); return; }
            }
            const selection = serviceSelectionState();
            const performedServiceNames = selection.selectedInputs.map(input => input.dataset.serviceName);
            const confirmationDetails = [{
                    label: 'Patient',
                    value: activeBillingAppointment.patient
                },
                {
                    label: 'Services performed',
                    value: performedServiceNames.join(', ')
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
            body.append('service_change_reason', serviceChangeReasonInput.value.trim());
            try {
                const response = await fetch('../../controllers/billingController.php', {
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

