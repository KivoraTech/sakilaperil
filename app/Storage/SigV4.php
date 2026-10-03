<?php

declare(strict_types=1);

namespace App\Storage;

/** Minimal AWS Signature Version 4 for single S3 object requests (what aws4fetch did). */
final class SigV4
{
    /**
     * @param  array<string, string>  $headers  lower-case names
     * @return array<string, string> headers to send (including Authorization)
     */
    public static function sign(string $method, string $url, array $headers, string $body, string $accessKey, string $secretKey, string $region, string $service, ?\DateTimeInterface $now = null): array
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $amzDate = $now->format('Ymd\THis\Z');
        $date = $now->format('Ymd');
        $parts = parse_url($url);
        $host = ($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');
        $path = $parts['path'] ?? '/';
        $payloadHash = hash('sha256', $body);

        $headers = array_change_key_case($headers) + ['host' => $host, 'x-amz-date' => $amzDate, 'x-amz-content-sha256' => $payloadHash];
        ksort($headers);
        $canonicalHeaders = '';
        foreach ($headers as $k => $v) {
            $canonicalHeaders .= $k.':'.trim((string) preg_replace('/\s+/', ' ', $v))."\n";
        }
        $signedHeaders = implode(';', array_keys($headers));
        $canonicalRequest = implode("\n", [$method, $path, $parts['query'] ?? '', $canonicalHeaders, $signedHeaders, $payloadHash]);
        $scope = "{$date}/{$region}/{$service}/aws4_request";
        $stringToSign = implode("\n", ['AWS4-HMAC-SHA256', $amzDate, $scope, hash('sha256', $canonicalRequest)]);
        $kDate = hash_hmac('sha256', $date, 'AWS4'.$secretKey, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);
        $headers['authorization'] = "AWS4-HMAC-SHA256 Credential={$accessKey}/{$scope}, SignedHeaders={$signedHeaders}, Signature={$signature}";
        unset($headers['host']);

        return $headers;
    }
}
