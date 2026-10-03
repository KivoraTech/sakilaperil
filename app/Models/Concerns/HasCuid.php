<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\Cuid;
use Illuminate\Database\Eloquent\Model;

/** Fills a Prisma-compatible cuid primary key on create when none was given. */
trait HasCuid
{
    public static function bootHasCuid(): void
    {
        static::creating(function (Model $model): void {
            $key = $model->getKeyName();
            if (empty($model->getAttribute($key))) {
                $model->setAttribute($key, Cuid::make());
            }
        });
    }
}
