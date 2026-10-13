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
    public string $exportPath = '';

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
        $this->resetPage('exportsPage');
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
        $this->resetPage('exportsPage');
    }

    private function isHiddenFile(string $name): bool
    {
        return str_starts_with($name, '.') || str_ends_with($name, '.tmp') || str_ends_with($name, '.lock');
    }
    public function exportsRoot()
    {
        return storage_path('app/exports/tightened-monitoring');
    }

    private function resolveExportDir(string $relative): ?string
    {
        $root = realpath($this->exportsRoot());

        if ($root === false) {
            return null;
        }

        if ($relative === '') {
            return $root;
        }

        $segments = explode('/', str_replace('\\', '/', $relative));

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        $full = realpath($root . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments));

        if ($full === false || ! is_dir($full) || ! str_starts_with($full, $root . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $full;
    }

    private function joinExportPath(string $name): string
    {
        return $this->exportPath === '' ? $name : $this->exportPath . '/' . $name;
    }

    public function openFolder(string $name): void
    {
        if ($name === '' || basename($name) !== $name) {
            return;
        }

        $target = $this->joinExportPath($name);

        if ($this->resolveExportDir($target) === null) {
            return;
        }

        $this->exportPath = $target;
        $this->searchExports = '';
        $this->resetPage('exportsPage');
    }

    /** Pumunta sa kahit anong level (breadcrumb). '' = root */
    public function goToPath(string $path): void
    {
        if ($this->resolveExportDir($path) === null) {
            return;
        }

        $this->exportPath = $path;
        $this->searchExports = '';
        $this->resetPage('exportsPage');
    }

    public function goUp(): void
    {
        $parent = dirname($this->exportPath);
        $this->goToPath($parent === '.' ? '' : $parent);
    }

    public function getBreadcrumbsProperty(): array
    {
        $crumbs = [];
        $acc = '';

        foreach (array_filter(explode('/', $this->exportPath), fn($s) => $s !== '') as $segment) {
            $acc = $acc === '' ? $segment : $acc . '/' . $segment;
            $crumbs[] = ['name' => $segment, 'path' => $acc];
        }

        return $crumbs;
    }

    /** Mga subfolder (una) at mga file sa kasalukuyang level */
    private function listExportItems(): Collection
    {
        $dir = $this->resolveExportDir($this->exportPath);

        if ($dir === null) {
            return collect();
        }

        $folders = collect(File::directories($dir))
            ->reject(fn($d) => $this->isHiddenFile(basename($d)))
            ->map(function (string $d) {
                $files = collect(File::files($d))
                    ->reject(fn($f) => $this->isHiddenFile($f->getFilename()));

                return [
                    'type'     => 'folder',
                    'name'     => basename($d),
                    'files'    => $files->count(),
                    'folders'  => count(File::directories($d)),
                    'modified' => $files->isNotEmpty()
                        ? $files->max(fn($f) => $f->getMTime())
                        : File::lastModified($d),
                ];
            })
            ->sortByDesc('modified')
            ->values();

        $files = collect(File::files($dir))
            ->reject(fn($f) => $this->isHiddenFile($f->getFilename()))
            ->map(fn($f) => [
                'type'     => 'file',
                'name'     => $f->getFilename(),
                'size'     => $f->getSize(),
                'modified' => $f->getMTime(),
                'relative' => $this->joinExportPath($f->getFilename()),
            ])
            ->sortByDesc('modified')
            ->values();

        return $folders->concat($files)->values();
    }

    public function getExportItemsProperty(): LengthAwarePaginator
    {
        $items = $this->listExportItems();

        if ($this->searchExports !== '') {
            $items = $items
                ->filter(fn($i) => str_contains(strtolower($i['name']), strtolower($this->searchExports)))
                ->values();
        }

        return $this->paginateCollection($items, 'exportsPage');
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
            <div class="flex items-center gap-2 min-w-0 flex-wrap">
                @if ($exportPath !== '')
                <button type="button" wire:click="goUp"
                    class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1 whitespace-nowrap">
                    <i class="ti ti-arrow-left"></i> Back
                </button>
                <span class="text-gray-300">|</span>
                @endif

                <button type="button" wire:click="goToPath('')"
                    class="font-medium whitespace-nowrap {{ $exportPath === '' ? 'text-gray-900' : 'text-blue-600 hover:text-blue-800' }}">
                    Tightened Monitoring Dimension
                </button>

                @foreach ($this->breadcrumbs as $crumb)
                <span class="text-gray-300">/</span>
                @if ($loop->last)
                <span class="font-medium text-gray-900 truncate">{{ $crumb['name'] }}</span>
                @else
                <button type="button" wire:click="goToPath(@js($crumb['path']))"
                    class="text-blue-600 hover:text-blue-800 truncate">
                    {{ $crumb['name'] }}
                </button>
                @endif
                @endforeach
            </div>

            <input type="text" wire:model.live.debounce.400ms="searchExports"
                placeholder="Search folder or file..."
                class="text-sm rounded-lg border-gray-300 px-3 py-1.5 w-56">
        </div>

        <div class="divide-y divide-gray-100">
            @forelse ($this->exportItems as $item)
            @if ($item['type'] === 'folder')
            <div wire:key="export-folder-{{ $exportPath }}-{{ $item['name'] }}" class="flex items-center justify-between px-6 py-3">
                <div class="flex items-center gap-3 min-w-0">
                    <i class="ti ti-folder text-xl text-amber-500"></i>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-800 truncate">{{ $item['name'] }}</p>
                        <p class="text-xs text-gray-400">
                            @if ($item['folders'] > 0)
                            {{ $item['folders'] }} {{ \Illuminate\Support\Str::plural('folder', $item['folders']) }} &middot;
                            @endif
                            {{ $item['files'] }} {{ \Illuminate\Support\Str::plural('file', $item['files']) }} &middot;
                            {{ \Illuminate\Support\Carbon::createFromTimestamp($item['modified'])->format('Y-m-d H:i') }}
                        </p>
                    </div>
                </div>
                <button type="button" wire:click="openFolder(@js($item['name']))"
                    class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
                    <i class="ti ti-folder-open"></i> Open
                </button>
            </div>
            @else
            <div wire:key="export-file-{{ $item['relative'] }}" class="flex items-center justify-between px-6 py-3">
                <div class="flex items-center gap-3 min-w-0">
                    <i class="ti ti-file-spreadsheet text-xl text-emerald-500"></i>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-800 truncate">{{ $item['name'] }}</p>
                        <p class="text-xs text-gray-400">
                            {{ $this->formatSize($item['size']) }} &middot;
                            {{ \Illuminate\Support\Carbon::createFromTimestamp($item['modified'])->format('Y-m-d H:i') }}
                        </p>
                    </div>
                </div>
                <button type="button" wire:click="download(@js($item['relative']), 'exports')"
                    class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
                    <i class="ti ti-download"></i> Download
                </button>
            </div>
            @endif
            @empty
            <p class="px-6 py-6 text-center text-sm text-gray-400">
                {{ $exportPath === '' ? 'No folders found' : 'This folder is empty' }}
            </p>
            @endforelse
        </div>

        @if ($this->exportItems->hasPages())
        <div class="px-6 py-3 border-t border-gray-100">
            {{ $this->exportItems->links() }}
        </div>
        @endif
    </div>
</div>