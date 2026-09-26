<?php
require_once __DIR__ . '/../config/conn.php';
require_once __DIR__ . '/../apps/models/billingModel.php';

function pricingExpect(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

$conn = (new Database())->connect();
$appointmentId = null;
$serviceId = null;
$originalService = null;
try {
    $source = $conn->query("SELECT patient_id, schedule_id, clinic_id FROM appointments WHERE patient_id IS NOT NULL LIMIT 1")
        ->fetch(PDO::FETCH_ASSOC);
    $adminId = (int) $conn->query("SELECT id FROM users WHERE user_role='Admin' ORDER BY id LIMIT 1")->fetchColumn();
    $service = $conn->query("SELECT service_id, default_price, billing_unit FROM services WHERE is_active=1 ORDER BY service_id LIMIT 1")
        ->fetch(PDO::FETCH_ASSOC);
    pricingExpect((bool) $source && $adminId > 0 && (bool) $service, 'Pricing workflow fixtures are available.');

    $serviceId = (int) $service['service_id'];
    $originalService = $service;
    $conn->prepare("UPDATE services SET default_price=350.00, billing_unit='tooth' WHERE service_id=:id")
        ->execute([':id' => $serviceId]);
    $insert = $conn->prepare("INSERT INTO appointments (patient_id,schedule_id,clinic_id,date,status,deposit_required,treatment_started_at) VALUES (:patient,:schedule,:clinic,CURDATE(),'In Progress',0,NOW())");
    $insert->execute([':patient' => $source['patient_id'], ':schedule' => $source['schedule_id'], ':clinic' => $source['clinic_id']]);
    $appointmentId = (int) $conn->lastInsertId();
    $conn->prepare("INSERT INTO appointment_services (appointment_id,service_id,quantity,unit_price_snapshot,billing_unit_snapshot) VALUES (:appointment,:service,1,350.00,'tooth')")
        ->execute([':appointment' => $appointmentId, ':service' => $serviceId]);

    $result = (new BillingModel($conn))->settleAndCompleteVisit(
        $appointmentId, 1, 1050, $adminId, 'Automated pricing workflow test.', [$serviceId], '',
        [$serviceId => ['quantity' => '3', 'unit_price' => '350']]
    );
    pricingExpect(($result['success'] ?? false) === true, 'Quantity-aware pricing completes the settlement.');
    $billing = $conn->query("SELECT actual_service_amount FROM appointment_billings WHERE appointment_id={$appointmentId}")->fetch(PDO::FETCH_ASSOC);
    pricingExpect((float) $billing['actual_service_amount'] === 1050.0, 'The server derives the final charge from quantity times rate.');
    $item = $conn->query("SELECT quantity,unit_price,billing_unit,line_total FROM appointment_billing_items bi JOIN appointment_billings b ON b.billing_id=bi.billing_id WHERE b.appointment_id={$appointmentId}")->fetch(PDO::FETCH_ASSOC);
    pricingExpect((float) $item['quantity'] === 3.0 && (float) $item['unit_price'] === 350.0 && $item['billing_unit'] === 'tooth' && (float) $item['line_total'] === 1050.0, 'The receipt snapshots the per-tooth rate, quantity, and line total.');
} finally {
    if ($appointmentId) {
        $conn->prepare("DELETE FROM audit_logs WHERE entity_type='appointment' AND entity_id=:id")->execute([':id' => $appointmentId]);
        $conn->prepare("DELETE FROM appointment_billings WHERE appointment_id=:id")->execute([':id' => $appointmentId]);
        $conn->prepare("DELETE FROM appointment_services WHERE appointment_id=:id")->execute([':id' => $appointmentId]);
        $conn->prepare("DELETE FROM appointments WHERE appointment_id=:id")->execute([':id' => $appointmentId]);
    }
    if ($serviceId && $originalService) {
        $conn->prepare("UPDATE services SET default_price=:price,billing_unit=:unit WHERE service_id=:id")
            ->execute([':price' => $originalService['default_price'], ':unit' => $originalService['billing_unit'], ':id' => $serviceId]);
    }
}

echo "Service pricing workflow test completed.\n";
