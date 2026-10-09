<?php

namespace App\Inspection\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class DraftAction
{
    // Ilagay dito LAHAT ng section name na ginagamit sa put()
    private const SECTIONS = [
        'ppfLookup',
        'check-time',
        'defects',
        'dimensions',
        'control-limit',
        'remarks',
    ];

    protected function key(int $ppf, string $section): string
    {
        $owner = Auth::user()->EmployeeID ?? session()->getId();

        return "draft.{$owner}.{$ppf}.{$section}";
    }

    public function put(int $ppf, string $section, array $data): void
    {
        if ($section === 'check-time') {
            logger('DRAFT put check-time', [
                'ppf'    => $ppf,
                'labels' => $data['check-time'] ?? null,
                'owner'  => Auth::user()->EmployeeID ?? session()->getId(),
                'from'   => collect(request('components', []))->map(function ($c) {
                    $name = data_get(json_decode($c['snapshot'] ?? '{}', true), 'memo.name');
                    $calls = collect($c['calls'] ?? [])->pluck('method')->join(',');
                    return "{$name} [{$calls}]";
                })->all(),
            ]);
        }

        Cache::put($this->key($ppf, $section), $data, now()->addHours(8));
    }

    public function get(int $ppf): array
    {
        $draft = [];

        foreach (self::SECTIONS as $section) {
            $value = Cache::get($this->key($ppf, $section));

            if ($value !== null) {
                $draft[$section] = $value;
            }
        }

        return $draft;
    }

    public function clear(int $ppf): void
    {
        foreach (self::SECTIONS as $section) {
            Cache::forget($this->key($ppf, $section));
        }
    }
}
