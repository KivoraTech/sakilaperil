{{-- brand/social-icons.tsx: simple geometric social glyphs. Params: $platform, $class. --}}
@switch($platform)
@case('facebook')
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="{{ $class }}"><path d="M15 3h-2.5A3.5 3.5 0 0 0 9 6.5V10H6.5v3.5H9V21h3.5v-7.5H15l.5-3.5h-3V7a1 1 0 0 1 1-1H15z"></path></svg>
@break
@case('instagram')
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="{{ $class }}"><rect x="3" y="3" width="18" height="18" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><circle cx="17.5" cy="6.5" r="0.6" fill="currentColor"></circle></svg>
@break
@case('youtube')
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="{{ $class }}"><rect x="2.5" y="5.5" width="19" height="13" rx="4"></rect><path d="m10 9.5 5 2.5-5 2.5z" fill="currentColor"></path></svg>
@break
@case('linkedin')
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="{{ $class }}"><rect x="3" y="3" width="18" height="18" rx="3"></rect><path d="M8 10.5V17M8 7.5v.01M12 17v-6.5M12 13.5a2.5 2.5 0 0 1 5 0V17"></path></svg>
@break
@case('x')
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="{{ $class }}"><path d="M4 4l16 16M20 4 4 20"></path></svg>
@break
@case('tiktok')
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="{{ $class }}"><path d="M14 3v11.5a3.5 3.5 0 1 1-3.5-3.5M14 3c.5 2.5 2.5 4.5 5 5"></path></svg>
@break
@endswitch
