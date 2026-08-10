<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class PublicMediaController extends Controller
{
    public function show(string $path)
    {
        abort_if($path === '', 404);

        $normalizedPath = ltrim($path, '/');
        $disk = config('bazario.uploads_disk', 'public');
        $storage = Storage::disk($disk);

        abort_unless($storage->exists($normalizedPath), 404);

        if (method_exists($storage, 'response')) {
            return $storage->response($normalizedPath);
        }

        $stream = $storage->readStream($normalizedPath);

        abort_unless($stream !== false, 404);

        $mimeType = $storage->mimeType($normalizedPath) ?: 'application/octet-stream';
        $size = $storage->size($normalizedPath);

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            fclose($stream);
        }, Response::HTTP_OK, array_filter([
            'Content-Type' => $mimeType,
            'Content-Length' => $size ?: null,
        ]));
    }
}
