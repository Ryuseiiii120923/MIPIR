<?php

namespace App\Inspection\Repositories\PPFLookUp;

use App\Domain\Master\MoldedProduct;
use App\Domain\Master\MoldingPlan;
use App\Domain\Master\NQR;
use App\Domain\Master\SEIHIN;
use App\Inspection\Models\ChckTRemarks;
use App\Inspection\Models\CheckTime;
use App\Inspection\Models\Defect;
use App\Inspection\Models\MIPIRDimensionMeasure;
use App\Inspection\Models\MIPIRInspectionRecord;
use App\Inspection\Models\SmallDefect;
use App\Inspection\Repositories\Contracts\PpfLookUpRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class PpfLookUpRepository implements PpfLookUpRepositoryInterface
{
    public function getPartNoMoldNo(int $ppf): ?MoldingPlan
    {
        return MoldingPlan::select('品番 as PartNo', '金型NO as MoldNo', 'PRESS_NO as PRESSNO')->where('流動NO', $ppf)->first();
    }

    public function getProdLotNo(int $ppf, string $operator)
    {
        $result = MoldingPlan::select(
            '班 as Shift',
            '成形日 as MoldingDate'
        )
            ->where('流動NO', $ppf)
            ->first();

        if (!$result) {
            return null;
        }

        $shift = $result->Shift;
        $moldingDate = Carbon::parse($result->MoldingDate);

        $year = $moldingDate->format('Y');
        $month = (int) $moldingDate->format('m');
        $day = $moldingDate->format('d');

        if ($month >= 10) {
            $romanMonths = [
                10 => 'X',
                11 => 'XI',
                12 => 'XII',
            ];

            $monthCode = $romanMonths[$month];
        } else {
            $monthCode = $month;
        }

        if (substr($year, 2, 1) == '2') {
            $yearCode = substr(
                'ABCDEFGHIJ',
                (int) substr($year, 3, 1),
                1
            );
        } elseif (substr($year, 2, 1) == '3') {
            $yearCode = substr(
                'KLMNOPQRST',
                (int) substr($year, 3, 1),
                1
            );
        } elseif (substr($year, 2, 1) == '4') {
            $yearCode = substr(
                'UVWXYZ',
                (int) substr($year, 3, 1),
                1
            );
        } else {
            $yearCode = substr($year, 3, 1);
        }
        $prodLotNo = '20'
            . $yearCode
            . $monthCode
            . $day
            . '-'
            . $shift
            . $operator;

        return $prodLotNo;
    }

    public function getCavity(string|null $partNo): ?int
    {
        return MoldedProduct::where('品番', $partNo)
            ->distinct()
            ->value('仕込取数');
    }

    public function getNQR(string|null $partNo, string|null $moldNo)
    {
        if (!$partNo || !$moldNo) {
            return null;
        }
        return NQR::where('partNo', $partNo)
            ->where('mdNo', $moldNo)
            ->where('status_remarks', 'APPROVED(CURRENT)')
            ->orderByDesc('approvedDate')
            ->value('nqrCriteria');
    }

    public function getNQRSeihin(string|null $partNo, string|null $moldNo)
    {
        if (!$partNo || !$moldNo) {
            return null;
        }

        return SEIHIN::where('品番 ', $partNo)->value('不良率');
    }
    public function isExist(int $ppf): bool
    {
        return MoldingPlan::where('流動NO', $ppf)->exists();
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
    int|string $encoder, // EmployeeID ng encoder
    int $perPage = 5,
    bool $excludeGenerated = false
) {
    $conn = MIPIRInspectionRecord::query()->getConnection();

    // Plant ng encoder
    $encoderPlant = $conn
        ->table('DB_MIPIR.dbo.tblUser')
        ->select('Plant')
        ->where('EmployeeID', $encoder)
        ->limit(1);

    // EmployeeID ng lahat ng nasa parehong plant (ito ang laman ng InspectBy)
    $plantEmployees = $conn
        ->table('DB_MIPIR.dbo.tblUser')
        ->select('EmployeeID')
        ->where('Plant', '=', $encoderPlant);

    $dimSub = $conn
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
            'tblInspectionRecord.InspectBy',
            'dm.DimItem',
            'dm.created_at',
        ])
        ->leftJoinSub($dimSub, 'dm', function ($join) {
            $join->on('dm.PPFNo', '=', 'tblInspectionRecord.PPFNo')
                ->where('dm.rn', '=', 1);
        })
        ->whereIn('tblInspectionRecord.InspectBy', $plantEmployees)
        ->when($search !== '', function ($query) use ($search) {
            $query->where('tblInspectionRecord.PPFNo', 'like', "%{$search}%");
        });

    if ($excludeGenerated) {
        $query->where(function ($q) {
            $q->whereNull('dm.DimItem')
                ->orWhereNull('dm.isRecord');
        });
    } else {
        $query->whereNotNull('dm.DimItem');
    }

    return $query
        ->whereIn(
            'tblInspectionRecord.RECNO',
            MIPIRInspectionRecord::query()
                ->selectRaw('MAX(RECNO)')
                ->whereIn('InspectBy', $plantEmployees)
                ->groupBy('PPFNo')
        )
        ->orderByDesc('tblInspectionRecord.DateJudge')
        ->orderByDesc('tblInspectionRecord.PPFNo')
        ->paginate($perPage);
}
   
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
                    'moldOperator' => $ppfLookUp['MoldingOperator'] ?? null
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
            ->get(['DimItem', 'Specs', 'ForXBar', 'Mode', 'CL', 'Judge', 'Set', 'Value1', 'Value2', 'Value3', 'Value4', 'Value5']);

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

    public function getGapOffsetChecktime(int $ppf): array
    {
        return MIPIRDimensionMeasure::where('PPFNo', $ppf)
            ->whereIn('DimItem', ['Gap-Offset', 'Gap-Offset (Y)'])
            ->distinct()
            ->pluck('Checktime')
            ->all();
    }
}
