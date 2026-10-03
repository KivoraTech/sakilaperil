<?php

declare(strict_types=1);

namespace App\Storage;

/**
 * Upload validation (port of lib/storage/validation.ts). The MIME type is SNIFFED from the
 * file's magic bytes — the client's Content-Type and extension are never trusted. SVG/HTML are
 * rejected (script risk). Deterministic signature checks (no dependency on the host's libmagic).
 */
final class UploadValidator
{
    /** @var array<string, array{kind: string, ext: string}> */
    public const ALLOWED_TYPES = [
        'image/jpeg' => ['kind' => 'IMAGE', 'ext' => 'jpg'],
        'image/png' => ['kind' => 'IMAGE', 'ext' => 'png'],
        'image/webp' => ['kind' => 'IMAGE', 'ext' => 'webp'],
        'image/avif' => ['kind' => 'IMAGE', 'ext' => 'avif'],
        'image/gif' => ['kind' => 'IMAGE', 'ext' => 'gif'],
        'application/pdf' => ['kind' => 'DOCUMENT', 'ext' => 'pdf'],
    ];

    public static function maxBytesFor(string $kind): int
    {
        $mb = (float) ($kind === 'IMAGE' ? config('sakila.upload_max_image_mb') : config('sakila.upload_max_document_mb'));
        $fallback = $kind === 'IMAGE' ? 10 : 25;

        return (int) floor(($mb > 0 ? $mb : $fallback) * 1024 * 1024);
    }

    /**
     * @param  list<string>|null  $allowedKinds
     * @return array{ok: true, mimeType: string, ext: string, kind: string, size: int}|array{ok: false, error: string, message: string}
     */
    public static function validate(string $bytes, ?array $allowedKinds = null): array
    {
        $size = strlen($bytes);
        if ($size === 0) {
            return ['ok' => false, 'error' => 'EMPTY', 'message' => 'The file is empty.'];
        }
        $mime = self::sniff($bytes);
        $allowed = $mime !== null ? self::ALLOWED_TYPES[$mime] : null;
        if ($allowed === null || ($allowedKinds !== null && ! in_array($allowed['kind'], $allowedKinds, true))) {
            return ['ok' => false, 'error' => 'UNSUPPORTED_TYPE', 'message' => 'Unsupported file type. Use JPG, PNG, WebP, AVIF, GIF or PDF.'];
        }
        $max = self::maxBytesFor($allowed['kind']);
        if ($size > $max) {
            return ['ok' => false, 'error' => 'TOO_LARGE', 'message' => 'File is too large (max '.(int) round($max / 1048576).' MB).'];
        }

        return ['ok' => true, 'mimeType' => (string) $mime, 'ext' => $allowed['ext'], 'kind' => $allowed['kind'], 'size' => $size];
    }

    /** Magic-byte detection for the allowed types only (anything else → null). */
    public static function sniff(string $b): ?string
    {
        if (str_starts_with($b, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($b, "\x89PNG\r\n\x1A\n")) {
            return 'image/png';
        }
        if (str_starts_with($b, 'GIF87a') || str_starts_with($b, 'GIF89a')) {
            return 'image/gif';
        }
        if (substr($b, 0, 4) === 'RIFF' && substr($b, 8, 4) === 'WEBP') {
            return 'image/webp';
        }
        if (substr($b, 4, 4) === 'ftyp') {
            $brands = substr($b, 8, max(0, min(strlen($b), (int) (unpack('N', substr($b, 0, 4))[1] ?? 0)) - 8));
            if (str_contains($brands, 'avif') || str_contains($brands, 'avis')) {
                return 'image/avif';
            }
        }
        if (str_starts_with($b, '%PDF-')) {
            return 'application/pdf';
        }

        return null;
    }
}
