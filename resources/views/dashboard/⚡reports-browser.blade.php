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
    public string $searchTightened = '';
    public string $searchExports = '';
    public ?string $exportFolder = null;

    private const PER_PAGE = 5;

    public function updatingSearchTightened(): void
    {
        $this->resetPage('tightenedPage');
    }
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
        $this->resetPage('tightenedPage');
        $this->resetPage('xbarPage');
        $this->resetPage('exportFoldersPage');
        $this->resetPage('exportFilesPage');
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
        $files = $this->listFiles(storage_path('app/excel-xbar'), '*.xlsx');

        if ($this->searchXbar !== '') {
            $files = $files->filter(fn($f) => str_contains(strtolower($f['name']), strtolower($this->searchXbar)))->values();
        }

        return $this->paginateCollection($files, 'xbarPage');
    }

    public function getTightenedFilesProperty(): LengthAwarePaginator
    {
        $files = $this->listFiles(storage_path('app/defects-tightened'), '*/SAIR_*.xls')
            ->map(fn(array $f) => $f + [
                'part'     => basename(dirname($f['path'])),
                'relative' => basename(dirname($f['path'])) . '/' . $f['name'],
            ]);

        if ($this->searchTightened !== '') {
            $files = $files
                ->filter(fn($f) => str_contains(strtolower($f['relative']), strtolower($this->searchTightened)))
                ->values();
        }

        return $this->paginateCollection($files, 'tightenedPage');
    }

    public function download(string $filename, string $type)
    {
        $directory = match ($type) {
            'mipir'     => storage_path('app/excel'),
            'tightened' => storage_path('app/defects-tightened'),
            'exports'   => $this->exportsRoot(),
            default     => storage_path('app/excel-xbar'),
        };

        $realDirectory = realpath($directory);
        $realPath = realpath($directory . DIRECTORY_SEPARATOR . $filename);

        if (
            $realPath === false
            || $realDirectory === false
            || ! str_starts_with($realPath, $realDirectory . DIRECTORY_SEPARATOR)
            || ! File::isFile($realPath)
        ) {
            abort(404);
        }

        if ($type === 'tightened' && strtolower(pathinfo($realPath, PATHINFO_EXTENSION)) !== 'xls') {
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

    public function updatingSearchExports(): void
    {
        $this->resetPage('exportFoldersPage');
        $this->resetPage('exportFilesPage');
    }

    public function openFolder(string $folder): void
    {
        // Folder lang na umiiral sa exports ang tinatanggap
        if ($folder === '' || basename($folder) !== $folder || ! File::isDirectory($this->exportsRoot() . DIRECTORY_SEPARATOR . $folder)) {
            return;
        }

        $this->exportFolder = $folder;
        $this->searchExports = '';
        $this->resetPage('exportFilesPage');
    }

    public function closeFolder(): void
    {
        $this->exportFolder = null;
        $this->searchExports = '';
        $this->resetPage('exportFoldersPage');
    }

    private function isHiddenFile(string $name): bool
    {
        return str_starts_with($name, '.') || str_ends_with($name, '.tmp') || str_ends_with($name, '.lock');
    }

    private function listExportFolders(): Collection
    {
        $root = $this->exportsRoot();

        if (! File::isDirectory($root)) {
            return collect();
        }

        return collect(File::directories($root))
            ->map(function (string $dir) {
                $files = collect(File::files($dir))
                    ->reject(fn($f) => $this->isHiddenFile($f->getFilename()));

                return [
                    'name'     => basename($dir),
                    'files'    => $files->count(),
                    'modified' => $files->isNotEmpty()
                        ? $files->max(fn($f) => $f->getMTime())
                        : File::lastModified($dir),
                ];
            })
            ->sortByDesc('modified')
            ->values();
    }

    public function exportsRoot(){
        return storage_path('app/exports/tightened-monitoring');
    }

    /** Mga file sa loob ng isang folder, pinakabago muna */
    private function listFolderFiles(string $folder): Collection
    {
        $dir = $this->exportsRoot() . DIRECTORY_SEPARATOR . $folder;

        if ($folder === '' || basename($folder) !== $folder || ! File::isDirectory($dir)) {
            return collect();
        }

        return collect(File::files($dir))
            ->reject(fn($f) => $this->isHiddenFile($f->getFilename()))
            ->map(fn($f) => [
                'name'     => $f->getFilename(),
                'size'     => $f->getSize(),
                'modified' => $f->getMTime(),
                'relative' => $folder . '/' . $f->getFilename(),
            ])
            ->sortByDesc('modified')
            ->values();
    }

    public function getExportFoldersProperty(): LengthAwarePaginator
    {
        $folders = $this->listExportFolders();

        if ($this->searchExports !== '') {
            $folders = $folders
                ->filter(fn($f) => str_contains(strtolower($f['name']), strtolower($this->searchExports)))
                ->values();
        }

        return $this->paginateCollection($folders, 'exportFoldersPage');
    }

    public function getExportFilesProperty(): LengthAwarePaginator
    {
        $files = $this->listFolderFiles((string) $this->exportFolder);

        if ($this->searchExports !== '') {
            $files = $files
                ->filter(fn($f) => str_contains(strtolower($f['name']), strtolower($this->searchExports)))
                ->values();
        }

        return $this->paginateCollection($files, 'exportFilesPage');
    }
};
?>

<div class="w-full mx-auto p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Generated Reports</h2>
            <p class="text-sm text-gray-500">MIPIR Excel records, tightened defect sheets, and XBar PDF reports</p>
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
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex items-center justify-between gap-4">
            <h3 class="font-medium text-gray-900">XBar Reports (.xlsx)</h3>
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

    {{-- Tightened Defects (SAIR) --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex items-center justify-between gap-4">
            <h3 class="font-medium text-gray-900">Tightened Defects (.xls)</h3>
            <input type="text" wire:model.live.debounce.400ms="searchTightened" placeholder="Search part number..."
                class="text-sm rounded-lg border-gray-300 px-3 py-1.5 w-56">
        </div>
        <div class="divide-y divide-gray-100">
            @forelse ($this->tightenedFiles as $file)
            <div wire:key="tightened-{{ $file['relative'] }}" class="flex items-center justify-between px-6 py-3">
                <div>
                    <div class="flex items-center gap-2">
                        <p class="text-sm font-medium text-gray-800">{{ $file['name'] }}</p>
                        <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700">{{ $file['part'] }}</span>
                    </div>
                    <p class="text-xs text-gray-400">
                        {{ $this->formatSize($file['size']) }} &middot;
                        {{ \Illuminate\Support\Carbon::createFromTimestamp($file['modified'])->format('Y-m-d H:i') }}
                    </p>
                </div>
                <button wire:click="download(@js($file['relative']), 'tightened')" type="button"
                    class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
                    <i class="ti ti-download"></i> Download
                </button>
            </div>
            @empty
            <p class="px-6 py-6 text-center text-sm text-gray-400">No tightened defect records found</p>
            @endforelse
        </div>
        @if ($this->tightenedFiles->hasPages())
        <div class="px-6 py-3 border-t border-gray-100">
            {{ $this->tightenedFiles->links() }}
        </div>
        @endif
    </div>

    {{-- Tightened Monitoring Dimension --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex items-center justify-between gap-4">
            <div class="flex items-center gap-2 min-w-0">
                @if ($exportFolder !== null)
                <button type="button" wire:click="closeFolder"
                    class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1 whitespace-nowrap">
                    <i class="ti ti-arrow-left"></i> Folders
                </button>
                <span class="text-gray-300">/</span>
                <h3 class="font-medium text-gray-900 truncate">{{ $exportFolder }}</h3>
                @else
                <h3 class="font-medium text-gray-900">Tightened Monitoring Dimension</h3>
                @endif
            </div>

            <input type="text" wire:model.live.debounce.400ms="searchExports"
                placeholder="{{ $exportFolder !== null ? 'Search file...' : 'Search folder...' }}"
                class="text-sm rounded-lg border-gray-300 px-3 py-1.5 w-56">
        </div>

        @if ($exportFolder === null)
        <div class="divide-y divide-gray-100">
            @forelse ($this->exportFolders as $folder)
            <div wire:key="export-folder-{{ $folder['name'] }}" class="flex items-center justify-between px-6 py-3">
                <div class="flex items-center gap-3 min-w-0">
                    <i class="ti ti-folder text-xl text-amber-500"></i>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-800 truncate">{{ $folder['name'] }}</p>
                        <p class="text-xs text-gray-400">
                            {{ $folder['files'] }} {{ \Illuminate\Support\Str::plural('file', $folder['files']) }} &middot;
                            {{ \Illuminate\Support\Carbon::createFromTimestamp($folder['modified'])->format('Y-m-d H:i') }}
                        </p>
                    </div>
                </div>
                <button type="button" wire:click="openFolder(@js($folder['name']))"
                    class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
                    <i class="ti ti-folder-open"></i> Open
                </button>
            </div>
            @empty
            <p class="px-6 py-6 text-center text-sm text-gray-400">No folders found</p>
            @endforelse
        </div>
        @if ($this->exportFolders->hasPages())
        <div class="px-6 py-3 border-t border-gray-100">
            {{ $this->exportFolders->links() }}
        </div>
        @endif
        @else
        <div class="divide-y divide-gray-100">
            @forelse ($this->exportFiles as $file)
            <div wire:key="export-file-{{ $file['relative'] }}" class="flex items-center justify-between px-6 py-3">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-800 truncate">{{ $file['name'] }}</p>
                    <p class="text-xs text-gray-400">
                        {{ $this->formatSize($file['size']) }} &middot;
                        {{ \Illuminate\Support\Carbon::createFromTimestamp($file['modified'])->format('Y-m-d H:i') }}
                    </p>
                </div>
                <button type="button" wire:click="download(@js($file['relative']), 'exports')"
                    class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
                    <i class="ti ti-download"></i> Download
                </button>
            </div>
            @empty
            <p class="px-6 py-6 text-center text-sm text-gray-400">No files in this folder</p>
            @endforelse
        </div>
        @if ($this->exportFiles->hasPages())
        <div class="px-6 py-3 border-t border-gray-100">
            {{ $this->exportFiles->links() }}
        </div>
        @endif
        @endif
    </div>
</div>