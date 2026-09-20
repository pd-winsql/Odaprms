<?php

final class SiteLogoUpload
{
    public const MAX_BYTES = 5 * 1024 * 1024;
    public const MAX_WIDTH = 4096;
    public const MAX_HEIGHT = 4096;
    public const MAX_PIXELS = 16_000_000;

    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Validate and replace a logo while keeping the old logo authoritative until
     * the database callback succeeds. Callers supply the native HTTP upload
     * functions so this workflow can be tested without accepting non-HTTP files
     * in production.
     */
    public static function replace(
        array $upload,
        string $targetDirectory,
        string $oldFilename,
        callable $databaseUpdate,
        callable $isUploadedFile,
        callable $moveUploadedFile
    ): array {
        $validated = self::validate($upload, $isUploadedFile);
        if (!$validated['success']) {
            return $validated;
        }

        $baseDirectory = realpath($targetDirectory);
        if ($baseDirectory === false || !is_dir($baseDirectory) || !is_writable($baseDirectory)) {
            return self::failure('Logo storage is unavailable.');
        }

        try {
            $filename = 'site_logo_' . bin2hex(random_bytes(16)) . '.' . $validated['extension'];
        } catch (Throwable $exception) {
            error_log('Logo filename generation failed: ' . $exception->getMessage());
            return self::failure('Unable to create a secure logo filename.');
        }

        $target = $baseDirectory . DIRECTORY_SEPARATOR . $filename;
        if (!self::isDirectChildPath($target, $baseDirectory)) {
            return self::failure('Invalid logo storage path.');
        }

        if (!$moveUploadedFile($validated['path'], $target)) {
            return self::failure('Failed to upload logo.');
        }

        $resolvedTarget = realpath($target);
        if ($resolvedTarget === false || !self::isDirectChildPath($resolvedTarget, $baseDirectory) || !is_file($resolvedTarget) || is_link($target)) {
            self::removeFile($target);
            return self::failure('Invalid logo storage path.');
        }

        try {
            $saved = $databaseUpdate($filename) === true;
        } catch (Throwable $exception) {
            error_log('Logo database update failed: ' . $exception->getMessage());
            $saved = false;
        }

        if (!$saved) {
            self::removeFile($resolvedTarget);
            return self::failure('Failed to save logo.');
        }

        self::removeManagedLogo($baseDirectory, $oldFilename, $filename);

        return ['success' => true, 'filename' => $filename];
    }

    public static function validate(array $upload, callable $isUploadedFile): array
    {
        $error = filter_var($upload['error'] ?? null, FILTER_VALIDATE_INT);
        if ($error !== UPLOAD_ERR_OK) {
            return self::failure(self::uploadErrorMessage($error));
        }

        $path = $upload['tmp_name'] ?? null;
        if (!is_string($path) || $path === '' || !$isUploadedFile($path) || !is_file($path)) {
            return self::failure('No valid upload found.');
        }

        $size = filesize($path);
        if ($size === false || $size <= 0) {
            return self::failure('Logo image cannot be empty.');
        }
        if ($size > self::MAX_BYTES) {
            return self::failure('Logo image must not exceed 5 MB.');
        }

        try {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        } catch (Throwable $exception) {
            $mime = false;
        }
        if (!is_string($mime) || !isset(self::MIME_EXTENSIONS[$mime])) {
            return self::failure('Logo must be a valid JPG, PNG, or WebP image.');
        }

        $image = @getimagesize($path);
        if ($image === false || !isset($image[0], $image[1], $image['mime']) || $image['mime'] !== $mime) {
            return self::failure('Logo image is corrupt or invalid.');
        }

        $width = (int) $image[0];
        $height = (int) $image[1];
        if ($width < 1 || $height < 1 || $width > self::MAX_WIDTH || $height > self::MAX_HEIGHT
            || $width > intdiv(self::MAX_PIXELS, $height)) {
            return self::failure('Logo dimensions must be at most 4096 by 4096 pixels and 16 megapixels.');
        }

        if (!self::hasExactImageContainer($path, $mime)) {
            return self::failure('Logo image contains invalid or trailing content.');
        }

        return [
            'success' => true,
            'path' => $path,
            'mime' => $mime,
            'extension' => self::MIME_EXTENSIONS[$mime],
            'width' => $width,
            'height' => $height,
        ];
    }

