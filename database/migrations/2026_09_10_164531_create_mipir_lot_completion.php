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
        Schema::connection('mipirDB')->create('mipir_lot_completion', function (Blueprint $table) {
            $table->id();
            $table->string('PPFNo')->unique();
            $table->boolean('Counted')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mipir_lot_completion');
    }
};
