<?php

declare(strict_types=1);

namespace App\View;

/** components/site/blocks/Contact.tsx helpers. */
final class Contact
{
    /**
     * OpenStreetMap embed/open URLs from coordinates, else the configured embed URL, else null.
     *
     * @param  array<string, mixed>  $contact
     * @return array{embed: string, open: string}|null
     */
    public static function map(array $contact): ?array
    {
        $lat = $contact['latitude'] ?? null;
        $lng = $contact['longitude'] ?? null;
        if (is_numeric($lat) && is_numeric($lng)) {
            $lat = (float) $lat;
            $lng = (float) $lng;
            $d = 0.01;
            $bbox = implode(',', array_map(fn (float $n): string => Brand::toFixed($n, 5), [$lng - $d, $lat - $d * 0.6, $lng + $d, $lat + $d * 0.6]));
            $la = Html::num($lat);
            $lo = Html::num($lng);

            return [
                'embed' => "https://www.openstreetmap.org/export/embed.html?bbox={$bbox}&layer=mapnik&marker={$la},{$lo}",
                'open' => "https://www.openstreetmap.org/?mlat={$la}&mlon={$lo}#map=16/{$la}/{$lo}",
            ];
        }
        if (! empty($contact['mapEmbedUrl']) && is_string($contact['mapEmbedUrl'])) {
            return ['embed' => $contact['mapEmbedUrl'], 'open' => $contact['mapEmbedUrl']];
        }

        return null;
    }
}
