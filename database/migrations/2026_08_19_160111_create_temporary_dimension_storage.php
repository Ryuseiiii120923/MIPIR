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
        Schema::connection('mipirDB')->create('tempDim_storage', function (Blueprint $table) {
            $table->id();
            $table->string('PartNo');
            $table->string('DimensionName');
            $table->string('Device');
            $table->string('Specification');
            $table->string('UpperLimit');
            $table->string('LowerLimit');
            $table->dateTime('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tempDim_storage');
    }
};
