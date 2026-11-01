<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->string('status', 15)->default('draft');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['status', 'starts_at', 'ends_at']);
        });
        Schema::create('marketing_visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('marketing_campaigns')->restrictOnDelete();
            $table->char('visitor_hash', 64);
            $table->foreignId('registered_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained('partners')->restrictOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['campaign_id', 'visitor_hash']);
            $table->index('created_at');
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('marketing_visit_id')->nullable()->constrained('marketing_visits')->restrictOnDelete();
        });
        Schema::create('marketing_assets', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 150);
            $table->string('kind', 20);
            $table->string('storage_key');
            $table->string('mime_type', 100);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('marketing_broadcast_drafts', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 150);
            $table->text('message');
            $table->string('audience', 30);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (! app()->runningUnitTests() && (DB::table('marketing_campaigns')->exists() || DB::table('marketing_assets')->exists() || DB::table('marketing_broadcast_drafts')->exists())) {
            throw new RuntimeException('Cannot discard marketing history. Use a forward migration.');
        }
        Schema::dropIfExists('marketing_broadcast_drafts');
        Schema::dropIfExists('marketing_assets');
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('marketing_visit_id');
        });
        Schema::dropIfExists('marketing_visits');
        Schema::dropIfExists('marketing_campaigns');
    }
};
