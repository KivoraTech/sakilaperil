{{-- components/site/blocks/Services.tsx: projects highlight — native rail or grid + optional end card. --}}
@php($c = $block['content'])
@php($s = $block['settings'])
@php($loc = $context['locale'])
@php($projects = ($c['source'] === 'manual' && count($c['projectIds'] ?? [])) ? \App\Queries\Public\Projects::list($loc, ['ids' => $c['projectIds']]) : \App\Queries\Public\Projects::list($loc, ['featuredOnly' => $c['source'] === 'featured', 'limit' => (int) $c['limit']]))
@if (! count($projects) && $c['source'] === 'featured')
@php($projects = \App\Queries\Public\Projects::list($loc, ['limit' => (int) $c['limit']]))
@endif
@php($projects = array_slice($projects, 0, (int) $c['limit']))
@php($endCard = ! empty($c['endCard']) ? view('site.blocks.partials.services-end-card', ['card' => $c['endCard']])->render() : null)
@php($allLink = \App\View\Ui::buttonLink('/projects', t('cta.allProjects'), variant: 'secondary', arrow: true))
<x-site.section :id="$s['anchor'] ?? null" :surface="\App\View\Ui::surfaceOf($s['background'] ?? null)" block="services">
<x-site.container>
<div class="flex flex-wrap items-end justify-between gap-6">
@partial('site.ui.section-intro', ['kicker' => $c['kicker'] ?? null, 'heading' => $c['heading'] ?? null, 'accent' => $c['headingAccent'] ?? null, 'lead' => $c['lead'] ?? null])
@if ($s['layout'] === 'grid')
{{ $allLink }}
@endif
</div>
@if ($s['layout'] === 'carousel')
@php($railItems = '')
@foreach ($projects as $p)
@php($railItems .= '<div class="flex w-[85%] shrink-0 sm:w-[46%] lg:w-[31.5%]">'.view('site.cards.project', ['project' => $p, 'locale' => $loc, 'class' => 'w-full'])->render().'</div>')
@endforeach
@if ($endCard)
@php($railItems .= '<div class="flex w-[85%] shrink-0 sm:w-[46%] lg:w-[31.5%] [&>*]:w-full">'.$endCard.'</div>')
@endif
@partial('site.ui.rail', ['label' => $c['heading'] ?? '', 'class' => 'mt-8', 'slot' => new \Illuminate\Support\HtmlString($railItems)])
@else
<div class="mt-10 grid gap-6 {{ \App\View\Ui::cardGrid(count($projects) + ($endCard ? 1 : 0)) }}">
@foreach ($projects as $i => $p)
<div data-reveal="true" style="--reveal-i:{{ $i % 3 }}" class="flex [&>*]:w-full">@partial('site.cards.project', ['project' => $p, 'locale' => $loc])</div>
@endforeach
{!! $endCard !!}
</div>
@endif
@if ($s['layout'] === 'carousel')
<div class="mt-2">{{ $allLink }}</div>
@endif
</x-site.container>
</x-site.section>
