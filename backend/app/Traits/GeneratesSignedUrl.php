<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

trait GeneratesSignedUrl
{
    protected function signedUrl(string $disk, string $path): string
    {
        if (in_array($disk, ['local', 'public'], true)) {
            return URL::signedRoute('files.serve', ['path' => $path], now()->addMinutes(15));
        }

        try {
            return Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(15));
        } catch (\RuntimeException $e) {
            return Storage::disk($disk)->url($path);
        }
    }
}
