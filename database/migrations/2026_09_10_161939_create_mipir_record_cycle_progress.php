<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('mipirDB')->table('tblDimensionMeasure', function (Blueprint $table) {
            $table->boolean('isRecord')->nullable()->default(null)->after('xbarTransaction');
        });

        Schema::connection('mipirDB')->create('mipir_record_cycle_progress', function (Blueprint $table) {
            $table->id();
            $table->string('PartNo')->unique();
            $table->unsignedInteger('Count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mipir_record_cycle_progress');
    }
};
