<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$allowedRoles = ['Admin', 'Dental Assistant', 'Patient'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', $allowedRoles, true)) {
    echo '<div class="vd-empty-state">Unauthorized.</div>'; exit;
}
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$accountRole = (string) $_SESSION['user_role'];
?>
<div class="d-flex flex-column gap-4">
    <div><div class="vd-welcome-greet">ACCOUNT SECURITY</div><div class="vd-welcome-name">Change Password</div><p class="text-muted small mb-0 mt-2">Confirm your current password before setting a new one.</p></div>
    <div class="vd-dash-card">
        <div class="vd-dash-card-header"><span class="vd-dash-card-title"><?= htmlspecialchars($accountRole) ?> password</span></div>
        <div class="vd-profile-body">
            <form id="accountChangePasswordForm" class="vd-change-password-form d-flex flex-column gap-3" novalidate>
                <?php foreach ([['current_password','accountCurrentPw','Current Password'],['new_password','accountNewPw','New Password'],['confirm_password','accountConfirmPw','Confirm New Password']] as [$name,$id,$label]): ?>
                <div class="vd-auth-group">
                    <label class="vd-label form-label" for="<?= $id ?>"><?= $label ?></label>
                    <div class="vd-auth-input-wrap">
                        <input type="password" name="<?= $name ?>" id="<?= $id ?>" class="vd-input vd-auth-input" autocomplete="<?= $name === 'current_password' ? 'current-password' : 'new-password' ?>" required>
                        <button type="button" class="vd-pw-toggle" data-toggle-account-password="<?= $id ?>" aria-label="Show <?= strtolower($label) ?>"><i class="ti ti-eye" aria-hidden="true"></i></button>
                    </div>
                    <?php if ($name === 'new_password'): ?><div class="small text-muted mt-1">Use at least 8 characters with both letters and numbers.</div><?php endif; ?>
                </div>
                <?php endforeach; ?>
                <div id="accountPasswordMessage" class="alert d-none mb-0" role="status" aria-live="polite"></div>
                <div><button type="submit" class="btn vd-btn-gold">Change Password</button></div>
            </form>
        </div>
    </div>
</div>
<script>
(function () {
    const form=document.getElementById('accountChangePasswordForm'),message=document.getElementById('accountPasswordMessage'),csrfToken=<?= json_encode($_SESSION['csrf_token']) ?>;
    document.querySelectorAll('[data-toggle-account-password]').forEach(button=>button.addEventListener('click',()=>{const input=document.getElementById(button.dataset.toggleAccountPassword),showing=input.type==='text';input.type=showing?'password':'text';button.setAttribute('aria-label',showing?'Show password':'Hide password');button.querySelector('i').className=showing?'ti ti-eye':'ti ti-eye-off';}));
    const showMessage=(text,success)=>{message.textContent=text;message.className=`alert ${success?'alert-success':'alert-danger'} mb-0`;};
    form?.addEventListener('submit',async event=>{event.preventDefault();message.classList.add('d-none');const data=new FormData(form),current=String(data.get('current_password')||''),next=String(data.get('new_password')||''),confirmation=String(data.get('confirm_password')||'');
        if(!current||!next||!confirmation){showMessage('Please fill in all password fields.',false);return;}if(next!==confirmation){showMessage('New passwords do not match.',false);return;}if(!/^(?=.*[A-Za-z])(?=.*\d).{8,}$/.test(next)){showMessage('Password must be at least 8 characters and include both letters and numbers.',false);return;}if(current===next){showMessage('Your new password must be different from your current password.',false);return;}
        data.append('action','changePassword');const submit=form.querySelector('button[type="submit"]');LoadingUI.setButton(submit,true,'Changing...');try{const response=await fetch('../../controllers/accountController.php',{method:'POST',headers:{'X-CSRF-Token':csrfToken},body:data}),result=await response.json();showMessage(result.message||(result.success?'Password changed successfully.':'Unable to change password.'),!!result.success);if(result.success)form.reset();}catch(error){showMessage('Network error. Please try again.',false);}finally{LoadingUI.setButton(submit,false);}
    });
})();
</script>

