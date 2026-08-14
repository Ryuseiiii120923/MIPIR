<?php

use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public bool $show = false;
    public string $message = '';
    public string $type = '';

    public function mount(): void{
        if(session()->has('failed')){
            $this->show = true;
            $this->message = session('failed');
            $this->type = 'Failed';
        }

        if(session()->has('success')){
            $this->show = true;
            $this->message = session('success');
            $this->type = 'Success';
        }
    }

    #[On('sessionNotify')]
    public function notify(string $type, string $message): void
    {
        $this->type = $type; // Success or Failed
        $this->message = $message;
        $this->show = true;
    }

    public function close():void
    {
        $this->show = false;
    }
};
?>

<div>
    @if ($show)
    @php
        $config = [
            'success' => [
                'bg'          => 'bg-green-100',
                'icon_bg'     => 'bg-green-200',
                'icon_color'  => 'text-green-600',
                'title_color' => 'text-green-600',
                'btn_bg'      => 'bg-green-600 hover:bg-green-700',
                'icon'        => '✔',
                'title'       => 'Success',
                'reload'      => true,
            ],
            'failed' => [
                'bg'          => 'bg-red-100',
                'icon_bg'     => 'bg-red-200',
                'icon_color'  => 'text-red-600',
                'title_color' => 'text-red-600',
                'btn_bg'      => 'bg-red-600 hover:bg-red-700',
                'icon'        => '✖',
                'title'       => 'Failed',
                'reload'      => false,
            ],
        ];

        $cfg = $config[$type] ?? $config['failed'];
    @endphp

    <div
        x-data="{ open: @entangle('show') }"
        x-show="open"
        class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-50 z-50"
        x-cloak>

        <div class="bg-white rounded-lg shadow-lg w-96 p-6 text-center relative">

            {{-- Close Button --}}
            <button @click="open = false"
                class="absolute top-2 right-2 text-gray-400 hover:text-gray-600 text-lg leading-none">
                ✕
            </button>

            {{-- Icon Circle --}}
            <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-full {{ $cfg['icon_bg'] }}">
                <span class="text-2xl font-bold {{ $cfg['icon_color'] }}">
                    {{ $cfg['icon'] }}
                </span>
            </div>

            {{-- Title --}}
            <h2 class="text-lg font-semibold {{ $cfg['title_color'] }} mb-1">
                {{ $cfg['title'] }}
            </h2>

            {{-- Message --}}
            <p class="text-gray-500 text-sm mb-5">
                {{ $message }}
            </p>

            {{-- OK Button --}}
            <button
                @if ($cfg['reload'])
                    @click="open = false; location.reload();"
                @else
                    @click="open = false"
                @endif
                class="{{ $cfg['btn_bg'] }} text-white px-6 py-2 rounded-lg text-sm font-medium w-full">
                OK
            </button>

        </div>
    </div>
    @endif
</div>