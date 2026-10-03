<?php

declare(strict_types=1);

namespace App\Auth;

use App\Enums\Role;

/**
 * Role → capability matrix, a 1:1 port of sakila/lib/permissions/index.ts (PLAN.md §7).
 * The admin UI receives `capabilitiesFor()` to hide controls, but EVERY controller re-checks
 * with the `capability:` middleware or Gate (`$this->authorize('pages.publish')`).
 */
final class Permissions
{
    /** @var list<string> */
    public const CAPABILITIES = [
        'dashboard.view',
        'users.manage',
        'settings.manage',
        'navigation.manage',
        'pages.view',
        'pages.editDraft',
        'pages.create',
        'pages.publish',
        'pages.delete',
        'content.create',
        'content.editOwn',
        'content.editAll',
        'content.publish',
        'content.delete',
        'media.upload',
        'media.edit',
        'media.delete',
        'submissions.read',
        'submissions.manage',
        'donations.read',
        'donations.manage',
        'audit.read',
    ];

    /** @var array<string, string> */
    public const ROLE_LABELS = [
        'SUPER_ADMIN' => 'Super admin',
        'ADMIN' => 'Admin',
        'EDITOR' => 'Editor',
        'AUTHOR' => 'Author',
    ];

    private const EDITOR = [
        'dashboard.view', 'navigation.manage', 'pages.view', 'pages.editDraft', 'pages.create', 'pages.publish',
        'content.create', 'content.editOwn', 'content.editAll', 'content.publish', 'content.delete',
        'media.upload', 'media.edit', 'media.delete', 'submissions.read', 'audit.read',
    ];

    private const AUTHOR = [
        'dashboard.view', 'pages.view', 'pages.editDraft', 'content.create', 'content.editOwn', 'media.upload', 'media.edit',
    ];

    private const RANK = ['SUPER_ADMIN' => 3, 'ADMIN' => 2, 'EDITOR' => 1, 'AUTHOR' => 0];

    public static function can(Role|string|null $role, string $capability): bool
    {
        $role = $role instanceof Role ? $role->value : $role;
        if ($role === null || ! isset(self::RANK[$role])) {
            return false;
        }

        return in_array($capability, self::granted($role), true);
    }

    /** @return list<string> */
    public static function capabilitiesFor(Role|string $role): array
    {
        $role = $role instanceof Role ? $role->value : $role;

        return array_values(array_filter(self::CAPABILITIES, fn (string $c): bool => self::can($role, $c)));
    }

    /** Editors+ may edit everything; authors only their own items. */
    public static function canEditContent(string $actorId, Role|string $actorRole, ?string $ownerId): bool
    {
        if (self::can($actorRole, 'content.editAll')) {
            return true;
        }

        return self::can($actorRole, 'content.editOwn') && $ownerId !== null && $ownerId !== '' && $ownerId === $actorId;
    }

    /** Only SUPER_ADMIN may grant SUPER_ADMIN; admins may assign ADMIN and below. */
    public static function canAssignRole(Role|string $actorRole, Role|string $targetRole): bool
    {
        $actor = $actorRole instanceof Role ? $actorRole->value : $actorRole;
        $target = $targetRole instanceof Role ? $targetRole->value : $targetRole;
        if (! self::can($actor, 'users.manage') || ! isset(self::RANK[$target])) {
            return false;
        }
        if ($target === 'SUPER_ADMIN') {
            return $actor === 'SUPER_ADMIN';
        }

        return self::RANK[$actor] >= self::RANK[$target];
    }

    /** Nobody manages a higher-ranked user; an ADMIN cannot manage a SUPER_ADMIN. */
    public static function canManageUser(Role|string $actorRole, Role|string $targetRole): bool
    {
        $actor = $actorRole instanceof Role ? $actorRole->value : $actorRole;
        $target = $targetRole instanceof Role ? $targetRole->value : $targetRole;
        if (! self::can($actor, 'users.manage') || ! isset(self::RANK[$target])) {
            return false;
        }
        if ($target === 'SUPER_ADMIN' && $actor !== 'SUPER_ADMIN') {
            return false;
        }

        return self::RANK[$actor] >= self::RANK[$target];
    }

    /** @return list<string> */
    private static function granted(string $role): array
    {
        return match ($role) {
            'SUPER_ADMIN', 'ADMIN' => self::CAPABILITIES,
            'EDITOR' => self::EDITOR,
            'AUTHOR' => self::AUTHOR,
            default => [],
        };
    }
}
