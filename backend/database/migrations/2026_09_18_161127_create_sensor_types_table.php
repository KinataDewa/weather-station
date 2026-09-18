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
    Schema::create('sensor_types', function (Blueprint $table) {
        $table->id();
        $table->string('code')->unique(); // temp_air, humidity, dll
        $table->string('name');
        $table->string('unit');
        $table->decimal('min_value', 10, 4)->nullable();
        $table->decimal('max_value', 10, 4)->nullable();
        $table->integer('precision')->default(2);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sensor_types');
    }
};
