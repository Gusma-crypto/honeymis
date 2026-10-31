<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->bigInteger('member_price')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            if (DB::table('product_variants')->whereNotNull('member_price')->exists() && ! app()->runningUnitTests()) {
                throw new RuntimeException('Cannot remove configured member prices. Use a forward migration.');
            }
            $table->dropColumn('member_price');
        });
    }
};
