<?php

namespace App\Dashboard\Repositories;

use App\Dashboard\Models\XBar\ControlSpecsLimit;
use Illuminate\Support\Facades\DB;

class SpecsControlRepository
{
    public function fetchLimit(string $partNo, string $dimItem)
    {
        return ControlSpecsLimit::where('PartNo', $partNo)->where('DimItem', $dimItem)->first();
    }

    public function saveLimit(string $partNo, string $dimItem, array $limits): void
    {
        $now = now();

        $table = fn() => DB::connection('mipirDB')
            ->table('control_specs_limit')
            ->where('PartNo', $partNo)
            ->where('DimItem', $dimItem);

        if ($table()->exists()) {
            $table()->update($limits + ['updated_at' => $now]);
            return;
        }

        DB::connection('mipirDB')->table('control_specs_limit')->insert(
            $limits + [
                'PartNo'     => $partNo,
                'DimItem'    => $dimItem,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }
}
