<?php

namespace App\Http\Controllers;

use App\Domains\Orders\Actions\PlaceOrder;
use App\Domains\Orders\Services\CartManager;
use App\Domains\Orders\Services\CheckoutQuote;
use App\Http\Requests\Commerce\CheckoutRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function index(Request $request, CartManager $carts, CheckoutQuote $quotes): Response|RedirectResponse
    {
        try {
            return Inertia::render('commerce/checkout', ['quote' => $quotes->calculate($carts->current($request->user()))]);
        } catch (ValidationException $exception) {
            return to_route('cart.index')->withErrors($exception->errors());
        }
    }

    public function store(CheckoutRequest $request, PlaceOrder $checkout): RedirectResponse
    {
        /** @var array{recipient: string, phone: string, province: string, city: string, district: string, postal_code: string, address: string} $address */
        $address = $request->safe()->only(['recipient', 'phone', 'province', 'city', 'district', 'postal_code', 'address']);
        $order = $checkout->handle($request->user(), $address, $request->string('quote_fingerprint')->toString(), $request->string('idempotency_key')->toString(), $request->cookie('honey_referral') ?: $request->session()->get('honey_referral'), $request->string('shipping_quote_token')->toString() ?: null);

        return to_route('orders.show', $order);
    }
}
