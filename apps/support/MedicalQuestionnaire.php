<?php

final class MedicalQuestionnaire
{
    public static function groups(): array
    {
        return require __DIR__ . '/../../config/medicalQuestionnaire.php';
    }

    public static function groupApplies(array $group, array $context): bool
    {
        $condition = $group['applies_when'] ?? null;
        if (!$condition) return true;

        return ($context[$condition['field']] ?? null) === $condition['value'];
    }

    public static function missingItems(array $answers, array $details, array $context): array
    {
        $missing = [];
        foreach (self::groups() as $group) {
            if (!self::groupApplies($group, $context)) continue;

            foreach ($group['questions'] as $field => $question) {
                if (!array_key_exists($field, $answers) || $answers[$field] === null) {
                    $missing[] = [
                        'field' => $field,
                        'message' => 'Answer “' . $question['label'] . '”',
                    ];
                    continue;
                }

                $detailField = $question['detail_field'] ?? null;
                if ($answers[$field] === 1 && $detailField && trim((string) ($details[$detailField] ?? '')) === '') {
                    $missing[] = [
                        'field' => $detailField,
                        'message' => 'Add ' . strtolower($question['detail_label']) . ' because “' . $question['label'] . '” is Yes',
                    ];
                }
            }
        }

        return $missing;
    }
}
