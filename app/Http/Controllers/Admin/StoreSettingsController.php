<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Shared\Services\StoreSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateStoreSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StoreSettingsController extends Controller
{
    public function update(UpdateStoreSettingsRequest $request, StoreSettings $settings): RedirectResponse
    {
        $settings->save($request->user(), $request->validated());

        return to_route('admin.commerce-settings')->with('success', 'Pengaturan berhasil disimpan.');
    }

    public function show(string $kind, StoreSettings $settings): StreamedResponse
    {
        abort_unless(in_array($kind, ['logo', 'favicon'], true), 404);
        $path = $settings->current()[$kind.'_path'];
        abort_unless(is_string($path) && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, headers: ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'public, max-age=3600']);
    }
}
