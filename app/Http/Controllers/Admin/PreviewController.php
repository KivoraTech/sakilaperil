<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\I18n\Locales;
use App\Models\User;
use App\Queries\Admin\PagesQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Draft preview of one page+locale (reference app/preview/[locale]/pages/[pageId]). Requires a
 * signed-in user with `pages.view` (route middleware); anonymous visitors go to the login page.
 * Renders the DRAFT sections with the real public block renderer inside the site chrome.
 *
 * `?draft=<clientId>` renders the UNSAVED editor state staged by that editor tab (live preview),
 * falling back to the saved draft. `?embed=1` is the editor's side-by-side iframe: no banner,
 * links/forms disabled, refreshed by postMessage (resources/js/preview-bridge.ts).
 */
final class PreviewController extends Controller
{
    public function page(Request $request, string $locale, string $pageId): View
    {
        abort_unless(Locales::isLocale($locale), 404);
        /** @var User $user */
        $user = $request->user();
        $embed = $request->query('embed') === '1';
        $clientId = is_string($request->query('draft')) ? (string) $request->query('draft') : null;

        $draft = PagesQuery::draftForPreview($user, $pageId, $locale);
        abort_if($draft === null, 404);
        $staged = $clientId !== null ? PagesQuery::stagedPreview($user, $pageId, $locale, $clientId) : null;
        $sections = $staged['sections'] ?? $draft['sections'];
        $title = ($staged['title'] ?? '') !== '' ? $staged['title'] : $draft['title'];

        $hiddenCount = count(array_filter($sections, fn ($s) => ! ($s['isVisible'] ?? true)));
        $liveState = match (true) {
            $staged !== null => 'Unsaved changes',
            $draft['publishedRevisionId'] === null => 'Never published in this language',
            $draft['hasDraftChanges'] => 'Differs from the live version',
            default => 'Same as the live version',
        };

        return view('preview.page', [
            'locale' => $locale,
            'pageId' => $pageId,
            'embed' => $embed,
            'title' => $title,
            'sections' => $sections,
            'hiddenCount' => $hiddenCount,
            'liveState' => $liveState,
            'stamp' => $staged['updatedAt'] ?? $draft['updatedAt'] ?? '',
            'staged' => $staged !== null,
            'meta' => [
                'title' => 'Preview · '.(($title !== '' ? $title : null) ?? 'Page'),
                'robots' => 'noindex, nofollow, nocache',
            ],
        ]);
    }
}
