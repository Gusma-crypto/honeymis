<?php

declare(strict_types=1);

namespace App\Domains\Shared\Services;

use App\Domains\Commissions\Enums\CommissionStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Payouts\Enums\PayoutStatus;
use App\Domains\Shared\Contracts\TransitionState;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Internal persistence primitive. Domain actions must authorize and enforce
 * payment, delivery, return-window and ledger invariants before invoking it.
 */
final class TransitionRecorder
{
    public function record(int $subjectId, TransitionState $target, int $actorId, string $idempotencyKey): void
    {
        if (trim($idempotencyKey) === '' || strlen($idempotencyKey) > 200) {
            throw new DomainException('A nonempty idempotency key of at most 200 bytes is required.');
        }

        $tableName = match ($target::class) {
            OrderStatus::class => 'orders',
            PaymentStatus::class => 'payments',
            CommissionStatus::class => 'commission_entries',
            PayoutStatus::class => 'payout_requests',
            default => throw new DomainException('Unsupported aggregate state.'),
        };

        DB::transaction(function () use ($subjectId, $target, $actorId, $idempotencyKey, $tableName): void {
            $aggregate = DB::table($tableName)->where('id', $subjectId)->lockForUpdate()->first();
            if ($aggregate === null) {
                throw new DomainException('Aggregate does not exist.');
            }

            $audit = DB::table('audit_logs')->where('idempotency_key', $idempotencyKey)->first();
            if ($audit !== null) {
                $recordedState = json_decode($audit->after_json, true, flags: JSON_THROW_ON_ERROR);
                if ($audit->subject_type !== $tableName || (int) $audit->subject_id !== $subjectId || $recordedState['status'] !== $target->value || (int) $audit->actor_id !== $actorId) {
                    throw new DomainException('Idempotency key was already used for another operation.');
                }

                return;
            }

            $currentState = $target::from($aggregate->status);
            if (! $currentState->canTransitionTo($target)) {
                throw new DomainException('Invalid state transition.');
            }

            DB::table($tableName)->where('id', $subjectId)->update(['status' => $target->value, 'updated_at' => now()]);
            DB::table('audit_logs')->insert([
                'actor_id' => $actorId,
                'action' => 'state_transition',
                'subject_type' => $tableName,
                'subject_id' => $subjectId,
                'before_json' => json_encode(['status' => $currentState->value], JSON_THROW_ON_ERROR),
                'after_json' => json_encode(['status' => $target->value], JSON_THROW_ON_ERROR),
                'idempotency_key' => $idempotencyKey,
                'created_at' => now(),
            ]);
            DB::table('outbox_messages')->insert([
                'event_key' => $idempotencyKey,
                'event_type' => $tableName.'.state_changed',
                'aggregate_type' => $tableName,
                'aggregate_id' => $subjectId,
                'payload' => json_encode(['from' => $currentState->value, 'to' => $target->value], JSON_THROW_ON_ERROR),
                'available_at' => now(),
                'created_at' => now(),
            ]);
        }, attempts: 3);
    }
}
