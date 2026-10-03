<?php

declare(strict_types=1);

namespace App\Models;

use App\Auth\UserAccounts;
use App\Enums\Role;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;

/**
 * Table `users` — generated from prisma/schema.prisma (model User).
 *
 * The password hash lives in `accounts` (providerId "credential"), exactly like the Better Auth
 * schema of the reference app; see App\Auth\AccountUserProvider.
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property bool $emailVerified
 * @property string|null $image
 * @property Role $role
 * @property bool $isActive
 * @property Carbon|null $lastLoginAt
 * @property Carbon $createdAt
 * @property Carbon $updatedAt
 */
class User extends Authenticatable
{
    use HasCuid;

    protected $table = 'users';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $guarded = [];

    /** DATETIME(3) columns, stored in UTC like the reference app. */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    /** No remember-me tokens (sessions only). */
    protected $rememberTokenName = '';

    /** The credential hash lives in `accounts` (used by the session guard and password checks). */
    public function getAuthPassword(): string
    {
        return (string) UserAccounts::passwordHash((string) $this->id);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'emailVerified' => 'boolean',
            'role' => Role::class,
            'isActive' => 'boolean',
            'lastLoginAt' => 'datetime',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    /** @return HasMany<Session, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class, 'userId', 'id');
    }

    /** @return HasMany<Account, $this> */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class, 'userId', 'id');
    }

    /** @return HasMany<AuditLog, $this> */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'userId', 'id');
    }

    /** @return HasMany<MediaAsset, $this> */
    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class, 'uploadedById', 'id');
    }

    /** @return HasMany<Page, $this> */
    public function pagesCreated(): HasMany
    {
        return $this->hasMany(Page::class, 'createdById', 'id');
    }

    /** @return HasMany<Page, $this> */
    public function pagesUpdated(): HasMany
    {
        return $this->hasMany(Page::class, 'updatedById', 'id');
    }

    /** @return HasMany<PageRevision, $this> */
    public function pageRevisions(): HasMany
    {
        return $this->hasMany(PageRevision::class, 'createdById', 'id');
    }

    /** @return HasMany<NewsPost, $this> */
    public function newsPosts(): HasMany
    {
        return $this->hasMany(NewsPost::class, 'authorId', 'id');
    }

    /** @return HasMany<Project, $this> */
    public function projectsCreated(): HasMany
    {
        return $this->hasMany(Project::class, 'createdById', 'id');
    }

    /** @return HasMany<Event, $this> */
    public function eventsCreated(): HasMany
    {
        return $this->hasMany(Event::class, 'createdById', 'id');
    }

    /** @return HasMany<VolunteerApplication, $this> */
    public function volunteerHandled(): HasMany
    {
        return $this->hasMany(VolunteerApplication::class, 'handledById', 'id');
    }

    /** @return HasMany<MembershipApplication, $this> */
    public function membershipHandled(): HasMany
    {
        return $this->hasMany(MembershipApplication::class, 'handledById', 'id');
    }

    /** @return HasMany<PartnerInquiry, $this> */
    public function partnerHandled(): HasMany
    {
        return $this->hasMany(PartnerInquiry::class, 'handledById', 'id');
    }

    /** @return HasMany<ContactSubmission, $this> */
    public function contactHandled(): HasMany
    {
        return $this->hasMany(ContactSubmission::class, 'handledById', 'id');
    }

    /** @return HasMany<PagePreviewDraft, $this> */
    public function pagePreviewDrafts(): HasMany
    {
        return $this->hasMany(PagePreviewDraft::class, 'userId', 'id');
    }
}
