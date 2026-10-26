<?php

namespace App\Inspection\Models;

use App\Inspection\Services\PartFamily;
use App\Inspection\Support\PartNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MIPIRInspectionRecord extends Model
{
    protected $table = 'tblInspectionRecord';
    protected $connection = 'mipirDB';
    protected $primaryKey = 'RECNO';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'PPFNo',
        'PartNo',
        'MDNo',
        'NoofCavity',
        'NQR',
        'ProdLotNo',
        'MachineNo',
        'Checktime',
        'Remarks',
        'DateJudge',
        'ConfirmedBy',
        'InspectBy',
        'Year',
        'MoldingOperator'
    ];

    public function dimensionMeasures()
    {
        return $this->hasMany(MIPIRDimensionMeasure::class, 'PPFNo', 'PPFNo');
    }

    public function scopeInGroup(Builder $query, string $partNo, ?string $mdNo, array $alsoPartNos = []): Builder
    {
        $family   = app(PartFamily::class);
        $base     = PartNumber::base($partNo);
        $len      = mb_strlen($base);
        $md       = trim((string) $mdNo);
        $isDotted = preg_match('/^[A-Za-z0-9]+\.[A-Za-z0-9]+\.\d+$/', $base) === 1;
        $foreign  = $family->foreign($partNo);
        $members  = array_values(array_unique([...$family->members($partNo), ...$alsoPartNos]));

        return $query
            ->where(function ($q) use ($base, $len, $isDotted, $members) {
                $q->where('PartNo', $base)
                    ->orWhereRaw(
                        "LEFT(PartNo, ?) = ? AND SUBSTRING(PartNo, ?, 1) IN (' ', '-')",
                        [$len, $base, $len + 1]
                    );

                if ($isDotted) {
                    $q->orWhereRaw(
                        "LEFT(PartNo, ?) = ? AND SUBSTRING(PartNo, ?, 1) LIKE '[A-Za-z]'",
                        [$len, $base, $len + 1]
                    );
                }

                if ($members !== []) {
                    $q->orWhereIn('PartNo', $members);
                }
            })
            ->where(function ($q) use ($md) {
                if ($md === '') {
                    $q->whereNull('MDNo')->orWhere('MDNo', '');
                } else {
                    $q->where('MDNo', $md);
                }
            })
            ->when($foreign !== [], fn($q) => $q->whereNotIn('PartNo', $foreign));
    }
}
