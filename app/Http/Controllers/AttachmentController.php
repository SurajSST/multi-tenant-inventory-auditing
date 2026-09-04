<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Scanned bills and delivery photos.
 *
 * These live on a private disk, never under public/, and are served only
 * through a signed URL to a signed-in user. A leaked link expires; a guessed
 * path gets nowhere.
 */
class AttachmentController extends Controller
{
    public function __invoke(Request $request, string $path): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403, 'That link has expired. Open the record again.');

        $disk = Storage::disk(config('prativa.attachments.disk'));
        $directory = config('prativa.attachments.directory');

        // Never let a path climb out of the attachments directory.
        abort_unless(str_starts_with($path, $directory.'/') && ! str_contains($path, '..'), 404);
        abort_unless($disk->exists($path), 404, 'That file is no longer on the server.');

        return $disk->response($path);
    }
}
