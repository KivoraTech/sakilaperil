<?php

declare(strict_types=1);

use App\Content\Real\Rt;

/*
 * Pages, as lists of page-builder sections (the same blocks the admin offers), in Italian and
 * English. `key` marks the system pages the site's routes rely on; `ref` is how menus.php
 * refers to a page ("@page:ref"). Links are written without the language ("/chi-siamo").
 *
 * Structure follows the client's brief (assets/Emailfromclient.md); texts follow the client's
 * documents. Sections with invented figures (impact numbers, testimonials, timeline, team)
 * were deliberately left out. No page ends with a newsletter block: the footer of every page
 * already carries the sign-up form, and two stacked forms looked like a mistake.
 *
 * `seoDescription`: one per page and language, unique, written for people (about 110–155
 * characters). `ogImage`: the photo shown when the page is shared (default: the Himalaya cover).
 */
$paper = ['background' => 'paper'];
$cream = ['background' => 'cream'];

// ── Reused sections ─────────────────────────────────────────────────────
$fiveIt = [
    'type' => 'cta',
    'settings' => ['anchor' => 'cinque-per-mille', 'background' => 'paper', 'tone' => 'marigold'],
    'content' => [
        'kicker' => '5 per mille',
        'heading' => 'Dona il tuo 5 per mille a Sakila per il Nepal',
        'body' => 'Non ti costa nulla. Nella dichiarazione dei redditi firma nel riquadro dedicato agli enti del Terzo settore e scrivi il nostro codice fiscale: 93059310164.',
        'primaryCta' => ['label' => 'Altri modi per aiutare', 'href' => '/partecipa'],
    ],
];
$fiveEn = [
    'type' => 'cta',
    'settings' => ['anchor' => 'cinque-per-mille', 'background' => 'paper', 'tone' => 'marigold'],
    'content' => [
        'kicker' => '5 per mille',
        'heading' => 'Give your 5 per mille to Sakila per il Nepal',
        'body' => 'If you pay taxes in Italy it costs you nothing: in your tax return, sign in the box for third-sector organisations and enter our tax code, 93059310164.',
        'primaryCta' => ['label' => 'Other ways to help', 'href' => '/get-involved'],
    ],
];
$partnerNames = [
    ['name' => 'Comune di Bariano (patrocinio)'],
    ['name' => 'Ginnastica Trevicass'],
    ['name' => 'U.S. Barianese'],
    ['name' => "L'Ulivo Cooperativa Sociale"],
    ['name' => 'Gruppo Alpini di Bariano'],
    ['name' => 'Ristorante nepalese Da Sakila'],
    ['name' => 'Margherita Conad, Arcene'],
    ['name' => 'Associazione Kora'],
    ['name' => 'Le Radici e Le Ali'],
];

