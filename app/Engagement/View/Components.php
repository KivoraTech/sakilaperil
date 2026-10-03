<?php

declare(strict_types=1);

namespace App\Engagement\View;

use App\Cache\CacheTags;
use App\Cache\TagCache;
use App\Engagement\Forms\FormContext;
use App\Engagement\Forms\FormSchemas;
use App\Engagement\Newsletter\Providers;
use App\Engagement\Payments\Bank;
use App\Engagement\Payments\DonationSchema;
use App\Engagement\Payments\PaymentConfig;
use App\Engagement\Spam\Turnstile;
use App\I18n\Format;
use App\I18n\Locales;
use App\I18n\Translator;
use App\Media\Assets;
use App\Settings\SettingsTodo;
use App\Settings\SiteSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Server side of the public engagement components (port of components/engagement/*.tsx server
 * wrappers): builds the island props and renders the initial markup, which equals the island's
 * first client render (resources/js/site/engagement/*.ts) and the reference React output.
 */
final class Components
{
    /** Monthly presets from the design system (§8.10); one-off presets come from SiteSettings. */
    private const MONTHLY_PRESETS = [5, 10, 20, 50];

    private const FIELDS = [
        'contact' => [
            ['name' => 'name', 'kind' => 'text', 'autoComplete' => 'name'],
            ['name' => 'email', 'kind' => 'email', 'autoComplete' => 'email'],
            ['name' => 'phone', 'kind' => 'tel', 'optional' => true, 'autoComplete' => 'tel'],
            ['name' => 'subject', 'kind' => 'select', 'optional' => true, 'options' => 'CONTACT_SUBJECTS', 'optionsNs' => 'subjects'],
            ['name' => 'message', 'kind' => 'textarea', 'wide' => true, 'rows' => 6],
        ],
        'volunteer' => [
            ['name' => 'firstName', 'kind' => 'text', 'autoComplete' => 'given-name'],
            ['name' => 'lastName', 'kind' => 'text', 'autoComplete' => 'family-name'],
            ['name' => 'email', 'kind' => 'email', 'autoComplete' => 'email'],
            ['name' => 'phone', 'kind' => 'tel', 'optional' => true, 'autoComplete' => 'tel'],
            ['name' => 'city', 'kind' => 'text', 'optional' => true, 'autoComplete' => 'address-level2'],
            ['name' => 'country', 'kind' => 'text', 'optional' => true, 'autoComplete' => 'country-name'],
            ['name' => 'skills', 'kind' => 'textarea', 'optional' => true, 'wide' => true, 'rows' => 3],
            ['name' => 'availability', 'kind' => 'text', 'optional' => true, 'wide' => true, 'hint' => 'availabilityHint'],
            ['name' => 'message', 'kind' => 'textarea', 'optional' => true, 'wide' => true, 'rows' => 4],
        ],
        'membership' => [
            ['name' => 'firstName', 'kind' => 'text', 'autoComplete' => 'given-name'],
            ['name' => 'lastName', 'kind' => 'text', 'autoComplete' => 'family-name'],
            ['name' => 'email', 'kind' => 'email', 'autoComplete' => 'email'],
            ['name' => 'phone', 'kind' => 'tel', 'optional' => true, 'autoComplete' => 'tel'],
            ['name' => 'membershipType', 'kind' => 'select', 'options' => 'MEMBERSHIP_TYPES', 'optionsNs' => 'membershipTypes', 'wide' => true],
            ['name' => 'address', 'kind' => 'text', 'optional' => true, 'autoComplete' => 'street-address', 'wide' => true],
            ['name' => 'city', 'kind' => 'text', 'optional' => true, 'autoComplete' => 'address-level2'],
            ['name' => 'country', 'kind' => 'text', 'optional' => true, 'autoComplete' => 'country-name'],
            ['name' => 'message', 'kind' => 'textarea', 'optional' => true, 'wide' => true, 'rows' => 3],
        ],
        'partner' => [
            ['name' => 'organization', 'kind' => 'text', 'autoComplete' => 'organization'],
            ['name' => 'contactName', 'kind' => 'text', 'autoComplete' => 'name'],
            ['name' => 'email', 'kind' => 'email', 'autoComplete' => 'email'],
            ['name' => 'phone', 'kind' => 'tel', 'optional' => true, 'autoComplete' => 'tel'],
            ['name' => 'website', 'kind' => 'url', 'optional' => true, 'autoComplete' => 'url'],
            ['name' => 'partnershipType', 'kind' => 'select', 'optional' => true, 'options' => 'PARTNERSHIP_TYPES', 'optionsNs' => 'partnershipTypes'],
            ['name' => 'message', 'kind' => 'textarea', 'wide' => true, 'rows' => 5],
        ],
    ];

    /** @param  array<string, string|int|float>  $params */
    private static function t(string $key, string $locale, array $params = []): string
    {
        return Translator::t($key, $params, $locale);
    }

    // ── Contact / Volunteer / Membership / Partner ──────────────────────────────────────

    public static function submissionForm(string $type, string $locale, ?string $source = null, ?string $className = null, bool $showTitle = true): string
    {
        $ctx = FormContext::get($locale, $type);
        $props = array_filter([
            'type' => $type,
            'locale' => $locale,
            'source' => $source,
            'privacyHref' => $ctx['privacyHref'],
            'successMessage' => $ctx['successMessage'],
            'intro' => $ctx['intro'],
            'showTitle' => $showTitle,
            'turnstileSiteKey' => $ctx['turnstileSiteKey'],
            'className' => $className,
        ], static fn ($v) => $v !== null);

        $f = static fn (string $key, array $params = []) => self::t("forms.{$key}", $locale, $params);
        $idPrefix = "{$type}-form";
        $titleId = "{$idPrefix}-title";
        // The intro belongs to the form's own title. When a block supplies the heading (showTitle=false)
        // it also shows the introduction itself, so repeating it here would print it twice.
        $intro = $showTitle ? ($ctx['intro'] ?: (Translator::has("forms.{$type}.intro", $locale) ? $f("{$type}.intro") : '')) : '';

        $html = '<div'.Html::attrs(['class' => Html::cx('relative', $className), 'data-engagement' => "{$type}-form"]).'>'
            .($showTitle ? '<h3'.Html::attrs(['id' => $titleId, 'class' => 'font-serif text-2xl font-semibold text-heading']).'>'.Html::e($f("{$type}.title")).'</h3>' : '')
            .($intro !== '' ? '<p class="mt-2 max-w-prose text-base leading-relaxed text-fg-muted">'.Html::e($intro).'</p>' : '')
            .'<form'.Html::attrs([
                'novalidate' => true,
                'aria-labelledby' => $showTitle ? $titleId : null,
                'aria-label' => $showTitle ? null : $f("{$type}.title"),
                'class' => 'mt-6 flex flex-col gap-5',
            ]).'>'
            .'<div class="grid gap-5 sm:grid-cols-2">';
        foreach (self::FIELDS[$type] as $def) {
            $name = $def['name'];
            $optional = $def['optional'] ?? false;
            $html .= Html::field(
                id: "{$idPrefix}-{$name}",
                label: $f($name),
                optionalLabel: $optional ? $f('optional') : null,
                hint: isset($def['hint']) ? $f($def['hint']) : null,
                className: ($def['wide'] ?? false) || $def['kind'] === 'textarea' ? 'sm:col-span-2' : null,
                control: function (array $ids) use ($def, $name, $idPrefix, $optional, $f): string {
                    $common = [
                        'id' => "{$idPrefix}-{$name}",
                        'aria-describedby' => $ids['describedBy'],
                        'aria-required' => $optional ? null : 'true',
                    ];
                    if ($def['kind'] === 'textarea') {
                        return '<textarea'.Html::attrs(['rows' => $def['rows'], ...$common, 'class' => Html::cx(Html::INPUT, 'resize-y'), 'name' => $name]).'></textarea>';
                    }
                    if ($def['kind'] === 'select') {
                        $options = constant(FormSchemas::class.'::'.$def['options']);
                        $out = '<select'.Html::attrs([...$common, 'class' => Html::cx(Html::INPUT, 'appearance-auto'), 'name' => $name]).'><option value="">—</option>';
                        foreach ($options as $o) {
                            $out .= '<option value="'.Html::e($o).'">'.Html::e($f("{$def['optionsNs']}.{$o}")).'</option>';
                        }

                        return $out.'</select>';
                    }

                    return '<input'.Html::attrs([
                        'type' => $def['kind'],
                        'autocomplete' => $def['autoComplete'] ?? null,
                        'inputmode' => $def['kind'] === 'tel' ? 'tel' : ($def['kind'] === 'email' ? 'email' : null),
                        ...$common,
                        'class' => Html::INPUT,
                        'name' => $name,
                    ]).'/>';
                },
            );
        }
        $html .= '</div>'
            .Html::checkbox(
                id: "{$idPrefix}-consent",
                labelHtml: Html::privacyConsentLabel($f('privacyConsentBefore'), $f('privacyLink'), $f('privacyConsentAfter'), $ctx['privacyHref']),
                name: 'consent',
                ariaRequired: true,
            )
            .Html::honeypot($f('honeypotLabel'))
            .Html::turnstileSlot($ctx['turnstileSiteKey'])
            .'<div>'.Html::submitButton($f("{$type}.submit")).'</div>'
            .'</form></div>';

        return Html::props('submission', $props).$html;
    }

    // ── Newsletter ──────────────────────────────────────────────────────────────────────

    /** @param  'inline'|'stacked'  $variant @param  'light'|'dark'  $tone */
    public static function newsletterForm(string $locale, ?string $source = null, string $variant = 'inline', string $tone = 'light', ?string $className = null): string
    {
        $ctx = FormContext::get($locale, 'newsletter');
        $uid = 'nl-'.Str::lower(Str::random(8));
        $dark = $tone === 'dark';
        $props = array_filter([
            'uid' => $uid,
            'locale' => $locale,
            'source' => $source,
            'variant' => $variant,
            'tone' => $tone,
            'providerConfigured' => Providers::isConfigured(),
            'privacyHref' => $ctx['privacyHref'],
            'successMessage' => $ctx['successMessage'],
            'turnstileSiteKey' => $ctx['turnstileSiteKey'],
            'className' => $className,
        ], static fn ($v) => $v !== null);
        $n = static fn (string $key) => self::t("newsletter.{$key}", $locale);
        $f = static fn (string $key) => self::t("forms.{$key}", $locale);
        $focusRing = $dark ? 'focus-visible:outline-[#F6C77A]' : 'focus-visible:outline-focus';
        $inputClass = Html::cx(Html::INPUT, 'sm:flex-1', $focusRing);

        $html = '<form'.Html::attrs([
            'novalidate' => true,
            // The footer form is on every page: its own name keeps two newsletter forms on one page distinct.
            'aria-label' => $source === 'footer' ? $n('footerLabel') : $n('pageTitle'),
            'class' => Html::cx('relative flex flex-col gap-3', $className),
            'data-engagement' => 'newsletter-form',
        ]).'>'
            .'<label'.Html::attrs(['for' => "{$uid}-email", 'class' => Html::cx('text-[15px] font-medium', $dark ? 'text-white' : 'text-fg')]).'>'.Html::e($n('emailLabel')).'</label>'
            .'<div class="'.Html::e(Html::cx('flex gap-2', $variant === 'stacked' ? 'flex-col' : 'flex-col sm:flex-row')).'">'
            .'<input'.Html::attrs([
                'id' => "{$uid}-email",
                'type' => 'email',
                'autocomplete' => 'email',
                'inputmode' => 'email',
                'placeholder' => $n('placeholder'),
                'aria-describedby' => "{$uid}-note",
                'class' => $inputClass,
                'name' => 'email',
            ]).'/>'
            .'<button'.Html::attrs([
                'type' => 'submit',
                'class' => Html::cx(
                    'inline-flex min-h-12 items-center justify-center rounded-full px-6 font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-70',
                    $dark ? 'bg-marigold text-deep hover:bg-[#F6C77A]' : 'bg-sindoor text-white hover:bg-sindoor-dark',
                    $focusRing,
                ),
            ]).'>'.Html::e($n('subscribe')).'</button>'
            .'</div>'
            .'<div'.Html::attrs(['class' => $dark ? '[&_label]:text-white/90 [&_p]:text-[#FFD2C8]' : null]).'>'
            .Html::checkbox(
                id: "{$uid}-consent",
                labelHtml: Html::e($n('consent')).' <a'.Html::attrs([
                    'href' => $ctx['privacyHref'],
                    'target' => '_blank',
                    'rel' => 'noopener',
                    'class' => Html::cx('underline underline-offset-2', $dark ? 'text-white' : 'text-himalaya'),
                ]).'>'.Html::e($f('privacyLink')).'</a>',
                name: 'consent',
            )
            .'</div>'
            .'<p'.Html::attrs(['id' => "{$uid}-note", 'class' => Html::cx('text-sm', $dark ? 'text-white/75' : 'text-fg-muted')]).'>'.Html::e($n('note')).'</p>'
            .Html::honeypot($f('honeypotLabel'))
            .Html::turnstileSlot($ctx['turnstileSiteKey'])
            .'</form>';

        return Html::props('newsletter', $props).$html;
    }

    public static function unsubscribeForm(string $locale, string $token): string
    {
        $n = static fn (string $key) => self::t("newsletter.{$key}", $locale);

        return Html::props('unsubscribe', ['token' => $token])
            .'<form class="flex flex-col items-start gap-5" data-engagement="unsubscribe-form">'
            .'<p class="text-lead text-fg">'.Html::e($n('unsubscribeBody')).'</p>'
            .Html::submitButton($n('unsubscribeButton'))
            .'</form>';
    }

    // ── Bank transfer panel ─────────────────────────────────────────────────────────────

    /** Panels rendered in this request: gives every panel heading its own id (two can share a page). */
    private static int $bankPanels = 0;

    /**
     * True when at least one online payment provider (card, PayPal, eSewa, Khalti) is configured.
     * False means the site accepts bank transfers only: pages should not promise online payments.
     */
    public static function onlinePaymentsAvailable(): bool
    {
        foreach (PaymentConfig::publicStatus() as $status) {
            if ($status['available']) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when the donate widget opens on the bank-transfer panel (no online provider and no Nepal
     * QR): a page that embeds the widget should then not render a second bank panel below it.
     */
    public static function donateWidgetIsBankOnly(): bool
    {
        if (self::onlinePaymentsAvailable()) {
            return false;
        }
        $nepal = app(SiteSettings::class)->get()['nepalPayments'] ?? [];

        return empty($nepal['esewa']['qrImageId']) && empty($nepal['khalti']['qrImageId']);
    }

    /** @param  int  $headingLevel  2–4: level of the panel title, so it follows the page's heading outline */
    public static function bankTransferPanel(string $locale, ?string $className = null, int $headingLevel = 3): string
    {
        $h = 'h'.max(2, min(4, $headingLevel));
        $titleId = 'bank-transfer-title'.(++self::$bankPanels > 1 ? '-'.self::$bankPanels : '');
        $settings = app(SiteSettings::class)->get();
        $d = static fn (string $key) => self::t("donate.{$key}", $locale);
        $bank = $settings['bankDetails'] ?? [];
        $iban = SettingsTodo::known($bank['iban'] ?? null);
        $holder = SettingsTodo::known($bank['accountHolder'] ?? null)
            ?? SettingsTodo::known($settings['organization']['legalName'] ?? null)
            ?? $settings['orgName'];
        $bic = ! empty($bank['bic']) ? SettingsTodo::known($bank['bic']) : null;
        $bankName = ! empty($bank['bankName']) ? SettingsTodo::known($bank['bankName']) : null;
        $reference = Locales::pick($bank['reference'] ?? null, $locale) ?: $d('reference');
        $ibanOk = is_string($iban) && $iban !== '' && Bank::isValidIban($iban);

        $qrSrc = null;
        if (! empty($bank['qrImageId'])) {
            $asset = Assets::byIds([$bank['qrImageId']], $locale)[$bank['qrImageId']] ?? null;
            $qrSrc = $asset['url'] ?? null;
        } elseif ($ibanOk) {
            // The QR is a convenience: bad settings (e.g. an over-long holder name) must not take the page down.
            try {
                $payload = Bank::epcPayload((string) $holder, (string) $iban, is_string($bic) ? $bic : null, null, $reference);
                $qrSrc = TagCache::remember([CacheTags::SETTINGS], 'engagement:epc-qr:'.md5($payload), fn () => Bank::qrDataUri($payload));
            } catch (Throwable $e) {
                Log::warning('[donate] could not build the bank-transfer QR code', ['error' => $e->getMessage()]);
            }
        }

        $txt = $ibanOk ? implode("\r\n", array_filter([
            $d('accountHolder').': '.$holder,
            $d('iban').': '.Bank::formatIban((string) $iban),
            $bic ? $d('bic').': '.$bic : null,
            $bankName ? $d('bankName').': '.$bankName : null,
            $d('reference').': '.$reference,
        ])) : null;

        $html = '<section'.Html::attrs([
            'aria-labelledby' => $titleId,
            'data-testid' => 'bank-transfer',
            'class' => Html::cx(Html::LIGHT_SCOPE, 'rounded-[20px] border border-line bg-white p-5 sm:p-6', $className),
        ]).'>'
            .'<'.$h.' id="'.$titleId.'" class="flex items-center gap-2 font-serif text-xl font-semibold text-heading">'
            .Html::icon('Landmark', 'size-5 text-sage').Html::e($d('bankTitle')).'</'.$h.'>'
            .'<p class="mt-1 text-[15px] text-fg-muted">'.Html::e($d('bankIntro')).'</p>';
        if (! $ibanOk) {
            $html .= '<p role="note" class="mt-4 flex items-start gap-2 rounded-[14px] bg-warning-bg p-4 text-[15px] font-medium text-warning">'
                .Html::icon('AlertTriangle', 'mt-0.5 size-5 shrink-0').Html::e($d('bankPending')).'</p>';
        }
        // dl > div > (dt + dd) only: anything else (the download link) lives outside the list.
        $html .= '<div class="mt-4 flex flex-col gap-6 sm:flex-row sm:items-start"><div class="flex-1"><dl class="grid gap-3 text-[15px]">'
            .'<div><dt class="text-sm text-fg-muted">'.Html::e($d('accountHolder')).'</dt><dd class="font-medium text-fg">'.Html::e($holder).'</dd></div>';
        if ($ibanOk) {
            $copy = $d('copyIban');
            $html .= '<div><dt class="text-sm text-fg-muted">'.Html::e($d('iban')).'</dt><dd class="flex flex-wrap items-center gap-3">'
                .'<span class="font-mono text-base font-semibold tracking-wide text-heading" translate="no">'.Html::e(Bank::formatIban((string) $iban)).'</span>'
                .'<button'.Html::attrs([
                    'type' => 'button',
                    'data-copy-value' => Bank::normalizeIban((string) $iban),
                    'data-copy-label' => $copy,
                    'data-copied-label' => $d('copied'),
                    'class' => 'inline-flex min-h-11 items-center gap-2 rounded-full border-[1.5px] border-himalaya px-4 text-sm font-semibold text-himalaya transition-colors hover:bg-himalaya hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
                ]).'>'.Html::icon('Copy', 'size-4').Html::e($copy).'</button>'
                .'<span aria-live="polite" class="sr-only"></span>'
                .'</dd></div>';
        }
        if ($bic) {
            $html .= '<div><dt class="text-sm text-fg-muted">'.Html::e($d('bic')).'</dt><dd class="font-mono font-medium text-fg" translate="no">'.Html::e($bic).'</dd></div>';
        }
        if ($bankName) {
            $html .= '<div><dt class="text-sm text-fg-muted">'.Html::e($d('bankName')).'</dt><dd class="font-medium text-fg">'.Html::e($bankName).'</dd></div>';
        }
        $html .= '<div><dt class="text-sm text-fg-muted">'.Html::e($d('reference')).'</dt><dd class="font-medium text-fg">'.Html::e($reference).'</dd></div>';
        $html .= '</dl>';
        if ($txt !== null) {
            $html .= '<p class="mt-3"><a'.Html::attrs([
                'href' => 'data:text/plain;charset=utf-8,'.rawurlencode($txt),
                'download' => 'sakila-bonifico.txt',
                'class' => 'inline-flex min-h-6 items-center gap-2 py-1 text-sm font-semibold text-himalaya underline underline-offset-4 hover:text-sindoor',
            ]).'>'.Html::icon('Download', 'size-4').Html::e($d('downloadDetails')).'</a></p>';
        }
        $html .= '</div>';
        if ($qrSrc !== null) {
            $html .= '<figure class="shrink-0 text-center"><img'.Html::attrs([
                'alt' => $d('qrAlt'),
                'loading' => 'lazy',
                'width' => 180,
                'height' => 180,
                'decoding' => 'async',
                'data-nimg' => '1',
                'class' => 'rounded-[10px] border border-line bg-white p-1',
                'style' => 'color:transparent',
                'src' => $qrSrc,
            ]).'/><figcaption class="mt-2 max-w-[180px] text-sm text-fg-muted">'.Html::e($d('qrTitle')).'</figcaption></figure>';
        }

        return $html.'</div></section>';
    }

    // ── Donate widget ───────────────────────────────────────────────────────────────────

    /**
     * @param  array{projectId?: ?string, campaignId?: ?string, presetAmounts?: list<int>|null, defaultAmount?: ?int, showDestination?: ?bool, source?: ?string, className?: ?string, panelHeadingLevel?: ?int}  $o
     */
    public static function donateWidget(string $locale, array $o = []): string
    {
        $settings = app(SiteSettings::class)->get();
        $projectId = $o['projectId'] ?? null;
        $projects = $projectId || ($o['showDestination'] ?? null) === false ? [] : self::destinationProjects($locale);
        $nepal = $settings['nepalPayments'] ?? [];
        $qrIds = array_values(array_filter([$nepal['esewa']['qrImageId'] ?? null, $nepal['khalti']['qrImageId'] ?? null]));
        $assets = $qrIds !== [] ? Assets::byIds($qrIds, $locale) : [];
        $nepalQr = static function (string $p) use ($nepal, $assets): ?array {
            $cfg = $nepal[$p] ?? [];
            $asset = ! empty($cfg['qrImageId']) ? ($assets[$cfg['qrImageId']] ?? null) : null;
            if ($asset === null) {
                return null;
            }
            $merchant = ! empty($cfg['merchantId']) ? SettingsTodo::known($cfg['merchantId']) : null;

            return array_filter(['url' => $asset['url'], 'merchantId' => $merchant], static fn ($v) => $v !== null);
        };
        $presets = ! empty($o['presetAmounts']) ? $o['presetAmounts'] : ($settings['donation']['presetAmounts'] ?? [10, 25, 50, 100]);
        $impactNotes = [];
        foreach ($settings['donation']['impactNotes'] ?? [] as $note) {
            $text = Locales::pick($note['text'] ?? null, $locale);
            if ($text !== '' && ! str_contains($text, '[[TODO')) {
                $impactNotes[] = ['amount' => $note['amount'], 'text' => $text];
            }
        }
        $props = [
            'uid' => 'dw-'.Str::lower(Str::random(8)),
            'locale' => $locale,
            'source' => $o['source'] ?? null,
            'className' => $o['className'] ?? null,
            'projectId' => $projectId,
            'campaignId' => $o['campaignId'] ?? null,
            'presets' => ['EUR' => array_slice(array_values($presets), 0, 4), 'EUR_MONTHLY' => self::MONTHLY_PRESETS, 'NPR' => DonationSchema::DEFAULT_NPR_PRESETS],
            'defaultAmount' => $o['defaultAmount'] ?? ($settings['donation']['defaultAmount'] ?? 25),
            'impactNotes' => $impactNotes,
            'providers' => PaymentConfig::publicStatus(),
            'nepalQr' => ['ESEWA' => $nepalQr('esewa'), 'KHALTI' => $nepalQr('khalti')],
            'projects' => $projects,
            'privacyHref' => FormContext::privacyHref($locale),
            'turnstileSiteKey' => Turnstile::siteKey(),
        ];
        $props = array_filter($props, static fn ($v) => $v !== null);

        return Html::props('donate', $props).self::donateWidgetInitial($props, self::bankTransferPanel($locale, null, $o['panelHeadingLevel'] ?? 3));
    }

    /** @return list<array{id: string, title: string}> published, not completed (listProjects order) */
    private static function destinationProjects(string $locale): array
    {
        return TagCache::remember([CacheTags::PROJECTS], "engagement:donate-projects:{$locale}", function () use ($locale): array {
            $rows = DB::table('projects')
                ->where('status', 'PUBLISHED')
                ->where(fn ($q) => $q->whereNull('publishedAt')->orWhere('publishedAt', '<=', now('UTC')->format('Y-m-d H:i:s.v')))
                ->where('stage', '!=', 'COMPLETED')
                ->orderBy('position')->orderByDesc('publishedAt')
                ->get(['id']);
            $out = [];
            foreach ($rows as $row) {
                $titles = DB::table('project_translations')->where('projectId', $row->id)->pluck('title', 'locale');
                $title = $titles[$locale] ?? $titles[Locales::DEFAULT] ?? null;
                if (is_string($title)) {
                    $out[] = ['id' => (string) $row->id, 'title' => $title];
                }
            }

            return $out;
        });
    }

    /**
     * First render of the widget (state: once, first usable method, default amount, no errors).
     *
     * @param  array<string, mixed>  $p  island props
     */
    private static function donateWidgetInitial(array $p, string $bankPanel): string
    {
        $locale = $p['locale'];
        $d = static fn (string $key, array $params = []) => self::t("donate.{$key}", $locale, $params);
        $f = static fn (string $key, array $params = []) => self::t("forms.{$key}", $locale, $params);
        $providers = $p['providers'];
        $uid = $p['uid'];
        $usable = static function (string $m, string $freq) use ($providers, $p): bool {
            if ($m === 'BANK') {
                return true;
            }
            if ($freq === 'monthly') {
                return $m === 'STRIPE' && $providers['STRIPE']['available'];
            }
            if ($m === 'ESEWA' || $m === 'KHALTI') {
                return $providers[$m]['available'] || ! empty($p['nepalQr'][$m]);
            }

            return $providers[$m]['available'];
        };
        $order = ['STRIPE', 'PAYPAL', 'ESEWA', 'KHALTI', 'BANK'];
        $method = 'BANK';
        foreach ($order as $m) {
            if ($usable($m, 'once')) {
                $method = $m;
                break;
            }
        }
        $frequency = 'once';
        $currency = $method === 'ESEWA' || $method === 'KHALTI' ? 'NPR' : 'EUR';
        $presetsEur = $p['presets']['EUR'];
        $selected = in_array($p['defaultAmount'], $presetsEur, true) ? $p['defaultAmount'] : ($presetsEur[1] ?? $presetsEur[0] ?? 25);
        $fmt = static fn (int|float $major, string $cur) => Format::money((int) round($major * 100), $cur, $locale, fmod((float) $major, 1.0) === 0.0 ? 0 : 2);
        $online = $method !== 'BANK' && $providers[$method]['available'];
        $qrOnly = ($method === 'ESEWA' || $method === 'KHALTI') && ! $providers[$method]['available'] && ! empty($p['nepalQr'][$method]);
        $testMode = $method !== 'BANK' && $providers[$method]['available'] && $providers[$method]['testMode'];
        $icons = ['STRIPE' => 'CreditCard', 'PAYPAL' => 'Wallet', 'ESEWA' => 'Smartphone', 'KHALTI' => 'Smartphone', 'BANK' => 'Landmark'];
        $keys = ['STRIPE' => 'methodCard', 'PAYPAL' => 'methodPaypal', 'ESEWA' => 'methodEsewa', 'KHALTI' => 'methodKhalti', 'BANK' => 'methodBank'];

        // A provider that is not set up is left out altogether (no "coming soon" tiles); bank transfer
        // is always offered. With bank transfer alone there is nothing to choose: the widget is just the
        // bank panel, without the card frame around a form. Keep in sync with donate-widget.ts.
        $offered = array_values(array_filter($order, static fn (string $m): bool => $m === 'BANK' || $usable($m, 'once')));
        $bankOnly = count($offered) === 1;

        $html = '<div'.Html::attrs([
            'class' => Html::cx(Html::LIGHT_SCOPE, $bankOnly ? 'relative' : 'relative overflow-hidden rounded-[28px] border border-line bg-[#FFFDF9] shadow-[0_24px_60px_-28px_rgba(16,31,51,0.35)]', $p['className'] ?? null),
            'data-engagement' => 'DonateWidget',
        ]).'>';
        if ($testMode) {
            $html .= '<p class="flex items-center justify-center gap-2 bg-warning-bg px-4 py-2 text-sm font-semibold text-warning">'.Html::icon('FlaskConical', 'size-4').Html::e($d('testMode')).'</p>';
        }
        $html .= '<form'.Html::attrs(['novalidate' => true, 'aria-label' => $d('title'), 'class' => $bankOnly ? 'flex flex-col gap-7' : 'flex flex-col gap-7 p-5 sm:p-8']).'>';

        // Monthly giving needs a card: without Stripe there is nothing to choose, so the step is left out
        // (no disabled "monthly" option). Keep in sync with donate-widget.ts.
        if ($providers['STRIPE']['available']) {
            $html .= '<fieldset><legend class="mb-3 text-[15px] font-semibold text-heading">'.Html::e($d('frequencyLegend')).'</legend><div class="grid grid-cols-2 gap-1 rounded-full bg-cream p-1">';
            foreach (['once', 'monthly'] as $freq) {
                $html .= '<label class="'.Html::e(Html::cx(
                    'flex min-h-11 cursor-pointer items-center justify-center gap-1.5 rounded-full px-3 text-[15px] font-semibold whitespace-nowrap text-fg-muted transition-colors',
                    'has-[:checked]:bg-white has-[:checked]:text-heading has-[:checked]:shadow-sm',
                    'has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-focus',
                )).'"><input'.Html::attrs([
                    'type' => 'radio', 'name' => "{$uid}-frequency", 'value' => $freq, 'checked' => $frequency === $freq,
                    'class' => 'sr-only', 'data-role' => 'frequency',
                ]).'/>'.Html::e($d($freq))
                    .($freq === 'monthly' ? '<span class="hidden rounded-full bg-sage/15 px-2 py-0.5 text-xs font-medium text-sage-700 min-[420px]:inline">'.Html::e($d('recommended')).'</span>' : '')
                    .'</label>';
            }
            $html .= '</div></fieldset>';
        }

        if ($method !== 'BANK' && ! $qrOnly) {
            $limits = DonationSchema::AMOUNT_LIMITS[$currency];
            $chips = $currency === 'NPR' ? $p['presets']['NPR'] : $presetsEur;
            $html .= '<fieldset><legend class="mb-3 text-[15px] font-semibold text-heading">'.Html::e($d('amountLegend')).' <span class="font-normal text-fg-muted">('.$currency.')</span></legend><div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4">';
            foreach ($chips as $a) {
                $html .= '<label class="relative flex min-h-14 cursor-pointer items-center justify-center rounded-[10px] border-[1.5px] border-border-input bg-white px-3 text-lg font-semibold text-fg transition-colors has-[:checked]:border-sindoor has-[:checked]:bg-sindoor-50 has-[:checked]:text-sindoor-dark has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-focus">'
                    .'<input'.Html::attrs(['type' => 'radio', 'name' => "{$uid}-amount", 'value' => $a, 'checked' => $selected === $a, 'class' => 'sr-only', 'data-role' => 'amount']).'/>'
                    .($selected === $a ? Html::icon('Check', 'absolute top-1.5 right-1.5 size-4') : '')
                    .Html::e($fmt($a, $currency)).'</label>';
            }
            $html .= '</div><div class="mt-3">'.Html::field(
                id: "{$uid}-custom",
                label: $d('customAmountLabel', ['currency' => $currency]),
                control: fn (array $ids) => '<div class="relative"><span aria-hidden="true" class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 font-semibold text-fg-muted">'.($currency === 'EUR' ? '€' : 'Rs').'</span>'
                    .'<input'.Html::attrs([
                        'id' => $ids['id'], 'type' => 'text', 'inputmode' => 'decimal', 'autocomplete' => 'off',
                        'aria-describedby' => $ids['describedBy'], 'value' => '', 'placeholder' => $limits['min'].'–'.$limits['max'],
                        'class' => Html::cx(Html::INPUT, $currency === 'EUR' ? 'pl-9' : 'pl-11'), 'data-role' => 'custom',
                    ]).'/></div>',
            ).'</div>';
            $impact = $d('impactGeneric');
            if ($currency === 'EUR') {
                $notes = $p['impactNotes'];
                usort($notes, static fn ($x, $y) => $y['amount'] <=> $x['amount']);
                foreach ($notes as $note) {
                    if ($note['amount'] <= $selected) {
                        $impact = $note['text'];
                        break;
                    }
                }
            }
            $html .= '<p aria-live="polite" class="mt-3 flex items-start gap-2 rounded-[14px] bg-sage-50 p-3.5 text-[15px] leading-snug text-sage-700">'
                .Html::icon('Heart', 'mt-0.5 size-4 shrink-0 text-sage')
                .'<span><strong class="font-semibold">'.Html::e($fmt($selected, $currency)).' · </strong>'.Html::e($impact).'</span></p></fieldset>';
        }

        if (! $bankOnly) {
            $html .= '<fieldset><legend class="mb-3 text-[15px] font-semibold text-heading">'.Html::e($d('methodLegend')).'</legend><div class="grid gap-2.5 sm:grid-cols-2">';
            foreach ($offered as $m) {
                $isUsable = $usable($m, $frequency);
                $hint = match (true) {
                    $m === 'STRIPE' => $d('methodCardHint'),
                    $m === 'BANK' => $d('methodBankHint'),
                    $m === 'ESEWA', $m === 'KHALTI' => $d('nprHint'),
                    default => null,
                };
                $reason = ! $isUsable ? $d('monthlyCardOnly') : null;
                $html .= '<label'.Html::attrs([
                    'data-method' => $m,
                    'class' => Html::cx(
                        'flex min-h-16 items-center gap-3 rounded-[14px] border-[1.5px] border-border-input px-4 py-3 transition-colors',
                        'has-[:checked]:border-sindoor has-[:checked]:bg-sindoor-50',
                        'has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-focus',
                        $isUsable ? 'cursor-pointer bg-white' : 'cursor-not-allowed border-dashed bg-paper opacity-70',
                        $m === 'BANK' ? 'sm:col-span-2' : null,
                    ),
                ]).'><input'.Html::attrs([
                    'type' => 'radio', 'name' => "{$uid}-method", 'value' => $m, 'checked' => $method === $m,
                    'disabled' => ! $isUsable, 'class' => 'size-4 shrink-0 accent-sindoor', 'data-role' => 'method',
                ]).'/>'.Html::icon($icons[$m], 'size-5 shrink-0 text-himalaya')
                    .'<span class="flex flex-1 flex-col"><span class="text-[15px] font-semibold text-fg">'.Html::e($d($keys[$m])).'</span>'
                    .($hint && $isUsable ? '<span class="text-sm text-fg-muted">'.Html::e($hint).'</span>' : '')
                    .'</span>'
                    .($reason ? '<span class="rounded-full bg-warning-bg px-2 py-0.5 text-xs font-semibold text-warning">'.Html::e($reason).'</span>' : '')
                    .'</label>';
            }
            $html .= '</div></fieldset>';
        }

        if ($method === 'BANK') {
            $html .= '<div data-bank-panel="">'.$bankPanel.'</div>';
        } elseif ($qrOnly) {
            $qr = $p['nepalQr'][$method];
            $provider = $method === 'ESEWA' ? 'eSewa' : 'Khalti';
            $html .= '<div class="flex flex-col items-center gap-3 rounded-[20px] border border-line bg-white p-5 text-center">'
                .'<p class="font-serif text-lg font-semibold text-heading">'.Html::e($d('nepalQrTitle', ['provider' => $provider])).'</p>'
                .'<img'.Html::attrs(['src' => $qr['url'], 'alt' => $d('nepalQrAlt', ['provider' => $provider]), 'width' => 220, 'height' => 220, 'class' => 'rounded-[10px] border border-line']).'/>'
                .(! empty($qr['merchantId']) ? '<p class="text-[15px]">'.Html::e($d('merchantId')).': <span class="font-mono font-semibold">'.Html::e($qr['merchantId']).'</span></p>' : '')
                .'<p lang="ne" class="text-sm text-fg-muted">'.Html::e($d('namaste')).'</p></div>';
        } elseif ($online) {
            $html .= '<fieldset class="flex flex-col gap-4"><legend class="mb-3 text-[15px] font-semibold text-heading">'.Html::e($d('detailsLegend')).'</legend>';
            if (empty($p['projectId']) && ! empty($p['projects'])) {
                $html .= Html::field(
                    id: "{$uid}-projectId",
                    label: $d('destination'),
                    optionalLabel: $f('optional'),
                    control: function (array $ids) use ($p, $d): string {
                        $out = '<select'.Html::attrs(['id' => $ids['id'], 'class' => Html::cx(Html::INPUT, 'appearance-auto'), 'data-role' => 'projectId']).'>'
                            .'<option value="" selected="">'.Html::e($d('whereNeeded')).'</option>';
                        foreach ($p['projects'] as $proj) {
                            $out .= '<option value="'.Html::e($proj['id']).'">'.Html::e($proj['title']).'</option>';
                        }

                        return $out.'</select>';
                    },
                );
            }
            $html .= '<div class="grid gap-4 sm:grid-cols-2">'
                .Html::field(id: "{$uid}-donorName", label: $d('donorName'), optionalLabel: $f('optional'), control: fn (array $ids) => '<input'.Html::attrs([
                    'id' => $ids['id'], 'autocomplete' => 'name', 'aria-describedby' => $ids['describedBy'], 'value' => '', 'class' => Html::INPUT, 'data-role' => 'donorName',
                ]).'/>')
                .Html::field(id: "{$uid}-donorEmail", label: $d('donorEmail'), optionalLabel: $f('optional'), control: fn (array $ids) => '<input'.Html::attrs([
                    'id' => $ids['id'], 'type' => 'email', 'inputmode' => 'email', 'autocomplete' => 'email', 'aria-describedby' => $ids['describedBy'], 'value' => '', 'class' => Html::INPUT, 'data-role' => 'donorEmail',
                ]).'/>')
                .'</div>'
                .Html::checkbox(id: "{$uid}-anon", labelHtml: Html::e($d('anonymous')), role: 'isAnonymous')
                .Html::checkbox(id: "{$uid}-nl", labelHtml: Html::e($d('newsletterOptIn')), role: 'newsletter')
                .Html::checkbox(
                    id: "{$uid}-consent",
                    labelHtml: Html::privacyConsentLabel($f('privacyConsentBefore'), $f('privacyLink'), $f('privacyConsentAfter'), $p['privacyHref']),
                    ariaRequired: true,
                    role: 'consent',
                )
                .Html::honeypot($f('honeypotLabel'))
                .Html::turnstileSlot($p['turnstileSiteKey'] ?? null)
                .'<button type="submit" class="inline-flex min-h-14 w-full items-center justify-center gap-2 rounded-full bg-sindoor px-6 text-lg font-semibold text-white shadow-[0_10px_24px_-10px_rgba(181,69,47,0.7)] transition-colors hover:bg-sindoor-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus disabled:cursor-wait disabled:opacity-75">'
                .Html::icon('Heart', 'size-5').Html::e($d('donateButton', ['amount' => $fmt($selected, $currency), 'method' => $d($keys[$method])])).'</button>'
                .'<p aria-live="polite" class="sr-only"></p></fieldset>';
        }

        // Only name the providers that are really set up; with none there is no online payment to describe.
        $names = ['STRIPE' => 'Stripe', 'PAYPAL' => 'PayPal', 'ESEWA' => 'eSewa', 'KHALTI' => 'Khalti'];
        $active = array_values(array_map(static fn (string $m): string => $names[$m], array_filter(array_keys($names), static fn (string $m): bool => $providers[$m]['available'])));
        if ($active !== []) {
            $last = array_pop($active);
            $list = $active === [] ? $last : implode(', ', $active).' '.$d('listOr').' '.$last;
            $html .= '<p class="flex items-start gap-2 border-t border-line pt-4 text-sm text-fg-muted">'
                .Html::icon('Lock', 'mt-0.5 size-4 shrink-0 text-sage').Html::e($d('trustFooter', ['providers' => $list])).'</p>';
        }

        return $html.'</form></div>';
    }
}
