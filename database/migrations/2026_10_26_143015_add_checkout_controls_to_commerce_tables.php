<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->bigInteger('discount_amount')->default(0);
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('request_fingerprint', 64)->nullable();
            $table->foreignId('cart_id')->nullable()->constrained('carts')->restrictOnDelete();
            $table->timestampTz('expires_at')->nullable();
            $table->index(['status', 'expires_at']);
        });
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('proof_hash', 64)->nullable();
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE product_variants ADD CONSTRAINT variant_discount_valid CHECK (discount_amount >= 0 AND discount_amount <= price)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE product_variants DROP CONSTRAINT variant_discount_valid');
        }
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('proof_hash'));
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['status', 'expires_at']);
            $table->dropConstrainedForeignId('cart_id');
            $table->dropColumn(['request_fingerprint', 'expires_at']);
        });
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn('discount_amount'));
    }
};
