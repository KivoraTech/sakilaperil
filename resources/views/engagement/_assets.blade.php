{{--
    Loads the engagement islands + the messages they need, once per page (ENGAGEMENT; internal).
    Every public engagement partial includes this. Pushed to `body-end` (not `head`): the site footer
    (newsletter form) renders after the <head> stack has already been printed.
--}}
@pushOnce('body-end')
    <script type="application/json" id="engagement-messages">{!! json_encode([
        'forms' => \App\I18n\Translator::raw('forms', $locale),
        'donate' => \App\I18n\Translator::raw('donate', $locale),
        'newsletter' => \App\I18n\Translator::raw('newsletter', $locale),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @vite('resources/js/site/engagement/index.ts')
@endPushOnce
