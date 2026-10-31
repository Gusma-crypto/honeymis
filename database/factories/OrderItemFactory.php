<?php

namespace Database\Factories;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderItem> */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['order_id' => Order::factory(), 'variant_id' => ProductVariant::factory(), 'sku_snapshot' => 'TEST-SKU', 'name_snapshot' => 'Madu test', 'attributes_snapshot' => [], 'quantity' => 1, 'unit_price_snapshot' => 150000, 'discount_snapshot' => 10000, 'cost_snapshot' => 80000, 'commission_eligible_snapshot' => false];
    }
}
