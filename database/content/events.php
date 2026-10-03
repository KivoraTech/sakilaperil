<?php

declare(strict_types=1);

use App\Content\Real\Rt;

/*
 * Events. Play for Nepal: date, venue and programme as printed on the posters and leaflet.
 * (One poster says "Via Piave 17"; the programme and the leaflet say "Viale/Via Piave, 26".)
 */
return [
    [
        'key' => 'play-for-nepal-2026',
        'startsAt' => '2026-05-16T10:00:00+02:00',
        'endsAt' => '2026-05-16T20:30:00+02:00',
        'allDay' => false,
        'timezone' => 'Europe/Rome',
        'address' => 'Viale Piave 26, 24050 Bariano (BG)',
        'mapUrl' => '',
        'onlineUrl' => '',
        'registrationUrl' => '',
        // The gymnastics poster framed on its "Play for Nepal" title (focal point in media.php). The
        // leaflet is full of small print and logos and was unreadable as a cropped cover.
        'coverImageId' => '@media:pfn-gymnastics',
        'isFeatured' => true,
        // Fixed, so re-loading the dataset does not change it (the day of the event itself).
        'publishedAt' => '2026-05-16T09:00:00+02:00',
        'translations' => [
            'it' => [
                'title' => 'Play for Nepal — 1ª edizione',
                'slug' => 'play-for-nepal-2026',
                'summary' => 'Una giornata di sport, musica e cucina nepalese con il patrocinio del Comune di Bariano. Una parte del ricavato va a sostegno delle famiglie bisognose del Nepal.',
                'locationName' => 'Centro Sportivo Comunale di Bariano',
                'seoTitle' => '',
                'seoDescription' => 'Play for Nepal, 1ª edizione: sabato 16 maggio 2026 a Bariano (BG). Sport, musica e cucina nepalese per le famiglie bisognose del Nepal.',
                'body' => Rt::doc(<<<'MD'
                    Play for Nepal è l'evento organizzato dall'associazione Sakila per il Nepal con il patrocinio del Comune di Bariano. Una parte del ricavato va a sostegno delle famiglie bisognose del Nepal.

                    ## Programma

                    - 10.00 – 17.00: Play for Nepal, torneo per esordienti under 13
                    - 14.30 – 16.00: torneo di beach volley amatori
                    - 16.00 – 17.00: esibizioni delle associazioni Kora e Le Radici e Le Ali (Tai Chi)
                    - 17.00 – 19.00: camminata alla scoperta dei sentieri e torneo di calcetto amatori
                    - 19.00 – 20.30: esibizione di ginnastica ritmica e artistica della società sportiva Trevicass
                    - 20.30: estrazione della lotteria, ringraziamenti e saluti delle autorità

                    ## Durante tutta la giornata

                    Autoraduno, animazione con musica, gonfiabili, minigolf, hobbisti, servizio bar e ristorazione. Alla sera, cucina nepalese.

                    ## Il torneo per esordienti under 13

                    Dodici squadre in tre gironi:

                    - Girone A: Barianese, Vailate, Zanconti, Virtus Adda
                    - Girone B: Cavernago, Arzago d'Adda, Serenella, Bottanuco
                    - Girone C: Comun Nuovo, Valentino Mazzola, Casiratese, Campagnola M.ta

                    Qualificazioni dalle 10.15 (tempo unico da 20 minuti), quarti di finale dalle 15.00, semifinali alle 16.00, finali alle 16.30 e alle 17.00, premiazioni delle squadre finaliste intorno alle 17.30. La squadra vincitrice partecipa al torneo internazionale Bariano Cup 2026 (22-24 maggio).

                    ## In collaborazione con

                    L'Ulivo Cooperativa Sociale, U.S. Barianese, Gruppo Alpini di Bariano, ristorante nepalese Da Sakila, Margherita Conad (Arcene).

                    ## La prossima edizione

                    La seconda edizione di Play for Nepal è già in programma, sempre a Bariano. La data sarà pubblicata in questa pagina e tra le [notizie](/news).
                    MD),
            ],
            'en' => [
                'enabled' => true,
                'title' => 'Play for Nepal — 1st edition',
                'slug' => 'play-for-nepal-2026',
                'summary' => 'A day of sport, music and Nepali food under the patronage of the Municipality of Bariano. Part of the proceeds supports families in need in Nepal.',
                'locationName' => 'Bariano municipal sports centre',
                'seoTitle' => '',
                'seoDescription' => 'Play for Nepal, 1st edition: Saturday 16 May 2026 in Bariano (Bergamo). Sport, music and Nepali food for families in need in Nepal.',
                'body' => Rt::doc(<<<'MD'
                    Play for Nepal is the event organised by the Sakila per il Nepal association under the patronage of the Municipality of Bariano. Part of the proceeds supports families in need in Nepal.

                    ## Programme

                    - 10:00 – 17:00: Play for Nepal, under-13 tournament
                    - 14:30 – 16:00: amateur beach volleyball tournament
                    - 16:00 – 17:00: demonstrations by the associations Kora and Le Radici e Le Ali (Tai Chi)
                    - 17:00 – 19:00: trail discovery walk and amateur five-a-side football tournament
                    - 19:00 – 20:30: rhythmic and artistic gymnastics show by the Trevicass sports club
                    - 20:30: lottery draw, thanks and greetings from the authorities

                    ## All day long

                    Car meet, music and entertainment, inflatables, minigolf, hobby stalls, bar and food service. In the evening, Nepali cuisine.

                    ## The under-13 tournament

                    Twelve teams in three groups:

                    - Group A: Barianese, Vailate, Zanconti, Virtus Adda
                    - Group B: Cavernago, Arzago d'Adda, Serenella, Bottanuco
                    - Group C: Comun Nuovo, Valentino Mazzola, Casiratese, Campagnola M.ta

                    Qualifying matches from 10:15 (a single 20-minute period), quarter-finals from 15:00, semi-finals at 16:00, finals at 16:30 and 17:00, prize-giving for the finalists at about 17:30. The winning team takes part in the Bariano Cup 2026 international tournament (22-24 May).

                    ## In collaboration with

                    L'Ulivo Cooperativa Sociale, U.S. Barianese, the Bariano Alpini group, the Nepali restaurant Da Sakila, Margherita Conad (Arcene).

                    ## The next edition

                    The second edition of Play for Nepal is already planned, again in Bariano. The date will be published on this page and in the [news](/news).
                    MD),
            ],
        ],
    ],
];
