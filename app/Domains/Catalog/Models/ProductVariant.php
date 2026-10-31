<?php

namespace App\Domains\Catalog\Models;

use App\Domains\Inventory\Models\InventoryBalance;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $product_id
 * @property string $sku
 * @property array<string, string> $attributes
 * @property int $price
 * @property int $cost
 * @property int $discount_amount
 * @property string $status
 * @property Product $product
 * @property InventoryBalance|null $inventory
 */
#[Fillable(['product_id', 'sku', 'attributes', 'price', 'cost', 'discount_amount', 'status'])]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    protected static function newFactory(): ProductVariantFactory
    {
        return ProductVariantFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'price' => 'integer',
            'cost' => 'integer',
            'discount_amount' => 'integer',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /** @return HasOne<InventoryBalance, $this> */
    public function inventory(): HasOne
    {
        return $this->hasOne(InventoryBalance::class, 'variant_id');
    }
}
