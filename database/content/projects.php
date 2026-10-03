<?php

declare(strict_types=1);

use App\Content\Real\Rt;

/*
 * Projects. Texts follow the client's documents ("Progetto lavoro femminile…", "Aiuti alluvione
 * del 26 agosto 2026") and email; nothing has been added to them.
 */
$base = [
    'latitude' => '', 'longitude' => '', 'startDate' => '', 'endDate' => '',
    'goalAmount' => '', 'raisedAmount' => '', 'currency' => 'EUR',
    // Fixed (the day this dataset was prepared), so re-loading never changes it.
    'publishedAt' => '2026-10-03T09:00:00+02:00',
];

return [
    [
        'key' => 'lavoro',
        ...$base,
        'stage' => 'ACTIVE',
        'district' => 'Lalitpur (Bagmati)',
        'coverImageId' => '@media:work-grain',
        'isFeatured' => true,
        'position' => 0,
        'translations' => [
            'it' => [
                'title' => 'Lavoro per donne e famiglie in difficoltà',
                'slug' => 'lavoro-per-donne-e-famiglie',
                'sector' => 'Lavoro',
                'metaLine' => 'Lavoro continuativo, non assistenza a vita',
                'summary' => "Opportunità di lavoro per donne sole, o che devono provvedere alla famiglia, e per persone fragili: un reddito continuativo al posto dell'assistenza a vita.",
                'seoTitle' => '',
                'seoDescription' => 'A Lalitpur, in Nepal, creiamo lavoro per donne sole e persone fragili: un reddito continuativo al posto dell\'assistenza a vita.',
                'body' => Rt::doc(<<<'MD'
                    Il progetto ha le sue basi a Lalitpur, nell'area di Kathmandu (provincia di Bagmati).

                    ## L'obiettivo

                    L'obiettivo principale è dare opportunità di lavoro a donne sole, o che devono provvedere al sostentamento economico della famiglia, e a persone in condizioni di fragilità.

                    Il punto cardine è uscire dalla logica dell'assistenzialismo fine a se stesso, se non nelle prime fasi del progetto. Un lavoro regolarmente retribuito dà dignità alla persona, che così può contare su un reddito mensile e programmare la propria vita e quella della famiglia.

                    ## Come funziona

                    Le nostre raccolte fondi servono ad agevolare l'avvio di nuove attività di qualsiasi genere (artigianali, agricole e altre): l'acquisto iniziale dei macchinari, l'affitto di un negozio, il sostegno durante la fase di avvio. In alternativa, le persone destinatarie del progetto vengono inserite in realtà locali già esistenti e disposte a collaborare con l'associazione.

                    Nelle prime fasi l'associazione garantisce lo stipendio, o il pagamento delle ore di lavoro svolte, fino a quando produzione e vendite permettono all'attività di sostenersi da sola.

                    L'associazione aiuta a raggiungere l'autosostentamento anche vendendo i prodotti delle realtà che collaborano al progetto: organizza eventi e bancarelle in occasione delle feste di paese e in tutte le occasioni che possono dare visibilità ai prodotti.

                    ## Le attività

                    Nel rispetto delle leggi e della cultura locale, le attività riguardano soprattutto:

                    - il confezionamento di abbigliamento di largo consumo (t-shirt, pantaloncini, felpe, calzini…);
                    - la produzione di prodotti tipici dei paesi asiatici, come tè e spezie;
                    - prodotti locali che si possono vendere nei mercati e nelle bancarelle in Italia e nei paesi dell'Unione Europea.

                    ## Come vengono usate le risorse

                    - acquisto di nuovi strumenti e macchine per la produzione e il confezionamento dei prodotti;
                    - spostamenti necessari per lo sviluppo del progetto;
                    - quote di partecipazione a mercati, fiere, eventi e bancarelle;
                    - tutto ciò che serve per produrre e distribuire i manufatti.

                    C'è anche un aiuto indiretto: le materie prime e tutto il necessario vengono acquistati nei luoghi in cui si sviluppa il progetto, sostenendo l'economia reale del distretto.

                    ## Aiutare non significa assistere a vita

                    Il primo ostacolo da superare è far capire che aiutare non significa assistere a vita, ma per il tempo necessario a creare il lavoro: il sostentamento certo si ottiene con un lavoro duraturo.

                    ## In rete con le associazioni locali

                    Per quanto possibile l'associazione cerca sinergie con altre associazioni locali, per trovare punti di incontro e dare insieme più forza ai progetti. Associazioni che danno alloggio a donne rimaste sole, o che ospitano bambini orfani, persone con disabilità e anziani non autosufficienti, potrebbero tutte beneficiare della collaborazione.
                    MD),
            ],
            'en' => [
                'enabled' => true,
                'title' => 'Work for women and families in need',
                'slug' => 'work-for-women-and-families',
                'sector' => 'Work',
                'metaLine' => 'Steady work, not lifelong assistance',
                'summary' => 'Job opportunities for women on their own, or who have to provide for their family, and for vulnerable people: a steady income instead of lifelong assistance.',
                'seoTitle' => '',
                'seoDescription' => 'In Lalitpur, Nepal, we create work for women on their own and for vulnerable people: a steady income instead of lifelong assistance.',
                'body' => Rt::doc(<<<'MD'
                    The project is based in Lalitpur, in the Kathmandu area (Bagmati province).

                    ## The goal

                    The main goal is to give job opportunities to women who are on their own, or who have to provide for their family, and to people in vulnerable conditions.

                    The key idea is to move away from aid for its own sake, except in the first stages of the project. Regularly paid work gives a person dignity: with a monthly income she can plan her own life and her family's.

                    ## How it works

                    Our fundraising helps new activities of any kind (crafts, farming and others) get started: buying the first machines, renting a shop, support during the start-up phase. Alternatively, the people the project is for join existing local businesses that are willing to work with the association.

                    In the first stages the association guarantees the salary, or pays for the hours worked, until production and sales allow the activity to support itself.

                    The association also helps the activities become self-sufficient by selling the products of the businesses that take part in the project: it organises events and market stalls at village fairs and on every occasion that can give the products visibility.

                    ## The activities

                    Respecting local laws and culture, the activities are mainly:

                    - making everyday clothing (T-shirts, shorts, sweatshirts, socks…);
                    - producing typical Asian products such as tea and spices;
                    - local products that can be sold at markets and stalls in Italy and in other EU countries.

                    ## How the funds are used

                    - buying new tools and machines for production and packaging;
                    - the travel needed to develop the project;
                    - fees for taking part in markets, fairs, events and stalls;
                    - everything needed to make and distribute the products.

                    There is an indirect benefit too: raw materials and everything else are bought where the project takes place, supporting the district's real economy.

                    ## Helping does not mean lifelong assistance

                    The first obstacle is making it clear that helping does not mean assisting for life, but for the time needed to create work: a secure livelihood comes from lasting employment.

                    ## Working with local associations

                    Wherever possible the association looks for synergies with other local associations, to find common ground and give the projects more strength together. Associations that house women left on their own, or that care for orphaned children, people with disabilities and elderly people who are not self-sufficient, could all benefit from working together.
                    MD),
            ],
        ],
    ],

    [
        'key' => 'alluvione',
        ...$base,
        'stage' => 'ACTIVE',
        'district' => 'Rasuwa',
        'coverImageId' => '@media:flood-distribution',
        'isFeatured' => true,
        'position' => 1,
        'translations' => [
            'it' => [
                'title' => "Aiuti per l'alluvione del 26 agosto 2026",
                'slug' => 'aiuti-alluvione-agosto-2026',
                'sector' => 'Emergenze',
                'metaLine' => 'Beni acquistati in Nepal e consegnati di persona',
                'summary' => 'Raccolta fondi in Italia e acquisto in Nepal di beni di prima necessità (abbigliamento nuovo, scarpe e giochi per i bambini) per chi è stato colpito dall\'alluvione.',
                'seoTitle' => '',
                'seoDescription' => 'Alluvione del 26 agosto 2026 in Nepal: raccogliamo fondi in Italia e acquistiamo sul posto abbigliamento, scarpe e giochi per i bambini.',
                'body' => Rt::doc(<<<'MD'
                    Subito dopo la notizia dell'alluvione che il 26 agosto 2026 ha colpito la zona settentrionale del Nepal, in particolare il distretto di Rasuwa al confine con il Tibet, l'associazione ha avviato una raccolta fondi in Italia.

                    Portare beni di prima necessità a chi è sopravvissuto è stato ritenuto di primaria importanza rispetto a tutte le altre iniziative in corso.

                    ## Come aiutiamo

                    I fondi vengono raccolti in Italia, dove l'associazione ha sede. Con il ricavato acquistiamo i beni di prima necessità direttamente in Nepal (nel nostro caso abbigliamento, scarpe e giochi per i bambini): così diamo anche un aiuto indiretto all'economia del paese.

                    Il materiale viene distribuito personalmente da Sakila, presidente dell'associazione.

                    ## I prossimi passi

                    Il progetto prevede anche un secondo tipo di aiuto: l'associazione si muoverà dove ci sarà maggiore richiesta (donne, bambini soli, famiglie rimaste senza nulla).

                    ## Come contribuire

                    Puoi sostenere il progetto con un bonifico: trovi le coordinate nella pagina [Dona](/donate).
                    MD),
            ],
            'en' => [
                'enabled' => true,
                'title' => 'Flood relief, 26 August 2026',
                'slug' => 'flood-relief-august-2026',
                'sector' => 'Emergencies',
                'metaLine' => 'Goods bought in Nepal and delivered in person',
                'summary' => 'Fundraising in Italy and essential goods bought in Nepal (new clothing, shoes and toys for children) for the people hit by the flood.',
                'seoTitle' => '',
                'seoDescription' => '',
                'body' => Rt::doc(<<<'MD'
                    Right after the news of the flood that hit northern Nepal on 26 August 2026, in particular the Rasuwa district on the border with Tibet, the association started a fundraiser in Italy.

                    Bringing essential goods to the survivors was considered more urgent than every other initiative under way.

                    ## How we help

                    Funds are raised in Italy, where the association is based. With the proceeds we buy essential goods directly in Nepal (in our case clothing, shoes and toys for children): this also gives indirect support to the country's economy.

                    The goods are handed out in person by Sakila, the association's president.

                    ## Next steps

                    The project also includes a second kind of help: the association will go where the need is greatest (women, children left on their own, families who have lost everything).

                    ## How to contribute

                    You can support the project with a bank transfer: the details are on the [Donate](/donate) page.
                    MD),
            ],
        ],
    ],

    // Only planned (client's email: "in futuro è previsto…"): stage PLANNED, no cover photo, no
    // fundraising goal, not featured. The templates must not ask for donations for it.
    [
        'key' => 'cooperativa',
        ...$base,
        'stage' => 'PLANNED',
        'district' => '',
        'coverImageId' => null,
        'isFeatured' => false,
        'position' => 2,
        'translations' => [
            'it' => [
                'title' => 'Cooperativa agricola di donne',
                'slug' => 'cooperativa-agricola-di-donne',
                'sector' => 'Lavoro',
                'metaLine' => '',
                'summary' => 'In futuro è previsto un progetto per aiutare le donne che vogliono avviare una cooperativa agricola.',
                'seoTitle' => '',
                'seoDescription' => '',
                'body' => Rt::doc(<<<'MD'
                    In futuro è previsto un progetto per aiutare le donne che vogliono avviare una cooperativa agricola.

                    Il progetto non è ancora partito: pubblicheremo qui i dettagli quando prenderà il via. Nel frattempo puoi conoscere i [progetti attivi](/projects).
                    MD),
            ],
            'en' => [
                'enabled' => true,
                'title' => "Women's farming cooperative",
                'slug' => 'womens-farming-cooperative',
                'sector' => 'Work',
                'metaLine' => '',
                'summary' => 'A future project is planned to help women who want to start a farming cooperative.',
                'seoTitle' => '',
                'seoDescription' => '',
                'body' => Rt::doc(<<<'MD'
                    A future project is planned to help women who want to start a farming cooperative.

                    The project has not started yet: we will publish the details here when it gets under way. In the meantime, see our [active projects](/projects).
                    MD),
            ],
        ],
    ],
];
