<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Storage\Storage;
use App\Storage\StorageKeys;
use App\Storage\UploadValidator;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves files stored by the LOCAL adapter (port of app/uploads/[...key]/route.ts). Keys are
 * validated (no traversal); Content-Type comes from the extension WE assigned after sniffing;
 * `nosniff`; locked-down CSP for images. SVG/HTML are never accepted at upload.
 */
final class UploadsController extends Controller
{
    public function __invoke(Request $request, string $key): Response
    {
        $storage = Storage::adapter();
        $ext = strtolower(pathinfo($key, PATHINFO_EXTENSION));
        $mime = null;
        foreach (UploadValidator::ALLOWED_TYPES as $type => $info) {
            if ($info['ext'] === $ext) {
                $mime = $type;
            }
        }
        if ($storage->driver() !== 'local' || $mime === null || ! StorageKeys::isValid($key)) {
            abort(404);
        }
        $file = $storage->path($key);
        if ($file === null) {
            abort(404);
        }
        // Keys are random and never reused, so the key itself identifies the content.
        $etag = '"'.rtrim(strtr(base64_encode(sha1($key, true)), '+/', '-_'), '=').'"';
        $cacheControl = 'public, max-age=31536000, immutable';
        if ($request->headers->get('if-none-match') === $etag) {
            return response('', 304, ['ETag' => $etag, 'Cache-Control' => $cacheControl]);
        }
        $headers = [
            'Content-Type' => $mime,
            'Cache-Control' => $cacheControl,
            'ETag' => $etag,
            'X-Content-Type-Options' => 'nosniff',
            'Cross-Origin-Resource-Policy' => 'same-site',
        ];
        $headers += $mime === 'application/pdf'
            ? ['Content-Disposition' => 'inline']
            : ['Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox"];

        $response = new BinaryFileResponse($file, 200, $headers, true, null, false, false);
        $response->headers->set('Content-Type', $mime);

        return $response;
    }
}
