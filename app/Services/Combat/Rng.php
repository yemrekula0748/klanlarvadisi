<?php

namespace App\Services\Combat;

/** Deterministik doğrusal eşlenik üreteç — aynı tohum, aynı savaş. */
final class Rng
{
    private int $state;

    public function __construct(int $seed)
    {
        $this->state = ($seed & 0x7FFFFFFF) ?: 1;
    }

    public function next(): int
    {
        $this->state = (int) ((($this->state * 1103515245) + 12345) & 0x7FFFFFFF);

        return $this->state;
    }

    public function float(): float
    {
        return $this->next() / 2147483647.0;
    }

    public function range(int $min, int $max): int
    {
        if ($max <= $min) {
            return $min;
        }

        return $min + ($this->next() % ($max - $min + 1));
    }

    public function jitter(float $scale = 1.0): float
    {
        return ($this->float() - 0.5) * 2.0 * $scale;
    }
}
