(() => {
    document.addEventListener('click', async event => {
        const button=event.target.closest('[data-patient-cancel]');
        if (!button || button.disabled) return;
        const container=button.closest('.vd-patient-cancellation');
        const errorBox=container.querySelector('[data-cancel-error]');
        const confirmed=button.dataset.cancelConfirmed==='true';
        const labelNode=button.querySelector('[data-cancel-label]') || button;
        const label=labelNode.textContent;
        button.disabled=true;
        errorBox.hidden=true;
        try {
            const decision=await window.showActionModal({
                title: confirmed?'Cancel appointment?':'Withdraw appointment request?',
                kicker:'Appointment #'+button.dataset.patientCancel,
                message:confirmed?'Your reserved slot will be released. Any verified deposit will follow the clinic’s existing refund or transfer process.':'Your unconfirmed request will be withdrawn and its reserved slot released.',
                confirmText:confirmed?'Cancel appointment':'Withdraw request', cancelText:'Keep appointment',
                tone:'warning',icon:'ti-calendar-x',
                fields:[{name:'reason',label:'Reason',multiline:true,rows:2,required:true,maxlength:255}]
            });
            if (!decision.confirmed) return;
            labelNode.textContent='Submitting…';
            const payload=new FormData();
            payload.append('action','cancelPatient');
            payload.append('appointment_id',button.dataset.patientCancel);
            payload.append('reason',decision.values.reason);
            payload.append('csrf_token',container.dataset.csrf);
            const response=await fetch(window.vdAppUrl('apps/controllers/appointmentController.php'),{method:'POST',body:payload});
            const result=await response.json();
            if (!response.ok || !result.success) throw new Error(result.message||'Unable to cancel. Please try again.');
            window.showToast?.(result.message,true);
            document.querySelector('.vd-nav-item[data-page="home-content.php"]')?.click();
        } catch(error) {
            errorBox.textContent=error.message||'Unable to cancel. Please try again.';
            errorBox.hidden=false;
        } finally {
            button.disabled=false;
            labelNode.textContent=label;
            if (button.isConnected) button.focus({preventScroll:true});
        }
    });
})();