return [
    // ═══════════════════════════════ HOME ═══════════════════════════════
    [
        'ref' => 'home', 'key' => 'home',
        'it' => [
            'title' => 'Home', 'slug' => 'home',
            'seoDescription' => 'Sakila per il Nepal ODV: aiuto alle famiglie nepalesi in difficoltà, lavoro per le donne, emergenze e cultura del Nepal.',
            'sections' => [
                ['type' => 'hero', 'settings' => ['background' => 'paper', 'height' => 'full', 'overlay' => 'dark'], 'content' => [
                    'kicker' => 'Organizzazione di volontariato · Italia ↔ Nepal',
                    'heading' => 'Insieme per le',
                    'headingAccent' => 'famiglie del Nepal.',
                    'subheading' => 'Portiamo aiuto alle famiglie nepalesi in difficoltà, creiamo opportunità di lavoro e facciamo conoscere la cultura del Nepal.',
                    'image' => ['assetId' => '@media:hero'],
                    'primaryCta' => ['label' => 'Dona ora', 'href' => '#dona'],
                    'secondaryCta' => ['label' => 'Scopri i progetti', 'href' => '/projects'],
                ]],
                ['type' => 'imageText', 'settings' => ['anchor' => 'chi-siamo', 'background' => 'paper', 'imagePosition' => 'right'], 'content' => [
                    'kicker' => 'Chi siamo',
                    'heading' => "Un'associazione nata per aiutare le fasce più deboli della società nepalese e per",
                    'headingAccent' => 'far conoscere la cultura del Nepal.',
                    'body' => "Anziani, bambini, mamme in difficoltà, famiglie in povertà: portiamo beni di prima necessità e, soprattutto, lavoro.\n\nSakila per il Nepal è un'organizzazione di volontariato con sede in Italia, fondata da Sakila Shrestha e iscritta al RUNTS.",
                    'image' => ['assetId' => '@media:flood-distribution'],
                    'secondaryImage' => ['assetId' => '@media:work-grain'],
                    'badgeText' => 'ITALIA ↔ NEPAL',
                    'primaryCta' => ['label' => "L'associazione", 'href' => '/chi-siamo'],
                    'secondaryCta' => ['label' => 'Documenti', 'href' => '/documenti'],
                ]],
                ['type' => 'partners', 'settings' => ['anchor' => 'cosa-facciamo', 'background' => 'cream'], 'content' => [
                    'kicker' => 'Cosa facciamo',
                    'heading' => 'Le nostre aree di impegno',
                    'body' => "Dallo statuto dell'associazione: aiuto concreto in Nepal, cultura nepalese e iniziative sul territorio in Italia.",
                    'partners' => [
                        ['name' => 'Aiuto alle fasce più deboli'],
                        ['name' => 'Lavoro e dignità'],
                        ['name' => 'Emergenze'],
                        ['name' => 'Cultura nepalese'],
                        ['name' => 'Viaggi per i soci'],
                        ['name' => 'Eventi sul territorio'],
                    ],
                    'cta' => ['label' => 'Scopri le attività', 'href' => '/attivita'],
                ]],
                ['type' => 'services', 'settings' => ['anchor' => 'progetti', 'background' => 'paper', 'layout' => 'grid'], 'content' => [
                    'kicker' => 'Dove va il tuo aiuto',
                    'heading' => 'I progetti',
                    'headingAccent' => 'attivi',
                    'lead' => "Il sostegno economico serve nell'emergenza; poi dev'essere un lavoro continuativo a garantire il sostentamento delle famiglie.",
                    'source' => 'featured', 'projectIds' => [], 'limit' => 4,
                ]],
                ['type' => 'imageText', 'settings' => ['anchor' => 'attivita', 'background' => 'cream', 'imagePosition' => 'left'], 'content' => [
                    'kicker' => 'Attività',
                    'heading' => 'Play for Nepal, bancarelle e',
                    'headingAccent' => 'viaggi alla scoperta del Nepal',
                    'body' => 'In Italia raccogliamo fondi con eventi sportivi, bancarelle di prodotti nepalesi e il 5 per mille. Ai soci proponiamo viaggi in Nepal alla scoperta della sua cultura.',
                    'image' => ['assetId' => '@media:pfn-gymnastics'],
                    'primaryCta' => ['label' => 'Tutte le attività', 'href' => '/attivita'],
                    'secondaryCta' => ['label' => 'Play for Nepal 2026', 'href' => '/events/play-for-nepal-2026'],
                ]],
                ['type' => 'gallery', 'settings' => ['anchor' => 'galleria', 'background' => 'paper', 'layout' => 'masonry'], 'content' => [
                    'kicker' => 'Galleria',
                    'heading' => "Dopo l'alluvione:",
                    'headingAccent' => 'la consegna degli aiuti',
                    'lead' => 'I beni acquistati in Nepal con la raccolta fondi, consegnati di persona.',
                    'source' => 'album', 'albumId' => '@album:alluvione', 'images' => [], 'limit' => 6,
                ]],
                ['type' => 'donateCta', 'settings' => ['anchor' => 'dona', 'background' => 'cream'], 'content' => [
                    'kicker' => 'Sostienici',
                    'heading' => 'Il tuo aiuto,',
                    'headingAccent' => 'consegnato di persona',
                    'lead' => 'Con le donazioni acquistiamo i beni direttamente in Nepal e sosteniamo progetti di lavoro. Puoi donare con un bonifico o con il tuo 5 per mille.',
                    'gifts' => [],
                    'reasons' => [
                        "I beni vengono acquistati in Nepal: un aiuto anche all'economia locale",
                        "La consegna è seguita personalmente dall'associazione",
                        'Organizzazione di volontariato iscritta al RUNTS (n. 147002)',
                    ],
                    'trustStats' => [],
                    'showWidget' => true,
                ]],
                $fiveIt,
                ['type' => 'newsList', 'settings' => ['anchor' => 'notizie', 'background' => 'paper'], 'content' => [
                    'kicker' => 'Notizie', 'heading' => 'Ultime', 'headingAccent' => 'notizie', 'limit' => 3, 'showAllLink' => true,
                ]],
                ['type' => 'partners', 'settings' => ['anchor' => 'partner', 'background' => 'paper'], 'content' => [
                    'kicker' => 'Amici e partner',
                    'heading' => 'Chi collabora con noi',
                    'body' => "Realtà sportive e non solo, che collaborano con l'associazione nella realizzazione degli eventi di raccolta fondi.",
                    'partners' => $partnerNames,
                    'cta' => ['label' => 'Diventa partner', 'href' => '/partecipa#partecipa'],
                ]],
            ],
        ],
        'en' => [
            'title' => 'Home', 'slug' => 'home',
            'seoDescription' => 'Sakila per il Nepal ODV: help for Nepali families in need, work for women, emergency relief and the culture of Nepal.',
            'sections' => [
                ['type' => 'hero', 'settings' => ['background' => 'paper', 'height' => 'full', 'overlay' => 'dark'], 'content' => [
                    'kicker' => 'Volunteer organisation · Italy ↔ Nepal',
                    'heading' => 'Together for the',
                    'headingAccent' => 'families of Nepal.',
                    'subheading' => 'We bring help to Nepali families in need, create job opportunities and share the culture of Nepal.',
                    'image' => ['assetId' => '@media:hero'],
                    'primaryCta' => ['label' => 'Donate now', 'href' => '#dona'],
                    'secondaryCta' => ['label' => 'See the projects', 'href' => '/projects'],
                ]],
                ['type' => 'imageText', 'settings' => ['anchor' => 'chi-siamo', 'background' => 'paper', 'imagePosition' => 'right'], 'content' => [
                    'kicker' => 'About us',
                    'heading' => 'An association created to help the most vulnerable people in Nepali society and to',
                    'headingAccent' => 'share the culture of Nepal.',
                    'body' => "The elderly, children, mothers in difficulty, families living in poverty: we bring essential goods and, above all, work.\n\nSakila per il Nepal is a volunteer organisation based in Italy, founded by Sakila Shrestha and registered with RUNTS, Italy's national third-sector register.",
                    'image' => ['assetId' => '@media:flood-distribution'],
                    'secondaryImage' => ['assetId' => '@media:work-grain'],
                    'badgeText' => 'ITALY ↔ NEPAL',
                    'primaryCta' => ['label' => 'The association', 'href' => '/about-us'],
                    'secondaryCta' => ['label' => 'Documents', 'href' => '/documents'],
                ]],
                ['type' => 'partners', 'settings' => ['anchor' => 'cosa-facciamo', 'background' => 'cream'], 'content' => [
                    'kicker' => 'What we do',
                    'heading' => 'Our areas of work',
                    'body' => "From the association's statute: practical help in Nepal, Nepali culture and local initiatives in Italy.",
                    'partners' => [
                        ['name' => 'Help for the most vulnerable'],
                        ['name' => 'Work and dignity'],
                        ['name' => 'Emergencies'],
                        ['name' => 'Nepali culture'],
                        ['name' => 'Trips for members'],
                        ['name' => 'Local events'],
                    ],
                    'cta' => ['label' => 'See the activities', 'href' => '/activities'],
                ]],
                ['type' => 'services', 'settings' => ['anchor' => 'progetti', 'background' => 'paper', 'layout' => 'grid'], 'content' => [
                    'kicker' => 'Where your help goes',
                    'heading' => 'Active',
                    'headingAccent' => 'projects',
                    'lead' => 'Financial support is right in an emergency; after that it must be steady work that provides for families.',
                    'source' => 'featured', 'projectIds' => [], 'limit' => 4,
                ]],
                ['type' => 'imageText', 'settings' => ['anchor' => 'attivita', 'background' => 'cream', 'imagePosition' => 'left'], 'content' => [
                    'kicker' => 'Activities',
                    'heading' => 'Play for Nepal, market stalls and',
                    'headingAccent' => 'trips to discover Nepal',
                    'body' => 'In Italy we raise funds with sports events, stalls selling Nepali products and the 5 per mille. For members we organise trips to Nepal to discover its culture.',
                    'image' => ['assetId' => '@media:pfn-gymnastics'],
                    'primaryCta' => ['label' => 'All activities', 'href' => '/activities'],
                    'secondaryCta' => ['label' => 'Play for Nepal 2026', 'href' => '/events/play-for-nepal-2026'],
                ]],
                ['type' => 'gallery', 'settings' => ['anchor' => 'galleria', 'background' => 'paper', 'layout' => 'masonry'], 'content' => [
                    'kicker' => 'Gallery',
                    'heading' => 'After the flood:',
                    'headingAccent' => 'delivering the aid',
                    'lead' => 'The goods bought in Nepal with the fundraiser, delivered in person.',
                    'source' => 'album', 'albumId' => '@album:alluvione', 'images' => [], 'limit' => 6,
                ]],
                ['type' => 'donateCta', 'settings' => ['anchor' => 'dona', 'background' => 'cream'], 'content' => [
                    'kicker' => 'Support us',
                    'heading' => 'Your help,',
                    'headingAccent' => 'delivered in person',
                    'lead' => 'With donations we buy goods directly in Nepal and support work projects. You can give by bank transfer or with your 5 per mille.',
                    'gifts' => [],
                    'reasons' => [
                        'Goods are bought in Nepal, which also helps the local economy',
                        'The association follows the delivery in person',
                        'Volunteer organisation registered with RUNTS (no. 147002)',
                    ],
                    'trustStats' => [],
                    'showWidget' => true,
                ]],
                $fiveEn,
                ['type' => 'newsList', 'settings' => ['anchor' => 'notizie', 'background' => 'paper'], 'content' => [
                    'kicker' => 'News', 'heading' => 'Latest', 'headingAccent' => 'news', 'limit' => 3, 'showAllLink' => true,
                ]],
                ['type' => 'partners', 'settings' => ['anchor' => 'partner', 'background' => 'paper'], 'content' => [
                    'kicker' => 'Friends and partners',
                    'heading' => 'Who works with us',
                    'body' => 'Sports clubs and other organisations that work with the association to run the fundraising events.',
                    'partners' => $partnerNames,
                    'cta' => ['label' => 'Become a partner', 'href' => '/get-involved#partecipa'],
                ]],
            ],
        ],
    ],

    // ═════════════════════════════ CHI SIAMO ═════════════════════════════
    [
        'ref' => 'about', 'key' => 'about',
        'it' => [
            'title' => 'Chi siamo', 'slug' => 'chi-siamo',
            'seoDescription' => "Chi è Sakila per il Nepal ODV: un'organizzazione di volontariato fondata da Sakila Shrestha, con le finalità e le attività previste dallo statuto.",
            'ogImage' => '@media:flood-sacks',
            'sections' => [
                ['type' => 'imageText', 'settings' => ['background' => 'paper', 'imagePosition' => 'right'], 'content' => [
                    'kicker' => 'Chi siamo',
                    'heading' => 'Sakila per il Nepal ODV',
                    'body' => "Siamo un'organizzazione di volontariato con sede in Italia, fondata da Sakila Shrestha. Portiamo aiuto alle fasce deboli della società nepalese e facciamo conoscere la cultura del Nepal.\n\nL'associazione è iscritta al Registro Unico Nazionale del Terzo Settore (RUNTS) e all'albo delle associazioni del Comune di Treviglio (BG).",
                    'image' => ['assetId' => '@media:flood-sacks'],
                    'primaryCta' => ['label' => 'Documenti e dati', 'href' => '/documenti'],
                    'secondaryCta' => ['label' => 'Contatti', 'href' => '/contatti'],
                ]],
                ['type' => 'richText', 'settings' => ['anchor' => 'finalita', 'background' => 'cream', 'width' => 'normal'], 'content' => [
                    'kicker' => 'Dallo statuto · art. 3',
                    'heading' => 'Le nostre finalità',
                    'body' => Rt::doc(<<<'MD'
                        L'associazione persegue in particolare queste finalità:

                        - la diffusione della cultura nepalese;
                        - portare aiuto alle fasce deboli della società nepalese: beni di prima necessità, materiali e quanto altro possa servire per aiutare anziani, bambini, mamme in difficoltà, famiglie in stato di povertà, di difficoltà sanitaria o alimentare;
                        - la creazione di strutture, anche sanitarie;
                        - lo sviluppo culturale e civile e la solidarietà nei rapporti umani.
                        MD),
                ]],
                ['type' => 'richText', 'settings' => ['anchor' => 'attivita', 'background' => 'paper', 'width' => 'normal'], 'content' => [
                    'kicker' => 'Dallo statuto · art. 4',
                    'heading' => 'Come realizziamo i nostri scopi',
                    'body' => Rt::doc(<<<'MD'
                        - Organizzazione di attività culturali e sportive, corsi (compresi quelli di cucina nepalese), meditazione, yoga e conoscenza delle culture orientali.
                        - Organizzazione e partecipazione a fiere, eventi e iniziative del territorio, con prodotti tipici artigianali nepalesi.
                        - Collaborazione con le associazioni del territorio.
                        - Organizzazione di corsi di benessere psicofisico, attività culturali in genere e corsi di discipline orientali.
                        - Organizzazione di viaggi in Nepal alla scoperta della cultura nepalese, con la consegna del materiale raccolto o acquistato grazie alle iniziative organizzate.
                        - Iniziative, servizi, attività culturali, turistiche e ricreative per soddisfare le esigenze di conoscenza e di svago degli associati.

                        Vuoi vedere cosa facciamo in concreto? Guarda i [progetti](/projects) e le [attività](/attivita).
                        MD),
                ]],
            ],
        ],
        'en' => [
            'title' => 'About us', 'slug' => 'about-us',
            'seoDescription' => 'About Sakila per il Nepal ODV: a volunteer organisation founded by Sakila Shrestha, with the aims and activities set out in its statute.',
            'ogImage' => '@media:flood-sacks',
            'sections' => [
                ['type' => 'imageText', 'settings' => ['background' => 'paper', 'imagePosition' => 'right'], 'content' => [
                    'kicker' => 'About us',
                    'heading' => 'Sakila per il Nepal ODV',
                    'body' => "We are a volunteer organisation based in Italy, founded by Sakila Shrestha. We bring help to the most vulnerable people in Nepali society and share the culture of Nepal.\n\nThe association is registered with RUNTS, Italy's national third-sector register, and in the register of associations of the Municipality of Treviglio (Bergamo).",
                    'image' => ['assetId' => '@media:flood-sacks'],
                    'primaryCta' => ['label' => 'Documents and details', 'href' => '/documents'],
                    'secondaryCta' => ['label' => 'Contact', 'href' => '/contact'],
                ]],
                ['type' => 'richText', 'settings' => ['anchor' => 'finalita', 'background' => 'cream', 'width' => 'normal'], 'content' => [
                    'kicker' => 'From the statute · art. 3',
                    'heading' => 'Our aims',
                    'body' => Rt::doc(<<<'MD'
                        The association pursues these aims in particular:

                        - spreading Nepali culture;
                        - bringing help to the vulnerable groups of Nepali society: essential goods, materials and anything else that can help the elderly, children, mothers in difficulty, and families facing poverty, health problems or lack of food;
                        - creating facilities, including health facilities;
                        - cultural and civic development and solidarity in human relations.
                        MD),
                ]],
                ['type' => 'richText', 'settings' => ['anchor' => 'attivita', 'background' => 'paper', 'width' => 'normal'], 'content' => [
                    'kicker' => 'From the statute · art. 4',
                    'heading' => 'How we pursue our aims',
                    'body' => Rt::doc(<<<'MD'
                        - Organising cultural and sports activities, courses (including Nepali cooking), meditation, yoga and the discovery of Eastern cultures.
                        - Organising and taking part in fairs, events and local initiatives, with typical Nepali handicrafts.
                        - Working with local associations.
                        - Organising wellbeing courses, cultural activities in general and courses in Eastern disciplines.
                        - Organising trips to Nepal to discover Nepali culture and to deliver the materials collected or bought thanks to our initiatives.
                        - Initiatives, services and cultural, tourist and recreational activities for our members.

                        Want to see what we do in practice? Look at the [projects](/projects) and the [activities](/activities).
                        MD),
                ]],
            ],
        ],
    ],

    // ═════════════════════════════ ATTIVITÀ ══════════════════════════════
    [
        'ref' => 'activities',
        'it' => [
            'title' => 'Attività', 'slug' => 'attivita',
            'seoDescription' => 'Play for Nepal, raccolta fondi per l\'alluvione, bancarelle, 5 per mille e viaggi per i soci alla scoperta del Nepal.',
            'ogImage' => '@media:flood-distribution',
            'sections' => [
                ['type' => 'imageText', 'settings' => ['anchor' => 'play-for-nepal', 'background' => 'paper', 'imagePosition' => 'right'], 'content' => [
                    'kicker' => 'Attività',
                    'heading' => 'Play for Nepal',
                    'body' => "La prima edizione si è svolta sabato 16 maggio 2026 al Centro Sportivo Comunale di Bariano (BG), con il patrocinio del Comune di Bariano: tornei, esibizioni, musica e cucina nepalese, con una parte del ricavato a sostegno delle famiglie bisognose del Nepal.\n\nLa seconda edizione è già in programma, sempre a Bariano: pubblicheremo la data appena sarà fissata.",
                    'image' => ['assetId' => '@media:pfn-gymnastics'],
                    'primaryCta' => ['label' => 'Il programma della giornata', 'href' => '/events/play-for-nepal-2026'],
                    'secondaryCta' => ['label' => 'Le locandine', 'href' => '/gallery/play-for-nepal-2026'],
                ]],
                ['type' => 'imageText', 'settings' => ['anchor' => 'raccolta-fondi', 'background' => 'cream', 'imagePosition' => 'left'], 'content' => [
                    'kicker' => 'Emergenze',
                    'heading' => "Raccolta fondi per l'alluvione",
                    'body' => "Dopo l'alluvione del 26 agosto 2026 abbiamo raccolto fondi in Italia e acquistato in Nepal beni di prima necessità: abbigliamento nuovo, scarpe e giochi per i bambini, consegnati di persona.",
                    'image' => ['assetId' => '@media:flood-distribution'],
                    'primaryCta' => ['label' => 'Il progetto', 'href' => '/projects/aiuti-alluvione-agosto-2026'],
                    'secondaryCta' => ['label' => 'Le foto della consegna', 'href' => '/gallery/aiuti-alluvione-2026'],
                ]],
                ['type' => 'richText', 'settings' => ['anchor' => 'bancarelle', 'background' => 'paper', 'width' => 'normal'], 'content' => [
                    'kicker' => 'Sul territorio',
                    'heading' => 'Bancarelle',
                    'body' => Rt::doc(<<<'MD'
                        Partecipiamo a feste di paese, fiere e mercati con bancarelle di prodotti tipici artigianali nepalesi e con i prodotti delle realtà che collaborano al nostro [progetto di lavoro](/projects/lavoro-per-donne-e-famiglie).

                        Ogni occasione che dà visibilità a questi prodotti aiuta chi li ha realizzati a raggiungere l'autonomia.
                        MD),
                ]],
                ['type' => 'imageText', 'settings' => ['anchor' => 'viaggi', 'background' => 'cream', 'imagePosition' => 'right'], 'content' => [
                    'kicker' => 'Per i soci',
                    'heading' => 'Alla scoperta del Nepal',
                    'body' => "Organizziamo per i soci viaggi in Nepal alla scoperta della cultura nepalese: piccoli gruppi, strutture con costi convenzionati e visite accompagnate nei luoghi tipici della storia e della cultura del paese.\n\nDurante i viaggi viene consegnato il materiale raccolto o acquistato grazie alle nostre iniziative.",
                    'image' => ['assetId' => '@media:hero'],
                    'primaryCta' => ['label' => 'Diventa socio', 'href' => '/partecipa#socio'],
                    'secondaryCta' => ['label' => 'Chiedi informazioni', 'href' => '/contatti'],
                ]],
                $fiveIt,
            ],
        ],
        'en' => [
            'title' => 'Activities', 'slug' => 'activities',
            'seoDescription' => 'Play for Nepal, the flood fundraiser, market stalls, 5 per mille and trips for members to discover Nepal.',
            'ogImage' => '@media:flood-distribution',
            'sections' => [
                ['type' => 'imageText', 'settings' => ['anchor' => 'play-for-nepal', 'background' => 'paper', 'imagePosition' => 'right'], 'content' => [
                    'kicker' => 'Activities',
                    'heading' => 'Play for Nepal',
                    'body' => "The first edition took place on Saturday 16 May 2026 at the municipal sports centre in Bariano (Bergamo), under the patronage of the Municipality of Bariano: tournaments, shows, music and Nepali food, with part of the proceeds supporting families in need in Nepal.\n\nThe second edition is already planned, again in Bariano: we will publish the date as soon as it is set.",
                    'image' => ['assetId' => '@media:pfn-gymnastics'],
                    'primaryCta' => ['label' => "The day's programme", 'href' => '/events/play-for-nepal-2026'],
                    'secondaryCta' => ['label' => 'The posters', 'href' => '/gallery/play-for-nepal-2026'],
                ]],
                ['type' => 'imageText', 'settings' => ['anchor' => 'raccolta-fondi', 'background' => 'cream', 'imagePosition' => 'left'], 'content' => [
                    'kicker' => 'Emergencies',
                    'heading' => 'Flood fundraiser',
                    'body' => 'After the flood of 26 August 2026 we raised funds in Italy and bought essential goods in Nepal: new clothing, shoes and toys for children, delivered in person.',
                    'image' => ['assetId' => '@media:flood-distribution'],
                    'primaryCta' => ['label' => 'The project', 'href' => '/projects/flood-relief-august-2026'],
                    'secondaryCta' => ['label' => 'Photos of the delivery', 'href' => '/gallery/flood-relief-2026'],
                ]],
                ['type' => 'richText', 'settings' => ['anchor' => 'bancarelle', 'background' => 'paper', 'width' => 'normal'], 'content' => [
                    'kicker' => 'In our area',
                    'heading' => 'Market stalls',
                    'body' => Rt::doc(<<<'MD'
                        We take part in village fairs, trade fairs and markets with stalls of typical Nepali handicrafts and with the products of the businesses that take part in our [work project](/projects/work-for-women-and-families).

                        Every occasion that gives these products visibility helps the people who made them become self-sufficient.
                        MD),
                ]],
                ['type' => 'imageText', 'settings' => ['anchor' => 'viaggi', 'background' => 'cream', 'imagePosition' => 'right'], 'content' => [
                    'kicker' => 'For members',
                    'heading' => 'Discovering Nepal',
                    'body' => "For our members we organise trips to Nepal to discover Nepali culture: small groups, accommodation at agreed rates and guided visits to the places that tell the country's history and culture.\n\nDuring the trips we deliver the materials collected or bought thanks to our initiatives.",
                    'image' => ['assetId' => '@media:hero'],
                    'primaryCta' => ['label' => 'Become a member', 'href' => '/get-involved#socio'],
                    'secondaryCta' => ['label' => 'Ask for information', 'href' => '/contact'],
                ]],
                $fiveEn,
            ],
        ],
    ],

    // ═════════════════════════════ PARTECIPA ═════════════════════════════
    [
        'ref' => 'get-involved', 'key' => 'get-involved',
        'it' => [
            'title' => 'Partecipa', 'slug' => 'partecipa',
            'seoDescription' => 'Dona con un bonifico o con il 5 per mille, diventa socio, volontario o partner di Sakila per il Nepal.',
            'ogImage' => '@media:work-grain',
            'sections' => [
                ['type' => 'donateCta', 'settings' => ['anchor' => 'dona', 'background' => 'paper'], 'content' => [
                    'kicker' => 'Dona',
                    'heading' => 'Ogni gesto conta',
                    'lead' => 'Con le donazioni acquistiamo beni di prima necessità direttamente in Nepal e sosteniamo progetti di lavoro.',
                    'gifts' => [], 'reasons' => [], 'trustStats' => [], 'showWidget' => true,
                ]],
                $fiveIt,
                ['type' => 'involveForms', 'settings' => ['anchor' => 'partecipa', 'background' => 'paper'], 'content' => [
                    'kicker' => 'Partecipa',
                    'heading' => 'Diventa volontario, socio o partner',
                    'forms' => ['volunteer', 'membership', 'partner'],
                ]],
            ],
        ],
        'en' => [
            'title' => 'Get involved', 'slug' => 'get-involved',
            'seoDescription' => 'Give by bank transfer or with the 5 per mille, become a member, volunteer or partner of Sakila per il Nepal.',
            'ogImage' => '@media:work-grain',
            'sections' => [
                ['type' => 'donateCta', 'settings' => ['anchor' => 'dona', 'background' => 'paper'], 'content' => [
                    'kicker' => 'Donate',
                    'heading' => 'Every gesture counts',
                    'lead' => 'With donations we buy essential goods directly in Nepal and support work projects.',
                    'gifts' => [], 'reasons' => [], 'trustStats' => [], 'showWidget' => true,
                ]],
                $fiveEn,
                ['type' => 'involveForms', 'settings' => ['anchor' => 'partecipa', 'background' => 'paper'], 'content' => [
                    'kicker' => 'Get involved',
                    'heading' => 'Become a volunteer, member or partner',
                    'forms' => ['volunteer', 'membership', 'partner'],
                ]],
            ],
        ],
    ],

    // ═════════════════════════════ DOCUMENTI ═════════════════════════════
    [
        'ref' => 'documents', 'key' => 'transparency',
        'it' => [
            'title' => 'Documenti', 'slug' => 'documenti',
            'seoDescription' => "Finalità, dati dell'associazione, iscrizione al RUNTS, coordinate bancarie, adesione e contatti di Sakila per il Nepal ODV.",
            'sections' => [
                ['type' => 'richText', 'settings' => ['background' => 'paper', 'width' => 'normal'], 'content' => [
                    'kicker' => 'Documenti',
                    'heading' => "Documenti e dati dell'associazione",
                    'body' => Rt::doc(<<<'MD'
                        ## Finalità

                        L'associazione diffonde la cultura nepalese e porta aiuto alle fasce deboli della società nepalese: anziani, bambini, mamme in difficoltà, famiglie in stato di povertà, di difficoltà sanitaria o alimentare. Il testo degli articoli 3 e 4 dello statuto è nella pagina [Chi siamo](/chi-siamo#finalita).

                        ## Dati dell'associazione

                        - Denominazione: Sakila per il Nepal ODV
                        - Codice fiscale: 93059310164
                        - Forma: organizzazione di volontariato (ODV), con sede in Italia

                        ## Iscrizione al RUNTS

                        L'associazione è iscritta al Registro Unico Nazionale del Terzo Settore (RUNTS), sezione A «Organizzazioni di volontariato», con repertorio n. 147002. L'iscrizione è stata disposta dall'Ufficio regionale del RUNTS di Regione Lombardia con determinazione n. 554, registrata il 3 marzo 2025.

                        ## Albo comunale

                        L'associazione è iscritta all'albo delle associazioni del Comune di Treviglio (BG).

                        ## Coordinate bancarie

                        - IBAN: IT88 V084 4053 6420 0000 0174 576
                        - Banca: Banca di Credito Cooperativo Carate e Treviglio, filiale Sud

                        Nella pagina [Dona](/donate) trovi anche il codice QR per il bonifico.

                        ## Adesione all'associazione

                        Per diventare socio puoi inviare la [richiesta di adesione online](/partecipa#socio), oppure chiedere il modulo di adesione ai nostri contatti.

                        ## Contatti

                        - Email: [sakilaperilnepal2023@gmail.com](mailto:sakilaperilnepal2023@gmail.com)
                        - Telefono: Sakila [392 812 2361](tel:+393928122361) · Gianfranco [334 631 9867](tel:+393346319867)

                        ## Crediti fotografici

                        La foto di copertina (alba sull'Annapurna, Himalaya) è di Shedeur, da [Wikimedia Commons](https://commons.wikimedia.org/wiki/File:Mt._Annapurna_Sunrise.jpg), con licenza [CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0/). Le altre foto sono dell'associazione.
                        MD),
                ]],
            ],
        ],
        'en' => [
            'title' => 'Documents', 'slug' => 'documents',
            'seoDescription' => "Aims, the association's details, RUNTS registration, bank details, membership and contacts of Sakila per il Nepal ODV.",
            'sections' => [
                ['type' => 'richText', 'settings' => ['background' => 'paper', 'width' => 'normal'], 'content' => [
                    'kicker' => 'Documents',
                    'heading' => "The association's documents and details",
                    'body' => Rt::doc(<<<'MD'
                        ## Aims

                        The association spreads Nepali culture and brings help to the vulnerable groups of Nepali society: the elderly, children, mothers in difficulty, and families facing poverty, health problems or lack of food. Articles 3 and 4 of the statute are on the [About us](/about-us#finalita) page.

                        ## The association's details

                        - Name: Sakila per il Nepal ODV
                        - Italian tax code (codice fiscale): 93059310164
                        - Legal form: volunteer organisation (ODV), based in Italy

                        ## RUNTS registration

                        The association is registered with RUNTS, Italy's national third-sector register, section A "Volunteer organisations", under no. 147002. Registration was ordered by the Lombardy regional RUNTS office with decision no. 554, recorded on 3 March 2025.

                        ## Municipal register

                        The association is listed in the register of associations of the Municipality of Treviglio (Bergamo).

                        ## Bank details

                        - IBAN: IT88 V084 4053 6420 0000 0174 576
                        - Bank: Banca di Credito Cooperativo Carate e Treviglio, Sud branch

                        The [Donate](/donate) page also has a QR code for the bank transfer.

                        ## Joining the association

                        To become a member you can send the [membership request online](/get-involved#socio), or ask our contacts for the membership form.

                        ## Contacts

                        - Email: [sakilaperilnepal2023@gmail.com](mailto:sakilaperilnepal2023@gmail.com)
                        - Phone: Sakila [+39 392 812 2361](tel:+393928122361) · Gianfranco [+39 334 631 9867](tel:+393346319867)

                        ## Photo credits

                        The cover photo (sunrise over Annapurna, Himalaya) is by Shedeur, from [Wikimedia Commons](https://commons.wikimedia.org/wiki/File:Mt._Annapurna_Sunrise.jpg), licensed under [CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0/). The other photos belong to the association.
                        MD),
                ]],
            ],
        ],
    ],

    // ═════════════════════════════ CONTATTI ══════════════════════════════
    [
        'ref' => 'contact', 'key' => 'contact',
        'it' => [
            'title' => 'Contatti', 'slug' => 'contatti',
            'seoDescription' => 'Contatta Sakila per il Nepal ODV: scrivici con il modulo o per email, oppure telefona a Sakila o a Gianfranco. Rispondiamo appena possibile.',
            'sections' => [
                ['type' => 'contact', 'settings' => $paper, 'content' => [
                    'kicker' => 'Contatti',
                    'heading' => 'Scrivici',
                    'lead' => 'Per informazioni puoi anche telefonare: Sakila 392 812 2361, Gianfranco 334 631 9867.',
                    'showForm' => true, 'showDetails' => true, 'showMap' => false,
                ]],
            ],
        ],
        'en' => [
            'title' => 'Contact', 'slug' => 'contact',
            'seoDescription' => 'Contact Sakila per il Nepal ODV: write to us with the form or by email, or call Sakila or Gianfranco. We will answer as soon as we can.',
            'sections' => [
                ['type' => 'contact', 'settings' => $paper, 'content' => [
                    'kicker' => 'Contact',
                    'heading' => 'Write to us',
                    'lead' => 'For information you can also call: Sakila +39 392 812 2361, Gianfranco +39 334 631 9867.',
                    'showForm' => true, 'showDetails' => true, 'showMap' => false,
                ]],
            ],
        ],
    ],

    // ══════════════════════════════ PRIVACY ══════════════════════════════
    // Draft written for what THIS site actually does. To be reviewed by the association.
    [
        'ref' => 'privacy', 'key' => 'privacy',
        'it' => [
            'title' => 'Informativa privacy', 'slug' => 'privacy',
            'seoDescription' => 'Quali dati personali raccoglie il sito di Sakila per il Nepal ODV, perché, per quanto tempo, e come esercitare i tuoi diritti (GDPR).',
            'sections' => [
                ['type' => 'richText', 'settings' => ['background' => 'paper', 'width' => 'narrow'], 'content' => [
                    'heading' => 'Informativa sulla privacy',
                    'body' => Rt::doc(<<<'MD'
                        Questa informativa spiega quali dati personali raccoglie questo sito e come vengono usati, ai sensi del Regolamento (UE) 2016/679 (GDPR).

                        ## Titolare del trattamento

                        Sakila per il Nepal ODV, codice fiscale 93059310164. Per qualsiasi richiesta sui tuoi dati scrivi a [sakilaperilnepal2023@gmail.com](mailto:sakilaperilnepal2023@gmail.com).

                        ## Quali dati raccogliamo e perché

                        - **Modulo di contatto**: nome, email, telefono (facoltativo) e messaggio, per risponderti.
                        - **Richieste di volontariato, adesione e collaborazione**: i dati che inserisci nel modulo, per valutare la richiesta e ricontattarti.
                        - **Newsletter**: il tuo indirizzo email, solo dopo che hai confermato l'iscrizione con il link che ricevi per email. Puoi cancellarti in qualsiasi momento con il link presente in ogni messaggio.
                        - **Donazioni**: se compili il modulo di donazione del sito registriamo il nome, l'email e l'importo che indichi; per i bonifici riceviamo dalla banca i dati dell'operazione (nome dell'ordinante, importo, causale). Li conserviamo per gli obblighi contabili e fiscali.
                        - **Sicurezza**: per proteggere i moduli dallo spam contiamo le richieste provenienti da uno stesso indirizzo IP, memorizzato solo in forma cifrata non reversibile.

                        Non usiamo i tuoi dati per profilazione e non li vendiamo a nessuno.

                        ## Base giuridica

                        Il consenso che esprimi inviando un modulo o iscrivendoti alla newsletter; l'esecuzione di misure precontrattuali per le richieste di adesione; gli obblighi di legge per i dati contabili; il legittimo interesse alla sicurezza del sito.

                        ## Chi può vedere i dati

                        Solo le persone autorizzate dall'associazione. Il sito è ospitato presso un fornitore di servizi di hosting, che tratta i dati per nostro conto, e le email sono inviate tramite il servizio di posta del dominio.

                        ## Per quanto tempo

                        Conserviamo i messaggi e le richieste per il tempo necessario a gestirli e, in seguito, per il periodo previsto dalla legge. I dati della newsletter restano fino alla cancellazione dell'iscrizione.

                        ## I tuoi diritti

                        Puoi chiedere in qualsiasi momento di accedere ai tuoi dati, correggerli, cancellarli, limitarne il trattamento, opporti e ricevere una copia dei dati che ci hai fornito, scrivendo all'indirizzo indicato sopra. Hai anche il diritto di presentare reclamo al Garante per la protezione dei dati personali ([www.garanteprivacy.it](https://www.garanteprivacy.it)).

                        ## Cookie

                        Vedi la [Cookie policy](/cookie).
                        MD),
                ]],
            ],
        ],
        'en' => [
            'title' => 'Privacy policy', 'slug' => 'privacy',
            'seoDescription' => 'Which personal data the Sakila per il Nepal ODV website collects, why, for how long, and how to exercise your rights under the GDPR.',
            'sections' => [
                ['type' => 'richText', 'settings' => ['background' => 'paper', 'width' => 'narrow'], 'content' => [
                    'heading' => 'Privacy policy',
                    'body' => Rt::doc(<<<'MD'
                        This notice explains which personal data this website collects and how they are used, under Regulation (EU) 2016/679 (GDPR).

                        ## Data controller

                        Sakila per il Nepal ODV, Italian tax code 93059310164. For any request about your data write to [sakilaperilnepal2023@gmail.com](mailto:sakilaperilnepal2023@gmail.com).

                        ## What we collect and why

                        - **Contact form**: name, email, phone (optional) and message, to answer you.
                        - **Volunteer, membership and partnership requests**: the data you enter in the form, to assess the request and get back to you.
                        - **Newsletter**: your email address, only after you confirm the subscription through the link we email you. You can unsubscribe at any time with the link in every message.
                        - **Donations**: if you fill in the donation form on the site we record the name, email and amount you enter; for bank transfers we receive the payment details from the bank (payer's name, amount, reference). We keep them for accounting and tax obligations.
                        - **Security**: to protect the forms from spam we count the requests coming from the same IP address, stored only in a one-way encrypted form.

                        We do not use your data for profiling and we do not sell them to anyone.

                        ## Legal basis

                        The consent you give by sending a form or subscribing to the newsletter; pre-contractual steps for membership requests; legal obligations for accounting data; our legitimate interest in keeping the site secure.

                        ## Who can see the data

                        Only people authorised by the association. The site is hosted by a hosting provider that processes data on our behalf, and emails are sent through the domain's mail service.

                        ## How long we keep them

                        We keep messages and requests for the time needed to handle them and, afterwards, for the period required by law. Newsletter data are kept until you unsubscribe.

                        ## Your rights

                        At any time you can ask to access, correct or erase your data, restrict or object to their processing, and receive a copy of the data you gave us, by writing to the address above. You also have the right to lodge a complaint with the Italian data protection authority ([www.garanteprivacy.it](https://www.garanteprivacy.it)).

                        ## Cookies

                        See the [Cookie policy](/cookies).
                        MD),
                ]],
            ],
        ],
    ],

    // ══════════════════════════════ COOKIE ═══════════════════════════════
    // Draft: describes what the site really sets. To be reviewed by the association.
    [
        'ref' => 'cookies', 'key' => 'cookies',
        'it' => [
            'title' => 'Cookie policy', 'slug' => 'cookie',
            'seoDescription' => 'Le pagine pubbliche di questo sito non usano cookie di profilazione, di statistica o di terze parti: ecco cosa viene usato e quando.',
            'sections' => [
                ['type' => 'richText', 'settings' => ['background' => 'paper', 'width' => 'narrow'], 'content' => [
                    'heading' => 'Cookie policy',
                    'body' => Rt::doc(<<<'MD'
                        ## In breve

                        Le pagine pubbliche di questo sito non usano cookie: né di profilazione, né di statistica, né di terze parti. Per questo non compare alcun banner.

                        ## Cookie tecnici

                        Solo l'area riservata agli amministratori del sito usa cookie tecnici, necessari per mantenere l'accesso dopo il login. Non riguardano i visitatori.

                        ## Contenuti di terze parti

                        Mappe e video esterni, quando presenti, vengono caricati solo dopo un tuo clic: fino a quel momento nessun dato viene inviato al servizio esterno.

                        ## Pagamenti

                        Se in futuro attiveremo i pagamenti online, la pagina di pagamento sarà quella del fornitore scelto (ad esempio Stripe o PayPal), che applica la propria informativa.

                        Per tutto il resto vale l'[Informativa privacy](/privacy).
                        MD),
                ]],
            ],
        ],
        'en' => [
            'title' => 'Cookie policy', 'slug' => 'cookies',
            'seoDescription' => 'The public pages of this website use no profiling, statistics or third-party cookies: here is what is used, and when.',
            'sections' => [
                ['type' => 'richText', 'settings' => ['background' => 'paper', 'width' => 'narrow'], 'content' => [
                    'heading' => 'Cookie policy',
                    'body' => Rt::doc(<<<'MD'
                        ## In short

                        The public pages of this website do not use cookies: no profiling, no statistics, no third-party cookies. That is why there is no cookie banner.

                        ## Technical cookies

                        Only the area reserved for the site's administrators uses technical cookies, needed to stay signed in after login. They do not concern visitors.

                        ## Third-party content

                        External maps and videos, when present, are loaded only after you click: until then no data are sent to the external service.

                        ## Payments

                        If we enable online payments in the future, the payment page will be that of the chosen provider (for example Stripe or PayPal), which applies its own policy.

                        For everything else, see the [Privacy policy](/privacy).
                        MD),
                ]],
            ],
        ],
    ],
];
