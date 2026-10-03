{{-- app/[locale]/(site)/newsletter/page.tsx (forms: ENGAGEMENT newsletter-form / unsubscribe-form). --}}
@extends('layouts.site')

@section('content')
@partial('site.ui.page-header', ['title' => $unsubscribe ? t('newsletter.unsubscribeTitle') : t('newsletter.pageTitle'), 'lead' => $unsubscribe ? null : t('newsletter.pageIntro')])
<x-site.container size="prose" class="py-section-sm">
@if ($status)
<p role="status" class="{{ $status === 'confirmed' ? 'mb-8 flex items-start gap-3 rounded-[14px] bg-success-bg p-4 text-success' : 'mb-8 flex items-start gap-3 rounded-[14px] bg-warning-bg p-4 text-warning' }}">{!! \App\View\Icon::svg($status === 'confirmed' ? 'CheckCircle2' : 'AlertTriangle', 'mt-0.5 size-5 shrink-0') !!}{{ t('newsletter.'.$status) }}</p>
@endif
@if ($unsubscribe)
@partialIf('engagement.unsubscribe-form', ['locale' => $locale, 'token' => $unsubscribe])
@elseif ($status !== 'confirmed')
@partialIf('engagement.newsletter-form', ['locale' => $locale, 'source' => 'newsletter-page', 'variant' => 'inline'])
@php(\App\View\Blocks::markEndsWithNewsletter())
@endif
</x-site.container>
@endsection
