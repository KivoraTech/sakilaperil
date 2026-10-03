<?php

declare(strict_types=1);

/*
 * Site settings, one entry per Settings screen section (Admin → Settings).
 * Facts come from the client's documents and flyers (assets/). A value the client has not
 * supplied yet is written as "[[TODO: …]]": the public site hides it and
 * `php artisan sakila:content-check` lists it.
 */
return [
    'organization' => [
        'orgName' => 'Sakila per il Nepal',
        'organization' => [
            'legalName' => 'Sakila per il Nepal ODV',
            'taxCode' => '93059310164',
            'registrationNumber' => 'RUNTS n. 147002',
            'registeredOffice' => '[[TODO: sede legale (indirizzo completo)]]',
        ],
        'features' => [
            'demoContent' => false,
            'fiveXMille' => ['enabled' => true, 'taxCode' => '93059310164'],
        ],
    ],

    'contact' => [
        'contact' => [
            // From the Play for Nepal leaflet and the flood fundraiser flyer.
            'email' => 'sakilaperilnepal2023@gmail.com',
            'phone' => '+39 392 812 2361',
        ],
        'socialLinks' => [],
    ],

    'donations' => [
        'bankDetails' => [
            // IBAN and bank as printed on the fundraiser flyer; the holder is assumed to be the association.
            'accountHolder' => 'Sakila per il Nepal ODV',
            'iban' => 'IT88V0844053642000000174576',
            'bankName' => 'Banca di Credito Cooperativo Carate e Treviglio, filiale Sud',
            'reference' => ['it' => 'Erogazione liberale', 'en' => 'Donation'],
        ],
        'nepalPayments' => new stdClass,
        'donation' => [
            'currency' => 'EUR',
            'presetAmounts' => [10, 25, 50, 100],
            'defaultAmount' => 25,
            'impactNotes' => [],
        ],
    ],

    // No figures until the association supplies real, documented ones.
    'impact' => ['impactStats' => []],

    'seo' => [
        'seo' => [
            'titleTemplate' => ['it' => '%s · Sakila per il Nepal', 'en' => '%s · Sakila per il Nepal'],
            'defaultTitle' => [
                'it' => 'Sakila per il Nepal — Organizzazione di volontariato',
                'en' => 'Sakila per il Nepal — Volunteer organisation for Nepal',
            ],
            'defaultDescription' => [
                'it' => 'Organizzazione di volontariato con sede in Italia: aiuto alle famiglie nepalesi in difficoltà, lavoro per le donne, emergenze e cultura del Nepal.',
                'en' => 'A volunteer organisation based in Italy: help for Nepali families in need, work for women, emergency relief and the culture of Nepal.',
            ],
            'ogImageId' => '@media:hero',
        ],
        'analytics' => new stdClass,
    ],

    'appearance' => [
        'appearance' => [
            'headerStyle' => 'transparent-on-hero', 'enableMotion' => true, 'accent' => 'sindoor',
            'logoImageId' => '@media:logo', 'faviconImageId' => '@media:logo',
        ],
    ],

    'messages' => [
        'formMessages' => [
            'contact' => ['success' => ['it' => 'Grazie! Ti risponderemo al più presto.', 'en' => 'Thank you! We will get back to you soon.']],
            'volunteer' => ['success' => ['it' => 'Grazie per la tua disponibilità! Ti contatteremo presto.', 'en' => 'Thank you for volunteering! We will contact you soon.']],
            'membership' => ['success' => ['it' => 'Grazie! Abbiamo ricevuto la tua richiesta di adesione.', 'en' => 'Thank you! We received your membership request.']],
            'partner' => ['success' => ['it' => 'Grazie! Ti contatteremo per parlarne insieme.', 'en' => 'Thank you! We will contact you to talk it through.']],
            'newsletter' => ['success' => ['it' => "Grazie! Controlla la tua email per confermare l'iscrizione.", 'en' => 'Thank you! Check your email to confirm your subscription.']],
        ],
    ],
];
