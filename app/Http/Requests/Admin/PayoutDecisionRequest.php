<?php

namespace App\Http\Requests\Admin;

use App\Domains\Payouts\Models\PayoutRequest;
use Illuminate\Foundation\Http\FormRequest;

class PayoutDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $payout = $this->route('payout');

        return $payout instanceof PayoutRequest && ($this->user()?->can('review', $payout) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'operation' => ['required', 'in:approve,reject,fail,paid'], 'idempotency_key' => ['required', 'uuid'],
            'reason' => ['nullable', 'required_if:operation,reject,fail', 'string', 'max:1000'],
            'transfer_reference' => ['nullable', 'required_if:operation,paid', 'string', 'max:150'],
            'proof' => ['nullable', 'required_if:operation,paid', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'], 'confirmed_transfer' => ['exclude_unless:operation,paid', 'required', 'accepted'],
        ];
    }
}
