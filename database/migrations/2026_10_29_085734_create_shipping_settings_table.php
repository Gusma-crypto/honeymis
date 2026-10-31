<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('mode', 12)->default('manual');
            $table->unsignedBigInteger('manual_amount');
            $table->string('manual_label', 100);
            $table->text('api_key')->nullable();
            $table->unsignedInteger('origin_id')->nullable();
            $table->string('couriers', 150);
            $table->unsignedInteger('packaging_weight_g')->default(100);
            $table->uuid('revision')->unique();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb', 'pgsql'], true)) {
            DB::statement("ALTER TABLE shipping_settings ADD CONSTRAINT shipping_settings_valid CHECK (id = 1 AND mode IN ('manual','api') AND manual_amount >= 0 AND manual_amount <= 100000000 AND packaging_weight_g <= 5000)");
        }
        Schema::table('orders', function (Blueprint $table): void {
            $table->json('shipping_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('shipping_snapshot');
        });
        Schema::dropIfExists('shipping_settings');
    }
};
