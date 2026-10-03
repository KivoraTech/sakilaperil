{{-- <UnsubscribeForm token>: @include('engagement.unsubscribe-form', ['locale' => $locale, 'token' => $token]) --}}
@include('engagement._assets')
{!! \App\Engagement\View\Components::unsubscribeForm($locale, $token) !!}
