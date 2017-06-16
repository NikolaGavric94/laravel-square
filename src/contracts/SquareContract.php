<?php

namespace Nikolag\Square\Contracts;

interface SquareContract
{
    public function locations();
    public function save();
    public function charge(int $amount, string $nonce);
}