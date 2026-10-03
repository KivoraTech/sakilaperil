<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Auth\Permissions;
use App\Http\Controllers\Controller;
use App\I18n\Locales;
use App\Models\User;
use App\Queries\Admin\PagesQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/** Pages list + page editor (reference app/admin/(shell)/pages/**). */
final class PagesController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $params = PagesQuery::params($request->query());
        $list = PagesQuery::list($user, $params);

        return Inertia::render('admin/pages/index', [
            'params' => $params,
            ...$list,
            'openCreate' => $request->query('new') === '1',
            'canCreate' => Permissions::can($user->role, 'pages.create'),
        ]);
    }

    public function edit(Request $request, string $id): Response|SymfonyResponse
    {
        /** @var User $user */
        $user = $request->user();
        $locale = Locales::isLocale($request->query('locale')) ? (string) $request->query('locale') : 'it';
        $data = PagesQuery::editor($user, $id, $locale);
        if ($data === null) {
            return AdminNotFound::response($request, 'page', '/admin/pages', 'Back to pages');
        }

        $references = null;
        $linkSuggestions = [];
        if ($data['draft'] !== null) {
            $references = PagesQuery::references($user, $locale);
            $linkSuggestions = PagesQuery::linkSuggestions($user, $locale);
        }

        return Inertia::render('admin/pages/edit', [
            'id' => $id,
            'locale' => $locale,
            'data' => $data,
            'references' => $references,
            'linkSuggestions' => $linkSuggestions,
            'permissions' => [
                'canPublish' => Permissions::can($user->role, 'pages.publish'),
                'canDelete' => Permissions::can($user->role, 'pages.delete'),
                'canCreate' => Permissions::can($user->role, 'pages.create'),
            ],
        ]);
    }
}
