{{-- <PartnerForm>: @include('engagement.partner-form', ['locale' => $locale, 'source' => …, 'class' => …, 'showTitle' => …]) --}}
@include('engagement._assets')
{!! \App\Engagement\View\Components::submissionForm('partner', $locale, $source ?? null, $class ?? null, $showTitle ?? true) !!}
