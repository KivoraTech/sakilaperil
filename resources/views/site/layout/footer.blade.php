{{--
    components/site/layout/site-footer.tsx: sign-off + newsletter, 4 columns, legal bar.
    Params: $settings, $locale. The short description is the site description from Admin → Settings → Google & sharing.
--}}
@php($explore = \App\Queries\Public\Navigation::get('footer-explore', $locale))
@php($help = \App\Queries\Public\Navigation::get('footer-help', $locale))
@php($legal = \App\Queries\Public\Navigation::get('footer-legal', $locale))
@php($demo = ! empty($settings['features']['demoContent']))
@php($toConfirm = t('common.toConfirm'))
@php($contact = $settings['contact'] ?? [])
@php($organization = $settings['organization'] ?? [])
@php($address = \App\I18n\Locales::pick($contact['address'] ?? null, $locale))
@php($socials = array_values(array_filter($settings['socialLinks'] ?? [], fn ($s) => ! \App\Settings\SettingsTodo::isTodo($s['url'] ?? ''))))
@php($newTab = t('a11y.externalLink'))
{{-- A page that already ends with a newsletter sign-up does not get a second one here. --}}
@php($footerNewsletter = ! \App\View\Blocks::consumeEndsWithNewsletter())
@php($about = \App\I18n\Locales::pick($settings['seo']['defaultDescription'] ?? null, $locale))
<footer class="relative mt-auto">
@partial('site.brand.ridge', ['class' => '-mb-px text-deep'])
<div class="surface-dark">
<div class="prayer-flags opacity-90" aria-hidden="true"></div>
<div class="mx-auto max-w-wide px-gutter pt-16 pb-10 md:pt-20">
<div class="grid gap-10 border-b border-white/10 pb-12 lg:grid-cols-[1.2fr_1fr] lg:items-end">
<div>
<p class="font-display text-h1 font-semibold text-white">{{ t('footer.signoffA') }} <em class="heading-accent">&amp;</em> {{ t('footer.signoffB') }}</p>
<p lang="ne" class="mt-2 text-lead text-mist">नमस्ते</p>
</div>
@if ($footerNewsletter)
<div id="newsletter-footer" class="scroll-mt-28">
<h2 class="font-display text-h3 text-white">{{ t('footer.newsletterTitle') }}</h2>
<p class="mt-2 text-small text-mist">{{ t('footer.newsletterBody') }}</p>
@partialIf('engagement.newsletter-form', ['locale' => $locale, 'source' => 'footer', 'variant' => 'inline', 'tone' => 'dark', 'class' => 'mt-4'])
</div>
@endif
</div>
<div class="grid gap-10 py-12 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1.3fr]">
<div>
<a class="inline-flex items-center gap-3 rounded-lg" aria-label="{{ $settings['orgName'] }} — {{ t('nav.home') }}" href="{{ lurl('/') }}">
@partial('site.brand.logo-mark', ['class' => 'size-11'])
@partial('site.brand.wordmark', ['name' => (string) $settings['orgName']])
</a>
<p class="mt-5 max-w-xs text-small text-fg-muted">{{ $about !== '' ? $about : t('footer.about') }}</p>
@if (count($socials))
<ul class="mt-5 flex gap-2" aria-label="{{ t('footer.social') }}">
@foreach ($socials as $s)
<li>
<a href="{{ $s['url'] }}" target="_blank" rel="noopener noreferrer" class="flex size-11 items-center justify-center rounded-full bg-white/8 text-white ring-1 ring-white/15 transition-[color,background-color,transform,translate] duration-300 ease-out-soft hover:bg-marigold hover:text-deep motion:hover:-translate-y-[3px]">
@partial('site.brand.social-icon', ['platform' => $s['platform'], 'class' => 'size-5'])
<span class="sr-only">{{ \App\View\Brand::SOCIAL_NAMES[$s['platform']] ?? '' }} {{ $newTab }}</span>
</a>
</li>
@endforeach
</ul>
@endif
</div>
@partial('site.layout.footer-links', ['title' => t('footer.explore'), 'items' => $explore, 'newTab' => $newTab])
@partial('site.layout.footer-links', ['title' => t('footer.help'), 'items' => $help, 'newTab' => $newTab])
<div>
<h2 class="text-kicker font-semibold tracking-[0.16em] text-gold uppercase">{{ t('footer.contact') }}</h2>
<ul class="mt-4 space-y-3 text-fg">
<li class="flex gap-3">
{!! \App\View\Icon::svg('Mail', 'mt-1 size-4 shrink-0 text-mist') !!}
{{ \App\View\Ui::fact($contact['email'] ?? null, $demo, $toConfirm, fn ($v) => new \Illuminate\Support\HtmlString('<a href="mailto:'.e($v).'" class="[overflow-wrap:anywhere] underline-offset-4 hover:text-white hover:underline">'.str_replace('@', '<wbr>@', e($v)).'</a>')) }}
</li>
@if (! empty($contact['phone']))
<li class="flex gap-3">
{!! \App\View\Icon::svg('Phone', 'mt-1 size-4 shrink-0 text-mist') !!}
{{ \App\View\Ui::fact($contact['phone'], $demo, $toConfirm, fn ($v) => new \Illuminate\Support\HtmlString('<a href="tel:'.e(preg_replace('/[^\d+]/', '', $v)).'" class="underline-offset-4 hover:text-white hover:underline">'.e($v).'</a>')) }}
</li>
@endif
@if ($address !== '')
<li class="flex gap-3">
{!! \App\View\Icon::svg('MapPin', 'mt-1 size-4 shrink-0 text-mist') !!}
{{ \App\View\Ui::fact($address, $demo, $toConfirm) }}
</li>
@endif
</ul>
<dl class="mt-6 space-y-1 text-small text-fg-muted">
<div>
<dt class="inline">{{ t('footer.legalName') }}: </dt>
<dd class="inline">{{ \App\View\Ui::fact($organization['legalName'] ?? null, $demo, $toConfirm) }}</dd>
</div>
<div>
<dt class="inline">{{ t('footer.taxCode') }}: </dt>
<dd class="inline">{{ \App\View\Ui::fact($organization['taxCode'] ?? null, $demo, $toConfirm) }}</dd>
</div>
@if (! empty($organization['registrationNumber']))
<div>
<dt class="inline">{{ t('footer.registration') }}: </dt>
<dd class="inline">{{ \App\View\Ui::fact($organization['registrationNumber'], $demo, $toConfirm) }}</dd>
</div>
@endif
</dl>
</div>
</div>
<div class="flex flex-col gap-4 border-t border-white/10 pt-6 text-small text-fg-muted md:flex-row md:items-center md:justify-between">
<p>© {{ date('Y') }} {{ $settings['orgName'] }}. {{ t('footer.rights') }}</p>
<ul class="flex flex-wrap items-center gap-x-5 gap-y-1">
@foreach ($legal as $li)
<li>
<a class="inline-flex min-h-11 items-center underline-offset-4 hover:text-white hover:underline" href="{{ lurl($li['href']) }}">{{ $li['label'] }}</a>
</li>
@endforeach
@if ($demo)
<li>{{ t('footer.photoCredits') }}</li>
@endif
<li>{{ t('footer.madeBy') }}</li>
</ul>
</div>
</div>
</div>
</footer>
