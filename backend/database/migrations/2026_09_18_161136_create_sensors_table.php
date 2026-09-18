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
    Schema::create('sensors', function (Blueprint $table) {
        $table->id();
        $table->string('serial_number')->unique();
        $table->foreignId('sensor_type_id')->constrained()->onDelete('restrict');
        $table->softDeletes();
        $table->timestamps();

        $table->index('sensor_type_id');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sensors');
    }
};
