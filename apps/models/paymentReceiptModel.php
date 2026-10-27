<?php
require_once __DIR__.'/emailNotificationModel.php';
require_once __DIR__.'/../helpers/siteBranding.php';

class PaymentReceiptModel {
    public function __construct(private PDO $conn) {}

    public function queue(int $billingId, string $recordedBy): ?array {
        $stmt=$this->conn->prepare("SELECT b.*, a.status AS appointment_status, c.clinic_name,
            CONCAT_WS(' ',p.firstname,p.lastname) AS patient_name
            FROM appointment_billings b JOIN appointments a ON a.appointment_id=b.appointment_id
            JOIN patients p ON p.patient_id=a.patient_id JOIN clinics c ON c.clinic_id=a.clinic_id
            WHERE b.billing_id=?");
        $stmt->execute([$billingId]); $billing=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$billing || $billing['payment_status']!=='Paid' || $billing['appointment_status']!=='Completed') {
            throw new RuntimeException('Receipt requires completed final settlement.');
        }
        $items=$this->conn->prepare('SELECT service_name_snapshot AS name, quantity, unit_price FROM appointment_billing_items WHERE billing_id=? ORDER BY sort_order, billing_item_id');
        $items->execute([$billingId]);
        $branding=vdLoadSiteBranding($this->conn);
        $logo=vdSiteLogoFilename($branding);
        $receipt=[
            'version'=>1, 'billing_id'=>$billingId,
            'number'=>'PAY-'.str_pad((string)$billingId,8,'0',STR_PAD_LEFT),
            'appointment_id'=>(int)$billing['appointment_id'], 'patient'=>$billing['patient_name'],
            'clinic'=>$billing['clinic_name'], 'settled_at'=>$billing['paid_at'],
            'recorded_by'=>$recordedBy, 'brand'=>vdBrandFullName($branding),
            'logo'=>($logo && strtolower(pathinfo($logo,PATHINFO_EXTENSION))!=='svg') ? base64_encode(file_get_contents(vdSiteLogoPath($logo))) : '',
            'items'=>$items->fetchAll(PDO::FETCH_ASSOC),
            'total'=>(float)$billing['actual_service_amount'], 'deposit'=>(float)$billing['deposit_applied'],
            'payment'=>(float)$billing['remaining_balance'], 'tendered'=>(float)$billing['cash_received'],
            'change'=>max(0,(float)$billing['cash_received']-(float)$billing['remaining_balance']),
        ];
        return (new EmailNotificationModel($this->conn))->enqueueAppointmentTemplate(
            (int)$billing['appointment_id'],'payment_receipt',$receipt['number'],
            'payment-receipt:'.$billingId,[], $receipt);
    }

    public function forPatient(int $billingId, int $userId): ?array {
        $stmt=$this->conn->prepare("SELECT n.payload FROM appointment_email_notifications n
            JOIN appointment_billings b ON b.appointment_id=n.appointment_id
            JOIN appointments a ON a.appointment_id=b.appointment_id JOIN patients p ON p.patient_id=a.patient_id
            WHERE b.billing_id=? AND p.user_id=? AND b.payment_status='Paid' AND a.status='Completed'
            AND n.deduplication_key=? AND n.notification_type='payment_receipt' LIMIT 1");
        $stmt->execute([$billingId,$userId,'payment-receipt:'.$billingId]);
        $payload=$stmt->fetchColumn();
        return $payload ? (json_decode($payload,true,512,JSON_THROW_ON_ERROR)['receipt']??null) : null;
    }
}
