<?php

namespace App\Inspection\Services\Excel;

use App\Inspection\Models\MIPIRInspectionRecord;
use App\Inspection\Support\PartNumber;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class TightenedDefectsRefresher
{
    public function refresh(
        int $ppf,
        ?string $confirmedBy = null,
        ?string $stampDate = null,
        ?string $sairBase = null
    ): void {
        try {
            $source = app(MipirSourceData::class);
            $header = $source->header($ppf);

            if ($header === null) {
                return;
            }

            $cavity = $header['cavity'];
            $nqr = $header['nqr'];

            $rows = $source->defects($ppf)
                ->filter(static fn($d) => $d->Shot !== null && (int) $d->Shot >= 1);

            $judgements = [];

            foreach ($rows->groupBy(fn($d) => (string) $d->Checktime) as $byTime) {
                foreach ($byTime->groupBy(fn($d) => (int) $d->Shot) as $label => $shotRows) {
                    $ng = $shotRows->sum(static fn($d) => (float) ($d->Qty ?? 0));
                    $percentage = $cavity > 0 ? ($ng / $cavity) * 100 : 0;
                    $judge = $percentage > $nqr ? 'X' : 'O';

                    $judgements[(int) $label] = ($judgements[(int) $label] ?? 'O') === 'X'
                        ? 'X'
                        : $judge;
                }
            }

            ksort($judgements);

            $base = $sairBase !== null && $sairBase !== ''
                ? $sairBase
                : $source->sairBase($ppf);

            app(InsertTightenedDefects::class)->sync([
                'partNo'      => $header['partNo'],
                'basePartNo'  => $base !== '' ? $base : PartNumber::base($header['partNo']),
                'partName'    => $header['partName'],
                'machineNo'   => $header['machineNo'],
                'moldNo'      => $header['moldNo'],
                'lotNo'       => $header['lotNo'],
                'inspector'   => $header['inspector'],
                'confirmedBy' => $confirmedBy,
                'stampDate'   => $stampDate,
            ], $judgements, ensureFile: $judgements !== []);
        } catch (Throwable $e) {
            Log::error('Tightened defects SAIR Excel failed', [
                'PPFNo' => $ppf,
                'error' => $e->getMessage(),
                'at'    => $e->getFile() . ':' . $e->getLine(),
            ]);
        }
    }

    public function refreshFamily(int $ppf, ?string $confirmedBy = null): void
    {
        $source = app(MipirSourceData::class);
        $base   = $source->sairBase($ppf);

        foreach ($source->groupPpfs($ppf) as $groupPpf) {
            $date = MIPIRInspectionRecord::where('PPFNo', $groupPpf)->min('DateJudge');

            $this->refresh(
                $groupPpf,
                $confirmedBy,
                $date ? Carbon::parse($date)->format('Y-m-d') : null,
                $base
            );
        }
    }
}
