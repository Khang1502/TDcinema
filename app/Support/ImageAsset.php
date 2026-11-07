<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class ImageAsset
{
    public static function storagePath(?string $path): string
    {
        $path = ltrim(str_replace('\\', '/', trim((string) $path)), '/');

        return str_starts_with($path, 'storage/') ? substr($path, 8) : $path;
    }

    public static function isRemote(?string $path): bool
    {
        return (bool) preg_match('#^https?://#i', trim((string) $path));
    }

    public static function exists(?string $path): bool
    {
        return filled($path) && (self::isRemote($path) || Storage::disk('public')->exists(self::storagePath($path)));
    }

    public static function url(?string $path, bool $versioned = false): ?string
    {
        if (blank($path)) return null;
        if (self::isRemote($path)) return trim($path);

        $path = self::storagePath($path);
        $version = '';
        if ($versioned) {
            $file = Storage::disk('public')->path($path);
            if (is_file($file)) $version = '?v='.filemtime($file);
        }

        return asset('storage/'.$path).$version;
    }
}
