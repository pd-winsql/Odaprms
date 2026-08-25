<?php

require_once __DIR__ . '/../apps/support/MedicalQuestionnaire.php';

function expectMedical(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

$groups = MedicalQuestionnaire::groups();
$generalFields = array_keys($groups['general']['questions']);
$answers = array_fill_keys($generalFields, 0);

expectMedical(
    count(MedicalQuestionnaire::missingItems([], [], ['gender' => 'Male'])) === count($generalFields),
    'Every general medical question is required.'
);

$answers['allergy'] = 1;
$missing = MedicalQuestionnaire::missingItems($answers, [], ['gender' => 'Male']);
expectMedical(
    count($missing) === 1 && $missing[0]['field'] === 'allergy_detail',
    'A Yes answer requires its configured follow-up detail.'
);

expectMedical(
    MedicalQuestionnaire::missingItems($answers, ['allergy_detail' => 'Penicillin'], ['gender' => 'Male']) === [],
    'A completed Yes follow-up satisfies the medical rules.'
);

$answers['allergy'] = 0;
$femaleMissing = MedicalQuestionnaire::missingItems($answers, [], ['gender' => 'Female']);
expectMedical(
    array_column($femaleMissing, 'field') === ['pregnant', 'nursing', 'birth_control'],
    'Women-only questions are required when gender is Female.'
);

expectMedical(
    MedicalQuestionnaire::missingItems($answers, [], ['gender' => 'Prefer not to say']) === [],
    'Women-only questions are not required outside their configured applicability.'
);
