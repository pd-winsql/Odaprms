<?php

/**
 * Resolve the public URL prefix for this installation.
 *
 * APP_BASE_URL may be set for Apache aliases or reverse proxies. Otherwise the
 * prefix is derived from SCRIPT_NAME, so renaming the project directory does
 * not break controller or partial requests.
 */
function vdResolveAppBaseUrl(string $scriptName, string $configuredBase = ''): string
{
    $configuredBase = trim($configuredBase);
    if ($configuredBase !== '') {
        return rtrim($configuredBase, '/');
    }

    $scriptPath = str_replace('\\', '/', (string) (parse_url($scriptName, PHP_URL_PATH) ?? ''));
    $appsPosition = strpos($scriptPath, '/apps/');
    if ($appsPosition !== false) {
        return rtrim(substr($scriptPath, 0, $appsPosition), '/');
    }

    $directory = str_replace('\\', '/', dirname($scriptPath));
    return $directory === '/' || $directory === '.' ? '' : rtrim($directory, '/');
}

function vdAppBaseUrl(): string
{
    static $baseUrl;
    if ($baseUrl === null) {
        $configured = (string) ($_ENV['APP_BASE_URL'] ?? getenv('APP_BASE_URL') ?: '');
        $baseUrl = vdResolveAppBaseUrl((string) ($_SERVER['SCRIPT_NAME'] ?? ''), $configured);
    }
    return $baseUrl;
}

function vdAppUrl(string $path = ''): string
{
    $baseUrl = vdAppBaseUrl();
    $path = ltrim($path, '/');
    if ($path === '') {
        return $baseUrl === '' ? '/' : $baseUrl . '/';
    }
    return ($baseUrl === '' ? '' : $baseUrl) . '/' . $path;
}

