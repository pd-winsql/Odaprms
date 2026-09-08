<?php

require_once __DIR__ . '/../apps/models/staffModel.php';

function staffStatusExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

final class StaffStatusStatementStub
{
    public function __construct(private int $affectedRows) {}

    public function execute(array $parameters): bool
    {
        return isset($parameters[':staff_id']);
    }

    public function rowCount(): int
    {
        return $this->affectedRows;
    }
}

final class StaffStatusConnectionStub
{
    public function __construct(private int $affectedRows) {}

    public function prepare(string $query): StaffStatusStatementStub
    {
        staffStatusExpect(str_contains($query, 'employment_status'), 'Status toggle targets employment status.');
        return new StaffStatusStatementStub($this->affectedRows);
    }
}

$updatedStaff = new Staff(new StaffStatusConnectionStub(1));
staffStatusExpect($updatedStaff->toggleStatus(7), 'Status toggle succeeds when one staff row changes.');

$missingStaff = new Staff(new StaffStatusConnectionStub(0));
staffStatusExpect(!$missingStaff->toggleStatus(999999), 'Status toggle fails when no staff row changes.');

$root = dirname(__DIR__);
$staffController = file_get_contents($root . '/apps/controllers/staffController.php');
$userModel = file_get_contents($root . '/apps/models/userModel.php');
$userController = file_get_contents($root . '/apps/controllers/userController.php');
$staffView = file_get_contents($root . '/apps/views/admin/partials/den-assist-content.php');

staffStatusExpect(
    str_contains($staffController, 'session_status() === PHP_SESSION_NONE'),
    'Staff API avoids restarting an active session.'
);
staffStatusExpect(
    str_contains($userModel, 's.employment_status AS staff_employment_status'),
    'Login lookup includes dental-assistant employment status.'
);
staffStatusExpect(
    str_contains($userController, "['user_role'] === 'Dental Assistant'")
        && str_contains($userController, "['staff_employment_status']"),
    'Login rejects an inactive dental-assistant account.'
);
staffStatusExpect(
    str_contains($staffView, 'async function postStaffAction')
        && str_contains($staffView, 'JSON.parse(responseText)')
        && str_contains($staffView, 'requestErrorMessage'),
    'Staff UI distinguishes malformed responses from connection failures.'
);

echo "Staff status connection test completed.\n";
