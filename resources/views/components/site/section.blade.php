{{-- components/site/ui/section.tsx Section: a page band (surface colour + vertical rhythm). --}}
@props(['id' => null, 'surface' => 'paper', 'spacing' => 'default', 'class' => null, 'labelledBy' => null, 'block' => null])
<section{{ \App\View\Html::attrs(['id' => $id, 'aria-labelledby' => $labelledBy, 'data-block' => $block, 'class' => \App\View\Html::cx('relative', \App\View\Ui::SURFACE_CLASS[$surface], $spacing === 'default' ? 'py-section' : null, $spacing === 'compact' ? 'py-section-sm' : null, $class)]) }}>{{ $slot }}</section>
