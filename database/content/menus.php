<?php

declare(strict_types=1);

/*
 * Menus (Admin → Menus): the header and the three footer columns.
 * PAGE links follow a page even if its address changes; ROUTE links point to a site section.
 */
$page = fn (string $ref, string $it, string $en): array => [
    'label' => ['it' => $it, 'en' => $en], 'linkType' => 'PAGE', 'pageId' => '@page:'.$ref,
    'openInNewTab' => false, 'isVisible' => true, 'depth' => 0,
];
$route = fn (string $key, string $it, string $en): array => [
    'label' => ['it' => $it, 'en' => $en], 'linkType' => 'ROUTE', 'routeKey' => $key,
    'openInNewTab' => false, 'isVisible' => true, 'depth' => 0,
];

return [
    'header' => [
        $page('about', 'Chi siamo', 'About us'),
        $route('projects', 'Progetti', 'Projects'),
        $page('activities', 'Attività', 'Activities'),
        $route('news', 'Notizie', 'News'),
        $route('events', 'Eventi', 'Events'),
        $page('documents', 'Documenti', 'Documents'),
        $page('contact', 'Contatti', 'Contact'),
    ],
    'footer-explore' => [
        $page('about', 'Chi siamo', 'About us'),
        $route('projects', 'Progetti', 'Projects'),
        $page('activities', 'Attività', 'Activities'),
        $route('news', 'Notizie', 'News'),
        $route('gallery', 'Galleria', 'Gallery'),
        $page('documents', 'Documenti', 'Documents'),
    ],
    'footer-help' => [
        $route('donate', 'Dona ora', 'Donate now'),
        $page('get-involved', 'Partecipa', 'Get involved'),
        $route('events', 'Eventi', 'Events'),
        $page('contact', 'Contatti', 'Contact'),
    ],
    'footer-legal' => [
        $page('privacy', 'Privacy', 'Privacy'),
        $page('cookies', 'Cookie', 'Cookies'),
    ],
];
