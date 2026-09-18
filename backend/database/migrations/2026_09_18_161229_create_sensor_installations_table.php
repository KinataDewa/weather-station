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
    Schema::create('sensor_installations', function (Blueprint $table) {
        $table->id();
        $table->foreignId('sensor_id')->constrained()->onDelete('restrict');
        $table->foreignId('device_id')->constrained()->onDelete('restrict');
        $table->timestamp('installed_at');
        $table->timestamp('removed_at')->nullable();
        $table->timestamps();

        $table->index(['device_id', 'installed_at']);
        $table->index(['sensor_id', 'installed_at']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sensor_installations');
    }
};
