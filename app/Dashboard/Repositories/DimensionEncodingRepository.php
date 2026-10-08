<?php

namespace App\Dashboard\Repositories;

use App\Dashboard\Models\DimensionMaster;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DimensionEncodingRepository
{
    public function paginate(?string $search, int $perPage = 10): LengthAwarePaginator
    {
        $term = $search !== null ? trim($search) : '';

        return DimensionMaster::query()
            ->when($term !== '', function ($query) use ($term) {
                $like = '%' . $term . '%';

                $query->where(function ($w) use ($like) {
                    $w->where('DimensionName', 'like', $like)
                      ->orWhere('PartNo', 'like', $like);
                });
            })
            ->orderByDesc('DEnc')
            ->orderByDesc('RecNo') // stable ORDER BY for SQL Server paging
            ->paginate($perPage);
    }

    public function find(int $recNo): ?DimensionMaster
    {
        return DimensionMaster::find($recNo);
    }

    public function existsByPartAndDimensionNo(string $partNo, int $dimensionNo, ?int $ignoreRecNo = null): bool
    {
        return DimensionMaster::query()
            ->where('PartNo', $partNo)
            ->where('DimensionNo', $dimensionNo)
            ->when($ignoreRecNo !== null, fn ($q) => $q->where('RecNo', '!=', $ignoreRecNo))
            ->exists();
    }

    public function create(array $data): DimensionMaster
    {
        return DimensionMaster::create($data);
    }

    public function update(int $recNo, array $data): void
    {
        DimensionMaster::query()->whereKey($recNo)->update($data);
    }


public function saveLimit(string $partNo, string $dimItem, array $limits): void
{
    $now = now();

    $table = fn () => DB::connection('mipirDB')
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