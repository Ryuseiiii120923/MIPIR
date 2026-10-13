<?php

namespace App\Dashboard\Repositories;

use App\Dashboard\Models\XBar\ControlSpecsLimit;
use Illuminate\Support\Facades\DB;

class SpecsControlRepository
{
    public function fetchLimit(string $partNo, string $dimItem, int $selectedDieNo)
    {
        return ControlSpecsLimit::where('PartNo', $partNo)
            ->where('DimItem', $dimItem)
            ->where('MoldingDieNo', $selectedDieNo)
            ->first();
    }

    public function saveLimit(string $partNo, string $dimItem, int $dieNo, array $limits): void
    {
        $now = now();

        $table = fn() => DB::connection('mipirDB')
            ->table('control_specs_limit')
            ->where('PartNo', $partNo)
            ->where('DimItem', $dimItem)
            ->where('MoldingDieNo', $dieNo);

        if ($table()->exists()) {
            $table()->update($limits + ['updated_at' => $now]);
            return;
        }

        DB::connection('mipirDB')->table('control_specs_limit')->insert(
            $limits + [
                'PartNo'       => $partNo,
                'DimItem'      => $dimItem,
                'MoldingDieNo' => $dieNo,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]
        );
    }
}
