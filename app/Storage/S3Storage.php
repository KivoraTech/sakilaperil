<?php

declare(strict_types=1);

namespace App\Storage;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * S3-compatible adapter (port of lib/storage/s3.ts): Cloudflare R2 / AWS S3 / MinIO with
 * SigV4-signed requests over Laravel's HTTP client (no AWS SDK — keeps the cPanel upload small).
 * Path-style URLs `${S3_ENDPOINT}/${S3_BUCKET}/${key}`; public reads through S3_PUBLIC_URL.
 */
final class S3Storage implements StorageAdapter
{
    /** @param array{endpoint: string, region: string, bucket: string, accessKeyId: string, secretAccessKey: string, publicUrl: string} $cfg */
    public function __construct(private readonly array $cfg) {}

    /** @return array{endpoint: string, region: string, bucket: string, accessKeyId: string, secretAccessKey: string, publicUrl: string} */
    public static function configFromEnv(): array
    {
        $c = config('sakila.s3');
        $cfg = [
            'endpoint' => rtrim(trim((string) ($c['endpoint'] ?? '')), '/'),
            'region' => trim((string) ($c['region'] ?? '')) ?: 'auto',
            'bucket' => trim((string) ($c['bucket'] ?? '')),
            'accessKeyId' => trim((string) ($c['access_key_id'] ?? '')),
            'secretAccessKey' => trim((string) ($c['secret_access_key'] ?? '')),
            'publicUrl' => rtrim(trim((string) ($c['public_url'] ?? '')), '/'),
        ];
        $missing = array_keys(array_filter($cfg, fn ($v) => $v === ''));
        if ($missing !== []) {
            throw new RuntimeException('STORAGE_DRIVER=s3 is missing configuration: '.implode(', ', $missing));
        }
        if (preg_match('#^https?://#', $cfg['endpoint']) !== 1 || ! str_starts_with($cfg['publicUrl'], 'https://')) {
            throw new RuntimeException('S3_ENDPOINT must be an http(s) URL and S3_PUBLIC_URL an https URL');
        }

        return $cfg;
    }

    public function driver(): string
    {
        return 's3';
    }

    public function put(string $key, string $body, string $contentType, ?string $cacheControl = null): array
    {
        $res = $this->send('PUT', $key, $body, [
            'content-type' => $contentType,
            'cache-control' => $cacheControl ?? 'public, max-age=31536000, immutable',
            'x-content-type-options' => 'nosniff',
        ]);
        if ($res->status() >= 300) {
            throw new RuntimeException("S3 PUT failed ({$res->status()})");
        }

        return ['key' => $key, 'url' => $this->publicUrl($key), 'size' => strlen($body)];
    }

    public function delete(string $key): void
    {
        $res = $this->send('DELETE', $key);
        if ($res->status() >= 300 && $res->status() !== 404) {
            throw new RuntimeException("S3 DELETE failed ({$res->status()})");
        }
    }

    public function exists(string $key): bool
    {
        return $this->send('HEAD', $key)->successful();
    }

    public function publicUrl(string $key): string
    {
        return $this->cfg['publicUrl'].'/'.self::encodeKey($key);
    }

    public function path(string $key): ?string
    {
        return null;
    }

    private static function encodeKey(string $key): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $key)));
    }

    /** @param array<string, string> $headers */
    private function send(string $method, string $key, string $body = '', array $headers = []): Response
    {
        if (! StorageKeys::isValid($key)) {
            throw new RuntimeException('Invalid storage key');
        }
        $url = $this->cfg['endpoint'].'/'.rawurlencode($this->cfg['bucket']).'/'.self::encodeKey($key);
        $signed = SigV4::sign($method, $url, $headers, $body, $this->cfg['accessKeyId'], $this->cfg['secretAccessKey'], $this->cfg['region'], 's3');

        return Http::withHeaders($signed)->withBody($body, $headers['content-type'] ?? 'application/octet-stream')
            ->retry(2, 200, throw: false)->send($method, $url);
    }
}
