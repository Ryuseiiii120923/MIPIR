<?php

namespace App\Inspection\Actions;

use App\Inspection\Models\Machine;

class CreateMachine
{
    public function execute(string $machineNumber):Machine
    {
        return Machine::create([
            'machine_number' => trim($machineNumber),
        ]);
    }
}