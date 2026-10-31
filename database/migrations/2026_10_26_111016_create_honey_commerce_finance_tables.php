<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->restrictOnDelete();
            $table->foreignId('beneficiary_partner_id')->constrained('partners')->restrictOnDelete();
            $table->foreignId('rule_id')->constrained('commission_rules')->restrictOnDelete();
            $table->smallInteger('level')->default(1);
            $table->bigInteger('base_amount');
            $table->bigInteger('amount');
            $table->string('status')->default('pending');
            $table->timestampTz('eligible_at')->nullable();
            $table->timestampTz('available_at')->nullable();
            $table->string('unique_key')->unique();
            $table->timestampsTz();
            $table->unique(['order_item_id', 'beneficiary_partner_id', 'level', 'rule_id'], 'commission_recipient_rule_unique');
            $table->index(['beneficiary_partner_id', 'status', 'eligible_at'], 'commission_beneficiary_status_eligible_idx');
        });

        Schema::create('payout_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->restrictOnDelete();
            $table->string('idempotency_key')->unique();
            $table->bigInteger('amount');
            $table->string('status')->default('requested');
            $table->text('bank_account_encrypted');
            $table->string('proof_storage_key')->nullable();
            $table->string('transfer_reference')->nullable()->unique();
            $table->timestampTz('requested_at');
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestampsTz();
            $table->index(['partner_id', 'status']);
        });

        Schema::create('wallet_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->restrictOnDelete();
            $table->foreignId('commission_entry_id')->nullable()->constrained('commission_entries')->restrictOnDelete();
            $table->foreignId('payout_id')->nullable()->constrained('payout_requests')->restrictOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained('refunds')->restrictOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->constrained('wallet_ledger_entries')->restrictOnDelete();
            $table->string('transaction_key');
            $table->string('idempotency_key')->unique();
            $table->string('bucket');
            $table->string('entry_type');
            $table->bigInteger('amount_signed');
            $table->char('currency', 3)->default('IDR');
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['partner_id', 'bucket', 'created_at']);
            $table->index('transaction_key');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->jsonb('before_json')->nullable();
            $table->jsonb('after_json')->nullable();
            $table->string('ip_hash')->nullable();
            $table->string('idempotency_key')->unique();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id', 'created_at']);
        });

        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->id();
            $table->string('event_key')->unique();
            $table->string('event_type');
            $table->string('aggregate_type');
            $table->unsignedBigInteger('aggregate_id');
            $table->jsonb('payload');
            $table->timestampTz('available_at');
            $table->timestampTz('processed_at')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['processed_at', 'available_at']);
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('event_id');
            $table->boolean('signature_verified')->default(false);
            $table->string('payload_hash');
            $table->timestampTz('processed_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('outbox_messages');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('wallet_ledger_entries');
        Schema::dropIfExists('payout_requests');
        Schema::dropIfExists('commission_entries');
    }
};
