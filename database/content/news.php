<?php

declare(strict_types=1);

use App\Content\Real\Rt;

/*
 * News stories, written only from the client's material (flyer, posters, documents, email).
 *
 * `publishedAt` is fixed, so re-loading the dataset never changes the date readers and search
 * engines see. The material does not say when each story was first published, so each one carries
 * the date of the fact it reports (the Play for Nepal day, the day of the flood); the exact
 * publication dates are on the list for the association (gaps.php).
 *
 * `authorId` stays null: the loader publishes these stories without a personal byline (they are
 * the association's own announcements, not written by an admin account).
 */
$base = ['categoryId' => null, 'authorId' => null, 'tags' => []];

return [
    [
        'key' => 'raccolta-fondi-alluvione',
        ...$base,
        'publishedAt' => '2026-08-26T12:00:00+02:00',
        'coverImageId' => '@media:flood-onsite',
        'projectId' => '@project:alluvione',
        'isFeatured' => true,
        'translations' => [
            'it' => [
                'title' => 'Alluvione in Nepal: la nostra raccolta fondi',
                'slug' => 'alluvione-in-nepal-raccolta-fondi',
                'excerpt' => "Dopo l'alluvione del 26 agosto 2026 abbiamo avviato una raccolta fondi: con il ricavato acquistiamo in Nepal abbigliamento, scarpe e giochi per i bambini.",
                'seoTitle' => '',
                'seoDescription' => "Raccolta fondi dopo l'alluvione del 26 agosto 2026 in Nepal: con il ricavato acquistiamo sul posto abbigliamento, scarpe e giochi per i bambini.",
                'body' => Rt::doc(<<<'MD'
                    Il 26 agosto 2026 un'alluvione ha colpito la zona settentrionale del Nepal, in particolare il distretto di Rasuwa, al confine con il Tibet. Subito dopo la notizia l'associazione ha avviato una raccolta fondi in Italia per aiutare il popolo nepalese in questo momento difficile.

                    ## Come usiamo il ricavato

                    Con il ricavato acquistiamo provviste e materiali direttamente in Nepal: nel nostro caso abbigliamento nuovo, scarpe e giochi per i bambini. Comprare sul posto significa aiutare anche, indirettamente, l'economia del paese.

                    Il materiale viene distribuito personalmente da Sakila, presidente dell'associazione. Le foto della consegna sono nella [galleria](/gallery/aiuti-alluvione-2026).

                    ## Come donare

                    Puoi contribuire con un bonifico: trovi IBAN e causale nella pagina [Dona](/donate). Per qualsiasi informazione puoi chiamare Sakila (392 812 2361) o Gianfranco (334 631 9867).

                    Grazie a tutte le persone che hanno donato e che doneranno.
                    MD),
            ],
            'en' => [
                'enabled' => true,
                'title' => 'Flood in Nepal: our fundraiser',
                'slug' => 'flood-in-nepal-our-fundraiser',
                'excerpt' => 'After the flood of 26 August 2026 we started a fundraiser: with the proceeds we buy clothing, shoes and toys for children in Nepal.',
                'seoTitle' => '',
                'seoDescription' => '',
                'body' => Rt::doc(<<<'MD'
                    On 26 August 2026 a flood hit northern Nepal, in particular the Rasuwa district on the border with Tibet. Right after the news, the association started a fundraiser in Italy to help the Nepali people at this difficult time.

                    ## How the proceeds are used

                    With the proceeds we buy supplies and materials directly in Nepal: in our case new clothing, shoes and toys for children. Buying locally also gives indirect support to the country's economy.

                    The goods are handed out in person by Sakila, the association's president. Photos of the delivery are in the [gallery](/gallery/flood-relief-2026).

                    ## How to donate

                    You can contribute with a bank transfer: the IBAN and payment reference are on the [Donate](/donate) page. For any information you can call Sakila (+39 392 812 2361) or Gianfranco (+39 334 631 9867).

                    Thank you to everyone who has donated and who will donate.
                    MD),
            ],
        ],
    ],

    [
        'key' => 'play-for-nepal-2026',
        ...$base,
        'publishedAt' => '2026-05-16T21:00:00+02:00',
        // The "16 maggio" poster, framed on the date (focal point in media.php): readable as a cover,
        // unlike the leaflet, which is full of small print and logos.
        'coverImageId' => '@media:pfn-date',
        'projectId' => null,
        'isFeatured' => false,
        'translations' => [
            'it' => [
                'title' => 'Play for Nepal: la prima edizione a Bariano',
                'slug' => 'play-for-nepal-prima-edizione',
                'excerpt' => 'Sabato 16 maggio 2026, al Centro Sportivo Comunale di Bariano, una giornata di sport, musica e cucina nepalese a sostegno delle famiglie bisognose del Nepal.',
                'seoTitle' => '',
                'seoDescription' => 'Play for Nepal, 16 maggio 2026 a Bariano (BG): tornei, esibizioni, musica e cucina nepalese a sostegno delle famiglie bisognose del Nepal.',
                'body' => Rt::doc(<<<'MD'
                    Sabato 16 maggio 2026 si è svolta la prima edizione di Play for Nepal, al Centro Sportivo Comunale di Bariano (BG), con il patrocinio del Comune di Bariano. L'evento è stato organizzato dalla nostra associazione: una parte del ricavato è andata a sostegno delle famiglie bisognose del Nepal.

                    ## Il programma della giornata

                    - 10.00 – 17.00: Play for Nepal, torneo per esordienti under 13
                    - 14.30 – 16.00: torneo di beach volley amatori
                    - 16.00 – 17.00: esibizioni delle associazioni Kora e Le Radici e Le Ali (Tai Chi)
                    - 17.00 – 19.00: camminata alla scoperta dei sentieri e torneo di calcetto amatori
                    - 19.00 – 20.30: esibizione di ginnastica ritmica e artistica della società sportiva Trevicass
                    - 20.30: estrazione della lotteria, ringraziamenti e saluti delle autorità

                    Per tutta la durata dell'evento: autoraduno, animazione con musica, gonfiabili, minigolf, hobbisti, servizio bar e ristorazione, con cucina nepalese alla sera.

                    ## Grazie a chi ha collaborato

                    Hanno collaborato alla giornata, tra gli altri, L'Ulivo Cooperativa Sociale, U.S. Barianese, il Gruppo Alpini di Bariano, il ristorante nepalese Da Sakila e Margherita Conad di Arcene.

                    La seconda edizione è già in programma, sempre a Bariano. Le locandine della giornata sono nella [galleria](/gallery/play-for-nepal-2026).
                    MD),
            ],
            'en' => [
                'enabled' => true,
                'title' => 'Play for Nepal: the first edition in Bariano',
                'slug' => 'play-for-nepal-first-edition',
                'excerpt' => 'On Saturday 16 May 2026, at the Bariano municipal sports centre, a day of sport, music and Nepali food in support of families in need in Nepal.',
                'seoTitle' => '',
                'seoDescription' => '',
                'body' => Rt::doc(<<<'MD'
                    The first edition of Play for Nepal took place on Saturday 16 May 2026 at the municipal sports centre in Bariano (Bergamo), under the patronage of the Municipality of Bariano. The event was organised by our association: part of the proceeds went to support families in need in Nepal.

                    ## The day's programme

                    - 10:00 – 17:00: Play for Nepal, under-13 tournament
                    - 14:30 – 16:00: amateur beach volleyball tournament
                    - 16:00 – 17:00: demonstrations by the associations Kora and Le Radici e Le Ali (Tai Chi)
                    - 17:00 – 19:00: trail discovery walk and amateur five-a-side football tournament
                    - 19:00 – 20:30: rhythmic and artistic gymnastics show by the Trevicass sports club
                    - 20:30: lottery draw, thanks and greetings from the authorities

                    Throughout the event: car meet, music and entertainment, inflatables, minigolf, hobby stalls, bar and food service, with Nepali cuisine in the evening.

                    ## Thank you to everyone who helped

                    Those who helped make the day happen include L'Ulivo Cooperativa Sociale, U.S. Barianese, the Bariano Alpini group, the Nepali restaurant Da Sakila and Margherita Conad in Arcene.

                    The second edition is already planned, again in Bariano. The posters of the day are in the [gallery](/gallery/play-for-nepal-2026).
                    MD),
            ],
        ],
    ],
];
