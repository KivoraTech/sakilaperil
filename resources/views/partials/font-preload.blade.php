{{--
    The latin subsets next/font preloaded in the reference app (files marked ".p." by next/font).
    Upright Fraunces (headings) and Inter (text) are on every page; the italic Fraunces accent is
    preloaded only when a page says it is above the fold (`$meta['preloadItalic']`, the home hero).
--}}
@foreach (array_filter([
    'resources/fonts/791bf8c4bb753ed6-s.p.0ggqn9ig56u4s.woff2',
    ! empty($meta['preloadItalic']) ? 'resources/fonts/8010b10db0d5feb2-s.p.1q-jjomgjvbjf.woff2' : null,
    'resources/fonts/83afe278b6a6bb3c-s.p.2bn3s6zvc0dyp.woff2',
]) as $font)
    <link rel="preload" href="{{ Vite::asset($font) }}" as="font" type="font/woff2" crossorigin>
@endforeach
