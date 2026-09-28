<?php

require_once __DIR__ . '/../apps/support/PatientProfilePolicy.php';

function expectMinorPolicy(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

$today = new DateTimeImmutable('2026-09-27');

expectMinorPolicy(
    PatientProfilePolicy::ageFromBirthdate('2008-09-28', $today) === 17,
    'Age calculation handles the day before an eighteenth birthday.'
);
expectMinorPolicy(
    PatientProfilePolicy::isMinor('2008-09-28', $today),
    'A seventeen-year-old is treated as a minor.'
);
expectMinorPolicy(
    !PatientProfilePolicy::isMinor('2008-09-27', $today),
    'A patient becomes an adult on the eighteenth birthday.'
);
expectMinorPolicy(
    PatientProfilePolicy::consentLabel('myself') === 'Myself'
        && PatientProfilePolicy::consentLabel('Treatment') === '—',
    'Consent labels accept only supported relationship values.'
);

$minor = [
    'birthdate' => '2012-04-10',
    'guardian_name' => '',
    'guardian_contact' => '',
    'consent_name' => '',
    'consent_for' => 'myself',
];
expectMinorPolicy(
    count(PatientProfilePolicy::minorRequirementErrors($minor)) === 4,
    'A minor cannot be completed without guardian details and representative consent.'
);

$minor['guardian_name'] = 'Maria Example';
$minor['guardian_contact'] = '09123456789';
$minor['consent_name'] = 'Maria Example';
$minor['consent_for'] = 'daughter';
expectMinorPolicy(
    PatientProfilePolicy::minorRequirementErrors($minor) === [],
    'A minor with guardian details and representative consent satisfies the policy.'
);

$adult = $minor;
$adult['birthdate'] = '1990-04-10';
$adult['guardian_name'] = '';
$adult['guardian_contact'] = '';
$adult['consent_for'] = 'myself';
expectMinorPolicy(
    PatientProfilePolicy::minorRequirementErrors($adult) === [],
    'Guardian details remain optional for adults.'
);