    private static function hasExactImageContainer(string $path, string $mime): bool
    {
        $contents = @file_get_contents($path);
        if (!is_string($contents)) {
            return false;
        }

        if ($mime === 'image/png') {
            return self::isCompletePng($contents);
        }
        if ($mime === 'image/webp') {
            return strlen($contents) >= 12
                && substr($contents, 0, 4) === 'RIFF'
                && substr($contents, 8, 4) === 'WEBP'
                && unpack('V', substr($contents, 4, 4))[1] + 8 === strlen($contents);
        }
        if ($mime === 'image/jpeg') {
            return self::isCompleteJpeg($contents);
        }
        return false;
    }

    private static function isCompletePng(string $contents): bool
    {
        if (substr($contents, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return false;
        }
        $offset = 8;
        $length = strlen($contents);
        $first = true;
        while ($offset + 12 <= $length) {
            $chunkLength = unpack('N', substr($contents, $offset, 4))[1];
            $type = substr($contents, $offset + 4, 4);
            $chunkEnd = $offset + 12 + $chunkLength;
            if ($chunkEnd > $length || ($first && ($type !== 'IHDR' || $chunkLength !== 13))) {
                return false;
            }
            $data = substr($contents, $offset + 8, $chunkLength);
            $crc = substr($contents, $offset + 8 + $chunkLength, 4);
            if (!hash_equals(hash('crc32b', $type . $data, true), $crc)) {
                return false;
            }
            if ($type === 'IEND') {
                return $chunkLength === 0 && $chunkEnd === $length;
            }
            $first = false;
            $offset = $chunkEnd;
        }
        return false;
    }

    private static function isCompleteJpeg(string $contents): bool
    {
        $length = strlen($contents);
        if ($length < 4 || substr($contents, 0, 2) !== "\xff\xd8") {
            return false;
        }
        $offset = 2;
        $inScan = false;
        while ($offset < $length) {
            if (!$inScan && ord($contents[$offset]) !== 0xff) {
                return false;
            }
            if ($inScan) {
                while ($offset < $length && ord($contents[$offset]) !== 0xff) {
                    $offset++;
                }
                if ($offset >= $length) {
                    return false;
                }
            }
            while ($offset < $length && ord($contents[$offset]) === 0xff) {
                $offset++;
            }
            if ($offset >= $length) {
                return false;
            }
            $marker = ord($contents[$offset++]);
            if ($inScan && ($marker === 0x00 || ($marker >= 0xd0 && $marker <= 0xd7))) {
                continue;
            }
            $inScan = false;
            if ($marker === 0xd9) {
                return $offset === $length;
            }
            if ($marker === 0xd8 || $marker === 0x01 || ($marker >= 0xd0 && $marker <= 0xd7)) {
                continue;
            }
            if ($offset + 2 > $length) {
                return false;
            }
            $segmentLength = unpack('n', substr($contents, $offset, 2))[1];
            if ($segmentLength < 2 || $offset + $segmentLength > $length) {
                return false;
            }
            $offset += $segmentLength;
            if ($marker === 0xda) {
                $inScan = true;
            }
        }
        return false;
    }

    private static function removeManagedLogo(string $baseDirectory, string $oldFilename, string $newFilename): void
    {
        if ($oldFilename === '' || $oldFilename === $newFilename || basename($oldFilename) !== $oldFilename
            || !preg_match('/^site_logo_(?:[a-f0-9]{32}\.(?:jpg|png|webp)|[0-9]{10}\.(?:jpe?g|png|webp|svg))$/D', $oldFilename)) {
            return;
        }
        $oldPath = $baseDirectory . DIRECTORY_SEPARATOR . $oldFilename;
        $resolvedOld = realpath($oldPath);
        if ($resolvedOld !== false && self::isDirectChildPath($resolvedOld, $baseDirectory) && is_file($resolvedOld) && !is_link($oldPath)) {
            self::removeFile($resolvedOld);
        }
    }

    private static function isDirectChildPath(string $path, string $baseDirectory): bool
    {
        $normalizedBase = rtrim(str_replace('\\', '/', $baseDirectory), '/');
        $normalizedPath = str_replace('\\', '/', $path);
        return dirname($normalizedPath) === $normalizedBase;
    }

    private static function removeFile(string $path): void
    {
        if (is_file($path) && !@unlink($path)) {
            error_log('Unable to remove staged logo file: ' . basename($path));
        }
    }

    private static function uploadErrorMessage($error): string
    {
        if ($error === UPLOAD_ERR_NO_FILE) {
            return 'No file selected.';
        }
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            return 'Logo image exceeds the upload size limit.';
        }
        return 'Upload failed. Please try again.';
    }

    private static function failure(string $message): array
    {
        return ['success' => false, 'message' => $message];
    }
}
