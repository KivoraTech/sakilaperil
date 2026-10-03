{{-- app/[locale]/(site)/donate/cancelled/page.tsx: after a cancelled/failed checkout. Never changes data. --}}
@extends('layouts.site')

@section('content')
@partial('site.ui.page-header', ['title' => t('donate.cancelledTitle'), 'lead' => t('donate.cancelledBody'), 'children' => new \Illuminate\Support\HtmlString('<div class="mt-8 flex flex-wrap gap-3">'.\App\View\Ui::buttonLink('/donate', t('donate.tryAgain'), arrow: true)->toHtml().'</div>')])
<x-site.container size="content" class="py-section-sm">
<h2 class="mb-4 font-display text-h3 font-semibold text-heading">{{ t('donate.useBank') }}</h2>
@partialIf('engagement.bank-transfer-panel', ['locale' => $locale])
</x-site.container>
@endsection
