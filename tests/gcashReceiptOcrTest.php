<?php

require_once __DIR__ . '/../apps/support/GcashReceiptOcr.php';

function expectReceiptOcr(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

$result = GcashReceiptOcr::parse(<<<'TEXT'
Sent via GCash
Amount 1,250.00
Total Amount Sent P1,250.00
Ref No. 1234 567 890123 Sep 01, 2026 9:07 AM
TEXT);

expectReceiptOcr($result['recognized_receipt'], 'A standard GCash receipt is recognized.');
expectReceiptOcr($result['fields']['amount'] === '1250.00', 'The total amount is normalized.');
expectReceiptOcr($result['fields']['reference_number'] === '1234567890123', 'Spaces are removed from the reference number.');
expectReceiptOcr($result['fields']['transaction_at'] === '2026-09-01T09:07', 'The transaction date and time are normalized.');
expectReceiptOcr($result['missing'] === [], 'A complete receipt has no missing fields.');

$partial = GcashReceiptOcr::parse("Sent via GCash\nTotal Amount Sent P400.00");
expectReceiptOcr($partial['fields']['amount'] === '400.00', 'A detected amount is returned even when other fields are missing.');
expectReceiptOcr($partial['missing'] === ['reference_number', 'transaction_at'], 'Missing fields are reported for manual completion.');
