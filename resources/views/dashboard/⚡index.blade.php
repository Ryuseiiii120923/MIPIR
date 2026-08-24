<?php

use Livewire\Component;

new class extends Component
{
    public function generateExcel()
    {
        return $this->redirect(route('inspection.xbar.download', ['ppf' => 1764898]));
    }
};
?>

<div>
    Dashboard
    <button wire:click="generateExcel">Generate Excel</button>
</div>