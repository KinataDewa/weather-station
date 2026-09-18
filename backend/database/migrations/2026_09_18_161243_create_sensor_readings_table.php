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
    Schema::create('sensor_readings', function (Blueprint $table) {
        $table->id();
        $table->foreignId('device_id')->constrained()->onDelete('restrict');
        $table->foreignId('sensor_id')->constrained()->onDelete('restrict');
        $table->decimal('raw_value', 12, 4);
        $table->decimal('calibrated_value', 12, 4)->nullable();
        $table->boolean('quality_flag')->default(true);
        $table->string('quality_note')->nullable();
        $table->timestamp('device_time');
        $table->timestamp('server_time')->useCurrent();
        $table->integer('seq')->nullable();
        $table->timestamps();

        $table->unique(['device_id', 'sensor_id', 'device_time']); // dedup
        $table->index(['device_id', 'sensor_id', 'device_time']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sensor_readings');
    }
};
