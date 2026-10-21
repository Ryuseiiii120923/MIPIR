<?php

namespace App\Inspection\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class XBarCycleFull implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public int $ppf,
        public string $plant,
        public string $dimension,
        public string $transactionId,
        public ?string $cycleStart = null,
        public ?string $cycleEnd = null,
    ) {}
}