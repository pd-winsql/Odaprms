<?php

return [
    'general' => [
        'label' => 'General health',
        'questions' => [
            'good_health' => ['label' => 'In good health?'],
            'medical_condition' => [
                'label' => 'Under medical treatment?',
                'detail_field' => 'medical_condition_detail',
                'detail_label' => 'Medical treatment details',
            ],
            'serious_illness' => [
                'label' => 'Serious illness or operation?',
                'detail_field' => 'serious_illness_detail',
                'detail_label' => 'Illness or operation details',
            ],
            'hospitalized' => [
                'label' => 'Previously hospitalized?',
                'detail_field' => 'hospitalized_detail',
                'detail_label' => 'Hospitalization details',
            ],
            'medication' => [
                'label' => 'Taking medication?',
                'detail_field' => 'medication_detail',
                'detail_label' => 'Medication details',
            ],
            'smoke' => ['label' => 'Smokes?'],
            'alcohol' => ['label' => 'Uses alcohol?'],
            'drugs' => ['label' => 'Uses prohibited drugs?'],
            'allergy' => [
                'label' => 'Has allergies?',
                'detail_field' => 'allergy_detail',
                'detail_label' => 'Allergy details',
            ],
        ],
    ],
    'women' => [
        'label' => 'For women only',
        'applies_when' => ['field' => 'gender', 'value' => 'Female'],
        'questions' => [
            'pregnant' => ['label' => 'Pregnant?'],
            'nursing' => ['label' => 'Nursing?'],
            'birth_control' => ['label' => 'Taking birth-control pills?'],
        ],
    ],
];
