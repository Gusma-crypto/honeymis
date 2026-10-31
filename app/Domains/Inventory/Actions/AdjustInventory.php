<?php

namespace App\Domains\Inventory\Actions;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Inventory\Models\InventoryBalance;
use App\Domains\Shared\Services\AuditWriter;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AdjustInventory
{
    public function __construct(private AuditWriter $audit) {}

    public function handle(User $actor, ProductVariant $variant, int $delta, string $reason, string $key): void
    {
        Gate::forUser($actor)->authorize('inventory.manage');
        DB::transaction(function () use ($actor, $variant, $delta, $reason, $key): void {
            ProductVariant::whereKey($variant->id)->lockForUpdate()->firstOrFail();
            $balance = InventoryBalance::where('variant_id', $variant->id)->lockForUpdate()->firstOrFail();
            $existing = DB::table('inventory_movements')->where('idempotency_key', 'stock:'.$key)->first();
            if ($existing !== null) {
                if ((int) $existing->variant_id !== $variant->id || (int) $existing->quantity_delta !== $delta || $existing->reason !== $reason || (int) $existing->reference_id !== $actor->id) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Key perubahan stok sudah digunakan.']);
                }

                return;
            }
            if ($delta === 0 || $balance->on_hand + $delta < $balance->reserved || $balance->on_hand + $delta > 2000000000) {
                throw ValidationException::withMessages(['quantity_delta' => 'Stok tidak boleh kurang dari jumlah yang direservasi.']);
            }
            $balance->update(['on_hand' => $balance->on_hand + $delta]);
            DB::table('inventory_movements')->insert(['variant_id' => $variant->id, 'quantity_delta' => $delta, 'reserved_delta' => 0, 'reason' => $reason,
                'reference_type' => 'users', 'reference_id' => $actor->id, 'idempotency_key' => 'stock:'.$key, 'created_at' => now()]);
            $this->audit->record($actor->id, 'inventory_adjusted', 'product_variants', $variant->id, 'stock-audit:'.$key, ['delta' => $delta]);
        }, attempts: 3);
    }
}
