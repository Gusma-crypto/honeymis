<?php

namespace App\Http\Requests\Commerce;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'recipient' => ['required', 'string', 'max:150'], 'phone' => ['required', 'string', 'max:30'],
            'province' => ['required', 'string', 'max:100'], 'city' => ['required', 'string', 'max:100'], 'district' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:12'], 'address' => ['required', 'string', 'max:1000'],
            'quote_fingerprint' => ['required', 'string', 'size:64'], 'idempotency_key' => ['required', 'uuid'],
            'shipping_quote_token' => ['nullable', 'uuid'], 'shipping_total' => ['prohibited'],
            'grand_total' => ['prohibited'], 'discount_total' => ['prohibited'], 'partner_referral_id' => ['prohibited'],
        ];
    }
}
