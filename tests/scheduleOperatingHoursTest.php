<?php
require_once __DIR__ . '/../apps/models/scheduleModel.php';

function expectHours(bool $condition): void {
    if (!$condition) throw new RuntimeException('Operating hours assertion failed.');
}

expectHours(Schedule::isWithinOperatingHours('08:00', '17:30'));
expectHours(!Schedule::isWithinOperatingHours('07:55', '17:30'));
expectHours(!Schedule::isWithinOperatingHours('08:00', '17:35'));
expectHours(!Schedule::isWithinOperatingHours('17:30', '17:30'));
expectHours(!Schedule::usesFiveMinuteIncrement('08:00:01'));

// A read-only fixture checks the two-clinic transition without database writes.
$connection = new class {
    public function prepare($sql) {
        return new class {
            public function execute($params) {}
            public function fetchAll($mode) {
                return [['schedule_id' => 1, 'clinic_id' => 1,
                    'start_time' => '08:00:00', 'end_time' => '12:00:00']];
            }
        };
    }
};
$model = new Schedule($connection);
expectHours(Schedule::isWithinOperatingHours('13:30', '17:30'));
expectHours($model->findWindowConflict(2, '2026-10-01', '13:30', '17:30') === null);
expectHours($model->findWindowConflict(2, '2026-10-01', '13:25', '17:30') !== null);
expectHours(!$model->addSchedules(2, [['start_time' => '13:30', 'end_time' => '17:35']])['success']);
expectHours(!$model->updateScheduleWindow(1, 2, '2026-10-01', '07:55', '12:00', 8)['success']);
echo "PASS: Operating hour boundaries and two-clinic transition validation.\n";
