<?php

namespace App\Policies;

use App\Domains\Orders\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewProof(User $user, Payment $payment): bool
    {
        return $user->isActive() && ($payment->order->customer->user_id === $user->id || $user->can('payments.verify'));
    }
}
