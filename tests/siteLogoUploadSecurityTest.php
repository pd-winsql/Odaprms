<?php

require_once __DIR__ . '/../apps/support/SiteLogoUpload.php';

function expectLogo(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

function logoUpload(string $path, string $clientName = 'logo.png', int $error = UPLOAD_ERR_OK): array
{
    return [
        'name' => $clientName,
        'tmp_name' => $path,
        'error' => $error,
        'size' => is_file($path) ? filesize($path) : 0,
    ];
}

function fakeUploadCheck(string $path): bool
{
    return is_file($path);
}

function fakeUploadMove(string $source, string $target): bool
{
    return copy($source, $target);
}

function writeFixture(string $directory, string $name, string $contents): string
{
    $path = $directory . DIRECTORY_SEPARATOR . $name;
    if (file_put_contents($path, $contents) === false) {
        throw new RuntimeException("Unable to write fixture {$name}");
    }
    return $path;
}

function pngWithDimensions(string $png, int $width, int $height): string
{
    $dimensions = pack('NN', $width, $height);
    $png = substr_replace($png, $dimensions, 16, 8);
    $ihdrTypeAndData = substr($png, 12, 17);
    return substr_replace($png, hash('crc32b', $ihdrTypeAndData, true), 29, 4);
}

function removeTestTree(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    foreach (scandir($directory) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                removeTestTree($path);
            } else {
                unlink($path);
            }
        }
    }
    rmdir($directory);
}

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'site-logo-security-' . bin2hex(random_bytes(6));
$uploads = $root . DIRECTORY_SEPARATOR . 'uploads';
$assets = $root . DIRECTORY_SEPARATOR . 'assets';
mkdir($uploads, 0700, true);
mkdir($assets, 0700, true);

$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
$jpeg = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABAf/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPxB//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPxB//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxB//9k=', true);
$webp = base64_decode('UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEADsD+JaQAA3AAAAAA', true);

