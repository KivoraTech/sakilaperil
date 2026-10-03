{{--
    <DonateWidget>: @include('engagement.donate-widget', ['locale' => $locale, 'source' => 'donate-page',
        'projectId' => …, 'campaignId' => …, 'presetAmounts' => [10, 25, 50, 100], 'defaultAmount' => 25,
        'showDestination' => true, 'class' => …, 'panelHeadingLevel' => 2|3|4 (level of the bank panel title, default 3)])
--}}
@include('engagement._assets')
{!! \App\Engagement\View\Components::donateWidget($locale, [
    'projectId' => $projectId ?? null,
    'campaignId' => $campaignId ?? null,
    'presetAmounts' => $presetAmounts ?? null,
    'defaultAmount' => $defaultAmount ?? null,
    'showDestination' => $showDestination ?? null,
    'source' => $source ?? null,
    'className' => $class ?? null,
    'panelHeadingLevel' => $panelHeadingLevel ?? null,
]) !!}
