<?php

namespace App\Inspection\Services\PPFLookUp;

use App\Inspection\Repositories\Contracts\PpfLookUpRepositoryInterface;
use App\Inspection\Repositories\PPFLookUp\PpfLookUpRepository;
use Illuminate\Support\Facades\Log;

use function Livewire\off;

class PpfLookUpService
{

    public function __construct(private PpfLookUpRepositoryInterface $repo) {}

    public function findByPpfNo(int $ppf)
    {
        $isExist = $this->repo->isExist($ppf);
        if (!$isExist) {
            return null;
        }

        $result = $this->repo->getPartNoMoldNo($ppf);
        $cavities = $this->repo->getCavity($result->PartNo);
        $nqr = $this->repo->getNQR($result->PartNo, $result->MoldNo);

        if($nqr === null || $nqr === 0) {
            $nqr = $this->repo->getNQRSeihin($result->PartNo, $result->MoldNo);
        } 

        Log::info('User logged in', [
            'nqr' => $nqr ?? 0,
        ]);

        $ppfLookUpRepo = app(PpfLookUpRepository::class);
        $mainData = $ppfLookUpRepo->getMainData($ppf);
        $checkTimes = $mainData['checkTime'] ?? [];
        foreach ($checkTimes as $time) {
            $offset = $ppfLookUpRepo->getOffsetbyCheckTime($ppf, $time);
            $measurementsXByTime[$time] = $offset['Gap-Offset'];
            $measurementsYByTime[$time] = $offset['Gap-Offset (Y)'];
            $judgementByTime[$time] = $offset['Judge'];
        }

        return [
            'partNo' => $result->PartNo ?? null,
            'moldNo' => $result->MoldNo ?? null,
            'machineNo' => $result->PRESSNO ?? null,
            'noOfCavity' => $cavities ?? null,
            'nqr' => round($nqr ?? 0, 2) ?? null,
            'prodLotNo' => $mainData['productionLotNo'] ?? "",
            'checkTimes' => $checkTimes,
            'measurementsXByTime' => $measurementsXByTime ?? [],
            'measurementsYByTime' => $measurementsYByTime ?? [],
            'judgementByTime' => $judgementByTime ?? [],
            'moldOperator' => $mainData['moldOperator'] ?? null
        ];
    }
}
