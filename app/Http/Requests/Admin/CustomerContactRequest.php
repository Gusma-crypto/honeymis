<?php

namespace App\Http\Requests\Admin;

use App\Domains\Orders\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;

class CustomerContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer ? ($this->user()?->can('update', $customer) ?? false) : ($this->user()?->can('create', Customer::class) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'string', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:30'], 'idempotency_key' => ['required', 'uuid'], 'user_id' => ['prohibited']];
    }
}
