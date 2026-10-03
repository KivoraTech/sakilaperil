<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\I18n\Format;
use App\I18n\Locales;
use App\I18n\Routes;
use App\Queries\Public\Events;
use App\Settings\SettingsTodo;
use App\View\Seo;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/** app/[locale]/(site)/events/page.tsx, events/[slug]/page.tsx and events/[slug]/ics/route.ts. */
final class EventsController extends SiteController
{
    public function index(): Response
    {
        $locale = $this->locale();
        $upcoming = Events::list($locale, ['when' => 'upcoming', 'limit' => 50]);
        $past = Events::list($locale, ['when' => 'past', 'limit' => 24]);
        $groups = [];
        foreach ($upcoming as $e) {
            $key = Format::date($e['startsAt'], $locale, ['month' => 'long', 'year' => 'numeric'], $e['timezone']);
            $groups[$key][] = $e;
        }

        return response()->view('site.pages.events.index', [
            'locale' => $locale,
            'groups' => $groups,
            'upcoming' => $upcoming,
            'past' => $past,
            'meta' => $this->meta(Seo::sameAlternates('/events'), t('events.title'), t('events.lead')),
        ]);
    }

    public function show(string $locale, string $slug): Response
    {
        $event = Events::get($locale, $slug);
        if ($event === null) {
            return $this->notFound();
        }
        $tz = $event['timezone'] ?: 'Europe/Rome';
        $isPast = Carbon::parse($event['endsAt'] ?? $event['startsAt'])->lessThan(Carbon::now());
        $path = Routes::event($event['slug']);
        $safeHttps = fn (?string $u): ?string => $u !== null && preg_match('/^https:\/\//', $u) === 1 && ! SettingsTodo::isTodo($u) ? $u : null;
        $tzParts = explode('/', $tz);
        $tzName = str_replace('_', ' ', (string) end($tzParts));

        return response()->view('site.pages.events.show', [
            'locale' => $locale,
            'event' => $event,
            'isPast' => $isPast,
            'path' => $path,
            'url' => Seo::absoluteUrl(Seo::localePath($locale, $path)),
            'dateLong' => Format::date($event['startsAt'], $locale, ['weekday' => 'long', 'day' => 'numeric', 'month' => 'long', 'year' => 'numeric'], $tz),
            'timeRange' => $event['allDay'] ? t('events.allDay') : Format::time($event['startsAt'], $locale, $tz).($event['endsAt'] ? '–'.Format::time($event['endsAt'], $locale, $tz) : ''),
            'tzName' => $tzName,
            'registration' => $safeHttps($event['registrationUrl']),
            'online' => $safeHttps($event['onlineUrl']),
            'map' => $safeHttps($event['mapUrl']),
            'settings' => $this->settings(),
            'alternates' => $event['alternates'],
            'meta' => $this->meta($event['alternates'], $event['seoTitle'] ?? $event['title'], $event['seoDescription'] ?? $event['summary'], $event['coverImage']),
        ]);
    }

    /** iCalendar (.ics) download for a published event (only published data). */
    public function ics(string $locale, string $slug): Response
    {
        $e = Locales::isLocale($locale) ? Events::get($locale, $slug) : null;
        if ($e === null) {
            return response('Not found', 404);
        }
        $url = Seo::siteUrl().'/'.$locale.Routes::event($e['slug']);
        $start = Carbon::parse($e['startsAt'])->utc();
        $end = $e['endsAt'] ? Carbon::parse($e['endsAt'])->utc() : $start->copy()->addHours(2);
        $esc = fn (string $v): string => preg_replace('/([,;])/', '\\\\$1', str_replace(['\\', "\r\n", "\n"], ['\\\\', '\\n', '\\n'], $v)) ?? $v;
        $stamp = fn (Carbon $d): string => $d->copy()->utc()->format('Ymd\THis\Z');
        $day = fn (Carbon $d): string => $d->copy()->utc()->format('Ymd');
        $lines = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Sakila per il Nepal//Events//IT', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'BEGIN:VEVENT',
            'UID:'.$e['id'].'@sakila',
            'DTSTAMP:'.$stamp(Carbon::now()),
            ...($e['allDay']
                ? ['DTSTART;VALUE=DATE:'.$day($start), 'DTEND;VALUE=DATE:'.$day($end->copy()->addDay())]
                : ['DTSTART:'.$stamp($start), 'DTEND:'.$stamp($end)]),
            'SUMMARY:'.$esc($e['title']),
            $e['summary'] ? 'DESCRIPTION:'.$esc($e['summary']."\n\n".$url) : 'DESCRIPTION:'.$esc($url),
            ...(($e['locationName'] ?? null) || ($e['address'] ?? null) ? ['LOCATION:'.$esc(implode(', ', array_filter([$e['locationName'], $e['address']])))] : []),
            'URL:'.$url,
            'END:VEVENT', 'END:VCALENDAR',
        ];
        // Fold lines at 75 octets (RFC 5545 §3.1), approximated by characters.
        $fold = function (string $line): string {
            $out = [];
            $len = mb_strlen($line);
            for ($i = 0; $i < $len; $i += 73) {
                $out[] = ($i ? ' ' : '').mb_substr($line, $i, 73);
            }

            return implode("\r\n", $out);
        };

        return response(implode("\r\n", array_map($fold, $lines))."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$e['slug'].'.ics"',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
