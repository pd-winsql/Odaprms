<?php

final class IdentityInput
{
    private const NAMES = [
        'firstname' => 'First name', 'middlename' => 'Middle name', 'lastname' => 'Last name',
        'guardian_name' => 'Guardian name', 'physician_name' => 'Physician name',
        'consent_name' => 'Consent name', 'previous_dentist' => 'Previous dentist name',
    ];
    private const CONTACTS = [
        'phone_number' => 'Phone number', 'guardian_contact' => 'Guardian contact',
        'office_contact' => 'Office contact', 'physician_contact' => 'Physician contact',
        'gcash_account_number' => 'GCash number',
    ];

    public static function isName(string $value): bool
    {
        return preg_match('/^\p{L}[\p{L}\p{M}]*(?: +\p{L}[\p{L}\p{M}]*)*$/u', trim($value)) === 1;
    }

    public static function isContact(string $value): bool
    {
        return preg_match('/^[0-9]{11}$/', $value) === 1;
    }

    /** Required fields remain the responsibility of each form's workflow. */
    public static function errors(array $data, bool $draft = false): array
    {
        $errors = [];
        foreach (self::NAMES as $field => $label) {
            if (!array_key_exists($field, $data)) continue;
            $value = $data[$field];
            if (!is_string($value)) {
                $errors[$field] = "$label must contain letters and spaces only.";
            } elseif (trim($value) !== '' && !self::isName($value)) {
                $errors[$field] = "$label must contain letters and spaces only.";
            } elseif (mb_strlen($value) > 100) {
                $errors[$field] = "$label must be 100 characters or fewer.";
            }
        }
        foreach (self::CONTACTS as $field => $label) {
            if (!array_key_exists($field, $data)) continue;
            $value = $data[$field];
            if (is_string($value) && $value === '') continue;
            $valid = is_string($value) && ($draft
                ? preg_match('/^[0-9]{1,11}$/', $value) === 1
                : self::isContact($value));
            if (!$valid) $errors[$field] = $draft
                ? "$label must contain numbers only and cannot exceed 11 digits."
                : "$label must contain exactly 11 digits.";
        }
        if (array_key_exists('contact_phone', $data)) {
            $value = $data['contact_phone'];
            $lines = is_string($value) ? preg_split('/\R/', trim($value)) : [null];
            foreach ($lines as $line) {
                if (is_string($line) && trim($line) === '') continue;
                if (!is_string($line) || !self::isContact(trim($line))) {
                    $errors['contact_phone'] = 'Enter one 11-digit clinic phone number per line.';
                    break;
                }
            }
        }
        return $errors;
    }
}
