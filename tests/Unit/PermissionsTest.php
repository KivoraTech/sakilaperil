<?php

declare(strict_types=1);

use App\Auth\Permissions as P;

it('mirrors the reference capability matrix', function () {
    expect(P::can('SUPER_ADMIN', 'users.manage'))->toBeTrue()
        ->and(P::can('ADMIN', 'donations.manage'))->toBeTrue()
        ->and(P::can('EDITOR', 'pages.publish'))->toBeTrue()
        ->and(P::can('EDITOR', 'settings.manage'))->toBeFalse()
        ->and(P::can('EDITOR', 'donations.read'))->toBeFalse()
        ->and(P::can('AUTHOR', 'pages.editDraft'))->toBeTrue()
        ->and(P::can('AUTHOR', 'pages.publish'))->toBeFalse()
        ->and(P::can(null, 'dashboard.view'))->toBeFalse()
        ->and(P::can('NOPE', 'dashboard.view'))->toBeFalse()
        ->and(count(P::capabilitiesFor('AUTHOR')))->toBe(7)
        ->and(count(P::capabilitiesFor('EDITOR')))->toBe(16);
});

it('guards role assignment and user management', function () {
    expect(P::canAssignRole('ADMIN', 'SUPER_ADMIN'))->toBeFalse()
        ->and(P::canAssignRole('SUPER_ADMIN', 'SUPER_ADMIN'))->toBeTrue()
        ->and(P::canAssignRole('ADMIN', 'EDITOR'))->toBeTrue()
        ->and(P::canAssignRole('EDITOR', 'AUTHOR'))->toBeFalse()
        ->and(P::canManageUser('ADMIN', 'SUPER_ADMIN'))->toBeFalse()
        ->and(P::canManageUser('ADMIN', 'ADMIN'))->toBeTrue()
        ->and(P::canEditContent('u1', 'AUTHOR', 'u1'))->toBeTrue()
        ->and(P::canEditContent('u1', 'AUTHOR', 'u2'))->toBeFalse()
        ->and(P::canEditContent('u1', 'EDITOR', 'u2'))->toBeTrue();
});
