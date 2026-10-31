<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CommissionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commission-rules.manage') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1', 'unique:commission_rules,version'], 'product_id' => ['nullable', 'integer', 'exists:products,id', 'prohibited_with:category_id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id', 'prohibited_with:product_id'], 'rate_bps' => ['required', 'integer', 'between:1500,2000'],
            'fixed_amount' => ['required', 'integer', 'in:0'], 'cap_amount' => ['required', 'integer', 'between:1,1000000000'],
            'valid_from' => ['required', 'date'], 'valid_until' => ['nullable', 'date', 'after:valid_from'], 'level' => ['prohibited'], 'strategy' => ['prohibited'],
        ];
    }
}
