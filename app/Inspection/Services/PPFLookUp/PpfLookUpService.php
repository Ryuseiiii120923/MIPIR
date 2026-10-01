<?php

namespace App\Inspection\Services\PPFLookUp;

use App\Inspection\Repositories\Contracts\PpfLookUpRepositoryInterface;
use App\Inspection\Repositories\PPFLookUp\PpfLookUpRepository;

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
            'partNo' => $result->PartNo,
            'moldNo' => $result->MoldNo,
            'machineNo' => $result->PRESSNO,
            'noOfCavity' => $cavities,
            'nqr' => round($nqr->nqrCriteria, 2),
            'prodLotNo' => $result->ProdLotNo,
            'checkTimes' => $checkTimes,
            'measurementsXByTime' => $measurementsXByTime ?? [],
            'measurementsYByTime' => $measurementsYByTime ?? [],
            'judgementByTime' => $judgementByTime ?? []
        ]; 
    }
}
