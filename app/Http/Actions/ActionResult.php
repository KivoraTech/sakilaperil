<?php

declare(strict_types=1);

namespace App\Http\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * ActionResult JSON (same shape as sakila/lib/actions/result.ts) for /admin/actions/* endpoints.
 * Wrap every action body in ActionResult::run(fn () => ActionResult::ok($data, 'Saved')).
 * Errors never leak details: validation → VALIDATION + fieldErrors, auth → FORBIDDEN/UNAUTHENTICATED.
 */
final class ActionResult
{
    public static function ok(mixed $data = null, ?string $message = null): JsonResponse
    {
        return response()->json(array_filter(['ok' => true, 'data' => $data, 'message' => $message], fn ($v, $k) => $k !== 'message' || $v !== null, ARRAY_FILTER_USE_BOTH));
    }

    /** @param array<string, list<string>>|null $fieldErrors */
    public static function fail(string $code, string $message, ?array $fieldErrors = null, ?int $status = null): JsonResponse
    {
        $status ??= match ($code) {
            'VALIDATION' => 422,
            'UNAUTHENTICATED' => 401,
            'FORBIDDEN' => 403,
            'NOT_FOUND' => 404,
            'CONFLICT' => 409,
            'RATE_LIMITED' => 429,
            default => 500,
        };

        return response()->json(array_filter([
            'ok' => false,
            'code' => $code,
            'message' => $message,
            'fieldErrors' => $fieldErrors,
        ], fn ($v) => $v !== null), $status);
    }

    /** Run an action; convert anything thrown into a safe ActionResult (toActionError in the reference). */
    public static function run(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (ValidationException $e) {
            /** @var array<string, list<string>> $errors */
            $errors = $e->errors();

            return self::fail('VALIDATION', 'Please check the highlighted fields.', $errors);
        } catch (AuthenticationException) {
            return self::fail('UNAUTHENTICATED', 'Your session has expired. Please sign in again.');
        } catch (AuthorizationException) {
            return self::fail('FORBIDDEN', 'You do not have permission to do this.');
        } catch (ModelNotFoundException) {
            return self::fail('NOT_FOUND', 'This item no longer exists.');
        } catch (HttpExceptionInterface $e) {
            return match ($e->getStatusCode()) {
                403 => self::fail('FORBIDDEN', 'You do not have permission to do this.'),
                404 => self::fail('NOT_FOUND', 'This item no longer exists.'),
                409 => self::fail('CONFLICT', $e->getMessage() ?: 'Someone else changed this item. Reload and try again.'),
                429 => self::fail('RATE_LIMITED', 'Too many requests. Please wait a moment.'),
                default => self::fail('ERROR', 'Something went wrong. Please try again.'),
            };
        } catch (Throwable $e) {
            Log::error('[action] unexpected error', ['exception' => $e]);

            return self::fail('ERROR', 'Something went wrong. Please try again.');
        }
    }
}
