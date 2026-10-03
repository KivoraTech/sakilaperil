{{--
    components/site/ui/heading.tsx SectionIntro: kicker + heading (+ italic accent) + lead.
    Params: $kicker, $heading, $accent, $lead, $id, $align ('start'|'center'), $as ('h2'), $size ('h2'), $class.
--}}
@php($siAlign = $align ?? 'start')
@php($siAs = $as ?? 'h2')
@if (! empty($kicker) || ! empty($heading) || ! empty($lead))
<div class="{{ \App\View\Html::cx('max-w-3xl', $siAlign === 'center' ? 'mx-auto text-center [&_.kicker]:justify-center' : null, $class ?? null) }}" data-reveal="true">
@if (! empty($kicker))
<p class="kicker">{{ $kicker }}</p>
@endif
@if (! empty($heading))
@partial('site.ui.heading', ['as' => $siAs, 'size' => $size ?? 'h2', 'text' => $heading, 'accent' => $accent ?? null, 'id' => $id ?? null, 'class' => ! empty($kicker) ? 'mt-4' : ''])
@endif
@if (! empty($lead))
<p class="{{ \App\View\Html::cx('mt-5 max-w-[36rem] text-lead text-fg-muted', $siAlign === 'center' ? 'mx-auto' : null) }}">{{ $lead }}</p>
@endif
</div>
@endif
