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
    Schema::create('device_status_histories', function (Blueprint $table) {
        $table->id();
        $table->foreignId('device_id')->constrained()->onDelete('cascade');
        $table->enum('status', ['provisioned', 'active', 'maintenance', 'decommissioned']);
        $table->text('note')->nullable();
        $table->timestamp('changed_at')->useCurrent();

        $table->index(['device_id', 'changed_at']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_status_histories');
    }
};
