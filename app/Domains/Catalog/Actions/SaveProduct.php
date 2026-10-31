<?php

namespace App\Domains\Catalog\Actions;

use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Inventory\Models\InventoryBalance;
use App\Domains\Shared\Services\AuditWriter;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveProduct
{
    public function __construct(private AuditWriter $audit) {}

    /** @param array{category_id: int, sku: string, name: string, slug: string, description?: string|null, status: string, weight_g: int, commission_eligible?: bool, variants: list<array{id?: int|null, sku: string, label: string, price: int, cost: int, discount_amount: int, status: string}>} $data */
    public function handle(User $actor, ?Product $product, array $data): Product
    {
        Gate::forUser($actor)->authorize('manage', Product::class);

        return DB::transaction(function () use ($actor, $product, $data): Product {
            $locked = $product === null ? null : Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            Category::whereKey($data['category_id'])->lockForUpdate()->firstOrFail();
            $fields = array_diff_key($data, ['variants' => true]);
            if ($locked === null) {
                $locked = Product::create($fields);
            } else {
                $locked->update($fields);
            }
            foreach ($data['variants'] as $row) {
                $variant = empty($row['id']) ? null : $locked->variants()->whereKey($row['id'])->lockForUpdate()->firstOrFail();
                if (ProductVariant::where('sku', $row['sku'])->when($variant !== null, fn ($query) => $query->where('id', '!=', $variant->id))->exists()) {
                    throw ValidationException::withMessages(['variants' => 'SKU varian sudah digunakan.']);
                }
                $values = ['sku' => $row['sku'], 'attributes' => ['label' => $row['label']], 'price' => $row['price'], 'cost' => $row['cost'], 'discount_amount' => $row['discount_amount'], 'status' => $row['status']];
                $variant === null ? $variant = $locked->variants()->create($values) : $variant->update($values);
                InventoryBalance::firstOrCreate(['variant_id' => $variant->id], ['on_hand' => 0, 'reserved' => 0]);
            }
            $this->audit->record($actor->id, 'product_saved', 'products', $locked->id, 'product-save:'.Str::uuid(), ['status' => $locked->status]);

            return $locked;
        }, attempts: 3);
    }
}
