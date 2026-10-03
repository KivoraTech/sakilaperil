{{-- components/site/blocks/RichText.tsx --}}
@php($c = $block['content'])
@php($s = $block['settings'])
@php($size = ['narrow' => 'prose', 'normal' => 'content', 'wide' => 'wide'][$s['width']])
<x-site.section :id="$s['anchor'] ?? null" :surface="\App\View\Ui::surfaceOf($s['background'] ?? null)" block="richText">
<x-site.container :size="$size === 'content' ? 'prose' : ($size === 'wide' ? 'content' : 'prose')">
@partial('site.ui.section-intro', ['kicker' => $c['kicker'] ?? null, 'heading' => $c['heading'] ?? null, 'class' => 'mb-8'])
<div data-reveal="true">{{ \App\View\SiteRichText::content($c['body'] ?? null) }}</div>
</x-site.container>
</x-site.section>
