<?php

declare(strict_types=1);

namespace App\Database;

use Illuminate\Database\MySqlConnection as BaseMySqlConnection;
use Illuminate\Database\Query\Grammars\MySqlGrammar;

/**
 * Every timestamp column in the reference schema is DATETIME(3) and models write
 * 'Y-m-d H:i:s.v'. Laravel's default query grammar formats DateTime BINDINGS as
 * 'Y-m-d H:i:s', dropping the milliseconds, so `publishedAt <= now()` hid an item for up to
 * a second after it was published (and `startsAt >= now` etc. were off the same way).
 * This connection keeps milliseconds in query bindings too.
 */
final class MySqlConnection extends BaseMySqlConnection
{
    protected function getDefaultQueryGrammar()
    {
        return new class($this) extends MySqlGrammar
        {
            public function getDateFormat()
            {
                return 'Y-m-d H:i:s.v';
            }
        };
    }
}
