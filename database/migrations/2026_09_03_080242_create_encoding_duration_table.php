<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mipirDB')->create('EncodingDuration', function (Blueprint $table) {
            $table->id('RecNo');
            $table->integer('PPFNo');
            $table->string('PartNo')->nullable();
            $table->string('MachineNo')->nullable();
            $table->string('Encoder')->nullable();
            $table->string('Checktime')->nullable();
            $table->dateTime('StartDatetime');
            $table->dateTime('EndDatetime');
        });
    }

    public function down(): void
    {
        Schema::connection('mipirDB')->dropIfExists('EncodingDuration');
    }
};
