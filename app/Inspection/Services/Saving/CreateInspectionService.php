<?php

namespace App\Inspection\Services\Saving;

use App\Inspection\Repositories\MIPIRInspectionReporsitory;

class CreateInspectionService
{
    protected $inspectionRepository;

    public function __construct(MIPIRInspectionReporsitory $inspectionRepository)
    {
        $this->inspectionRepository = $inspectionRepository;
    }

    public function createInspectionRecord(array $data)
    {
        return $this->inspectionRepository->createInspectionRecord($data);
    }

    public function createDimensionMeasure(array $data)
    {
        return $this->inspectionRepository->createDimensionMeasure($data);
    }

    public function createDefect(array $data)
    {
        return $this->inspectionRepository->createDefect($data);
    }
}