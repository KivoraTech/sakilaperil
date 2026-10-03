{{--
    components/site/blocks/InvolveForms.tsx: path chooser (jump links) + one card per form
    (ENGAGEMENT: engagement.volunteer-form / membership-form / partner-form).
--}}
@php($c = $block['content'])
@php($s = $block['settings'])
@php($loc = $context['locale'])
@php($settings = app(\App\Settings\SiteSettings::class)->get())
@php($meta = ['volunteer' => ['anchor' => 'volontariato', 'icon' => 'HandHeart', 'view' => 'engagement.volunteer-form'], 'membership' => ['anchor' => 'socio', 'icon' => 'Users', 'view' => 'engagement.membership-form'], 'partner' => ['anchor' => 'partner-form', 'icon' => 'Handshake', 'view' => 'engagement.partner-form']])
<x-site.section :id="$s['anchor'] ?? null" :surface="\App\View\Ui::surfaceOf($s['background'] ?? null)" block="involveForms">
<x-site.container>
@partial('site.ui.section-intro', ['kicker' => $c['kicker'] ?? null, 'heading' => $c['heading'] ?? null, 'accent' => $c['headingAccent'] ?? null, 'lead' => $c['lead'] ?? null])
@if (count($c['forms']) > 1)
<nav aria-label="{{ t('involve.chooser') }}" class="mt-10">
<ul class="grid gap-4 sm:grid-cols-3">
@foreach ($c['forms'] as $f)
<li><a href="#{{ $meta[$f]['anchor'] }}" class="group flex h-full items-start gap-4 rounded-lg bg-white p-5 shadow-card ring-1 ring-line/70 transition-[box-shadow,transform,translate] hover:shadow-raised motion:hover:-translate-y-1"><span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-sage-50 text-sage-700">{!! \App\View\Icon::svg($meta[$f]['icon'], 'size-6') !!}</span><span><span class="block font-display text-xl font-semibold text-heading">{{ t('involve.'.$f.'.title') }}</span><span class="mt-1 block text-small text-fg-muted">{{ t('involve.'.$f.'.teaser') }}</span></span></a></li>
@endforeach
</ul>
</nav>
@endif
<div class="mt-12 space-y-8">
@foreach ($c['forms'] as $f)
@php($ifIntro = \App\I18n\Locales::pick($settings['formMessages'][$f]['intro'] ?? null, $loc))
@php($ifIntro = $ifIntro !== '' ? $ifIntro : t('involve.'.$f.'.intro'))
<section id="{{ $meta[$f]['anchor'] }}" aria-labelledby="{{ $meta[$f]['anchor'] }}-title" class="grid scroll-mt-28 gap-8 rounded-xl bg-white p-6 shadow-card ring-1 ring-line/70 md:p-10 lg:grid-cols-12">
<div class="lg:col-span-4">
<span class="flex size-14 items-center justify-center rounded-full bg-cream text-sindoor">{!! \App\View\Icon::svg($meta[$f]['icon'], 'size-7') !!}</span>
<h3 id="{{ $meta[$f]['anchor'] }}-title" class="mt-5 font-display text-h3 font-semibold text-heading">{{ t('involve.'.$f.'.title') }}</h3>
<p class="mt-3 text-fg-muted">{{ \App\View\Ui::todoText($ifIntro) }}</p>
</div>
<div class="lg:col-span-8">@partialIf($meta[$f]['view'], ['locale' => $loc, 'source' => 'block:'.$block['id'], 'showTitle' => false])</div>
</section>
@endforeach
</div>
</x-site.container>
</x-site.section>
