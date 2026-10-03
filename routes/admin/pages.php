<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\PagesActionsController as Pages;
use Illuminate\Support\Facades\Route;

// Page builder actions. Loaded inside the authenticated /admin/actions group.
Route::post('pages/create', [Pages::class, 'create'])->middleware('capability:pages.create');
Route::post('pages/create-system', [Pages::class, 'createSystem'])->middleware('capability:pages.create');
Route::post('pages/create-translation', [Pages::class, 'createTranslation'])->middleware('capability:pages.create');
Route::post('pages/stage-preview', [Pages::class, 'stagePreview'])->middleware('capability:pages.editDraft');
Route::post('pages/save-draft', [Pages::class, 'saveDraft'])->middleware('capability:pages.editDraft');
Route::post('pages/publish', [Pages::class, 'publish'])->middleware('capability:pages.publish');
Route::post('pages/unpublish', [Pages::class, 'unpublish'])->middleware('capability:pages.publish');
Route::post('pages/restore', [Pages::class, 'restore'])->middleware('capability:pages.editDraft');
Route::post('pages/copy-sections', [Pages::class, 'copySections'])->middleware('capability:pages.editDraft');
Route::post('pages/delete', [Pages::class, 'delete'])->middleware('capability:pages.delete');
