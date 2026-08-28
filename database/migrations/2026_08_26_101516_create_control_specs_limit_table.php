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
        Schema::connection('mipirDB')->create('control_specs_limit', function (Blueprint $table) {
            $table->id();
            $table->text('PartNo');
            $table->decimal('xSpecs', 15,3);
            $table->decimal('xControl', 15,3);
            $table->decimal('ySpecs', 15,3);
            $table->decimal('yControl', 15,3);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mipirDB')->dropIfExists('control_specs_limit');
    }
};
