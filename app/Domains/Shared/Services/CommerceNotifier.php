<?php

namespace App\Domains\Shared\Services;

use Illuminate\Support\Facades\DB;

class CommerceNotifier
{
    public function queue(int $userId, string $key, string $title, string $detail, string $path): void
    {
        if (DB::transactionLevel() < 1) {
            throw new \RuntimeException('Commerce notifications require a committed domain transaction.');
        }
        DB::table('outbox_messages')->insertOrIgnore(['event_key' => 'notification:'.$key, 'event_type' => 'users.commerce_update', 'aggregate_type' => 'users', 'aggregate_id' => $userId,
            'payload' => json_encode(['title' => $title, 'detail' => $detail, 'path' => $path], JSON_THROW_ON_ERROR), 'available_at' => now(), 'created_at' => now()]);
    }
}
