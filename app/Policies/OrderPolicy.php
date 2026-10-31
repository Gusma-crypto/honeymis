<?php

namespace App\Policies;

use App\Domains\Orders\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->isActive() && ($order->customer->user_id === $user->id || $user->can('orders.manage') || $user->can('payments.verify'));
    }

    public function manage(User $user, Order $order): bool
    {
        return $user->isActive() && $user->can('orders.manage');
    }

    public function verifyPayment(User $user, Order $order): bool
    {
        return $user->isActive() && $user->can('payments.verify');
    }

    public function cancel(User $user, Order $order): bool
    {
        return $user->isActive() && ($order->customer->user_id === $user->id || $user->can('orders.manage'));
    }

    public function submitProof(User $user, Order $order): bool
    {
        return $user->isActive() && $order->customer->user_id === $user->id;
    }
}
