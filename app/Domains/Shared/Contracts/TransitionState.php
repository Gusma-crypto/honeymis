<?php

declare(strict_types=1);

namespace App\Domains\Shared\Contracts;

interface TransitionState extends \BackedEnum
{
    public function canTransitionTo(self $target): bool;
}
