<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Queries\Admin\DashboardQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Dashboard (reference app/admin/(shell)/page.tsx). */
final class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('admin/dashboard', ['data' => DashboardQuery::data($user)]);
    }
}
