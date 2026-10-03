<?php require_once __DIR__ . '/../../helpers/csrf.php'; ?>
<script>
(() => {
    const root = document.getElementById('profileRequestsUI');
    const staff = root.dataset.mode === 'staff';
    const endpoint = <?= json_encode(vdAppUrl('apps/controllers/profilePrintRequestController.php')) ?>;
    const token = <?= json_encode(get_csrf_token()) ?>;
    const requestButton = document.getElementById('openProfileRequest');
    function reportError(message='') { root.querySelectorAll('[data-request-error], #profileRequestError').forEach(el=>{el.textContent=message;el.hidden=!message;}); }
    let requests = [];
    let busy = false;
    let confirming = false;
    async function confirmUpdate(id, status) {
        if (busy || confirming) return;
        confirming = true;
        try {
            const cancelling = status === 'Cancelled';
            const decision = await window.showActionModal({
                title: cancelling ? 'Cancel printed profile request?' : 'Mark printed profile collected?',
                kicker: 'Printed profile',
                message: cancelling ? 'The clinic will no longer prepare this printed copy.' : 'Confirm that the patient has received the printed profile.',
                confirmText: cancelling ? 'Cancel request' : 'Mark collected',
                cancelText: cancelling ? 'Keep request' : 'Go back',
                icon: cancelling ? 'ti-printer-off' : 'ti-check',
                tone: cancelling ? 'warning' : 'info'
            });
            if (decision.confirmed && root.isConnected) await update(id, status);
        } finally { confirming = false; }
    }
    function text(tag, value) { const el=document.createElement(tag); el.textContent=value; return el; }
    function button(label, action) { const el=text('button',label); el.type='button'; el.className='btn vd-request-secondary'; el.addEventListener('click',action); return el; }
    function badge(value) { const el=text('span',value);el.className='vd-request-badge';el.dataset.status=value;return el; }
    function date(value) { return new Date(value.replace(' ','T')).toLocaleString('en-PH',{month:'short',day:'numeric',year:'numeric',hour:'numeric',minute:'2-digit'}); }
    function render() {
        if (staff) {
            document.getElementById('profileRequestPendingCount').textContent=requests.filter(r=>r.status==='Pending').length;
            const filter=root.querySelector('#profileRequestFilter').value;
            const rows=requests.filter(r=>filter==='all'||(filter==='active'?['Pending','Ready for pickup'].includes(r.status):r.status===filter));
            const body=root.querySelector('#profileRequestRows'); body.replaceChildren();
            rows.forEach(r=>{
                const tr=document.createElement('tr');
                [r.patient_name,r.clinic_name,r.purpose,Number(r.include_billing)?'Included':'Excluded',date(r.requested_at)].forEach(value=>tr.appendChild(text('td',value)));
                const state=document.createElement('td');state.appendChild(badge(r.status));tr.appendChild(state);
                if(r.reason) { const reason=text('div',r.reason);reason.className='vd-request-reason';tr.children[2].appendChild(reason); }
                const actions=document.createElement('td'); const wrap=document.createElement('div'); wrap.className='vd-request-row-actions';
                const print=text('a','View / Print'); print.className='btn vd-request-secondary'; print.target='_blank'; print.rel='noopener';
                print.href=<?= json_encode(vdAppUrl('apps/views/shared/patient-record-print.php')) ?>+'?patient_id='+Number(r.patient_id)+'&request_id='+Number(r.request_id);
                wrap.appendChild(print);
                if(r.status==='Pending') { const ready=button('Mark ready',()=>update(r.request_id,'Ready for pickup'));ready.className='btn vd-btn-gold';wrap.appendChild(ready); }
                if(r.status==='Ready for pickup') { const collected=button('Mark collected',()=>confirmUpdate(r.request_id,'Collected'));collected.className='btn vd-btn-gold';wrap.appendChild(collected); }
                actions.appendChild(wrap);tr.appendChild(actions);body.appendChild(tr);
            });
            root.querySelector('#profileRequestEmpty').hidden=rows.length>0;
        } else {
            const active=requests.find(r=>['Pending','Ready for pickup'].includes(r.status));
            const status=root.querySelector('#profileRequestStatus'); status.replaceChildren();
            requestButton.disabled=Boolean(active)||busy;
            requestButton.title=active?'You already have an active request.':'';
            const latest=active||requests[0];
            if(latest){
                const line=document.createElement('div');line.className='vd-print-request-status';
                const info=document.createElement('div');info.className='vd-request-status-info';
                const heading=document.createElement('div');heading.className='vd-request-status-heading';heading.appendChild(text('strong','Printed profile'));heading.appendChild(badge(latest.status));
                info.appendChild(heading);
                info.appendChild(text('p',latest.clinic_name+' · '+(Number(latest.include_billing)?'Billing history included':'Billing history excluded')));
                line.appendChild(info);
                if(latest.status==='Pending') line.appendChild(button('Cancel request',()=>confirmUpdate(latest.request_id,'Cancelled')));
                status.appendChild(line);
            }
        }
    }
    async function load() {
        try { const response=await fetch(endpoint,{cache:'no-store'}); const result=await response.json(); if(!result.success)throw Error(result.message); requests=result.requests;render(); }
        catch(e){reportError(e.message||'Unable to load requests.');}
    }
    async function send(data) {
        if(busy)return false;busy=true;reportError();
        root.querySelectorAll('button').forEach(el=>el.disabled=true);
        data.set('csrf_token',token);
        try { const response=await fetch(endpoint,{method:'POST',body:data});const result=await response.json();if(!result.success)throw Error(result.message);requests=result.requests;render();window.showToast?.('Request updated.',true);return true; }
        catch(e){reportError(e.message||'Unable to update request.');return false;}
        finally {busy=false;root.querySelectorAll('button').forEach(el=>el.disabled=false);if(requestButton)requestButton.disabled=requests.some(r=>['Pending','Ready for pickup'].includes(r.status));}
    }
    function update(id,status){const data=new FormData();data.set('action','status');data.set('request_id',id);data.set('status',status);return send(data);}
    if(staff){
        root.querySelector('#profileRequestFilter').addEventListener('change',render);
        const records=document.getElementById('patientRecordsPanel'), panel=document.getElementById('profileRequestsPanel');
        document.querySelectorAll('[data-patient-tab]').forEach(btn=>btn.addEventListener('click',()=>{
            const show=btn.dataset.patientTab==='requests';records.hidden=show;panel.hidden=!show;
            document.querySelectorAll('[data-patient-tab]').forEach(tab=>tab.setAttribute('aria-selected',String(tab===btn)));
        }));
        if(<?= json_encode(!empty($_GET['profile_requests'])) ?> || sessionStorage.getItem('venturaOpenProfileRequests')==='1') {
            sessionStorage.removeItem('venturaOpenProfileRequests');document.querySelector('[data-patient-tab="requests"]').click();
        }
    }else{
        const form=root.querySelector('form');
        const dialog=root.querySelector('dialog');
        requestButton.addEventListener('click',()=>{reportError();dialog.showModal();});
        root.querySelectorAll('[data-close-request]').forEach(btn=>btn.addEventListener('click',()=>dialog.close()));
        root.querySelector('#requestPurpose').addEventListener('change',e=>{
            const other=e.target.value==='Other';root.querySelector('#requestReasonField').hidden=!other;root.querySelector('#requestReason').required=other;
            form.elements.include_billing.checked=e.target.value==='Personal copy';
        });
        form.addEventListener('submit',async e=>{e.preventDefault();const data=new FormData(form);data.set('action','create');if(await send(data)){dialog.close();form.reset();root.querySelector('#requestReasonField').hidden=true;root.querySelector('#requestReason').required=false;}});
    }
    load();
    const interval=setInterval(()=>{if(!root.isConnected){clearInterval(interval);return;}if(!busy&&!confirming&&!document.hidden)load();},15000);
})();
</script>
