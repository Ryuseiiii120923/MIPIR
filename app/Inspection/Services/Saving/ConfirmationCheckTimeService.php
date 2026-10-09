<?php

namespace App\Inspection\Services\Saving;

use App\Inspection\Actions\DraftAction;

class ConfirmationCheckTimeService
{
    private const KIND_REGULAR = 'regular';
    private const KIND_CS = 'cs';
    private const KIND_CA = 'ca';

    /**
     * A check time is rejected when a dimension row OR its physical appearance
     * (defects) judgement is X. Every cycle restarts the numbering:
     * - Regular touched + rejected -> CS
     * - CS      touched + rejected -> CA1
     * - CA{n}   touched + rejected -> CA{n+1}
     *
     * A source gets a follow-up only if no follow-up of the right kind
     * was encoded at/after the source itself.
     *
     * @return bool true if at least one check time was added
     */
    public function appendIfRejected(int $ppf): bool
    {
        $drafts = app(DraftAction::class);
        $draft  = $drafts->get($ppf);
        $checkTimeDraft = $draft['check-time'] ?? null;

        if (! $checkTimeDraft) {
            return false;
        }

        $dimensions = $draft['dimensions'] ?? [];
        $judgements = $draft['defects']['judgement'] ?? [];
        $touched    = $checkTimeDraft['touched'] ?? [];
        $labels     = $checkTimeDraft['check-time'] ?? [];
        $dateEncode = $checkTimeDraft['date-encode'] ?? [];
        $now        = now()->toDateTimeString();
        $added      = false;

        foreach ($touched as $source) {
            logger('CS check', [
                'source'    => $source,
                'inLabels'  => in_array($source, $labels, true),
                'judgement' => $judgements[$source] ?? null,
                'rowJudges' => array_column($dimensions[$source] ?? [], 'judge'),
                'rejected'  => $this->isRejected($source, $dimensions, $judgements),
                'followUp'  => $this->hasFollowUp($source, $this->kindOf($source) === self::KIND_REGULAR ? self::KIND_CS : self::KIND_CA, $labels, $dateEncode),
            ]);
            if (! in_array($source, $labels, true)) {
                continue; // removed during this session
            }

            if (! $this->isRejected($source, $dimensions, $judgements)) {
                continue;
            }

            $sourceKind = $this->kindOf($source);
            $targetKind = $sourceKind === self::KIND_REGULAR ? self::KIND_CS : self::KIND_CA;

            if ($this->hasFollowUp($source, $targetKind, $labels, $dateEncode)) {
                continue;
            }

            $base = match ($sourceKind) {
                self::KIND_REGULAR => 'CS',
                self::KIND_CS      => 'CA1',
                self::KIND_CA      => 'CA' . ($this->caNumber($source) + 1),
            };

            $new = $this->uniqueLabel($base, $labels);

            $labels[]                           = $new;
            $dateEncode[$new]                   = $now;
            $checkTimeDraft['start-time'][$new] = $now;
            $checkTimeDraft['end-time'][$new]   = $now;
            $checkTimeDraft['touched'][]        = $new;
            $added = true;
        }

        if (! $added) {
            return false;
        }

        $checkTimeDraft['check-time']  = $labels;
        $checkTimeDraft['date-encode'] = $dateEncode;

        $drafts->put($ppf, 'check-time', $checkTimeDraft);

        return true;
    }

    /**
     * PPFNo + Checktime is the DB key, so a label can't repeat within a PPF.
     * Change the suffix format here if you want a different one.
     */
    private function uniqueLabel(string $base, array $labels): string
    {
        if (! in_array($base, $labels, true)) {
            return $base;
        }

        $n = 2;
        while (in_array("{$base}-{$n}", $labels, true)) {
            $n++;
        }

        return "{$base}-{$n}";
    }

    private function hasFollowUp(string $source, string $targetKind, array $labels, array $dateEncode): bool
    {
        $sourceDate = $dateEncode[$source] ?? null;

        if ($sourceDate === null) {
            return false;
        }

        foreach ($labels as $label) {
            if ($label === $source || $this->kindOf($label) !== $targetKind) {
                continue;
            }

            $labelDate = $dateEncode[$label] ?? null;

            if ($labelDate !== null && $labelDate >= $sourceDate) {
                return true;
            }
        }

        return false;
    }

    private function kindOf(string $label): string
    {
        if (preg_match('/^CA\d+(-\d+)?$/', $label)) {
            return self::KIND_CA;
        }

        if (preg_match('/^CS(\d+|-\d+)?$/', $label)) {
            return self::KIND_CS;
        }

        return self::KIND_REGULAR;
    }

    private function caNumber(string $label): int
    {
        preg_match('/^CA(\d+)/', $label, $m);

        return (int) ($m[1] ?? 0);
    }

    private function isRejected(string $checkTime, array $dimensions, array $judgements): bool
    {
        return ($judgements[$checkTime] ?? null) === 'X'
            || $this->hasRejectedDimension($dimensions[$checkTime] ?? []);
    }

    private function hasRejectedDimension(array $rows): bool
    {
        foreach ($rows as $row) {
            if (($row['judge'] ?? null) === 'X') {
                return true;
            }
        }

        return false;
    }
}
