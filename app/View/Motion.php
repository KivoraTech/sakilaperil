<?php

declare(strict_types=1);

namespace App\View;

/** Helpers for components/site/motion/split-text.tsx. */
final class Motion
{
    /**
     * `(text ?? "").split(/\s+/).filter(Boolean)`
     *
     * @return list<string>
     */
    public static function words(?string $text): array
    {
        return array_values(array_filter(preg_split('/\s+/u', $text ?? '') ?: [], static fn (string $w): bool => $w !== ''));
    }

    /** `Number((i / total).toFixed(3))` as JS prints it. */
    public static function wordProgress(int $i, int $total): string
    {
        return Html::num((float) Brand::toFixed($i / $total, 3));
    }
}
