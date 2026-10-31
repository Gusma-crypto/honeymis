<?php

namespace App\Http\Requests\Admin;

use App\Domains\Orders\Models\ShippingSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShippingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', ShippingSetting::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(['manual', 'api'])],
            'manual_amount' => ['required', 'integer', 'min:0', 'max:100000000'],
            'manual_label' => ['required', 'string', 'max:100'],
            'api_key' => ['nullable', 'string', 'max:500', 'regex:/^[A-Za-z0-9._-]+$/D'],
            'clear_api_key' => ['required', 'boolean'],
            'origin_id' => ['nullable', 'required_if:mode,api', 'integer', 'min:1', 'max:4294967295'],
            'couriers' => ['required', 'string', 'max:150', 'regex:/^[a-z0-9]+(?::[a-z0-9]+){0,9}$/D'],
            'packaging_weight_g' => ['required', 'integer', 'min:0', 'max:5000'],
            'revision' => ['required', 'string', 'max:100'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
