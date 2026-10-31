<?php

declare(strict_types=1);

namespace App\Domains\Orders\Services;

use App\Domains\Shared\ValueObjects\Idr;

final class OrderTotals
{
    public function calculate(Idr $subtotal, Idr $discount, Idr $shipping, Idr $tax): Idr
    {
        return $subtotal->subtract($discount)->add($shipping)->add($tax);
    }
}
