{{-- <MembershipForm>: @include('engagement.membership-form', ['locale' => $locale, 'source' => …, 'class' => …, 'showTitle' => …]) --}}
@include('engagement._assets')
{!! \App\Engagement\View\Components::submissionForm('membership', $locale, $source ?? null, $class ?? null, $showTitle ?? true) !!}
