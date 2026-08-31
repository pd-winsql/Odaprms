<?php

return [
    // Maximum number of distinct services on a new or edited visit.
    'max_services_per_visit' => 5,

    // Travel/preparation time required when the clinic changes branches on
    // the same date. Schedule windows themselves must also never overlap.
    'clinic_transition_minutes' => 90,
];
