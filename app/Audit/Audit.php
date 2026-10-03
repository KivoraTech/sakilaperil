<?php

declare(strict_types=1);

namespace App\Audit;

use App\Models\AuditLog;
use App\Support\RequestInfo;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Port of sakila/lib/audit: record an admin action. Never throws; metadata is sanitised. */
final class Audit
{
    private const SECRET_KEY = '/pass(word)?|secret|token|authorization|cookie|api[-_]?key|card|cvc|cvv/i';

    private const MAX_DEPTH = 5;

    private const MAX_STRING = 2000;

    /** @param array<string, mixed>|null $metadata */
    public static function log(
        AuditAction $action,
        ?string $userId = null,
        ?string $entityType = null,
        ?string $entityId = null,
        ?array $metadata = null,
    ): void {
        try {
            AuditLog::query()->create([
                'action' => $action->value,
                'userId' => $userId,
                'entityType' => $entityType,
                'entityId' => $entityId,
                'metadata' => $metadata !== null ? self::sanitize($metadata) : null,
                'ipAddress' => RequestInfo::clientIp(),
                'userAgent' => RequestInfo::userAgent(),
            ]);
        } catch (Throwable $e) {
            Log::error('[audit] failed to write audit log', ['action' => $action->value, 'error' => $e->getMessage()]);
        }
    }

    public static function sanitize(mixed $value, int $depth = 0): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            return '[truncated]';
        }
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value)) {
            return mb_strlen($value) > self::MAX_STRING ? mb_substr($value, 0, self::MAX_STRING).'…' : $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        if (is_array($value)) {
            if (array_is_list($value)) {
                return array_map(fn ($v) => self::sanitize($v, $depth + 1), array_slice($value, 0, 100));
            }
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = preg_match(self::SECRET_KEY, (string) $k) ? '[redacted]' : self::sanitize($v, $depth + 1);
            }

            return $out;
        }

        return is_object($value) && method_exists($value, '__toString') ? (string) $value : get_debug_type($value);
    }
}
