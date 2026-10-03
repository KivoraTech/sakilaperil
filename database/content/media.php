<?php

declare(strict_types=1);

/*
 * Photo manifest. Files live in database/content/photos/ and are imported through the normal
 * upload pipeline (orientation fixed, EXIF/GPS removed). `key` is how pages.php, projects.php…
 * refer to a photo ("@media:key"). Every photo needs an Italian description (alt text).
 * Optional: `focalX`/`focalY` (0–1), the point that must stay visible when the photo is cropped.
 *
 * Source: the client's material (assets/), except `hero` (the Himalaya background the client asked to keep).
 */
return [
    [
        // Not the client's photo: the client asked for a Nepal mountain background and did not send one.
        // Wikimedia Commons, "Mt. Annapurna Sunrise" by Shedeur, CC BY-SA 4.0 (attribution required).
        'key' => 'hero',
        'file' => 'annapurna-alba.jpg',
        'alt' => ['it' => "Alba sulla catena dell'Annapurna, in Nepal", 'en' => 'Sunrise over the Annapurna range, Nepal'],
        'credit' => 'Shedeur / Wikimedia Commons, CC BY-SA 4.0',
    ],

    [
        // The heart mark cut from the client's logo (transparent background). Used in the header and footer
        // and as the browser icon; it can be replaced in Admin → Settings → Look & feel.
        'key' => 'logo',
        'file' => 'logo-sakila-per-il-nepal.png',
        'alt' => ['it' => "Logo dell'associazione Sakila per il Nepal", 'en' => 'Logo of the Sakila per il Nepal association'],
    ],

    // ── Flood relief, 2026 ──────────────────────────────────────────────
    [
        'key' => 'flood-distribution',
        'file' => 'alluvione-distribuzione.jpg',
        'alt' => [
            'it' => "Consegna di abbigliamento nuovo alle persone colpite dall'alluvione",
            'en' => 'New clothing being handed out to people affected by the flood',
        ],
    ],
    [
        'key' => 'flood-sacks',
        'file' => 'alluvione-sacchi-aiuti.jpg',
        'alt' => [
            'it' => "Una volontaria accanto ai sacchi di aiuti con il logo dell'associazione, pronti per la consegna",
            'en' => "A volunteer beside sacks of aid marked with the association's logo, ready for delivery",
        ],
    ],
    [
        'key' => 'flood-onsite',
        'file' => 'alluvione-sakila-sul-posto.jpg',
        'alt' => [
            'it' => "Una volontaria dell'associazione tra gli abitanti durante la distribuzione degli aiuti",
            'en' => 'A volunteer of the association among local people during the distribution of aid',
        ],
    ],
    [
        'key' => 'flood-valley',
        'file' => 'alluvione-valle.jpg',
        'alt' => [
            'it' => "Il letto del fiume e i versanti segnati dall'alluvione, visti tra gli alberi",
            'en' => 'The riverbed and hillsides scarred by the flood, seen through the trees',
        ],
    ],
    [
        'key' => 'flood-vehicle',
        'file' => 'alluvione-veicolo-striscione.jpg',
        'alt' => [
            'it' => 'Fuoristrada con lo striscione «Flood Relief Materials» e il logo di Sakila per il Nepal',
            'en' => 'Off-road vehicle with a "Flood Relief Materials" banner and the Sakila per il Nepal logo',
        ],
    ],
    [
        'key' => 'flood-car',
        'file' => 'alluvione-auto-logo.jpg',
        'alt' => [
            'it' => "Portiera di un'auto con l'adesivo dell'associazione Sakila per il Nepal",
            'en' => 'Car door with the Sakila per il Nepal association sticker',
        ],
    ],
    [
        'key' => 'flood-road',
        'file' => 'alluvione-strada.jpg',
        'alt' => [
            'it' => "Strada di montagna invasa dall'acqua, con alcuni fuoristrada fermi",
            'en' => 'Mountain road covered in water, with off-road vehicles stopped',
        ],
    ],
    // The fundraiser flyer (raccolta-fondi-alluvione-volantino.jpg) is deliberately NOT published:
    // it is an image of text (its IBAN and phone numbers are on the Dona and Documenti pages as real
    // text) and it contains a press-style photo the association has to confirm it may publish
    // (gaps.php). The file stays in photos/; see retired-photos.php.

    // ── Work project ────────────────────────────────────────────────────
    [
        'key' => 'work-grain',
        'file' => 'lavoro-donne-cereali.jpg',
        'alt' => [
            'it' => 'Una donna setaccia a mano i semi in un grande cesto di bambù',
            'en' => 'A woman sifting seeds by hand in a large bamboo basket',
        ],
    ],
    [
        'key' => 'work-spices',
        'file' => 'lavoro-donne-spezie.jpg',
        'alt' => [
            'it' => 'Una donna seleziona le spezie stese ad asciugare su un telo',
            'en' => 'A woman sorting spices laid out to dry on a cloth',
        ],
    ],

    // ── Play for Nepal, 16 May 2026 ─────────────────────────────────────
    [
        'key' => 'pfn-leaflet',
        'file' => 'play-for-nepal-pieghevole.jpg',
        'alt' => [
            'it' => 'Pieghevole di Play for Nepal con luogo, data, contatti e i loghi delle realtà che hanno collaborato',
            'en' => 'Play for Nepal leaflet with venue, date, contacts and the logos of the organisations that took part',
        ],
    ],
    [
        'key' => 'pfn-programme',
        'file' => 'play-for-nepal-programma.jpg',
        'alt' => [
            'it' => 'Programma di Play for Nepal del 16 maggio al Centro Sportivo Comunale di Bariano',
            'en' => 'Programme of Play for Nepal on 16 May at the Bariano municipal sports centre',
        ],
    ],
    [
        'key' => 'pfn-date',
        'file' => 'play-for-nepal-16-maggio.jpg',
        // Tall poster used as the news cover: keep the "16 MAGGIO" title in a landscape crop.
        'focalX' => 0.5, 'focalY' => 0.28,
        'alt' => [
            'it' => "Locandina «16 maggio» di Play for Nepal, organizzato dall'associazione Sakila per il Nepal",
            'en' => '"16 May" poster for Play for Nepal, organised by the Sakila per il Nepal association',
        ],
    ],
    [
        'key' => 'pfn-gymnastics',
        'file' => 'play-for-nepal-ginnastica.jpg',
        // Used as the event cover: keep the "Play for Nepal" title in a landscape crop.
        'focalX' => 0.5, 'focalY' => 0.33,
        'alt' => [
            'it' => 'Locandina di Play for Nepal: evento solidale di ginnastica artistica e ritmica con Ginnastica Trevicass',
            'en' => 'Play for Nepal poster: charity artistic and rhythmic gymnastics event with Ginnastica Trevicass',
        ],
    ],
    [
        'key' => 'pfn-tournament',
        'file' => 'play-for-nepal-torneo-under-13.jpg',
        'alt' => [
            'it' => 'Locandina del torneo per esordienti under 13 di Play for Nepal, con gironi e orari delle partite',
            'en' => 'Poster of the Play for Nepal under-13 tournament, with groups and match times',
        ],
    ],
];
