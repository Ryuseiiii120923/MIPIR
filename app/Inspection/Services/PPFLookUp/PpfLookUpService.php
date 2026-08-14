<?php

namespace App\Inspection\Services\PPFLookUp;

use App\Inspection\Repositories\Contracts\PpfLookUpRepositoryInterface;



class PpfLookUpService{

    public function __construct(private PpfLookUpRepositoryInterface $repo)
    {

    }

    public function findByPpfNo(int $ppf){
        $isExist = $this->repo->isExist($ppf);
        if (!$isExist) {
            return null;
        }

        $result = $this->repo->getPartNoMoldNo($ppf);
        $cavities = $this->repo->getCavity($result->PartNo);
        $nqr = $this->repo->getNQR($result->PartNo, $result->MoldNo);

        return[
            'partNo' => $result->PartNo,
            'moldNo' => $result->MoldNo,
            'noOfCavity' => $cavities,
            'nqr' => round($nqr->nqrCriteria,2)
        ];
    }
}