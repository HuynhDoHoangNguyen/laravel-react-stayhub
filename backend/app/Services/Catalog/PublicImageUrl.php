<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\Storage;

final class PublicImageUrl
{
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
