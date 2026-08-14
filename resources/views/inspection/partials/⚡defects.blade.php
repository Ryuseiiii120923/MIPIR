<?php

use App\Inspection\Repositories\Defect\DefectRepository;
use App\Inspection\Services\Defect\DefectStagingService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    /*
    |--------------------------------------------------------------------------
    | State
    |--------------------------------------------------------------------------
    */

    // Defect data
    public Collection $largeDefectMaster;
    public array $defects = [];
    public array $staged = [];


    // Form
    public ?string $selectedCheckTime = null;
    public ?string $dispatchPrefix = null;
    public string $action = '';

    // Modal
    public ?string $modalSelectedType = null;
    public string $modalLargeQty = '';

    // Inline editing
    public ?string $editingType = null;

    #[Validate(
        'required|numeric|min:1',
        message: 'Please enter a quantity.'
    )]
    public string $newQuan = '';

    // Summary
    public int $totalNg = 0;
    public float $ngpercent = 0;
    public string $judgement = '';

    // Permission
    #[Locked]
    public bool $readonly = false;

    //Computation
    public int $cavity;
    public float $nqr;

    /*
    |--------------------------------------------------------------------------
    | Lifecycle
    |--------------------------------------------------------------------------
    */

    public function mount(
        DefectRepository $repository,
        ?string $dispatchPrefix = null,
        ?string $selectedCheckTime = null,
        array $loadedDefects = [],
        string $action = '',
        array $fromMaster = []
    ): void {
        $this->action = $action;
        $this->selectedCheckTime = $selectedCheckTime;
        $this->dispatchPrefix = $dispatchPrefix;
        $this->cavity = $fromMaster['cavity'];
        $this->nqr = $fromMaster['nqr'];
        $this->readonly = in_array(
            $action,
            ['view', 'delete'],
            true
        );

        $this->largeDefectMaster = $repository->getLargeDefects();

        $this->loadDefects($loadedDefects);
    }


    /*
    |--------------------------------------------------------------------------
    | External Events
    |--------------------------------------------------------------------------
    */

    #[On('read-only')]
    public function handleReadOnly(bool $readonly = false): void
    {
        $this->readonly = $readonly;
    }

    #[On('FetchDefect')]
    public function handleFetchDefect(array $data): void
    {
        $this->defects = $data['defects'] ?? [];

        $this->updateTotalNg();
        $this->computeAndjudge();
        $this->syncToParent();
    }

    #[On('ClearForm')]
    public function handleClearForm(?string $selectedCheckTime = null): void
    {
        if (
            $selectedCheckTime !== null &&
            $selectedCheckTime !== $this->selectedCheckTime
        ) {
            return;
        }

        $this->defects = [];
        $this->totalNg = 0;

        $this->computeAndjudge();
        $this->syncToParent();
    }

    


    /*
    |--------------------------------------------------------------------------
    | Defect Selection
    |--------------------------------------------------------------------------
    */

    public function selectLargeDefect(string $type): void
    {
        $this->autoStageCurrentDefect($type);

        if ($this->modalSelectedType === $type) {
            $this->resetModal();

            return;
        }

        $this->modalSelectedType = $type;
        $this->modalLargeQty = '';

        $this->loadModalQuantity($type);
    }

    /*
    |--------------------------------------------------------------------------
    | Compute and Judge
    |--------------------------------------------------------------------------
    */

    public function computeAndjudge()
    {
        $this->ngpercent = ($this->totalNg / $this->cavity) * 100;
        if ($this->ngpercent > $this->nqr) {
            $this->judgement = 'X';
        } else {
            $this->judgement = 'O';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Staging
    |--------------------------------------------------------------------------
    */

    public function stageDefect(): void
    {
        $this->validateDefectQuantity();

        $result = $this->stagingService()->buildStagedEntry(
            $this->modalSelectedType,
            (int) $this->modalLargeQty,
            []
        );

        if (! $result['ok']) {
            $this->addError(
                'modalLargeQty',
                $result['error']
            );

            return;
        }

        $this->staged = $this->stagingService()
            ->upsertStagedDefect(
                $this->staged,
                $result['entry']
            );

        $this->resetModal();
        $this->resetErrorBag();
    }

    public function removeStagedDefect(string $type): void
    {
        $this->staged = $this->stagingService()
            ->removeStagedDefect(
                $this->staged,
                $type
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Confirm Defects
    |--------------------------------------------------------------------------
    */

    public function confirmDefects(): void
    {
        $this->autoStageSelectedDefect();

        if (empty($this->staged)) {
            $this->validateDefectSelection();

            return;
        }

        $this->commitStagedDefects();


        $this->resetDefectState();

        $this->dispatch('defect-confirmed');
    }


    /*
    |--------------------------------------------------------------------------
    | Review Existing Defects
    |--------------------------------------------------------------------------
    */

    public function reviewCommittedDefects(): void
    {
        $this->staged = [];

        foreach ($this->defects as $defect) {
            $type = $defect['type'] ?? null;

            if (! $type) {
                continue;
            }

            $this->staged[] = [
                'type' => $type,
                'qty' => (int) ($defect['qty'] ?? 0),
                'smallDefects' => [],
            ];
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Inline Edit
    |--------------------------------------------------------------------------
    */

    public function startEditDefect(string $type): void
    {
        $this->editingType = $type;

        $defect = collect($this->defects)
            ->firstWhere('type', $type);

        if ($defect) {
            $this->newQuan = (string) $defect['qty'];
        }
    }

    public function updateDefect(): void
    {
        $result = $this->stagingService()
            ->updateLargeDefectQty(
                $this->defects,
                [],
                $this->editingType,
                (float) $this->newQuan
            );

        $this->defects = $result['defects'];

        $this->updateTotalNg();
        $this->computeAndjudge();
        $this->syncToParent();

        $this->dispatchDefectUpdate(
            [
                [
                    'type' => trim($this->editingType),
                    'qty' => $this->newQuan,
                ],
            ],
            'update'
        );

        $this->resetEditingState();
    }


    /*
    |--------------------------------------------------------------------------
    | Inline Delete
    |--------------------------------------------------------------------------
    */

    public function deleteDefect(string $type): void
    {
        $result = $this->stagingService()
            ->removeDefect(
                $this->defects,
                [],
                $type
            );

        $this->defects = $result['defects'];

        $this->updateTotalNg();
        $this->computeAndjudge();
        $this->syncToParent();

        $this->dispatchDefectUpdate(
            [
                [
                    'type' => $type,
                    'qty' => 0,
                ],
            ],
            'delete'
        );

        $this->dispatch(
            'NeedToDeleteDefect',
            selectedCheckTime: $this->selectedCheckTime,
            type: $type
        );

        $this->broadcastNg();
    }


    /*
    |--------------------------------------------------------------------------
    | Modal
    |--------------------------------------------------------------------------
    */

    public function cancelDefectModal(): void
    {
        $this->staged = [];

        $this->resetModal();
        $this->resetErrorBag();
    }


    /*
    |--------------------------------------------------------------------------
    | Private Validation
    |--------------------------------------------------------------------------
    */

    private function validateDefectQuantity(): void
    {
        $this->validate([
            'modalSelectedType' => 'required|string',
            'modalLargeQty' => 'required|numeric|min:1',
        ], [
            'modalSelectedType.required' =>
            'Please select a defect type.',

            'modalLargeQty.required' =>
            'Please enter a quantity.',

            'modalLargeQty.min' =>
            'Quantity must be at least 1.',
        ]);
    }

    private function validateDefectSelection(): void
    {
        $this->validate([
            'modalSelectedType' => 'required|string',
            'modalLargeQty' => 'required|numeric|min:1',
        ], [
            'modalSelectedType.required' =>
            'Please select and configure at least one defect.',

            'modalLargeQty.required' =>
            'Please enter a quantity.',

            'modalLargeQty.min' =>
            'Quantity must be at least 1.',
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Private Staging
    |--------------------------------------------------------------------------
    */

    private function autoStageCurrentDefect(string $newType): void
    {
        if (
            $this->modalSelectedType === null ||
            $this->modalSelectedType === $newType
        ) {
            return;
        }

        $this->tryAutoStage(
            $this->modalSelectedType
        );
    }

    private function autoStageSelectedDefect(): void
    {
        if (
            $this->modalSelectedType === null ||
            (int) $this->modalLargeQty < 1
        ) {
            return;
        }

        $this->tryAutoStage(
            $this->modalSelectedType
        );
    }

    private function tryAutoStage(string $type): void
    {
        $result = $this->stagingService()
            ->buildStagedEntry(
                $type,
                (int) $this->modalLargeQty,
                []
            );

        if (! $result['ok']) {
            return;
        }

        $this->staged = $this->stagingService()
            ->upsertStagedDefect(
                $this->staged,
                $result['entry']
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Private Commit
    |--------------------------------------------------------------------------
    */

    private function commitStagedDefects(): void
    {
        $merged = $this->stagingService()
            ->mergeStagedIntoDefects(
                $this->staged,
                $this->defects,
                [],
                $this->largeDefectMaster
            );

        $this->defects = $merged['defects'];

        $this->updateTotalNg();
        $this->computeAndjudge();
        $this->syncToParent();

        $this->dispatchDefectUpdate(
            $this->defects,
            'add'
        );

        $this->broadcastNg();

        $this->dispatch(
            'isDropdownUpdate',
            $this->selectedCheckTime
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Private Synchronization
    |--------------------------------------------------------------------------
    */

    private function syncToParent(): void
    {
        if ($this->selectedCheckTime === null) {
            return;
        }

        $this->dispatch(
            'defects-synced',
            selectedCheckTime: $this->selectedCheckTime,
            defects: $this->defects,
            ngpercent: $this->ngpercent,
            judgement: $this->judgement
        );
    }

    private function updateTotalNg(): void
    {
        $this->totalNg = $this->stagingService()
            ->calculateTotalNg($this->defects);
    }

    private function broadcastNg(): void
    {
        $this->dispatch(
            'sendNg',
            $this->totalNg
        );

        $this->dispatch(
            $this->dispatchPrefix . '.FetchNgDefectDropdown',
            selectedCheckTime: $this->selectedCheckTime,
            defectNg: $this->totalNg
        );

        $this->dispatch(
            'isDropdownUpdate',
            $this->selectedCheckTime
        );
    }

    private function dispatchDefectUpdate(
        array $defects,
        string $action
    ): void {
        $this->dispatch(
            $this->dispatchPrefix . '.defects-updated',
            defects: $defects,
            selectedCheckTime: $this->selectedCheckTime,
            action: $action
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Private Reset / UI State
    |--------------------------------------------------------------------------
    */

    private function resetModal(): void
    {
        $this->modalSelectedType = null;
        $this->modalLargeQty = '';
    }

    private function resetEditingState(): void
    {
        $this->editingType = null;
        $this->newQuan = '';
    }

    private function resetDefectState(): void
    {
        $this->staged = [];

        $this->resetModal();

        $this->resetErrorBag();
    }


    /*
    |--------------------------------------------------------------------------
    | Private Loading / Prefill
    |--------------------------------------------------------------------------
    */

    private function loadModalQuantity(string $type): void
    {
        if ($this->prefillFromStaged($type)) {
            return;
        }

        $this->prefillFromCommitted($type);
    }

    private function prefillFromStaged(string $type): bool
    {
        $entry = collect($this->staged)
            ->firstWhere('type', $type);

        if (! $entry) {
            return false;
        }

        $this->modalLargeQty = (string) $entry['qty'];

        return true;
    }

    private function prefillFromCommitted(string $type): void
    {
        $defect = collect($this->defects)
            ->firstWhere('type', $type);

        if ($defect) {
            $this->modalLargeQty = (string) $defect['qty'];
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Private Initialization
    |--------------------------------------------------------------------------
    */

    private function loadDefects(array $defects): void
    {
        $this->defects = $defects;

        $this->updateTotalNg();
    }


    /*
    |--------------------------------------------------------------------------
    | Services
    |--------------------------------------------------------------------------
    */

    private function stagingService(): DefectStagingService
    {
        return app(DefectStagingService::class);
    }
};
?>

@include('inspection.ui.defect-section')