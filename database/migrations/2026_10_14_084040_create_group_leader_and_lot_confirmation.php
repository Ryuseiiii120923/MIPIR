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
        Schema::connection('mipirDB')->create('GroupLeader', function (Blueprint $t) {
            $t->id('Id');
            $t->string('EmployeeID', 10);           // 社員CD ng GL
            $t->string('Name', 100)->nullable();
            $t->string('Plant', 10);                // P1A | P1B | P2
            $t->string('MachineNo', 20)->nullable(); // null = lahat ng machine sa plant
            $t->boolean('IsActive')->default(true);
            $t->timestamps();
            $t->unique(['EmployeeID', 'Plant', 'MachineNo']);
        });

        Schema::connection('mipirDB')->create('LotConfirmation', function (Blueprint $t) {
            $t->id('Id');
            $t->string('PartNo', 50);
            $t->string('ProdLotNo', 50);
            $t->string('Plant', 10)->nullable();
            $t->string('MachineNo', 20)->nullable();
            $t->string('Status', 12)->default('pending');   // pending | confirmed | rejected
            $t->timestamp('RequestedAt')->nullable();
            $t->string('ConfirmedBy', 10)->nullable();
            $t->timestamp('ConfirmedAt')->nullable();
            $t->string('RejectedBy', 10)->nullable();
            $t->text('RejectReason')->nullable();
            $t->timestamps();
            $t->unique(['PartNo', 'ProdLotNo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_leader_and_lot_confirmation');
    }
};
