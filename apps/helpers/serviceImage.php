<?php

function vdServiceImageUrl(?string $path, string $prefix = ''): string
{
    $normalized = ltrim(str_replace('\\', '/', trim((string) $path)), '/');
    if ($normalized === '' || !str_starts_with($normalized, 'public/uploads/services/')) {
        return '';
    }

    return ($prefix !== '' ? rtrim($prefix, '/') . '/' : '') . $normalized;
}
