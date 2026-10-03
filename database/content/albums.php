<?php

declare(strict_types=1);

/*
 * Gallery albums. `items` are photos from media.php with an optional caption.
 * `publishedAt` is fixed (the day this dataset was prepared), so re-loading never changes it.
 * The posters are images of text: what they say (date, place, programme, groups) is written out on
 * the event page /events/play-for-nepal-2026, and the album description links the two.
 */
$published = '2026-10-03T09:00:00+02:00';
$photo = fn (string $key, string $it = '', string $en = ''): array => [
    'key' => $key, 'type' => 'PHOTO', 'mediaAssetId' => '@media:'.$key,
    'videoProvider' => null, 'videoId' => null, 'captionIt' => $it, 'captionEn' => $en,
];

return [
    [
        'key' => 'alluvione',
        'coverImageId' => '@media:flood-distribution',
        'projectId' => '@project:alluvione',
        'takenAt' => '',
        'position' => 0,
        'publishedAt' => $published,
        'items' => [
            $photo('flood-distribution', 'La consegna di abbigliamento nuovo', 'Handing out new clothing'),
            $photo('flood-sacks', "I sacchi di aiuti con il logo dell'associazione", "Sacks of aid with the association's logo"),
            $photo('flood-onsite', 'Tra gli abitanti durante la distribuzione', 'Among local people during the distribution'),
            $photo('flood-vehicle', 'Il fuoristrada con il materiale di soccorso', 'The vehicle carrying the relief materials'),
            $photo('flood-road', 'Lungo la strada', 'Along the road'),
            $photo('flood-valley', "La valle dopo l'alluvione", 'The valley after the flood'),
            $photo('flood-car', "Il logo dell'associazione", "The association's logo"),
        ],
        'translations' => [
            'it' => [
                'title' => "Aiuti per l'alluvione 2026",
                'slug' => 'aiuti-alluvione-2026',
                'description' => "La raccolta fondi per l'alluvione del 26 agosto 2026 e la consegna in Nepal dei beni acquistati.",
            ],
            'en' => [
                'enabled' => true,
                'title' => 'Flood relief 2026',
                'slug' => 'flood-relief-2026',
                'description' => 'The fundraiser for the flood of 26 August 2026 and the delivery in Nepal of the goods bought.',
            ],
        ],
    ],

    [
        'key' => 'play-for-nepal',
        'coverImageId' => '@media:pfn-gymnastics',
        'projectId' => null,
        'takenAt' => '2026-05-16T10:00:00+02:00',
        'position' => 1,
        'publishedAt' => $published,
        'items' => [
            $photo('pfn-gymnastics', 'Ginnastica artistica e ritmica con Ginnastica Trevicass', 'Artistic and rhythmic gymnastics with Ginnastica Trevicass'),
            $photo('pfn-programme', 'Il programma della giornata', "The day's programme"),
            $photo('pfn-tournament', 'Il torneo per esordienti under 13: dodici squadre in tre gironi, finali alle 17.00', 'The under-13 tournament: twelve teams in three groups, finals at 17:00'),
            $photo('pfn-date', 'La locandina: sabato 16 maggio, Centro Sportivo Comunale di Bariano', 'The poster: Saturday 16 May, Bariano municipal sports centre'),
            $photo('pfn-leaflet', 'Il pieghevole con le realtà che hanno collaborato', 'The leaflet with the organisations that took part'),
        ],
        'translations' => [
            'it' => [
                'title' => 'Play for Nepal 2026',
                'slug' => 'play-for-nepal-2026',
                'description' => 'Locandine e programma di Play for Nepal, 16 maggio 2026 a Bariano (BG). Il programma completo, in testo, è nella pagina dell\'evento.',
            ],
            'en' => [
                'enabled' => true,
                'title' => 'Play for Nepal 2026',
                'slug' => 'play-for-nepal-2026',
                'description' => 'Posters and programme of Play for Nepal, 16 May 2026 in Bariano (Bergamo). The full programme, as text, is on the event page.',
            ],
        ],
    ],

    [
        'key' => 'lavoro',
        'coverImageId' => '@media:work-grain',
        'projectId' => '@project:lavoro',
        'takenAt' => '',
        'position' => 2,
        'publishedAt' => $published,
        'items' => [
            $photo('work-grain'),
            $photo('work-spices'),
        ],
        'translations' => [
            'it' => [
                'title' => 'Il lavoro delle donne',
                'slug' => 'il-lavoro-delle-donne',
                'description' => 'Immagini dal progetto di lavoro per donne e famiglie in difficoltà.',
            ],
            'en' => [
                'enabled' => true,
                'title' => "Women's work",
                'slug' => 'womens-work',
                'description' => 'Images from the work project for women and families in need.',
            ],
        ],
    ],
];
