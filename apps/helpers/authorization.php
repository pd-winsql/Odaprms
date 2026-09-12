<?php

function vdCurrentRole(): string
{
    return (string) ($_SESSION['user_role'] ?? '');
}

function vdIsAdmin(): bool
{
    return isset($_SESSION['user_id']) && vdCurrentRole() === 'Admin';
}

function vdIsDentalAssistant(): bool
{
    return isset($_SESSION['user_id']) && vdCurrentRole() === 'Dental Assistant';
}

function vdCanPerformBilling(): bool
{
    return vdIsAdmin();
}

function vdRequireRoleJson(array $roles, string $message = 'Forbidden.'): void
{
    if (!isset($_SESSION['user_id']) || !in_array(vdCurrentRole(), $roles, true)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
}

function vdRequireDentalAssistantJson(): void
{
    vdRequireRoleJson(['Dental Assistant']);
}

function vdRequireAdminJson(): void
{
    vdRequireRoleJson(['Admin']);
}
