<?php

use App\Inspection\Models\XBarCycleLog;
use App\Inspection\Models\XBarCycleProgress;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([XBarCycleProgress::class, XBarCycleLog::class] as $model) {
            Schema::connection('mipirDB')->table((new $model)->getTable(), function (Blueprint $table) {
                $table->string('GroupKey', 200)->default('');
            });
        }
    }

    public function down(): void
    {
        foreach ([XBarCycleProgress::class, XBarCycleLog::class] as $model) {
            Schema::connection('mipirDB')->table((new $model)->getTable(), function (Blueprint $table) {
                $table->dropColumn('GroupKey');
            });
        }
    }
};