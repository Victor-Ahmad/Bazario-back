<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

class PublicMediaController extends Controller
{
    public function show(string $path)
    {
        abort_if($path === '', 404);

        $normalizedPath = ltrim($path, '/');

        abort_unless(Storage::disk('public')->exists($normalizedPath), 404);

        return Storage::disk('public')->response($normalizedPath);
    }
}
