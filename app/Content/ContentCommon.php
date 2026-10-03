<?php

declare(strict_types=1);

namespace App\Content;

use App\Audit\AuditAction;
use App\Auth\Permissions;
use App\Cache\CacheTags;
use App\Cache\TagCache;
use App\I18n\Locales;
use App\Models\EventTranslation;
use App\Models\GalleryAlbumTranslation;
use App\Models\MediaAsset;
use App\Models\NewsPostTranslation;
use App\Models\ProjectTranslation;
use App\Models\User;
use App\RichText\RichText;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Server helpers shared by the content admin (port of sakila/lib/content/{common,server}.ts and
 * lib/queries/admin/shared-content.ts).
 */
final class ContentCommon
{
    public const PAGE_SIZE = 20;

    public const STATUSES = ['DRAFT', 'PUBLISHED', 'ARCHIVED'];

    /** Every public tag that can display media (alt text / URL changes). */
    public const ALL_CONTENT_TAGS = [
        CacheTags::MEDIA, CacheTags::PAGES, CacheTags::NEWS, CacheTags::PROJECTS,
        CacheTags::EVENTS, CacheTags::ALBUMS, CacheTags::REPORTS, CacheTags::SETTINGS,
    ];

    // ── Auth ─────────────────────────────────────────────────────────

