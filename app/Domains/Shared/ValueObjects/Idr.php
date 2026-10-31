<?php

declare(strict_types=1);

namespace App\Domains\Shared\ValueObjects;

use DomainException;

final readonly class Idr
{
    public function __construct(public int $amount)
    {
        if ($amount < 0) {
            throw new DomainException('IDR amount must not be negative.');
        }
    }

    public function add(self $other): self
    {
        if ($this->amount > PHP_INT_MAX - $other->amount) {
            throw new DomainException('IDR amount exceeds integer capacity.');
        }

        return new self($this->amount + $other->amount);
    }

    public function subtract(self $other): self
    {
        if ($other->amount > $this->amount) {
            throw new DomainException('IDR amount must not be negative.');
        }

        return new self($this->amount - $other->amount);
    }

    public function multiply(int $quantity): self
    {
        if ($quantity < 0 || ($quantity > 0 && $this->amount > intdiv(PHP_INT_MAX, $quantity))) {
            throw new DomainException('Invalid quantity or integer overflow.');
        }

        return new self($this->amount * $quantity);
    }

    public function basisPoints(int $rate): self
    {
        if ($rate < 0 || $rate > 10000) {
            throw new DomainException('Rate must be between 0 and 10000 basis points.');
        }

        return new self(intdiv($this->amount, 10000) * $rate + intdiv(($this->amount % 10000) * $rate, 10000));
    }

    /** Compute floor(amount * part / whole) without overflowing intermediate products. */
    public function proportion(int $part, int $whole): self
    {
        if ($whole < 1 || $part < 0 || $part > $whole) {
            throw new DomainException('Invalid monetary proportion.');
        }
        $result = 0;
        $carry = 0;
        $quotient = intdiv($this->amount, $whole);
        $remainder = $this->amount % $whole;
        while ($part > 0) {
            if ($part % 2 === 1) {
                $result += $quotient;
                if ($carry >= $whole - $remainder) {
                    $result++;
                    $carry -= $whole - $remainder;
                } else {
                    $carry += $remainder;
                }
            }
            $part = intdiv($part, 2);
            if ($part === 0) {
                break;
            }
            $quotient += $quotient;
            if ($remainder >= $whole - $remainder) {
                $quotient++;
                $remainder -= $whole - $remainder;
            } else {
                $remainder += $remainder;
            }
        }

        return new self($result);
    }
}
