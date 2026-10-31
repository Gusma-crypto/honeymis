<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('catalog.manage') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'], 'name' => ['required', 'string', 'max:150'],
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($this->route('product'))],
            'slug' => ['required', 'alpha_dash', 'max:150', Rule::unique('products', 'slug')->ignore($this->route('product'))],
            'description' => ['nullable', 'string', 'max:10000'], 'weight_g' => ['required', 'integer', 'between:1,100000'], 'status' => ['required', 'in:active,inactive,draft'],
            'variants' => ['required', 'array', 'min:1', 'max:20'], 'variants.*.id' => ['nullable', 'integer'], 'variants.*.sku' => ['required', 'string', 'max:100', 'distinct'],
            'variants.*.label' => ['required', 'string', 'max:100'], 'variants.*.price' => ['required', 'integer', 'between:1,1000000000'],
            'variants.*.cost' => ['required', 'integer', 'between:0,1000000000'], 'variants.*.discount_amount' => ['required', 'integer', 'min:0', 'lte:variants.*.price'],
            'variants.*.status' => ['required', 'in:active,inactive,draft'], 'commission_eligible' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array{category_id: int, sku: string, name: string, slug: string, description: string, status: string, weight_g: int, commission_eligible: bool, variants: list<array{id: int|null, sku: string, label: string, price: int, cost: int, discount_amount: int, status: string}>} */
    public function productData(): array
    {
        $this->validated();
        $variants = array_values(array_map(fn (array $row): array => [
            'id' => empty($row['id']) ? null : (int) $row['id'], 'sku' => (string) $row['sku'], 'label' => (string) $row['label'],
            'price' => (int) $row['price'], 'cost' => (int) $row['cost'], 'discount_amount' => (int) $row['discount_amount'], 'status' => (string) $row['status'],
        ], $this->array('variants')));

        return ['category_id' => $this->integer('category_id'), 'sku' => $this->string('sku')->toString(), 'name' => $this->string('name')->toString(),
            'slug' => $this->string('slug')->toString(), 'description' => $this->string('description')->toString(), 'status' => $this->string('status')->toString(), 'weight_g' => $this->integer('weight_g'), 'commission_eligible' => $this->boolean('commission_eligible'), 'variants' => $variants];
    }
}