    /** requireCapability(): the signed-in user, or AuthorizationException (→ FORBIDDEN ActionResult). */
    public static function requireCapability(string $capability): User
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new AuthenticationException;
        }
        if (! Permissions::can($user->role, $capability)) {
            throw new AuthorizationException;
        }

        return $user;
    }

    public static function can(User $user, string $capability): bool
    {
        return Permissions::can($user->role, $capability);
    }

    public static function canEditContent(User $user, ?string $ownerId): bool
    {
        return Permissions::canEditContent((string) $user->id, $user->role, $ownerId);
    }

    /** A failed ActionResult with a specific code + message (mirrors `return fail(code, message)`). */
    /** @param array<string, list<string>>|null $fieldErrors */
    public static function fail(string $code, string $message, ?array $fieldErrors = null): never
    {
        throw new ContentActionError($code, $message, $fieldErrors);
    }

    // ── Request body ─────────────────────────────────────────────────

    /**
     * A key of the action's JSON body, read from the RAW request body. Laravel's global TrimStrings /
     * ConvertEmptyStringsToNull middleware rewrite `$request->json()` (empty strings → null, rich-text
     * runs like " and " trimmed), which would change what the editor sent; the reference received the
     * body untouched. Validation (Input) trims exactly where the Zod schemas do.
     */
    public static function body(Request $request, string $key): mixed
    {
        $decoded = $request->attributes->get('content.rawJson');
        if (! is_array($decoded)) {
            $parsed = json_decode((string) $request->getContent(), true, 512);
            $decoded = is_array($parsed) ? $parsed : [];
            $request->attributes->set('content.rawJson', $decoded);
        }

        return $decoded[$key] ?? null;
    }

    /**
     * The whole raw JSON body (see body()).
     *
     * @return array<string, mixed>
     */
    public static function bodyAll(Request $request): array
    {
        self::body($request, '');
        $all = $request->attributes->get('content.rawJson');

        return is_array($all) ? $all : [];
    }

    // ── Values ───────────────────────────────────────────────────────

    /** Enum-or-string column value as a plain string (models cast locale/status/kind to enums). */
    public static function val(mixed $v): string
    {
        return $v instanceof \BackedEnum ? (string) $v->value : (string) $v;
    }

    /**
     * The translation row for a locale (translations cast `locale` to App\Enums\Locale).
     *
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  iterable<T>  $translations
     * @return T|null
     */
    public static function byLocale(iterable $translations, string $locale): mixed
    {
        foreach ($translations as $t) {
            if (self::val($t->getAttribute('locale')) === $locale) {
                return $t;
            }
        }

        return null;
    }

    /** "" → null (server-side normalisation). */
    public static function blank(mixed $v): ?string
    {
        return $v === '' || $v === null ? null : (string) $v;
    }

    public static function dateOrNull(?string $iso): ?Carbon
    {
        return $iso !== null && $iso !== '' ? Carbon::parse($iso)->utc() : null;
    }

    /** Date#toISOString() (UTC, milliseconds, "Z"). */
    public static function iso(?DateTimeInterface $d): ?string
    {
        if ($d === null) {
            return null;
        }

        return Carbon::instance($d)->utc()->format('Y-m-d\TH:i:s.v\Z');
    }

    public static function isoOrEmpty(?DateTimeInterface $d): string
    {
        return self::iso($d) ?? '';
    }

    /** @return array<string, mixed> */
    public static function docOrEmpty(mixed $v): array
    {
        return is_array($v) && ($v['type'] ?? null) === 'doc' ? $v : RichText::emptyDoc();
    }

    public static function isScheduled(string $status, ?DateTimeInterface $publishedAt): bool
    {
        return $status === 'PUBLISHED' && $publishedAt !== null && $publishedAt > Carbon::now();
    }

    public static function pageCount(int $total, int $pageSize = self::PAGE_SIZE): int
    {
        return max(1, (int) ceil($total / $pageSize));
    }

    /**
     * Italian title (fallback: first) from translations.
     *
     * @param  iterable<Model>  $translations
     * @return array{title: string, slug: ?string}
     */
    public static function itTitle(iterable $translations): array
    {
        $first = null;
        foreach ($translations as $t) {
            $first ??= $t;
            if (self::val($t->getAttribute('locale')) === 'it') {
                $first = $t;
                break;
            }
        }
        if ($first === null) {
            return ['title' => '(untitled)', 'slug' => null];
        }
        $slug = $first->getAttribute('slug');

        return ['title' => (string) $first->getAttribute('title'), 'slug' => $slug !== null ? (string) $slug : null];
    }

    /**
     * List-page search params (bad values fall back to defaults, like listParamsSchema.catch()).
     *
     * @param  array<string, mixed>  $query
     * @return array{q: string, status: string, page: int}
     */
    public static function listParams(array $query): array
    {
        $one = fn (mixed $v): mixed => is_array($v) ? ($v[0] ?? null) : $v;
        $q = $one($query['q'] ?? '');
        $q = is_string($q) ? trim($q) : '';
        if (mb_strlen($q) > 100) {
            $q = '';
        }
        $status = $one($query['status'] ?? '');
        $status = is_string($status) && in_array($status, self::STATUSES, true) ? $status : '';
        $page = $one($query['page'] ?? 1);
        $page = is_numeric($page) && (string) (int) $page === trim((string) $page) ? (int) $page : 1;
        if ($page < 1 || $page > 10000) {
            $page = 1;
        }

        return ['q' => $q, 'status' => $status, 'page' => $page];
    }

    public static function expireTags(string ...$tags): void
    {
        TagCache::invalidate(...array_values(array_unique($tags)));
    }

    // ── Integrity checks ─────────────────────────────────────────────

    /**
     * Field errors for slugs already used by ANOTHER item in the same locale (unique per locale, ADR-007).
     *
     * @param  list<array{locale: string, slug: string}>  $entries
     * @return array<string, list<string>>
     */
    public static function slugConflicts(string $model, array $entries, ?string $excludeParentId): array
    {
        [$class, $fk] = match ($model) {
            'news' => [NewsPostTranslation::class, 'postId'],
            'project' => [ProjectTranslation::class, 'projectId'],
            'event' => [EventTranslation::class, 'eventId'],
            'album' => [GalleryAlbumTranslation::class, 'albumId'],
            default => throw new \InvalidArgumentException("Unknown slug model {$model}"),
        };
        $errors = [];
        foreach ($entries as ['locale' => $locale, 'slug' => $slug]) {
            $other = $class::query()->where('locale', $locale)->where('slug', $slug)->value($fk);
            if ($other !== null && $other !== $excludeParentId) {
                $errors["translations.{$locale}.slug"] = ['This slug is already used by another item in this language.'];
            }
        }

        return $errors;
    }

    /** @param array<string, list<string>> $fieldErrors */
    public static function slugConflict(array $fieldErrors = []): never
    {
        self::fail('CONFLICT', 'That slug is already in use. Choose another one.', $fieldErrors === [] ? null : $fieldErrors);
    }

    /**
     * Accessibility gate for publishing: filenames of referenced IMAGES missing Italian alt text.
     *
     * @param  iterable<string|null>  $ids
     * @return list<string>
     */
    public static function imagesMissingAlt(iterable $ids): array
    {
        $unique = [];
        foreach ($ids as $id) {
            if (is_string($id) && $id !== '') {
                $unique[$id] = true;
            }
        }
        if ($unique === []) {
            return [];
        }
        $out = [];
        foreach (MediaAsset::query()->whereIn('id', array_keys($unique))->where('kind', 'IMAGE')->get(['filename', 'alt']) as $r) {
            if (trim(Locales::pick($r->alt, 'it')) === '') {
                $out[] = (string) $r->filename;
            }
        }

        return $out;
    }

    /** @param list<string> $files */
    public static function missingAlt(array $files): never
    {
        self::fail('VALIDATION', 'Add alt text (Italian) to these images in the Media library before publishing: '
            .implode(', ', array_slice($files, 0, 5)).(count($files) > 5 ? '…' : ''));
    }

    /**
     * Referenced media ids must exist and be of the right kind.
     *
     * @param  list<array{id: ?string, kind: string, field: string}>  $refs
     * @return array<string, list<string>>
     */
    public static function assertMediaKinds(array $refs): array
    {
        $wanted = array_values(array_filter($refs, fn (array $r): bool => is_string($r['id']) && $r['id'] !== ''));
        if ($wanted === []) {
            return [];
        }
        $kinds = MediaAsset::query()
            ->whereIn('id', array_values(array_unique(array_column($wanted, 'id'))))
            ->pluck('kind', 'id')
            ->map(fn (mixed $k): string => $k instanceof \BackedEnum ? (string) $k->value : (string) $k)
            ->all();
        $errors = [];
        foreach ($wanted as $r) {
            $k = $kinds[$r['id']] ?? null;
            if ($k === null) {
                $errors[$r['field']] = ['This file no longer exists. Choose another one.'];
            } elseif ($k !== $r['kind']) {
                $errors[$r['field']] = [$r['kind'] === 'IMAGE' ? 'Choose an image.' : 'Choose a PDF document.'];
            }
        }

        return $errors;
    }

    /**
     * Referenced rows (category, project, author …) must still exist: a colleague may have deleted one
     * while this editor was open. Without this the save fails on the foreign key with a generic 500.
     *
     * @param  list<array{id: ?string, model: class-string<Model>, field: string, label: string}>  $refs
     * @return array<string, list<string>>
     */
    public static function assertReferences(array $refs): array
    {
        $errors = [];
        foreach ($refs as $r) {
            if (! is_string($r['id']) || $r['id'] === '') {
                continue;
            }
            if (! $r['model']::query()->whereKey($r['id'])->exists()) {
                $errors[$r['field']] = ["This {$r['label']} no longer exists. Choose another one."];
            }
        }

        return $errors;
    }

    /** @param array<string, list<string>> $errors */
    public static function throwIfErrors(array $errors, string $message = 'Please check the highlighted fields.'): void
    {
        if ($errors !== []) {
            self::fail('VALIDATION', $message, $errors);
        }
    }

    /** "Moved to drafts" etc. for status changes. */
    /** A published item with a future publication date is not public yet: say so instead of "live". */
    public static function statusMessage(string $status, ?DateTimeInterface $publishedAt = null): string
    {
        if (self::isScheduled($status, $publishedAt)) {
            return 'Scheduled — it goes live on '.self::scheduleLabel($publishedAt);
        }

        return $status === 'PUBLISHED' ? 'Published' : ($status === 'ARCHIVED' ? 'Archived' : 'Moved to drafts');
    }

    /** Message after "save": draft, live, or scheduled for a future publication date. */
    public static function savedMessage(string $status, ?DateTimeInterface $publishedAt = null): string
    {
        if (self::isScheduled($status, $publishedAt)) {
            return 'Saved — scheduled, not on the website until '.self::scheduleLabel($publishedAt);
        }

        return $status === 'PUBLISHED' ? 'Saved — live on the site' : 'Draft saved';
    }

    private static function scheduleLabel(?DateTimeInterface $at): string
    {
        return $at === null ? '' : Carbon::instance($at)->setTimezone('Europe/Rome')->format('j M Y, H:i').' (Italian time)';
    }

    public static function statusAuditAction(string $status): AuditAction
    {
        return match ($status) {
            'PUBLISHED' => AuditAction::CONTENT_PUBLISHED,
            'ARCHIVED' => AuditAction::CONTENT_ARCHIVED,
            default => AuditAction::CONTENT_UNPUBLISHED,
        };
    }

    public static function notFound(string $message): never
    {
        self::fail('NOT_FOUND', $message);
    }

    /** Unique-key violation (duplicate slug that raced the pre-check). */
    public static function isUniqueViolation(\Throwable $e): bool
    {
        return $e instanceof UniqueConstraintViolationException
            || ($e instanceof QueryException && (string) ($e->errorInfo[1] ?? '') === '1062');
    }

    public static function abort(int $status): never
    {
        throw new HttpException($status);
    }
}
