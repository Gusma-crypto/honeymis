<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'category_id' => ['nullable', 'integer', 'exists:categories,id', 'prohibited_with:product_id'], 'rate_bps' => ['required', 'integer', 'between:0,10000'],
            'fixed_amount' => ['required', 'integer', 'between:0,1000000000'], 'cap_amount' => ['required', 'integer', 'between:1,1000000000'],
            'valid_from' => ['required', 'date'], 'valid_until' => ['nullable', 'date', 'after:valid_from'], 'level' => ['prohibited'], 'strategy' => ['prohibited'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->integer('rate_bps') > 0 && $this->integer('fixed_amount') > 0) {
                $validator->errors()->add('fixed_amount', 'Pilih persentase atau nominal tetap, bukan keduanya.');
            }
            if ($this->integer('rate_bps') === 0 && $this->integer('fixed_amount') === 0) {
                $validator->errors()->add('rate_bps', 'Aturan harus memiliki tarif atau nominal positif.');
            }
        }];
    }
}
