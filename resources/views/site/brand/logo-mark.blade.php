{{--
    The association's mark. Comes from Admin → Settings → Look & feel (logo); until one is chosen, the
    bundled mark cut from the client's logo is used.
--}}
@php($logoSrc = \App\View\Brand::images(app(\App\Settings\SiteSettings::class)->get())['logo'])
@php($logoSmall = str_starts_with($logoSrc, '/uploads/') && ! \App\View\Img::isUnoptimized($logoSrc))
<img src="{{ $logoSmall ? \App\View\Img::url($logoSrc, 128, 75) : $logoSrc }}" @if ($logoSmall) srcset="{{ \App\View\Img::url($logoSrc, 64, 75) }} 1x, {{ \App\View\Img::url($logoSrc, 128, 75) }} 2x" @endif alt="" aria-hidden="true" width="256" height="256" decoding="async" class="{{ \App\View\Html::cx('shrink-0 object-contain', $class ?? null) }}">
