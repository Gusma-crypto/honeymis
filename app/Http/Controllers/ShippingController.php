<?php

namespace App\Http\Controllers;

use App\Domains\Orders\Services\ShippingQuotes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingController extends Controller
{
    public function destinations(Request $request, ShippingQuotes $shipping): JsonResponse
    {
        $request->validate(['search' => ['required', 'string', 'min:3', 'max:100']]);

        return response()->json(['destinations' => $shipping->destinations($request->user(), $request->string('search')->toString())]);
    }

    public function quotes(Request $request, ShippingQuotes $shipping): JsonResponse
    {
        $request->validate(['destination_id' => ['required', 'integer', 'min:1']]);

        return response()->json(['options' => $shipping->options($request->user(), $request->integer('destination_id'))]);
    }
}
