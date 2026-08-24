<?php

function vdFormatPesoAmount(float $amount): string
{
    return '₱' . number_format($amount, 2);
}

function vdFormatDurationMinutes(int $minutes): string
{
    $minutes = max(1, $minutes);
    $days = intdiv($minutes, 1440);
    $hours = intdiv($minutes % 1440, 60);
    $remainingMinutes = $minutes % 60;
    $parts = [];

    if ($days > 0) {
        $parts[] = $days . ' ' . ($days === 1 ? 'day' : 'days');
    }
    if ($hours > 0) {
        $parts[] = $hours . ' ' . ($hours === 1 ? 'hour' : 'hours');
    }
    if ($remainingMinutes > 0) {
        $parts[] = $remainingMinutes . ' ' . ($remainingMinutes === 1 ? 'minute' : 'minutes');
    }

    return implode(' ', $parts);
}

