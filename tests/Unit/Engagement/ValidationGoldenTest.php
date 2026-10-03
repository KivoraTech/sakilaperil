<?php

declare(strict_types=1);

use App\Engagement\Forms\FormSchemas;
use App\Engagement\Payments\DonationSchema;

/*
 * The PHP validators must answer exactly like the reference's Zod schemas (same success, same
 * transformed output, same fieldErrors keys and messages in the same order).
 * Fixtures: npx tsx tests/Fixtures/engagement/generate-golden.ts
 */
$golden = json_decode((string) file_get_contents(__DIR__.'/../../Fixtures/engagement/validation-golden.json'), true, flags: JSON_THROW_ON_ERROR);

$cases = [];
foreach ($golden as $schema => $list) {
    foreach ($list as $i => $case) {
        $cases["{$schema} #{$i}"] = [$schema, $case];
    }
}

it('matches Zod', function (string $schema, array $case): void {
    $input = $case['input'];
    $result = $schema === 'donation' ? DonationSchema::parse($input) : FormSchemas::parse($schema, $input);

    expect($result->ok())->toBe($case['success'], json_encode($result->fieldErrors, JSON_UNESCAPED_UNICODE) ?: '');
    if ($case['success']) {
        expect($result->data)->toBe($case['data']);
    } else {
        expect($result->fieldErrors)->toBe($case['fieldErrors']);
    }
})->with($cases);
