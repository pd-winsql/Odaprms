<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/conn.php';
require_once __DIR__ . '/../apps/models/patientModel.php';
require_once __DIR__ . '/../apps/models/emailNotificationModel.php';

function emailExpect(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}
$db = (new Database())->connect();
if (!$db) exit(1);
emailExpect((int) $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='patients' AND COLUMN_NAME='email'")->fetchColumn() === 0, 'Duplicate patient email column is absent.');
$db->beginTransaction();
try {
    $email = 'account-email-' . bin2hex(random_bytes(6)) . '@example.invalid';
    $stmt = $db->prepare("INSERT INTO users(email,password,email_verified_at,user_role) VALUES(?,?,NOW(),'Patient')");
    $stmt->execute([$email, password_hash('TestPassword123', PASSWORD_DEFAULT)]);
    $userId = (int) $db->lastInsertId();
    $patients = new Patient($db);
    $id = $patients->createRegisteredPatient($userId, [
        'firstname'=>'Email', 'lastname'=>'Fixture', 'birthdate'=>'1996-01-01',
        'gender'=>'Prefer not to say', 'phone_number'=>'09123456789',
    ], 'ignored-legacy@example.invalid');
    emailExpect($id > 0, 'Registration creates a patient without the removed column.');
    emailExpect($patients->getPatient($id)['email'] === $email, 'Patient lookup uses account email.');
    emailExpect($patients->getPatientByUserId($userId)['email'] === $email, 'Portal lookup uses account email.');
    emailExpect((int) $patients->getPatientByEmail($email)['patient_id'] === $id, 'Email lookup finds the linked patient.');
    emailExpect($patients->getPatientFull($id)['email'] === $email, 'Full patient view uses account email.');
    emailExpect($patients->searchPatients('Email')[0]['email'] === $email, 'Search exposes account email.');
    emailExpect($patients->filterPatients(null,null,null,'Email')[0]['email'] === $email, 'Filtered list exposes account email.');
    emailExpect($patients->updatePatient($id, ['email'=>'tampered@example.invalid']), 'Legacy profile email writes are safely ignored.');
    emailExpect($patients->getPatient($id)['email'] === $email, 'Profile updates cannot alter login email.');

    $clinic = (int) $db->query('SELECT clinic_id FROM clinics ORDER BY clinic_id LIMIT 1')->fetchColumn();
    $db->prepare('INSERT INTO schedules(clinic_id,sched_date,max_appointments) VALUES(?, ?, 5)')->execute([$clinic,'2040-01-03']);
    $schedule = (int) $db->lastInsertId();
    $db->prepare("INSERT INTO appointments(patient_id,schedule_id,clinic_id,date,status) VALUES(?,?,?,?,'Pending Review')")->execute([$id,$schedule,$clinic,'2040-01-03']);
    $appointment = (int) $db->lastInsertId();
    $view = $db->prepare('SELECT email FROM vw_appointment_overview WHERE appointment_id=?');
    $view->execute([$appointment]);
    emailExpect($view->fetchColumn() === $email, 'Appointment overview uses account email.');
    $notifications = new EmailNotificationModel($db);
    $queued = $notifications->enqueueAppointmentTemplate($appointment,'appointment_cancelled','Cancelled','email-source-test-' . $appointment);
    emailExpect($queued !== null, 'Automated notification can be queued using account email.');
    $recipient = $db->prepare('SELECT recipient_email FROM appointment_email_notifications WHERE appointment_id=? ORDER BY notification_id DESC LIMIT 1');
    $recipient->execute([$appointment]);
    emailExpect($recipient->fetchColumn() === $email, 'Queued recipient matches the account email.');
    $newEmail = 'updated-' . $email;
    $db->prepare('UPDATE users SET email=? WHERE id=?')->execute([$newEmail,$userId]);
    emailExpect($patients->getPatientFull($id)['email'] === $newEmail, 'Account email updates immediately reflect in the profile view.');
    $notifications->enqueueAppointmentTemplate($appointment,'appointment_cancelled','Cancelled','email-source-test-updated-' . $appointment);
    $recipient->execute([$appointment]);
    emailExpect($recipient->fetchColumn() === $newEmail, 'New notifications use the updated account email.');
    $recipient = $db->prepare('SELECT recipient_email FROM appointment_email_notifications WHERE appointment_id=? ORDER BY notification_id ASC LIMIT 1');
    $recipient->execute([$appointment]);
    emailExpect($recipient->fetchColumn() === $email, 'Historical queue recipients remain unchanged.');
} finally {
    if ($db->inTransaction()) $db->rollBack();
}
