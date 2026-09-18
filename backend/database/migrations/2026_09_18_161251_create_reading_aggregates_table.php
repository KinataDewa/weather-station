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
    Schema::create('reading_aggregates', function (Blueprint $table) {
        $table->id();
        $table->foreignId('device_id')->constrained()->onDelete('restrict');
        $table->foreignId('sensor_id')->constrained()->onDelete('restrict');
        $table->enum('interval', ['1m', '1h', '1d']);
        $table->timestamp('bucket_time');
        $table->decimal('avg_value', 12, 4)->nullable();
        $table->decimal('min_value', 12, 4)->nullable();
        $table->decimal('max_value', 12, 4)->nullable();
        $table->decimal('sum_value', 12, 4)->nullable();
        $table->integer('count')->default(0);
        $table->timestamps();

        $table->unique(['device_id', 'sensor_id', 'interval', 'bucket_time']);
        $table->index(['device_id', 'sensor_id', 'interval', 'bucket_time']);
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reading_aggregates');
    }
};
