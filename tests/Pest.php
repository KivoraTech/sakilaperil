<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Feature tests run against MySQL/MariaDB (`sakila_laravel_test`; the schema uses MySQL DDL), each
| test inside a transaction (RefreshDatabase migrates once per run).
*/
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');

/** Shared by several test files (parallel runs load each file in its own process). */
/** The Origin a browser on this app would send (test requests use APP_URL's host). */
function testOrigin(): string
{
    $u = parse_url((string) config('app.url'));

    return $u['scheme'].'://'.$u['host'].(isset($u['port']) ? ':'.$u['port'] : '');
}
