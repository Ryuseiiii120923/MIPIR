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
use App\Inspection\Repositories\Contracts\PpfLookUpRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class PpfLookUpRepository implements PpfLookUpRepositoryInterface
{
    public function getPartNoMoldNo(int $ppf): ?Molding
    {
        return Molding::select('品番 as PartNo', '金型NO as MoldNo')->where('流動NO', $ppf)->first();
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
                    'checkTime' => $checkTime->pluck('check-time')->all(),
                    'dateEncode' => $checkTime->pluck('date-encode', 'check-time')->all(),
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
                'type' => $d->Defect,
                'qty' => $d->Qty,
            ])
            ->all();
    }

    public function getDimensionbyCheckTime(int $ppf, string $checkTime): array
    {
        $records = MIPIRDimensionMeasure::where('PPFNo', $ppf)
            ->where('Checktime', $checkTime)
            ->orderBy('Set')
            ->get(['DimItem', 'Specs', 'Mode', 'Note', 'Judge', 'Set', 'Value1', 'Value2', 'Value3', 'Value4', 'Value5']);

        $rows = [];

        foreach ($records as $d) {
            $item = $d->DimItem;
            $values = [$d->Value1, $d->Value2, $d->Value3, $d->Value4, $d->Value5];

            // "Gap-Offset (Y)" rows merge into the "Gap-Offset" row as measurements_y
            if ($item === 'Gap-Offset (Y)') {
                $rows['Gap-Offset']['measurements_y'] = array_merge(
                    $rows['Gap-Offset']['measurements_y'] ?? [],
                    $values
                );
                continue;
            }

          if (!isset($rows[$item]['measurements'])) {
    $rows[$item] = array_merge($rows[$item] ?? [], [
        'item' => $item,
        'editable' => !in_array($item, ['Flash Thickness', 'Gap-Offset'], true),
        'specification' => $d->Specs,
        'CL' => $d->Note,
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

    public function getRemarks(int $ppf, string $check)
    {
        return ChckTRemarks::where('PPFNo', $ppf)
            ->where('CheckTime', $check)
            ->value('Remarks');
    }
}
