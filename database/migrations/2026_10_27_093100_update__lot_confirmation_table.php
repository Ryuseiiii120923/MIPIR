<?php

use App\Inspection\Models\GroupLeader\LotConfirmation;
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
        Schema::connection('mipirDB')->table('LotConfirmation', function(Blueprint $table){
            $table->integer('PPFNo')->nullable()->after('Id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mipirDB')->table('LotConfirmation', function(Blueprint $table){
            $table->dropColumn('PPFNo');
        });
    }
};
