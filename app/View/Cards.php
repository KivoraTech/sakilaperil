<?php

declare(strict_types=1);

namespace App\View;

/** Shared constants for the card partials (components/site/cards/*). */
final class Cards
{
    /** project-card.tsx stageTone. */
    public const STAGE_TONE = [
        'ACTIVE' => ['key' => 'ongoing', 'dot' => 'bg-sage-700'],
        'COMPLETED' => ['key' => 'completed', 'dot' => 'bg-himalaya'],
        'PLANNED' => ['key' => 'planned', 'dot' => 'bg-marigold-700'],
    ];

    /** Team.tsx initials(): first letters of the first two words, upper-cased. */
    public static function initials(string $name): string
    {
        $parts = array_slice(preg_split('/\s+/u', $name) ?: [], 0, 2);

        return mb_strtoupper(implode('', array_map(fn (string $p): string => mb_substr($p, 0, 1), $parts)));
    }
}
