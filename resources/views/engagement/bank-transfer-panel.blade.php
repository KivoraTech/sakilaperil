{{-- <BankTransferPanel>: @include('engagement.bank-transfer-panel', ['locale' => $locale, 'class' => …, 'headingLevel' => 2|3|4 (default 3)]) --}}
@include('engagement._assets')
{!! \App\Engagement\View\Components::bankTransferPanel($locale, $class ?? null, $headingLevel ?? 3) !!}