try {
    $validFixtures = [
        'png' => writeFixture($uploads, 'valid-png.bin', $png),
        'jpg' => writeFixture($uploads, 'valid-jpeg.bin', $jpeg),
        'webp' => writeFixture($uploads, 'valid-webp.bin', $webp),
    ];
    foreach ($validFixtures as $extension => $path) {
        $validated = SiteLogoUpload::validate(logoUpload($path, 'misleading.exe'), 'fakeUploadCheck');
        expectLogo($validated['success'] && $validated['extension'] === $extension, "Valid {$extension} content is accepted and determines its extension.");
    }

    $failedStatus = SiteLogoUpload::validate(logoUpload($validFixtures['png'], 'logo.png', UPLOAD_ERR_PARTIAL), 'fakeUploadCheck');
    expectLogo(!$failedStatus['success'], 'A non-successful PHP upload status is rejected.');
    $notHttpUpload = SiteLogoUpload::validate(logoUpload($validFixtures['png']), fn() => false);
    expectLogo(!$notHttpUpload['success'], 'A file not recognized as an HTTP upload is rejected.');

    $rejected = [
        'renamed executable' => writeFixture($uploads, 'malware.png', "MZ\x90\x00This program cannot be run in DOS mode"),
        'SVG with script' => writeFixture($uploads, 'script.jpg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        'HTML' => writeFixture($uploads, 'page.webp', '<!doctype html><script>alert(document.domain)</script>'),
        'PNG polyglot' => writeFixture($uploads, 'polyglot.png', $png . '<?php echo "active"; ?>'),
        'corrupt image' => writeFixture($uploads, 'corrupt.png', substr($png, 0, 25)),
        'zero-byte file' => writeFixture($uploads, 'empty.png', ''),
        'oversized dimensions' => writeFixture($uploads, 'huge.png', pngWithDimensions($png, 5000, 4000)),
        'oversized bytes' => writeFixture($uploads, 'large.jpg', str_repeat('A', SiteLogoUpload::MAX_BYTES + 1)),
    ];

    foreach ($rejected as $label => $path) {
        $before = scandir($assets);
        $databaseCalls = 0;
        $result = SiteLogoUpload::replace(
            logoUpload($path, '..\\..\\public\\shell.php'),
            $assets,
            'site_logo_1234567890.png',
            function () use (&$databaseCalls): bool {
                $databaseCalls++;
                return true;
            },
            'fakeUploadCheck',
            'fakeUploadMove'
        );
        expectLogo(!$result['success'], "{$label} is rejected.");
        expectLogo($databaseCalls === 0 && scandir($assets) === $before, "Rejected {$label} leaves no residue and does not change branding.");
    }

    $oldManaged = 'site_logo_1234567890.png';
    $oldPath = writeFixture($assets, $oldManaged, $png);
    $unsafeClientName = '..\\..\\evil.php.jpg';
    $oldExistedDuringCommit = false;
    $success = SiteLogoUpload::replace(
        logoUpload($validFixtures['png'], $unsafeClientName),
        $assets,
        $oldManaged,
        function (string $filename) use (&$oldExistedDuringCommit, $oldPath): bool {
            $oldExistedDuringCommit = is_file($oldPath);
            return (bool) preg_match('/^site_logo_[a-f0-9]{32}\.png$/D', $filename);
        },
        'fakeUploadCheck',
        'fakeUploadMove'
    );
    expectLogo($success['success'] && $oldExistedDuringCommit, 'The existing logo remains present until the database update succeeds.');
    expectLogo(!is_file($oldPath) && is_file($assets . DIRECTORY_SEPARATOR . $success['filename']), 'A successful replacement removes only the old managed logo after commit.');
    expectLogo(!str_contains($success['filename'], 'evil') && !str_contains($success['filename'], '..'), 'Traversal client names never influence the stored filename.');

    $unmanagedPath = writeFixture($assets, 'shared-logo.png', $png);
    $unmanagedResult = SiteLogoUpload::replace(logoUpload($validFixtures['png']), $assets, 'shared-logo.png', fn() => true, 'fakeUploadCheck', 'fakeUploadMove');
    expectLogo($unmanagedResult['success'] && is_file($unmanagedPath), 'A successful replacement never deletes an old file outside the managed logo naming scheme.');

    $first = SiteLogoUpload::replace(logoUpload($validFixtures['webp']), $assets, '', fn() => true, 'fakeUploadCheck', 'fakeUploadMove');
    $second = SiteLogoUpload::replace(logoUpload($validFixtures['webp']), $assets, '', fn() => true, 'fakeUploadCheck', 'fakeUploadMove');
    expectLogo($first['success'] && $second['success'] && $first['filename'] !== $second['filename'], 'Duplicate submissions receive unpredictable unique filenames.');

    $filesBeforeFailure = scandir($assets);
    $databaseCalled = false;
    $writeFailure = SiteLogoUpload::replace(
        logoUpload($validFixtures['jpg']),
        $assets,
        '',
        function () use (&$databaseCalled): bool {
            $databaseCalled = true;
            return true;
        },
        'fakeUploadCheck',
        fn() => false
    );
    expectLogo(!$writeFailure['success'] && !$databaseCalled && scandir($assets) === $filesBeforeFailure, 'A write failure leaves storage and branding unchanged.');

    $preservedOld = 'site_logo_1234567891.png';
    $preservedPath = writeFixture($assets, $preservedOld, $png);
    $beforeDatabaseFailure = scandir($assets);
    $databaseFailure = SiteLogoUpload::replace(
        logoUpload($validFixtures['jpg']),
        $assets,
        $preservedOld,
        fn() => false,
        'fakeUploadCheck',
        'fakeUploadMove'
    );
    expectLogo(!$databaseFailure['success'] && is_file($preservedPath), 'A database failure preserves the existing logo.');
    expectLogo(scandir($assets) === $beforeDatabaseFailure, 'A database failure removes the staged replacement without residue.');

    $controllerSource = file_get_contents(__DIR__ . '/../apps/controllers/siteSettingsController.php');
    expectLogo(!str_contains($controllerSource, "['logo']['name']"), 'The controller never reads or audits the unsafe client filename.');
} finally {
    removeTestTree($root);
}

echo "Site logo upload security test completed.\n";
