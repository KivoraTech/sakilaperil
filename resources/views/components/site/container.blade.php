{{-- components/site/ui/section.tsx Container. --}}
@props(['size' => 'wide', 'class' => null])
<div class="{{ \App\View\Html::cx('mx-auto w-full px-gutter', ['prose' => 'max-w-prose', 'content' => 'max-w-content', 'wide' => 'max-w-wide', 'bleed' => 'max-w-bleed'][$size], $class) }}">{{ $slot }}</div>
