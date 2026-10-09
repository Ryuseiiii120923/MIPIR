<?php

use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public string $currentPage = 'dashboard';

    #[On('navigate-to')]
    public function navigate(string $page): void
    {
        $this->currentPage = $page;
    }
};
?>

<div>
    @switch($currentPage)
        @case('gap-offset')
            <livewire:inspection::gapoffset-encoding-page wire:key="page-gap-offset" />
            @break

        @case('mka-measuring-encoding')
            <livewire:inspection::mka-measuring-encoding-page wire:key="page-mka" />
            @break

        @default
            <livewire:inspection::encodingPage wire:key="page-dashboard" />
    @endswitch
</div>