<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Services;

use DomainException;

final class Purchasability
{
    public function ensureActive(string $categoryStatus, string $productStatus, string $variantStatus): void
    {
        if ($categoryStatus !== 'active' || $productStatus !== 'active' || $variantStatus !== 'active') {
            throw new DomainException('Inactive catalog items cannot be purchased.');
        }
    }
}
