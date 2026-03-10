<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileServeController extends Controller
{
    public function serve(Request $request): StreamedResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired URL.');
        }

        $path = $request->query('path');

        if (! $path || str_contains($path, '..') || str_starts_with($path, '/')) {
            abort(400, 'Invalid path.');
        }

        $disk = config('filesystems.default', 'local');

        if (! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        $mimeType = Storage::disk($disk)->mimeType($path) ?? 'application/octet-stream';
        $filename = basename($path);

        return Storage::disk($disk)->response($path, $filename, [
            'Content-Type' => $mimeType,
        ]);
    }
}
