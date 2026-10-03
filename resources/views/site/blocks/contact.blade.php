{{--
    components/site/blocks/Contact.tsx: form (7/12, ENGAGEMENT engagement.contact-form) + details, socials,
    legal identity and click-to-load map (5/12). The address row is left out while the address is unknown
    (outside demo mode).
--}}
@php($c = $block['content'])
@php($s = $block['settings'])
@php($loc = $context['locale'])
@php($settings = app(\App\Settings\SiteSettings::class)->get())
@php($demo = ! empty($settings['features']['demoContent']))
@php($toConfirm = t('common.toConfirm'))
@php($contact = $settings['contact'] ?? [])
@php($organization = $settings['organization'] ?? [])
@php($address = \App\I18n\Locales::pick($contact['address'] ?? null, $loc))
@php($map = \App\View\Contact::map($contact))
@php($socials = array_values(array_filter($settings['socialLinks'] ?? [], fn ($l) => ! \App\Settings\SettingsTodo::isTodo($l['url'] ?? ''))))
@php($intro = \App\I18n\Locales::pick($settings['formMessages']['contact']['intro'] ?? null, $loc))
@php($detailsIcon = fn (string $icon) => '<span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-cream text-sindoor">'.\App\View\Icon::svg($icon, 'size-5').'</span>')
<x-site.section :id="$s['anchor'] ?? null" :surface="\App\View\Ui::surfaceOf($s['background'] ?? null)" block="contact">
<x-site.container>
@partial('site.ui.section-intro', ['kicker' => $c['kicker'] ?? null, 'heading' => $c['heading'] ?? null, 'lead' => ! empty($c['lead']) ? $c['lead'] : ($intro !== '' ? $intro : null)])
<div class="mt-12 grid gap-10 lg:grid-cols-12 lg:gap-14">
@if (! empty($c['showForm']))
<div class="lg:col-span-7">
<div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-line/70 md:p-10">
<h3 class="font-display text-h3 font-semibold text-heading">{{ t('contactBlock.formTitle') }}</h3>
@partialIf('engagement.contact-form', ['locale' => $loc, 'source' => 'block:'.$block['id'], 'class' => 'mt-6', 'showTitle' => false])
</div>
</div>
@endif
@if (! empty($c['showDetails']) || ! empty($c['showMap']))
<div class="{{ ! empty($c['showForm']) ? 'space-y-8 lg:col-span-5' : 'grid gap-8 lg:col-span-12 lg:grid-cols-2' }}">
@if (! empty($c['showDetails']))
<div>
<h3 class="font-display text-h3 font-semibold text-heading">{{ t('contactBlock.detailsTitle') }}</h3>
<ul class="mt-5 space-y-4">
<li class="flex gap-4">{!! $detailsIcon('Mail') !!}<span><span class="block text-small text-fg-muted">{{ t('contactBlock.email') }}</span>{{ \App\View\Ui::fact($contact['email'] ?? null, $demo, $toConfirm, fn ($v) => new \Illuminate\Support\HtmlString('<a href="mailto:'.e($v).'" class="font-semibold break-all text-himalaya underline underline-offset-4">'.e($v).'</a>')) }}</span></li>
@if (! empty($contact['phone']))
<li class="flex gap-4">{!! $detailsIcon('Phone') !!}<span><span class="block text-small text-fg-muted">{{ t('contactBlock.phone') }}</span>{{ \App\View\Ui::fact($contact['phone'], $demo, $toConfirm, fn ($v) => new \Illuminate\Support\HtmlString('<a href="tel:'.e(preg_replace('/[^\d+]/', '', $v)).'" class="font-semibold text-himalaya underline underline-offset-4">'.e($v).'</a>')) }}</span></li>
@endif
@if ($address !== '' || $demo || \App\Settings\SettingsTodo::known($organization['registeredOffice'] ?? null))
<li class="flex gap-4">{!! $detailsIcon('MapPin') !!}<span><span class="block text-small text-fg-muted">{{ t('contactBlock.address') }}</span>@if ($address !== ''){{ \App\View\Ui::fact($address, $demo, $toConfirm, fn ($v) => new \Illuminate\Support\HtmlString('<span class="font-semibold text-fg">'.e($v).'</span>')) }}@else{{ \App\View\Ui::fact($organization['registeredOffice'] ?? null, $demo, $toConfirm) }}@endif</span></li>
@endif
</ul>
@if (count($socials))
<ul class="mt-6 flex gap-2" aria-label="{{ t('footer.social') }}">
@foreach ($socials as $l)
<li><a href="{{ $l['url'] }}" target="_blank" rel="noopener noreferrer" class="flex size-11 items-center justify-center rounded-full bg-white text-himalaya ring-1 ring-line hover:bg-himalaya hover:text-white">@partial('site.brand.social-icon', ['platform' => $l['platform'], 'class' => 'size-5'])<span class="sr-only">{{ \App\View\Brand::SOCIAL_NAMES[$l['platform']] ?? '' }} {{ t('a11y.externalLink') }}</span></a></li>
@endforeach
</ul>
@endif
<dl class="mt-8 rounded-lg bg-cream p-5 text-small">
<div class="flex flex-wrap gap-x-2"><dt class="text-fg-muted">{{ t('footer.legalName') }}:</dt><dd class="font-medium">{{ \App\View\Ui::fact($organization['legalName'] ?? null, $demo, $toConfirm) }}</dd></div>
<div class="mt-1 flex flex-wrap gap-x-2"><dt class="text-fg-muted">{{ t('footer.taxCode') }}:</dt><dd class="font-medium">{{ \App\View\Ui::fact($organization['taxCode'] ?? null, $demo, $toConfirm) }}</dd></div>
</dl>
</div>
@endif
@if (! empty($c['showMap']))
@if ($map)
@partial('site.ui.map-embed', ['embedUrl' => $map['embed'], 'openUrl' => $map['open'], 'label' => t('contactBlock.mapTitle')])
@elseif ($demo)
<p class="rounded-lg border border-dashed border-line-strong p-5 text-small text-fg-muted">{{ t('contactBlock.mapMissing') }}</p>
@endif
@endif
</div>
@endif
</div>
</x-site.container>
</x-site.section>
