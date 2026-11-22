<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\Storage;

final class PublicImageUrl
{
    /** Identify a public-disk reference, never an arbitrary external URL or a deletion permission. */
    public static function publicDiskKey(string $reference): ?string
    {
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $reference)) return null;
        $parts = parse_url($reference);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) return null;
        $path = rawurldecode($parts['path'] ?? '');
        if (isset($parts['scheme']) || isset($parts['host'])) {
            $base = parse_url(url(Storage::disk('public')->url('')));
            if ($base === false || self::origin($parts) === null || self::origin($parts) !== self::origin($base)) return null;
            $prefix = rtrim(rawurldecode($base['path'] ?? ''), '/').'/';
            if (!str_starts_with($path, $prefix)) return null;
            $path = substr($path, strlen($prefix));
        } elseif (str_starts_with($path, '/storage/')) {
            $path = substr($path, strlen('/storage/'));
        } elseif (str_starts_with($path, '/')) {
            $base = parse_url(url(Storage::disk('public')->url('')));
            $prefix = rtrim(rawurldecode($base['path'] ?? ''), '/').'/';
            if (!str_starts_with($path, $prefix)) return null;
            $path = substr($path, strlen($prefix));
        }
        if (!preg_match('~\A(?:rooms|properties)/[A-Za-z0-9_./-]+\z~D', $path)) return null;
        foreach (explode('/', $path) as $segment) {
            if (in_array($segment, ['', '.', '..'], true)) return null;
        }

        return $path;
    }

    private static function origin(array $parts): ?string
    {
        $scheme = strtolower($parts['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true) || empty($parts['host'])) return null;

        return $scheme.'://'.strtolower($parts['host']).':'.($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
    }

    public static function from(?string $path): ?string
    {
        if (!$path || preg_match('/[\x00-\x20\x7f\\\\]/', $path)) return null;
        $parts = parse_url($path);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) return null;
        $imagePath = rawurldecode($parts['path'] ?? '');
        if (str_contains($imagePath, '..') || str_contains($imagePath, '%')
            || preg_match('~(?:^|/)(?:private|profile-avatars)(?:/|$)|/api/profile/avatar~i', $imagePath)) return null;
        if (isset($parts['scheme'])) {
            return in_array(strtolower($parts['scheme']), ['https', 'http'], true)
                && filter_var($path, FILTER_VALIDATE_URL) ? $path : null;
        }
        // Only explicitly public catalog folders can be resolved through the configured public disk.
        if (preg_match('~\A(?:properties|rooms)/[A-Za-z0-9_./-]+\z~', $path)) return Storage::disk('public')->url($path);
        if (preg_match('~\A/storage/(?:properties|rooms)/[A-Za-z0-9_./-]+\z~', $path)) return url($path);
        return null;
    }
}
