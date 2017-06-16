<?php

namespace Nikolag\Square\Contracts;

interface SquareContract
{
    public function locations();
    public function save();
    public function charge(float $amount, string $nonce);
}