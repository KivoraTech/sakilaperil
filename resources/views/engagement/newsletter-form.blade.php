{{-- <NewsletterForm>: @include('engagement.newsletter-form', ['locale' => $locale, 'source' => 'footer', 'variant' => 'inline'|'stacked', 'tone' => 'light'|'dark', 'class' => …]) --}}
@include('engagement._assets')
{!! \App\Engagement\View\Components::newsletterForm($locale, $source ?? null, $variant ?? 'inline', $tone ?? 'light', $class ?? null) !!}
