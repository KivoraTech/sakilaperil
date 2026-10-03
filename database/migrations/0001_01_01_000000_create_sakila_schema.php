<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The complete Sakila data model, byte-for-byte the MySQL DDL of the reference app
 * (sakila/prisma/migrations/20261002164328_init, ADR-010), so data can move between the
 * Next.js and Laravel apps unchanged (ADR-012). Later schema changes are normal Laravel migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sql = file_get_contents(database_path('schema/sakila-mysql-init.sql'));
        if ($sql === false) {
            throw new RuntimeException('database/schema/sakila-mysql-init.sql is missing');
        }
        foreach (self::statements($sql) as $statement) {
            DB::unprepared($statement);
        }
    }

    public function down(): void
    {
        $sql = (string) file_get_contents(database_path('schema/sakila-mysql-init.sql'));
        preg_match_all('/CREATE TABLE `([a-z_]+)`/', $sql, $m);
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (array_reverse($m[1]) as $table) {
            DB::statement("DROP TABLE IF EXISTS `{$table}`");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /** @return list<string> */
    private static function statements(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? '';

        return array_values(array_filter(array_map('trim', explode(";\n", $sql)), fn (string $s): bool => $s !== ''));
    }
};
