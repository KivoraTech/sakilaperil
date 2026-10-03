@extends('layouts.document')
@section('document')
<main id="main" class="mx-auto max-w-3xl space-y-16 p-8">
    <section id="contact">@include('engagement.contact-form', ['locale' => $locale, 'source' => 'sandbox', 'class' => 'mt-6', 'showTitle' => false])</section>
    <section id="volunteer">@include('engagement.volunteer-form', ['locale' => $locale])</section>
    <section id="newsletter">@include('engagement.newsletter-form', ['locale' => $locale, 'source' => 'sandbox'])</section>
    <section id="newsletter-dark" class="bg-deep p-6">@include('engagement.newsletter-form', ['locale' => $locale, 'source' => 'footer', 'tone' => 'dark', 'class' => 'mt-4'])</section>
    <section id="donate">@include('engagement.donate-widget', ['locale' => $locale, 'source' => 'sandbox'])</section>
    @if ($token)
        <section id="unsubscribe">@include('engagement.unsubscribe-form', ['locale' => $locale, 'token' => $token])</section>
    @endif
</main>
@endsection
