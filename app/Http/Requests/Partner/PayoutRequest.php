<?php

namespace App\Http\Requests\Partner;

use App\Domains\Partners\Models\Partner;
use Illuminate\Foundation\Http\FormRequest;

class PayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        $partner = Partner::where('user_id', $this->user()?->id)->first();

        return $partner !== null && ($this->user()?->can('requestPayout', $partner) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'], 'bank_name' => ['required', 'string', 'max:60'],
            'account_name' => ['required', 'string', 'max:150'], 'account_number' => ['required', 'regex:/^[0-9]{6,34}$/'], 'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
