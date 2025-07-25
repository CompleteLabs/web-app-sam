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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('can_access_web')->default(false);
            $table->boolean('can_access_mobile')->default(false);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->json('scope_required_fields')->nullable();
            $table->json('scope_multiple_fields')->nullable();
            $table->timestamps();

            // Add foreign key constraint for parent_id
            $table->foreign('parent_id')->references('id')->on('roles')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
