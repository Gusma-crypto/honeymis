<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Orders\Models\ShippingSetting;
use App\Domains\Orders\Services\ShippingQuotes;
use App\Domains\Orders\Services\ShippingSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShippingSettingsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ShippingSettingsController extends Controller
{
    public function update(ShippingSettingsRequest $request, ShippingSettings $settings): RedirectResponse
    {
        $settings->save($request->user(), $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengaturan ongkir disimpan. Berlaku untuk checkout berikutnya.']);

        return to_route('admin.commerce-settings');
    }

    public function destinations(Request $request, ShippingQuotes $shipping): JsonResponse
    {
        Gate::authorize('manage', ShippingSetting::class);
        $request->validate(['search' => ['required', 'string', 'min:3', 'max:100']]);

        return response()->json(['destinations' => $shipping->destinations($request->user(), $request->string('search')->toString(), true)]);
    }
}
