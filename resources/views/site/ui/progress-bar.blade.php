{{--
    components/site/ui/progress-bar.tsx: fundraising progress (amounts in MINOR units). Only when a goal
    exists. Params: $raised, $goal, $currency, $locale, $class, $compact.
--}}
@if (! empty($goal) && $goal > 0)
@php($pbPct = max(0, (int) round(($raised / $goal) * 100)))
@php($pbFill = min(100, $pbPct))
@php($pbRaised = \App\I18n\Format::money($raised, $currency, $locale))
@php($pbGoal = \App\I18n\Format::money($goal, $currency, $locale))
<div class="{{ \App\View\Html::cx('w-full', $class ?? null) }}" data-reveal="true">
<div class="flex items-baseline justify-between gap-3 text-small">
<span class="font-semibold text-fg">{{ t('progress.raised', ['amount' => $pbRaised]) }}</span>
<span class="font-semibold text-sage-700 tabular-nums [.surface-dark_&]:text-gold">{{ $pbPct }}%</span>
</div>
<div role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $pbFill }}" aria-valuetext="{{ t('progress.valueText', ['raised' => $pbRaised, 'goal' => $pbGoal, 'pct' => $pbPct]) }}" aria-label="{{ t('progress.label') }}" class="{{ \App\View\Html::cx('mt-2 overflow-hidden rounded-full bg-cream [.surface-dark_&]:bg-white/15', ! empty($compact) ? 'h-2' : 'h-2.5') }}">
<div class="progress-fill h-full rounded-full bg-gradient-to-r from-marigold to-sindoor" style="transform:scaleX({{ \App\View\Html::num($pbFill / 100) }})"></div>
</div>
@if (empty($compact))
<p class="mt-2 flex items-center gap-2 text-small text-fg-muted">
@if ($pbPct >= 100)
{!! \App\View\Icon::svg('PartyPopper', 'size-4 text-accent-text') !!}
{{ t('progress.reached') }}
@else
{{ t('progress.goal', ['amount' => $pbGoal]) }}
@endif
</p>
@endif
</div>
@endif
