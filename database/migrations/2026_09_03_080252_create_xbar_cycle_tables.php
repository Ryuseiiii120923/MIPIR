<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mipirDB')->create('XBarCycleProgress', function (Blueprint $table) {
            $table->id('RecNo');
            $table->string('Plant');
            $table->string('DimensionName');
            $table->unsignedTinyInteger('Count')->default(0);
            $table->dateTime('CycleStart')->nullable();
            $table->unique(['PartNo', 'DimensionName']);
        });

        Schema::connection('mipirDB')->create('XBarCycleLog', function (Blueprint $table) {
            $table->id('RecNo');
            $table->string('Plant');
            $table->string('DimensionName');
            $table->dateTime('StartDatetime');
            $table->dateTime('EndDatetime');
            $table->string('FilePath')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('mipirDB')->dropIfExists('XBarCycleLog');
        Schema::connection('mipirDB')->dropIfExists('XBarCycleProgress');
    }
};