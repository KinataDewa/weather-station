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
    Schema::create('devices', function (Blueprint $table) {
        $table->id();
        $table->string('device_id')->unique();
        $table->string('name');
        $table->foreignId('location_id')->constrained()->onDelete('restrict');
        $table->enum('status', ['provisioned', 'active', 'maintenance', 'decommissioned'])->default('provisioned');
        $table->string('api_key_hash');
        $table->string('firmware_version')->nullable();
        $table->decimal('battery_v', 4, 2)->nullable();
        $table->integer('rssi')->nullable();
        $table->timestamp('last_seen_at')->nullable();
        $table->softDeletes();
        $table->timestamps();

        $table->index('status');
        $table->index('location_id');
        $table->index('last_seen_at');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
