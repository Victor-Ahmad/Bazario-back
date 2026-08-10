<?php

namespace App\Support;

class MediaPath
{
    public static function uploadsDisk(): string
    {
        return (string) config('bazario.uploads_disk', 'public');
    }

    public static function normalizeStoredPath(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $normalized = preg_replace('#^/?storage/#', '', $path);
        $normalized = ltrim((string) $normalized, '/');

        return $normalized !== '' ? $normalized : null;
    }

    public static function publicUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        $normalized = self::normalizeStoredPath($path);

        return $normalized ? url('/media/' . $normalized) : null;
    }
}
