<?php

declare(strict_types=1);

namespace App\Content;

use App\Http\Actions\ActionResult;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * An expected action failure with a user-facing message (the reference's `return fail(code, msg)`).
 * Thrown by ContentCommon::fail(); converted by ContentActionError::run().
 */
final class ContentActionError extends RuntimeException
{
    /** @param array<string, list<string>>|null $fieldErrors */
    public function __construct(public readonly string $errorCode, string $message, public readonly ?array $fieldErrors = null)
    {
        parent::__construct($message);
    }

    /**
     * ActionResult::run() plus expected failures (ContentActionError) and racing unique-slug inserts.
     *
     * @param  callable(): JsonResponse  $action
     */
    public static function run(callable $action, bool $slugConflictOnUnique = false): JsonResponse
    {
        return ActionResult::run(function () use ($action, $slugConflictOnUnique): JsonResponse {
            try {
                return $action();
            } catch (ContentActionError $e) {
                return ActionResult::fail($e->errorCode, $e->getMessage(), $e->fieldErrors);
            } catch (\Throwable $e) {
                if ($slugConflictOnUnique && ContentCommon::isUniqueViolation($e)) {
                    return ActionResult::fail('CONFLICT', 'That slug is already in use. Choose another one.');
                }
                throw $e;
            }
        });
    }
}
