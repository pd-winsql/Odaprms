<?php

require_once __DIR__ . '/../../config/conn.php';
require_once __DIR__ . '/../models/siteSettingsModel.php';

/**
 * Load the small subset of site settings used by visual brand components.
 * The defaults keep public/auth pages usable if the settings table is unavailable.
 */
function vdLoadSiteBranding(?PDO $conn = null): array
{
    $defaults = [
        'brand_name_top' => 'Dr. Aprille',
        'brand_name_sub' => 'Clinica Dental',
        'site_logo'      => '',
    ];

    try {
        $conn ??= (new Database())->connect();
        if (!$conn) {
            return $defaults;
        }

        $settings = (new SiteSettingsModel($conn))->getSettings();
        return array_merge($defaults, array_intersect_key($settings, $defaults));
    } catch (Throwable $e) {
        error_log('Site branding load error: ' . $e->getMessage());
        return $defaults;
    }
}

/**
 * Return a safe configured logo filename only when the corresponding asset exists.
 */
function vdSiteLogoFilename(array $branding): string
{
    $filename = trim((string) ($branding['site_logo'] ?? ''));
    if ($filename === '' || basename($filename) !== $filename) {
        return '';
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
    if (!in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), $allowedExtensions, true)) {
        return '';
    }

    return is_file(vdSiteLogoPath($filename)) ? $filename : '';
}

function vdSiteLogoPath(string $filename): string
{
    return __DIR__ . '/../../public/assets/' . $filename;
}

function vdBrandFullName(array $branding): string
{
    $top = trim((string) ($branding['brand_name_top'] ?? 'Dr. Aprille')) ?: 'Dr. Aprille';
    $sub = trim((string) ($branding['brand_name_sub'] ?? 'Clinica Dental')) ?: 'Clinica Dental';
    return trim($top . ' Ventura ' . $sub);
}

/**
 * Render the shared browser wordmark/logo. $assetBase must point to public/assets.
 */
function vdRenderSiteBranding(array $branding, string $assetBase, string $variant = 'default'): string
{
    $filename = vdSiteLogoFilename($branding);
    $top = htmlspecialchars((string) ($branding['brand_name_top'] ?? 'Dr. Aprille'), ENT_QUOTES, 'UTF-8');
    $sub = htmlspecialchars((string) ($branding['brand_name_sub'] ?? 'Clinica Dental'), ENT_QUOTES, 'UTF-8');
    $fullName = htmlspecialchars(vdBrandFullName($branding), ENT_QUOTES, 'UTF-8');

    if ($filename !== '') {
        $src = rtrim($assetBase, '/') . '/' . rawurlencode($filename);
        $class = match ($variant) {
            'navbar'  => 'vd-navbar-logo-img',
            'sidebar' => 'vd-sidebar-logo-img',
            'auth'    => 'vd-auth-logo-img',
            'form'    => 'vd-form-logo-img',
            'report'  => 'vd-report-logo-img',
            default   => 'vd-site-logo-img',
        };

        return '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="' . $fullName . '" class="' . $class . '">';
    }

    if ($variant === 'report') {
        return '<div class="vd-report-brand">' . $fullName . '</div>';
    }

    $venturaClass = $variant === 'auth' ? 'vd-logo-ventura vd-auth-ventura' : 'vd-logo-ventura';
    $crossClass = $variant === 'auth' ? 'vd-cross vd-auth-cross' : 'vd-cross';

    return '<div class="vd-logo-name">' . $top . '</div>'
        . '<div class="' . $venturaClass . '">VEN<span class="' . $crossClass . '">✚</span>URA</div>'
        . '<div class="vd-logo-sub">' . $sub . '</div>';
}
