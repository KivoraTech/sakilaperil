{{--
    components/site/blocks/Hero.tsx: photo hero. As the FIRST block it slides under the sticky header
    (data-header-overlay), gets the LCP priority image and the one-shot motion (CSS only).
--}}
@php($c = $block['content'])
@php($s = $block['settings'])
@php($first = $context['index'] === 0)
@php($isHome = $s['height'] === 'full')
@php($image = $context['assets'][$c['image']['assetId']] ?? null)
@php($project = ! empty($c['highlightProjectId']) ? (\App\Queries\Public\Projects::list($context['locale'], ['ids' => [$c['highlightProjectId']]])[0] ?? null) : null)
@php($heights = ['full' => 'min-h-[max(34rem,88svh)]', 'large' => 'min-h-[max(30rem,70svh)]', 'medium' => 'min-h-[max(24rem,52svh)]'])
@php($overlays = ['dark' => 'from-deep/90 via-deep/55 to-deep/20', 'medium' => 'from-deep/85 via-deep/45 to-deep/10', 'light' => 'from-deep/80 via-deep/35 to-transparent'])
<section{{ \App\View\Html::attrs(['id' => $s['anchor'] ?? null, 'data-block' => 'hero', 'data-header-overlay' => $first ? '' : null, 'data-hero-scroll' => $first ? '' : null, 'class' => \App\View\Html::cx('surface-dark relative isolate flex overflow-hidden', $heights[$s['height']], $first ? '-mt-16 md:-mt-18' : null)]) }}>
<div class="hero-parallax absolute inset-0 -z-10">
<div class="{{ \App\View\Html::cx('absolute inset-0', $first ? 'hero-zoom' : null) }}">
{{ \App\View\Img::media($image, sizes: '100vw', alt: $c['image']['alt'] ?? $image['alt'] ?? null, priority: $first, quality: 70, class: 'brightness-[.92] saturate-[.95]') }}
</div>
</div>
<div class="{{ \App\View\Html::cx('absolute inset-0 -z-10 bg-gradient-to-t md:bg-gradient-to-r', $overlays[$s['overlay']]) }}" aria-hidden="true"></div>
<div class="absolute inset-x-0 top-0 -z-10 h-40 bg-gradient-to-b from-deep/70 to-transparent" aria-hidden="true"></div>
@if ($first && $isHome)
@partial('site.brand.prayer-flags', ['class' => 'absolute top-[4.875rem] -left-[2%] h-[clamp(4.375rem,9vw,7.5rem)] w-[104%] opacity-95 md:top-[5.375rem]'])
@endif
<div class="{{ \App\View\Html::cx('hero-fade mx-auto flex w-full max-w-bleed flex-col justify-end gap-10 px-gutter pb-14 md:pb-20 lg:flex-row lg:items-end lg:justify-between', $first ? 'pt-32' : 'pt-20') }}">
<div class="max-w-4xl"{!! $first ? '' : ' data-reveal=""' !!}>
@if (! empty($c['kicker']))
<p class="{{ \App\View\Html::cx('kicker text-gold', $first ? 'hero-rise' : null) }}" style="--d:300ms">{{ $c['kicker'] }}</p>
@endif
@if ($first)
<h1 class="{{ \App\View\Html::cx('mt-5 font-display text-white', $isHome ? 'text-display font-medium' : 'text-h1 font-semibold') }}">@partial('site.motion.masked-heading', ['heading' => $c['heading'], 'accent' => $c['headingAccent'] ?? null])</h1>
@else
<h2 class="mt-5 font-display text-h1 font-semibold text-white">{{ $c['heading'] }}@if (! empty($c['headingAccent'])) <em class="heading-accent">{{ $c['headingAccent'] }}</em>@endif</h2>
@endif
@if (! empty($c['subheading']))
<p class="{{ \App\View\Html::cx('mt-6 max-w-[38rem] text-lead text-white/90', $first ? 'hero-rise' : null) }}" style="--d:900ms">{{ $c['subheading'] }}</p>
@endif
@if (! empty($c['primaryCta']) || ! empty($c['secondaryCta']))
<div class="{{ \App\View\Html::cx('mt-9 flex flex-wrap gap-3', $first ? 'hero-rise' : null) }}" style="--d:1050ms">
@if (! empty($c['primaryCta']))
{{ \App\View\Ui::buttonLink($c['primaryCta']['href'], $c['primaryCta']['label'], size: 'lg', arrow: true) }}
@endif
@if (! empty($c['secondaryCta']))
{{ \App\View\Ui::buttonLink($c['secondaryCta']['href'], $c['secondaryCta']['label'], variant: 'ghost-light', size: 'lg') }}
@endif
</div>
@endif
</div>
@if ($project)
<div class="{{ \App\View\Html::cx('w-full max-w-sm shrink-0 rounded-lg bg-deep/60 p-5 ring-1 ring-white/20 backdrop-blur-md lg:mb-1', $first ? 'hero-side' : null) }}" style="--d:1150ms">
<p class="text-kicker font-semibold tracking-[0.14em] text-gold uppercase">{{ t('hero.featuredProject') }}</p>
<p class="mt-2 font-display text-xl leading-snug text-white"><a class="link-sweep" href="{{ lurl(\App\I18n\Routes::project($project['slug'])) }}">{{ $project['title'] }}</a></p>
@if (! empty($project['goalAmount']))
@partial('site.ui.progress-bar', ['class' => 'mt-4', 'raised' => $project['raisedAmount'] ?? 0, 'goal' => $project['goalAmount'], 'currency' => $project['currency'], 'locale' => $context['locale']])
@elseif (! empty($project['district']))
<p class="mt-1 text-small text-mist">{{ $project['district'] }}</p>
@endif
</div>
@endif
</div>
@if ($first && $isHome)
<div class="absolute bottom-5 left-1/2 hidden -translate-x-1/2 justify-items-center gap-2 text-[0.6875rem] font-semibold tracking-[0.25em] text-mist uppercase lg:grid" aria-hidden="true" data-loop="true">
<span>{{ t('hero.scroll') }}</span>
<i class="scroll-cue-line block h-10 w-[1.5px] bg-gradient-to-b from-gold to-transparent"></i>
</div>
@endif
</section>
