<?php

namespace App\Domains\Partners\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketingAttribution
{
    public function __construct(private Request $request) {}

    public function orderVisitId(User $user): ?int
    {
        if (! $this->request->hasSession()) {
            return null;
        }
        $id = $this->request->session()->get('honey_marketing_visit');
        if (! is_int($id)) {
            return null;
        }
        $visit = DB::table('marketing_visits')->where('id', $id)->where('expires_at', '>', now())->first();
        if ($visit === null || ($visit->registered_user_id !== null && (int) $visit->registered_user_id !== $user->id)) {
            return null;
        }

        return (int) $visit->id;
    }

    public function registered(User $user): void
    {
        $id = $this->orderVisitId($user);
        if ($id !== null) {
            DB::table('marketing_visits')->where('id', $id)->whereNull('registered_user_id')->update(['registered_user_id' => $user->id, 'updated_at' => now()]);
        }
    }
}
