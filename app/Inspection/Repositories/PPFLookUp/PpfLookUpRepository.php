<?php

namespace App\Inspection\Repositories\PPFLookUp;

use App\Domain\Master\MoldedProduct;
use App\Domain\Master\Molding;
use App\Domain\Master\NQR;
use App\Inspection\Models\ChckTRemarks;
use App\Inspection\Models\CheckTime;
use App\Inspection\Models\Defect;
use App\Inspection\Models\MIPIRDimensionMeasure;
use App\Inspection\Models\MIPIRInspectionRecord;
use App\Inspection\Models\SmallDefect;
use App\Inspection\Repositories\Contracts\PpfLookUpRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class PpfLookUpRepository implements PpfLookUpRepositoryInterface
{
    public function getPartNoMoldNo(int $ppf): ?Molding
    {
        return Molding::select('品番 as PartNo', '金型NO as MoldNo', 'PRESSNO', '成形ﾛｯﾄ as ProdLotNo')->where('流動NO', $ppf)->first();
    }

    public function getCavity(string $partNo): ?int
    {
        return MoldedProduct::where('品番', $partNo)
            ->distinct()
            ->value('仕込取数');
    }

    public function getNQR(string $partNo, string $moldNo): ?NQR
    {
        return NQR::where('partNo', $partNo)
            ->where('mdNo', $moldNo)
            ->where('status_remarks', 'APPROVED(CURRENT)')
            ->orderByDesc('approvedDate')
            ->first();
    }

    public function isExist(int $ppf): bool
    {
        return Molding::where('流動NO', $ppf)->exists();
    }

    public function getDataforSearch(
        string $search,
        int $encoder,
        int $perPage = 5
    ) {
        return MIPIRInspectionRecord::query()
            ->select([
                'PPFNo',
                'PartNo',
                'MDNo',
                'DateJudge',
                'MachineNo'
            ])
            ->where('InspectBy', $encoder)

            ->when($search !== '', function ($query) use ($search) {
                $query->where('PPFNo', 'like', "%{$search}%");
            })
            ->whereIn(
                'RECNO',
                MIPIRInspectionRecord::query()
                    ->selectRaw('MAX(RECNO)')
                    ->where('InspectBy', $encoder)

                    ->groupBy('PPFNo')
            )
            ->orderByDesc('DateJudge')
            ->paginate($perPage);
    }


public function getDataforSearchGapOffset(
    string $search,
    int $encoder,
    int $perPage = 5,
    bool $excludeGenerated = false
) {
    $dimSub = MIPIRInspectionRecord::query()
        ->getConnection()
        ->table('DB_MIPIR.dbo.tblDimensionMeasure')
        ->select(['PPFNo', 'DimItem', 'created_at', 'isRecord'])
        ->selectRaw('ROW_NUMBER() OVER (PARTITION BY PPFNo ORDER BY created_at DESC) as rn')
        ->whereIn('DimItem', ['Gap-Offset', 'Gap-Offset (Y)']);

    $query = MIPIRInspectionRecord::query()
        ->select([
            'tblInspectionRecord.PPFNo',
            'tblInspectionRecord.PartNo',
            'tblInspectionRecord.MDNo',
            'tblInspectionRecord.DateJudge',
            'tblInspectionRecord.MachineNo',
            'dm.DimItem',
            'dm.created_at',
        ])
        ->leftJoinSub($dimSub, 'dm', function ($join) {
            $join->on('dm.PPFNo', '=', 'tblInspectionRecord.PPFNo')
                 ->where('dm.rn', '=', 1);
        })
        ->where('tblInspectionRecord.InspectBy', $encoder)
        ->when($search !== '', function ($query) use ($search) {
            $query->where('tblInspectionRecord.PPFNo', 'like', "%{$search}%");
        });

    if ($excludeGenerated) {
        // Add/Update: ipakita kung walang Gap-Offset pa (bagong ie-encode),
        // O may Gap-Offset na pero hindi pa naka-generate sa report (isRecord null).
        $query->where(function ($q) {
            $q->whereNull('dm.DimItem')
              ->orWhereNull('dm.isRecord');
        });
    } else {
        // Delete: kailangang may existing Gap-Offset na para may matanggal.
        $query->whereNotNull('dm.DimItem');
    }

    return $query
        ->whereIn(
            'tblInspectionRecord.RECNO',
            MIPIRInspectionRecord::query()
                ->selectRaw('MAX(RECNO)')
                ->where('InspectBy', $encoder)
                ->groupBy('PPFNo')
        )
        ->orderByDesc('tblInspectionRecord.DateJudge')
        ->orderByDesc('tblInspectionRecord.PPFNo')
        ->paginate($perPage);
}
    //Fetching Repositories

    // public function getMainData(int $ppf): ?array
    // {
    //     $records = MIPIRInspectionRecord::where('PPFNo', $ppf)->get();

    //     if ($records->isEmpty()) {
    //         return null;
    //     }

    //     $ppfLookUp = $records->first();

    //     return [
    //         'ppfno' => $ppf,
    //         'partNumber' => $ppfLookUp['PartNo'],
    //         'moldingDieNo' =>  $ppfLookUp['MDNo'],
    //         'noOfCavity' => $ppfLookUp['NoofCavity'],
    //         'productionLotNo' => $ppfLookUp['ProdLotNo'],
    //         'machineNo' => $ppfLookUp['MachineNo'],
    //         'checkTime' => $records->pluck('Checktime')->all()
    //     ];
    // }



    public static function cacheKey(int $ppf): string
    {
        return "ppf-main-data:{$ppf}";
    }

    public function getMainData(int $ppf): ?array
    {
        return Cache::remember(
            self::cacheKey($ppf),
            now()->addMinutes(30),
            function () use ($ppf) {
                $records = MIPIRInspectionRecord::where('PPFNo', $ppf)->get();
                $checkTime = CheckTime::where('PPFNo', $ppf)->get();

                if ($records->isEmpty()) {
                    return null;
                }

                $ppfLookUp = $records->first();

                return [
                    'ppfno' => $ppf,
                    'partNumber' => $ppfLookUp['PartNo'],
                    'moldingDieNo' => $ppfLookUp['MDNo'],
                    'noOfCavity' => $ppfLookUp['NoofCavity'],
                    'productionLotNo' => $ppfLookUp['ProdLotNo'],
                    'machineNo' => $ppfLookUp['MachineNo'],
                    'checkTime' => $checkTime->pluck('Checktime')->all(),
                    'dateEncode' => $checkTime->pluck('DateEncode', 'Checktime')->all(),
                    'judgement' => $ppfLookUp['Judgement'] === 1 ? 'Failed' : 'Passed',
                    'dateJudge' => \Carbon\Carbon::parse($ppfLookUp['DateJudge'])->format('Y/m/d'),
                ];
            }
        );
    }

    public static function forgetMainData(int $ppf): void
    {
        Cache::forget(self::cacheKey($ppf));
    }

    public function getDefectbyCheckTime(int $ppf, string $checkTime): array
    {
        return Defect::where('PPFNo', $ppf)
            ->where('Checktime', $checkTime)
            ->get(['Defect', 'Qty'])
            ->map(fn($d) => [
                'type' => trim($d->Defect),
                'qty'  => $d->Qty,
            ])
            ->all();
    }

    public function getSmallDefectbyCheckTime(int $ppf, string $checkTime): array
    {
        return SmallDefect::where('PPFNo', $ppf)
            ->where('Checktime', $checkTime)
            ->get(['largeDefect', 'smallDefect', 'qty'])
            ->groupBy(fn($d) => trim($d->largeDefect))
            ->map(fn($group) => $group
                ->map(fn($d) => [
                    'type' => trim($d->smallDefect),
                    'qty'  => $d->qty,
                ])
                ->values()
                ->all())
            ->all();
    }

    public function getDimensionbyCheckTime(int $ppf, string $checkTime): array
    {
        $records = MIPIRDimensionMeasure::where('PPFNo', $ppf)
            ->where('Checktime', $checkTime)
            ->orderBy('Set')
            ->get(['DimItem', 'Specs','ForXBar', 'Mode', 'CL', 'Judge', 'Set', 'Value1', 'Value2', 'Value3', 'Value4', 'Value5']);

        $rows = [];

        foreach ($records as $d) {
            $item = $d->DimItem;
            if ($item == 'Gap-Offset' || $item === 'Gap-Offset (Y)') {
                continue; // Skip these items
            }
            $values = [$d->Value1, $d->Value2, $d->Value3, $d->Value4, $d->Value5];

            if (!isset($rows[$item]['measurements'])) {
                $rows[$item] = array_merge($rows[$item] ?? [], [
                    'item' => $item,
                    'editable' => !in_array($item, ['Flash Thickness'], true),
                    'specification' => $d->Specs,
                    'forXBar' => $d->ForXBar,
                    'CL' => $d->CL,
                    'mode' => $d->Mode,
                    'judge' => $d->Judge === 1 ? 'X' : 'O',
                    'measurements' => [],
                    'revealed' => true,
                ]);
            }

            $rows[$item]['measurements'] = array_merge($rows[$item]['measurements'], $values);
        }

        // Now that all sets are merged, compute the sets count per item
        foreach ($rows as $item => $row) {
            $rows[$item]['sets'] = (int) ceil(count($row['measurements']) / 5);
        }

        return array_values($rows);
    }

    public function getOffsetbyCheckTime(int $ppf, string $checkTime): array
    {
        $records = MIPIRDimensionMeasure::where('PPFNo', $ppf)
            ->where('Checktime', $checkTime)
            ->whereIn('DimItem', ['Gap-Offset', 'Gap-Offset (Y)'])
            ->orderBy('Set')
            ->get(['DimItem', 'Value1', 'Value2', 'Value3', 'Value4', 'Value5', 'Judge']);

        $offsets = [
            'Gap-Offset' => [],
            'Gap-Offset (Y)' => [],
        ];
        $judge = null;

        foreach ($records as $d) {
            $item = $d->DimItem;
            $values = [$d->Value1, $d->Value2, $d->Value3, $d->Value4, $d->Value5];
            $offsets[$item] = array_merge($offsets[$item], $values);
            if ($item === 'Gap-Offset') {
                $judge = $d->Judge;
            }
        }

        return [
            'Gap-Offset' => $offsets['Gap-Offset'],
            'Gap-Offset (Y)' => $offsets['Gap-Offset (Y)'],
            'Judge' => $judge === 1 ? 'X' : 'O',
        ];
    }

    public function getRemarks(int $ppf, string $check)
    {
        return ChckTRemarks::where('PPFNo', $ppf)
            ->where('CheckTime', $check)
            ->value('Remarks');
    }
}
