<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'Dental Assistant'], true)) {
    echo '<div class="vd-empty-state">Unauthorized.</div>'; exit;
}
require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/patientModel.php';
require_once __DIR__ . '/../../../support/MedicalQuestionnaire.php';
$db = new Database(); $conn = $db->connect();
$patient = (new Patient($conn))->getPatientFull((int) ($_GET['id'] ?? 0));
if (!$patient) { echo '<div class="vd-empty-state">Patient not found.</div>'; exit; }
$appointmentId = (int) ($_GET['appointment_id'] ?? 0);
$appointment = null;
if ($appointmentId) {
    $stmt = $conn->prepare("SELECT a.appointment_id,a.appointment_code,a.clinic_name,a.service_name,ci.arrived_at FROM vw_appointment_overview a LEFT JOIN appointment_checkins ci ON ci.appointment_id=a.appointment_id WHERE a.appointment_id=:appointment_id AND a.patient_id=:patient_id LIMIT 1");
    $stmt->execute([':appointment_id'=>$appointmentId, ':patient_id'=>$patient['patient_id']]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
$conditionGroups = require __DIR__ . '/../../../../config/medicalConditions.php';
$selectedConditions = array_values(array_filter(array_map('trim', explode(',', (string) ($patient['patient_conditions'] ?? '')))));
$profileComplete = !empty($patient['profile_completed_at']);
$storedNoKnownConditions = $patient['no_known_conditions'] ?? null;
$noKnownConditions = $storedNoKnownConditions !== null
    ? (bool) $storedNoKnownConditions
    : ($profileComplete && !$selectedConditions && empty($patient['cond_others']));
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
function checkinVal($value): string { return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES); }
function checkinRadio(string $name, string $value, $current): string {
    $checked = (string)$current === $value ? ' checked' : '';
    return '<label class="form-check form-check-inline mb-0"><input class="form-check-input" type="radio" name="'.$name.'" value="'.$value.'"'.$checked.'> '.ucfirst($value).'</label>';
}
function checkinSource($source, $date): string {
    if (!$date) return 'No previous update recorded';
    $actor = str_starts_with((string)$source, 'staff:') ? 'clinic staff' : ((string)$source === 'patient' ? 'patient' : 'clinic staff');
    return 'Last updated by '.$actor.' · '.date('M j, Y g:i A', strtotime($date));
}
$questionnaireGroups = MedicalQuestionnaire::groups();
$alerts = [];
foreach ([['allergy','Allergy','allergy_detail'],['medication','Medication','medication_detail'],['medical_condition','Medical treatment','medical_condition_detail'],['serious_illness','Serious illness/operation','serious_illness_detail']] as [$flag,$label,$detail]) {
    if (!empty($patient[$flag])) $alerts[] = $label.': '.($patient[$detail] ?: 'details not recorded');
}
if (!empty($patient['pregnant'])) $alerts[] = 'Pregnant';
$alerts = array_values(array_unique(array_merge($alerts, $selectedConditions, array_filter([(string)($patient['cond_others'] ?? '')]))));
?>
<div class="vd-checkin-profile-workspace d-flex flex-column gap-4">
  <div class="vd-checkin-context vd-dash-card">
    <div class="vd-checkin-context-main">
      <div><div class="vd-welcome-greet">CHECK-IN PROFILE REVIEW</div><div class="vd-welcome-name"><?= checkinVal($patient['full_name']) ?></div>
        <div class="vd-checkin-context-meta">
          <span><i class="ti ti-ticket"></i><?= checkinVal($appointment['appointment_code'] ?? 'No appointment code') ?></span>
          <span><i class="ti ti-building-hospital"></i><?= checkinVal($appointment['clinic_name'] ?? 'Clinic not listed') ?></span>
          <span><i class="ti ti-stethoscope"></i><?= checkinVal($appointment['service_name'] ?? 'Service not listed') ?></span>
          <span><i class="ti ti-clock"></i><?= !empty($appointment['arrived_at']) ? date('g:i A', strtotime($appointment['arrived_at'])) : 'Arrival not recorded' ?></span>
        </div>
      </div>
      <button type="button" class="btn vd-btn-outline" data-back-logbook><i class="ti ti-arrow-left me-1"></i>Back to Logbook</button>
    </div>
    <div class="vd-checkin-steps" aria-label="Check-in progress">
      <div class="is-complete"><span>1</span><strong>Arrived</strong></div><div class="is-active"><span>2</span><strong>Verify profile</strong></div><div><span>3</span><strong>Medical review</strong></div><div><span>4</span><strong>Consent</strong></div><div><span>5</span><strong>Ready</strong></div>
    </div>
  </div>

  <div class="row g-4 align-items-start"><div class="col-xl-8">
    <form id="checkinPatientForm" novalidate>
      <input type="hidden" name="action" value="completeProfileByStaff"><input type="hidden" name="csrf_token" value="<?= checkinVal($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="patient_id" value="<?= (int)$patient['patient_id'] ?>"><input type="hidden" name="appointment_id" value="<?= (int)($appointment['appointment_id'] ?? 0) ?>">

      <div class="vd-dash-card mb-4" id="checkin-section-personal" data-checkin-section="personal">
        <div class="vd-dash-card-header"><div><span class="vd-dash-card-title">Personal and Contact Information</span><div class="vd-section-source"><?= $profileComplete ? 'Previously completed · '.date('M j, Y g:i A', strtotime($patient['profile_completed_at'])) : 'Profile has not been completed' ?></div></div><button type="button" class="btn vd-btn-outline btn-sm" data-edit-checkin-section="personal"><i class="ti ti-edit me-1"></i>Edit</button></div>
        <div class="vd-dash-card-body"><fieldset data-checkin-fieldset="personal" disabled><div class="row g-3">
          <div class="col-md-4"><label class="vd-label form-label">First Name *</label><input class="form-control vd-input" name="firstname" value="<?= checkinVal($patient['firstname']) ?>" required></div>
          <div class="col-md-4"><label class="vd-label form-label">Middle Name</label><input class="form-control vd-input" name="middlename" value="<?= checkinVal($patient['middlename']) ?>"></div>
          <div class="col-md-4"><label class="vd-label form-label">Last Name *</label><input class="form-control vd-input" name="lastname" value="<?= checkinVal($patient['lastname']) ?>" required></div>
          <div class="col-md-4"><label class="vd-label form-label">Birthdate *</label><input type="date" class="form-control vd-input" name="birthdate" max="<?= date('Y-m-d') ?>" value="<?= checkinVal($patient['birthdate']) ?>" required></div>
          <div class="col-md-4"><label class="vd-label form-label">Gender *</label><select class="form-select vd-input" name="gender" required><option value="">Select</option><?php foreach (['Male','Female','Prefer not to say'] as $option): ?><option <?= $patient['gender']===$option?'selected':'' ?>><?= $option ?></option><?php endforeach; ?></select></div>
          <div class="col-md-4"><label class="vd-label form-label">Civil Status</label><select class="form-select vd-input" name="civil_status"><option value="">Select</option><?php foreach (['Single','Married','Widowed','Separated'] as $option): ?><option <?= $patient['civil_status']===$option?'selected':'' ?>><?= $option ?></option><?php endforeach; ?></select></div>
          <div class="col-md-6"><label class="vd-label form-label">Phone Number *</label><input type="tel" class="form-control vd-input" name="phone_number" inputmode="numeric" pattern="[0-9]{11}" maxlength="11" value="<?= checkinVal($patient['phone_number']) ?>" required></div>
          <div class="col-md-6"><label class="vd-label form-label">Email</label><input type="email" class="form-control vd-input" name="email" value="<?= checkinVal($patient['email']) ?>"></div>
          <div class="col-12"><label class="vd-label form-label">Home Address</label><input class="form-control vd-input" name="home_address" value="<?= checkinVal($patient['home_address']) ?>"></div>
          <div class="col-md-6"><label class="vd-label form-label">Work Address</label><input class="form-control vd-input" name="work_address" value="<?= checkinVal($patient['work_address']) ?>"></div>
          <div class="col-md-3"><label class="vd-label form-label">Occupation</label><input class="form-control vd-input" name="occupation" value="<?= checkinVal($patient['occupation']) ?>"></div>
          <div class="col-md-3"><label class="vd-label form-label">Office Contact</label><input class="form-control vd-input" name="office_contact" value="<?= checkinVal($patient['office_contact']) ?>"></div>
          <div class="col-md-4"><label class="vd-label form-label">Guardian Name</label><input class="form-control vd-input" name="guardian_name" value="<?= checkinVal($patient['guardian_name']) ?>"></div>
          <div class="col-md-4"><label class="vd-label form-label">Guardian Contact</label><input class="form-control vd-input" name="guardian_contact" value="<?= checkinVal($patient['guardian_contact']) ?>"></div>
          <div class="col-md-4"><label class="vd-label form-label">Facebook Account</label><input class="form-control vd-input" name="fb_account" value="<?= checkinVal($patient['fb_account']) ?>"></div>
          <div class="col-md-4"><label class="vd-label form-label">Physician Name</label><input class="form-control vd-input" name="physician_name" value="<?= checkinVal($patient['physician_name']) ?>"></div>
          <div class="col-md-4"><label class="vd-label form-label">Physician Contact</label><input class="form-control vd-input" name="physician_contact" value="<?= checkinVal($patient['physician_contact']) ?>"></div>
          <div class="col-md-4"><label class="vd-label form-label">Physician Address</label><input class="form-control vd-input" name="physician_address" value="<?= checkinVal($patient['physician_address']) ?>"></div>
        </div></fieldset>
        <label class="vd-contact-confirm mt-3"><input class="form-check-input" type="checkbox" name="contact_confirmed" value="1"><span><strong>Contact information confirmed</strong><small>I asked the patient to confirm the phone number and email shown above.</small></span></label></div>
      </div>

      <div class="vd-dash-card mb-4" id="checkin-section-dental" data-checkin-section="dental">
        <div class="vd-dash-card-header"><div><span class="vd-dash-card-title">Dental History</span><div class="vd-section-source"><?= checkinVal(checkinSource($patient['dental_last_updated_by'] ?? null,$patient['dental_last_updated_at'] ?? null)) ?></div></div><button type="button" class="btn vd-btn-outline btn-sm" data-edit-checkin-section="dental"><i class="ti ti-edit me-1"></i>Edit</button></div>
        <div class="vd-dash-card-body"><fieldset data-checkin-fieldset="dental" disabled><div class="row g-3">
          <div class="col-md-4"><label class="vd-label form-label">Previous Dentist</label><input class="form-control vd-input" name="previous_dentist" value="<?= checkinVal($patient['previous_dentist']) ?>"></div>
          <div class="col-md-4"><label class="vd-label form-label">Last Dental Visit</label><input type="date" class="form-control vd-input" name="last_dental_visit" max="<?= date('Y-m-d') ?>" value="<?= checkinVal($patient['last_dental_visit']) ?>"></div>
          <div class="col-md-4"><label class="vd-label form-label">Referred By</label><input class="form-control vd-input" name="referred_by" value="<?= checkinVal($patient['referred_by']) ?>"></div>
          <div class="col-md-6"><label class="vd-label form-label">Treatment Done</label><textarea class="form-control vd-input" name="treatment_done" rows="2"><?= checkinVal($patient['treatment_done']) ?></textarea></div>
          <div class="col-md-6"><label class="vd-label form-label">Reason for Visit *</label><textarea class="form-control vd-input" name="reason_for_visit" rows="2" required><?= checkinVal($patient['reason_for_visit']) ?></textarea></div>
        </div></fieldset></div>
      </div>

      <div class="vd-dash-card mb-4" id="checkin-section-medical" data-checkin-section="medical">
        <div class="vd-dash-card-header"><div><span class="vd-dash-card-title">Medical Review</span><div class="vd-section-source"><?= checkinVal(checkinSource($patient['medical_last_updated_by'] ?? null,$patient['medical_last_updated_at'] ?? null)) ?></div></div><button type="button" class="btn vd-btn-outline btn-sm" data-edit-checkin-section="medical"><i class="ti ti-edit me-1"></i>Edit</button></div>
        <div class="vd-dash-card-body"><fieldset data-checkin-fieldset="medical" disabled>
          <p class="vd-medical-help">Answer every visible question. When an answer needs more information, its follow-up appears directly below it.</p>
          <?php foreach ($questionnaireGroups as $groupKey => $group): ?>
            <div data-medical-group="<?= checkinVal($groupKey) ?>">
              <?php if ($groupKey !== 'general'): ?><div class="vd-medical-subsection mt-3"><?= checkinVal($group['label']) ?></div><?php endif; ?>
              <?php foreach ($group['questions'] as $field => $question):
                $currentAnswer = $patient[$field] === null ? null : ($patient[$field] ? 'yes' : 'no');
                $detailField = $question['detail_field'] ?? null;
              ?>
                <div class="row align-items-center py-2 border-bottom vd-medical-question" id="medical-field-<?= checkinVal($field) ?>" data-medical-question="<?= checkinVal($field) ?>">
                  <div class="col-md-7"><span><?= checkinVal($question['label']) ?></span> <span class="vd-required-mark" aria-label="required">*</span></div>
                  <div class="col-md-5"><?= checkinRadio($field, 'yes', $currentAnswer) ?><?= checkinRadio($field, 'no', $currentAnswer) ?></div>
                  <?php if ($detailField): ?>
                    <div class="col-12 vd-medical-follow-up mt-2" id="medical-field-<?= checkinVal($detailField) ?>" data-follow-up-for="<?= checkinVal($field) ?>">
                      <label class="vd-label form-label" for="<?= checkinVal($detailField) ?>"><?= checkinVal($question['detail_label']) ?> <span class="vd-required-note">Required because Yes was selected</span></label>
                      <input class="form-control vd-input" id="<?= checkinVal($detailField) ?>" name="<?= checkinVal($detailField) ?>" value="<?= checkinVal($patient[$detailField]) ?>">
                    </div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
          <div class="row g-3 mt-2"><div class="col-md-6"><label class="vd-label form-label">Blood Type</label><input class="form-control vd-input" name="blood_type" value="<?= checkinVal($patient['blood_type']) ?>"></div><div class="col-md-6"><label class="vd-label form-label">Blood Pressure</label><input class="form-control vd-input" name="blood_pressure" value="<?= checkinVal($patient['blood_pressure']) ?>"></div></div>
          <div class="vd-medical-subsection mt-4">Medical conditions *</div>
          <label class="vd-no-condition-option mb-3"><input type="checkbox" class="form-check-input" id="noKnownConditions" name="no_known_conditions" value="1" <?= $noKnownConditions?'checked':'' ?>><span>No known medical conditions</span></label>
          <div class="vd-condition-groups"><?php foreach ($conditionGroups as $group=>$conditions): ?><div class="vd-condition-group"><div class="vd-condition-group-title"><?= htmlspecialchars($group) ?></div><div class="row row-cols-1 row-cols-sm-2 g-1"><?php foreach ($conditions as $condition): ?><div class="col"><label class="vd-check-item d-flex align-items-center gap-2 py-2 px-1"><input type="checkbox" name="conditions[]" class="form-check-input m-0" value="<?= htmlspecialchars($condition) ?>" <?= in_array($condition,$selectedConditions,true)?'checked':'' ?>><span class="small"><?= htmlspecialchars($condition) ?></span></label></div><?php endforeach; ?></div></div><?php endforeach; ?></div>
          <div class="mt-3"><label class="vd-label form-label">Other Conditions</label><input class="form-control vd-input" name="cond_others" value="<?= checkinVal($patient['cond_others'] ?? '') ?>" placeholder="Specify a condition not listed"></div>
        </fieldset></div>
      </div>

      <div class="vd-dash-card mb-4" id="checkin-section-consent" data-checkin-section="consent">
        <div class="vd-dash-card-header"><div><span class="vd-dash-card-title">Consent</span><div class="vd-section-source"><?= !empty($patient['consent_date'])?'Recorded '.date('M j, Y',strtotime($patient['consent_date'])):'No consent recorded' ?></div></div><button type="button" class="btn vd-btn-outline btn-sm" data-edit-checkin-section="consent"><i class="ti ti-edit me-1"></i>Edit</button></div>
        <div class="vd-dash-card-body"><fieldset data-checkin-fieldset="consent" disabled><div class="row g-3"><div class="col-md-6"><label class="vd-label form-label">Consent Name *</label><input class="form-control vd-input" name="consent_name" value="<?= checkinVal($patient['consent_name']) ?>" required></div><div class="col-md-6"><label class="vd-label form-label">Consent For *</label><select class="form-select vd-input" name="consent_for" required><option value="">Select</option><?php foreach (['myself'=>'Myself','spouse'=>'Spouse','son'=>'Son','daughter'=>'Daughter','others'=>'Others'] as $value=>$label): ?><option value="<?= $value ?>" <?= $patient['consent_for']===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></div></div></fieldset></div>
      </div>
      <div id="checkinPatientFormError" class="alert alert-danger d-none" role="alert"></div>
      <div class="vd-checkin-form-actions"><button type="button" class="btn vd-btn-outline" data-back-logbook>Back to Logbook</button><button type="button" class="btn vd-btn-outline" id="saveCheckinDraft">Save Draft</button><button type="submit" class="btn vd-btn-gold" id="confirmCheckinProfile"><i class="ti ti-check me-1"></i>Confirm Profile and Mark Ready</button></div>
    </form>
  </div><div class="col-xl-4"><div class="vd-checkin-side-panel">
    <div class="vd-dash-card mb-4"><div class="vd-dash-card-header"><span class="vd-dash-card-title">Readiness Checklist</span></div><div class="vd-dash-card-body vd-readiness-list"><div data-readiness="personal"><i class="ti ti-circle"></i><span>Required profile details</span></div><div data-readiness="contact"><i class="ti ti-circle"></i><span>Contact information confirmed</span></div><div data-readiness="dental"><i class="ti ti-circle"></i><span>Reason for visit recorded</span></div><div data-readiness="medical"><i class="ti ti-circle"></i><span>Medical questionnaire reviewed</span></div><div data-readiness="conditions"><i class="ti ti-circle"></i><span>Medical conditions reviewed</span></div><div data-readiness="consent"><i class="ti ti-circle"></i><span>Consent recorded</span></div></div><div class="vd-medical-missing-wrap"><div class="vd-medical-subsection">Required medical answers</div><div id="medicalMissingSummary"></div></div></div>
    <div class="vd-dash-card"><div class="vd-dash-card-header"><span class="vd-dash-card-title">Clinical Alerts</span></div><div class="vd-dash-card-body" id="clinicalAlertSummary"><?php if ($alerts): ?><div class="vd-clinical-alert-list"><?php foreach ($alerts as $alert): ?><span><i class="ti ti-alert-triangle"></i><?= checkinVal($alert) ?></span><?php endforeach; ?></div><?php else: ?><div class="vd-profile-na">No clinical alerts recorded.</div><?php endif; ?></div></div>
  </div></div></div>
</div>
<script>
(function(){
 const form=document.getElementById('checkinPatientForm'), errorBox=document.getElementById('checkinPatientFormError'), patientId=form.elements.patient_id.value;
 const questionnaire=<?= json_encode($questionnaireGroups, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
 const back=()=>document.querySelector('[data-page="dashboard-content.php"]')?.click(); document.querySelectorAll('[data-back-logbook]').forEach(b=>b.addEventListener('click',back));
 document.querySelectorAll('[data-edit-checkin-section]').forEach(button=>button.addEventListener('click',()=>{const name=button.dataset.editCheckinSection,fs=form.querySelector(`[data-checkin-fieldset="${name}"]`);fs.disabled=!fs.disabled;button.innerHTML=fs.disabled?'<i class="ti ti-edit me-1"></i>Edit':'<i class="ti ti-eye me-1"></i>Review';document.querySelector(`[data-checkin-section="${name}"]`)?.classList.toggle('is-editing',!fs.disabled);if(!fs.disabled)fs.querySelector('input,select,textarea')?.focus();}));
 const named=name=>form.elements.namedItem(name), value=name=>String(named(name)?.value||'').trim(), answered=name=>Boolean(form.querySelector(`[name="${name}"]:checked`));
 const conditionInputs=()=>[...form.querySelectorAll('[name="conditions[]"]:checked')], noKnown=document.getElementById('noKnownConditions');
 const phoneInput=named('phone_number'); phoneInput.addEventListener('input',()=>{phoneInput.value=phoneInput.value.replace(/\D/g,'').slice(0,11);refresh();});
 const groupApplies=group=>!group.applies_when||value(group.applies_when.field)===group.applies_when.value;
 function syncMedicalFlow(clearInactive=false){Object.entries(questionnaire).forEach(([groupKey,group])=>{const applies=groupApplies(group),groupElement=form.querySelector(`[data-medical-group="${groupKey}"]`);if(groupElement)groupElement.hidden=!applies;Object.entries(group.questions).forEach(([field,question])=>{form.querySelectorAll(`[name="${field}"]`).forEach(input=>{input.disabled=!applies;input.required=applies;});const yes=form.querySelector(`[name="${field}"][value="yes"]:checked`),followUp=question.detail_field?form.querySelector(`[data-follow-up-for="${field}"]`):null,detail=question.detail_field?named(question.detail_field):null,triggered=applies&&Boolean(yes);if(followUp)followUp.hidden=!triggered;if(detail){detail.disabled=!triggered;detail.required=triggered;if(clearInactive&&!triggered)detail.value='';}if(clearInactive&&!applies)form.querySelectorAll(`[name="${field}"]`).forEach(input=>input.checked=false);});});}
 function medicalItems(){const missing=[];Object.values(questionnaire).forEach(group=>{if(!groupApplies(group))return;Object.entries(group.questions).forEach(([field,question])=>{if(!answered(field)){missing.push({field,message:`Answer “${question.label}”`});return;}if(question.detail_field&&form.querySelector(`[name="${field}"][value="yes"]:checked`)&&!value(question.detail_field))missing.push({field:question.detail_field,message:`Add ${question.detail_label.toLowerCase()} because “${question.label}” is Yes`});});});return missing;}
 function state(){return {personal:['firstname','lastname','birthdate','gender','phone_number'].every(value),contact:named('contact_confirmed')?.checked===true,dental:Boolean(value('reason_for_visit')),medical:medicalItems().length===0,conditions:noKnown.checked||conditionInputs().length>0||Boolean(value('cond_others')),consent:Boolean(value('consent_name'))&&Boolean(value('consent_for'))};}
 function openField(section,field){const fs=form.querySelector(`[data-checkin-fieldset="${section}"]`);if(fs)fs.disabled=false;document.querySelector(`[data-checkin-section="${section}"]`)?.classList.add('is-editing');const target=field?document.getElementById(`medical-field-${field}`):document.getElementById(`checkin-section-${section}`);target?.classList.add('is-missing');target?.scrollIntoView({behavior:'smooth',block:'center'});setTimeout(()=>{const control=field?(named(field)?.length?form.querySelector(`[name="${field}"]`):named(field)):fs?.querySelector('input,select,textarea');control?.focus();},350);}
 function renderMedicalSummary(items){const box=document.getElementById('medicalMissingSummary');if(!items.length){box.innerHTML='<div class="vd-medical-complete"><i class="ti ti-circle-check"></i> All required medical answers are complete.</div>';return;}box.innerHTML=`<ul class="vd-medical-missing-list">${items.map(item=>`<li><button type="button" data-medical-target="${escapeHtml(item.field)}"><i class="ti ti-alert-circle" aria-hidden="true"></i><span>${escapeHtml(item.message)}</span></button></li>`).join('')}</ul>`;box.querySelectorAll('[data-medical-target]').forEach(button=>button.addEventListener('click',()=>openField('medical',button.dataset.medicalTarget)));}
 function refresh(){syncMedicalFlow();const medicalMissing=medicalItems(),s=state();Object.entries(s).forEach(([key,done])=>{const row=document.querySelector(`[data-readiness="${key}"]`),icon=row?.querySelector('i');row?.classList.toggle('is-complete',done);if(icon)icon.className=done?'ti ti-circle-check':'ti ti-circle';});renderMedicalSummary(medicalMissing);form.querySelectorAll('.is-missing').forEach(element=>{const field=element.dataset.medicalQuestion||element.id.replace('medical-field-','');if(field&&!medicalMissing.some(item=>item.field===field))element.classList.remove('is-missing');});return s;}
 noKnown.addEventListener('change',()=>{if(noKnown.checked){conditionInputs().forEach(i=>i.checked=false);named('cond_others').value='';}refresh();alerts();});
 form.querySelectorAll('[name="conditions[]"]').forEach(i=>i.addEventListener('change',()=>{if(i.checked)noKnown.checked=false;refresh();alerts();})); named('cond_others').addEventListener('input',()=>{if(value('cond_others'))noKnown.checked=false;refresh();alerts();});
 function escapeHtml(v){const d=document.createElement('div');d.textContent=v;return d.innerHTML;}
 function alerts(){const list=[],map={allergy:['Allergy','allergy_detail'],medication:['Medication','medication_detail'],medical_condition:['Medical treatment','medical_condition_detail'],serious_illness:['Serious illness/operation','serious_illness_detail'],pregnant:['Pregnant',null]};Object.entries(map).forEach(([name,c])=>{if(form.querySelector(`[name="${name}"][value="yes"]:checked`)){const detail=c[1]?value(c[1]):'';list.push(detail?`${c[0]}: ${detail}`:c[0]);}});conditionInputs().forEach(i=>list.push(i.value));if(value('cond_others'))list.push(value('cond_others'));document.getElementById('clinicalAlertSummary').innerHTML=list.length?`<div class="vd-clinical-alert-list">${[...new Set(list)].map(v=>`<span><i class="ti ti-alert-triangle"></i>${escapeHtml(v)}</span>`).join('')}</div>`:'<div class="vd-profile-na">No clinical alerts recorded.</div>';}
 function issues(s){const rows=[];if(!s.personal)rows.push({message:'Complete required profile details.',section:'personal'});if(!s.contact)rows.push({message:'Confirm the phone number and email.',section:'personal'});if(!s.dental)rows.push({message:'Record the reason for visit.',section:'dental'});medicalItems().forEach(item=>rows.push({message:item.message+'.',section:'medical',field:item.field}));if(!s.conditions)rows.push({message:'Select conditions or confirm no known conditions.',section:'medical'});if(!s.consent)rows.push({message:'Complete consent information.',section:'consent'});if(!rows.length)return false;errorBox.innerHTML=`<strong>Complete these items before marking the patient ready:</strong><ul class="mb-0 mt-2">${rows.map((row,index)=>`<li><button type="button" class="btn btn-link p-0 text-start" data-issue-index="${index}">${escapeHtml(row.message)}</button></li>`).join('')}</ul>`;errorBox.classList.remove('d-none');errorBox.querySelectorAll('[data-issue-index]').forEach(button=>button.addEventListener('click',()=>{const row=rows[Number(button.dataset.issueIndex)];openField(row.section,row.field||null);}));errorBox.scrollIntoView({behavior:'smooth',block:'center'});return true;}
 const enableAll=()=>form.querySelectorAll('[data-checkin-fieldset]').forEach(fs=>fs.disabled=false);
 async function save(body,button,label){LoadingUI.setButton(button,true,label);try{const response=await fetch('../../controllers/patientController.php',{method:'POST',body}),result=await response.json();if(!result.success)throw new Error(result.message);sessionStorage.setItem('highlightLogbookPatient',patientId);window.showToast(result.message,true);back();}catch(error){errorBox.textContent=error.message||'Unable to save the profile.';errorBox.classList.remove('d-none');LoadingUI.setButton(button,false);errorBox.scrollIntoView({behavior:'smooth',block:'center'});}}
 document.getElementById('saveCheckinDraft').addEventListener('click',function(){syncMedicalFlow(true);enableAll();const body=new FormData(form);body.set('save_mode','draft');save(body,this,'Saving…');});
 form.addEventListener('submit',event=>{event.preventDefault();errorBox.classList.add('d-none');if(issues(refresh()))return;enableAll();if(!form.checkValidity()){form.reportValidity();return;}save(new FormData(form),document.getElementById('confirmCheckinProfile'),'Confirming…');});
 form.addEventListener('input',refresh);form.addEventListener('change',event=>{if(event.target.matches('[type="radio"],select[name="gender"]'))syncMedicalFlow(true);refresh();});form.querySelectorAll('[name="allergy"],[name="medication"],[name="medical_condition"],[name="serious_illness"],[name="pregnant"],[name$="_detail"]').forEach(i=>i.addEventListener('change',alerts));syncMedicalFlow();refresh();
})();
</script>
