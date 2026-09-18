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
    Schema::create('sensor_calibrations', function (Blueprint $table) {
        $table->id();
        $table->foreignId('sensor_id')->constrained()->onDelete('restrict');
        $table->decimal('offset', 10, 4)->default(0);
        $table->decimal('scale', 10, 6)->default(1);
        $table->timestamp('valid_from');
        $table->text('note')->nullable();
        $table->timestamps();

        $table->index(['sensor_id', 'valid_from']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sensor_calibrations');
    }
};
