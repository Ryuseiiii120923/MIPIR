<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mipirDB')->table('control_specs_limit', function (Blueprint $table) {


            $table->decimal('CSLx', 15, 3)->nullable();
            $table->decimal('USLx', 15, 3)->nullable();
            $table->decimal('LSLx', 15, 3)->nullable();
            $table->decimal('CCLx', 15, 3)->nullable();
            $table->decimal('UCLx', 15, 3)->nullable();
            $table->decimal('LCLx', 15, 3)->nullable();
            $table->decimal('CSLr', 15, 3)->nullable();
            $table->decimal('USLr', 15, 3)->nullable();
            $table->decimal('LSLr', 15, 3)->nullable();
            $table->decimal('CCLr', 15, 3)->nullable();
            $table->decimal('UCLr', 15, 3)->nullable();
            $table->decimal('LCLr', 15, 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('mipirDB')->table('control_specs_limit', function (Blueprint $table) {
            $table->dropColumn(['DimItem', 'CSLx', 'USLx', 'LSLx', 'CCLx', 'UCLx', 'LCLx', 'CSLr', 'USLr', 'LSLr', 'CCLr', 'UCLr', 'LCLr']);
            $table->decimal('xSpecs', 15, 3);
            $table->decimal('xControl', 15, 3);
            $table->decimal('ySpecs', 15, 3);
            $table->decimal('yControl', 15, 3);
        });
    }
};