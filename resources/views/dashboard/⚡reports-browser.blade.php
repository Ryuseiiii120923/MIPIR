<?php

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $searchMipir = '';
    public string $searchXbar = '';

    private const PER_PAGE = 5;

    public function updatingSearchMipir(): void
    {
        $this->resetPage('mipirPage');
    }

    public function updatingSearchXbar(): void
    {
        $this->resetPage('xbarPage');
    }

    public function loadFiles(): void
    {
        $this->resetPage('mipirPage');
        $this->resetPage('xbarPage');
    }

    private function listFiles(string $directory, string $pattern): Collection
    {
        if (! File::isDirectory($directory)) {
            return collect();
        }

        return collect(File::glob("{$directory}/{$pattern}"))
            ->map(function (string $path) {
                return [
                    'name' => basename($path),
                    'path' => $path,
                    'size' => File::size($path),
                    'modified' => File::lastModified($path),
                ];
            })
            ->sortByDesc('modified')
            ->values();
    }

    private function paginateCollection(Collection $items, string $pageName): LengthAwarePaginator
    {
        $page = $this->getPage($pageName);
        $slice = $items->slice(($page - 1) * self::PER_PAGE, self::PER_PAGE)->values();

        return new LengthAwarePaginator(
            $slice,
            $items->count(),
            self::PER_PAGE,
            $page,
            ['pageName' => $pageName]
        );
    }

    public function getMipirFilesProperty(): LengthAwarePaginator
    {
        $files = $this->listFiles(storage_path('app/excel'), '*.xlsx');

        if ($this->searchMipir !== '') {
            $files = $files->filter(fn($f) => str_contains(strtolower($f['name']), strtolower($this->searchMipir)))->values();
        }

        return $this->paginateCollection($files, 'mipirPage');
    }

    public function getXbarFilesProperty(): LengthAwarePaginator
    {
        $files = $this->listFiles(storage_path('app/excel-archive'), '*.pdf');

        if ($this->searchXbar !== '') {
            $files = $files->filter(fn($f) => str_contains(strtolower($f['name']), strtolower($this->searchXbar)))->values();
        }

        return $this->paginateCollection($files, 'xbarPage');
    }

    public function download(string $filename, string $type)
    {
        $directory = $type === 'mipir'
            ? storage_path('app/excel')
            : storage_path('app/excel-archive');

        $path = $directory . DIRECTORY_SEPARATOR . $filename;

        $realDirectory = realpath($directory);
        $realPath = realpath($path);

        if ($realPath === false || $realDirectory === false || ! str_starts_with($realPath, $realDirectory)) {
            abort(404);
        }

        if (! File::exists($realPath)) {
            abort(404);
        }

        return response()->download($realPath);
    }

    public function formatSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
};
?>

<div class="w-full mx-auto p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Generated Reports</h2>
            <p class="text-sm text-gray-500">MIPIR Excel records and XBar PDF reports</p>
        </div>
        <button wire:click="loadFiles" type="button"
            class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <i class="ti ti-refresh"></i> Refresh
        </button>
    </div>

    {{-- MIPIR Excel Files --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex items-center justify-between gap-4">
            <h3 class="font-medium text-gray-900">MIPIR Records (.xlsx)</h3>
            <input type="text" wire:model.live.debounce.400ms="searchMipir" placeholder="Search filename..."
                class="text-sm rounded-lg border-gray-300 px-3 py-1.5 w-56">
        </div>
        <div class="divide-y divide-gray-100">
            @forelse ($this->mipirFiles as $file)
            <div wire:key="mipir-{{ $file['name'] }}" class="flex items-center justify-between px-6 py-3">
                <div>
                    <p class="text-sm font-medium text-gray-800">{{ $file['name'] }}</p>
                    <p class="text-xs text-gray-400">
                        {{ $this->formatSize($file['size']) }} &middot;
                        {{ \Illuminate\Support\Carbon::createFromTimestamp($file['modified'])->format('Y-m-d H:i') }}
                    </p>
                </div>
                <button wire:click="download('{{ $file['name'] }}', 'mipir')" type="button"
                    class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
                    <i class="ti ti-download"></i> Download
                </button>
            </div>
            @empty
            <p class="px-6 py-6 text-center text-sm text-gray-400">
                No MIPIR records found</p>
            @endforelse
        </div>
        @if ($this->mipirFiles->hasPages())
        <div class="px-6 py-3 border-t border-gray-100">
            {{ $this->mipirFiles->links() }}
        </div>
        @endif
    </div>

    {{-- XBar PDF Files --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex items-center justify-between gap-4">
            <h3 class="font-medium text-gray-900">XBar Reports (.pdf)</h3>
            <input type="text" wire:model.live.debounce.400ms="searchXbar" placeholder="Search filename..."
                class="text-sm rounded-lg border-gray-300 px-3 py-1.5 w-56">
        </div>
        <div class="divide-y divide-gray-100">
            @forelse ($this->xbarFiles as $file)
            <div wire:key="xbar-{{ $file['name'] }}" class="flex items-center justify-between px-6 py-3">
                <div>
                    <p class="text-sm font-medium text-gray-800">{{ $file['name'] }}</p>
                    <p class="text-xs text-gray-400">
                        {{ $this->formatSize($file['size']) }} &middot;
                        {{ \Illuminate\Support\Carbon::createFromTimestamp($file['modified'])->format('Y-m-d H:i') }}
                    </p>
                </div>
                <button wire:click="download('{{ $file['name'] }}', 'xbar')" type="button"
                    class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
                    <i class="ti ti-download"></i> Download
                </button>
            </div>
            @empty
            <p class="px-6 py-6 text-center text-sm text-gray-400">No XBar record found</p>
            @endforelse
        </div>
        @if ($this->xbarFiles->hasPages())
        <div class="px-6 py-3 border-t border-gray-100">
            {{ $this->xbarFiles->links() }}
        </div>
        @endif
    </div>
</div>