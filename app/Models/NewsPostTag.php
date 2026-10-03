<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Table `news_post_tags` — generated from prisma/schema.prisma (model NewsPostTag).
 *
 * @property string $postId
 * @property string $tagId
 */
class NewsPostTag extends Model
{
    protected $table = 'news_post_tags';

    // Composite primary key (postId, tagId): use query builder for updates/deletes.
    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    /** DATETIME(3) columns, stored in UTC like the reference app. */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    /** @return BelongsTo<NewsPost, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(NewsPost::class, 'postId', 'id');
    }

    /** @return BelongsTo<NewsTag, $this> */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(NewsTag::class, 'tagId', 'id');
    }
}
