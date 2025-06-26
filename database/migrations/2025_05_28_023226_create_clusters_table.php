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
        Schema::create('clusters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('badan_usaha_id')
                ->constrained('badan_usahas')
                ->onDelete('cascade');
            $table->foreignId('division_id')
                ->constrained('divisions')
                ->onDelete('cascade');
            $table->foreignId('region_id')
                ->constrained('regions')
                ->onDelete('cascade');
            $table->string('name')->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clusters');
    }
};
