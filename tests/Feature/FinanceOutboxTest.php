<?php

namespace Tests\Feature;

use App\Domains\Shared\Services\CommerceNotifier;
use App\Domains\Shared\Services\FinanceOutbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class FinanceOutboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_notification_job_is_persisted_before_event_is_acknowledged(): void
    {
        config(['queue.default' => 'database']);
        $user = User::factory()->create();
        DB::transaction(fn () => app(CommerceNotifier::class)->queue($user->id, 'durable-test', 'Status', 'Update', '/dashboard'));
        $this->assertSame(1, app(FinanceOutbox::class)->process());
        $this->assertDatabaseCount('jobs', 1);
        $this->assertNotNull(DB::table('outbox_messages')->first()->processed_at);
        $this->assertSame(0, app(FinanceOutbox::class)->process());
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_queue_failure_leaves_event_pending_with_backoff_then_retry_succeeds(): void
    {
        $this->freezeTime();
        config(['queue.default' => 'database']);
        $user = User::factory()->create();
        DB::transaction(fn () => app(CommerceNotifier::class)->queue($user->id, 'failure-test', 'Status', 'Update', '/dashboard'));
        Queue::createPayloadUsing(function (): array {
            throw new RuntimeException('Simulated queue outage');
        });
        try {
            app(FinanceOutbox::class)->process();
            $this->fail('Failed notification enqueue must fail the consumer.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('deferred for retry', $exception->getMessage());
        } finally {
            Queue::createPayloadUsing(null);
        }
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseHas('outbox_messages', ['processed_at' => null, 'attempts' => 1]);
        $this->assertSame(0, app(FinanceOutbox::class)->process());
        $this->travel(6)->seconds();
        $this->assertSame(1, app(FinanceOutbox::class)->process());
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_bad_event_does_not_block_following_valid_notification(): void
    {
        config(['queue.default' => 'database']);
        DB::table('outbox_messages')->insert(['event_key' => (string) Str::uuid(), 'event_type' => 'unknown.event', 'aggregate_type' => 'users', 'aggregate_id' => 1, 'payload' => '{}', 'available_at' => now(), 'created_at' => now()]);
        $user = User::factory()->create();
        DB::transaction(fn () => app(CommerceNotifier::class)->queue($user->id, 'healthy-event', 'Status', 'Update', '/dashboard'));
        try {
            app(FinanceOutbox::class)->process();
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('1 outbox events failed', $exception->getMessage());
        }
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseHas('outbox_messages', ['event_type' => 'unknown.event', 'processed_at' => null, 'attempts' => 1]);
        $this->assertNotNull(DB::table('outbox_messages')->where('event_type', 'users.commerce_update')->first()->processed_at);
    }
}
