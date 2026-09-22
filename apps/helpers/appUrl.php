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
    // Prefer an explicitly configured URL because it is authoritative when
    // the application is served through an alias or reverse proxy.
    $configuredBase = trim($configuredBase);
    if ($configuredBase !== '') {
        return rtrim($configuredBase, '/');
    }

    // Normalize the script path so URL detection works consistently on all
    // operating systems and when SCRIPT_NAME contains a query string.
    $scriptPath = str_replace('\\', '/', (string) (parse_url($scriptName, PHP_URL_PATH) ?? ''));
    $appsPosition = strpos($scriptPath, '/apps/');
    if ($appsPosition !== false) {
        // Remove the /apps/ portion to obtain the project’s public URL prefix.
        return rtrim(substr($scriptPath, 0, $appsPosition), '/');
    }

    // If /apps/ is not present, use the script’s containing directory as a
    // fallback, while treating the web root and current directory as no prefix.
    $directory = str_replace('\\', '/', dirname($scriptPath));
    return $directory === '/' || $directory === '.' ? '' : rtrim($directory, '/');
}

function vdAppBaseUrl(): string
{
    // Cache the resolved prefix so repeated URL generation does not recalculate
    // the environment and request path during the same request.
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
    // Remove leading slashes before joining the prefix and path, preventing
    // accidental double slashes in generated application URLs.
    $path = ltrim($path, '/');
    if ($path === '') {
        // Return the application root with exactly one trailing slash.
        return $baseUrl === '' ? '/' : $baseUrl . '/';
    }
    // Join the base prefix and requested path while supporting web-root installs.
    return ($baseUrl === '' ? '' : $baseUrl) . '/' . $path;
}
