<?php

namespace App\Http\Requests\Commerce;

use Illuminate\Foundation\Http\FormRequest;

class OrderTransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');
        $ability = match ($this->input('operation')) {
            'verify', 'reject' => 'verifyPayment', 'cancel' => 'cancel', default => 'manage'
        };

        return $this->user()?->can($ability, $order) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'operation' => ['required', 'in:verify,reject,cancel,process,ship,deliver'], 'idempotency_key' => ['required', 'uuid'],
            'courier' => ['nullable', 'required_if:operation,ship', 'string', 'max:100'], 'tracking_number' => ['nullable', 'required_if:operation,ship', 'string', 'max:100'],
        ];
    }
}
