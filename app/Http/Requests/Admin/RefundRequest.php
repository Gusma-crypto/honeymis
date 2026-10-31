<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('refunds.manage') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'order_item_id' => ['nullable', 'integer'], 'amount' => ['required', 'integer', 'between:1,1000000000'],
            'reason' => ['required', 'string', 'max:1000'], 'idempotency_key' => ['required', 'uuid'], 'confirmed_refund' => ['required', 'accepted'],
        ];
    }
}
