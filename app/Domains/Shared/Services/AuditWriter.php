<?php

namespace App\Domains\Shared\Services;

use Illuminate\Support\Facades\DB;

class AuditWriter
{
    /** @param array<string, mixed> $metadata */
    public function record(?int $actorId, string $action, string $subjectType, int $subjectId, string $key, array $metadata = []): void
    {
        DB::table('audit_logs')->insert([
            'actor_id' => $actorId, 'action' => $action, 'subject_type' => $subjectType, 'subject_id' => $subjectId,
            'before_json' => null, 'after_json' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'idempotency_key' => $key, 'created_at' => now(),
        ]);
    }
}
