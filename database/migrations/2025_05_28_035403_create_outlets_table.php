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
        Schema::create('outlets', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->text('address');
            $table->string('owner_name')->nullable();
            $table->string('owner_phone')->nullable();
            $table->unsignedBigInteger('badan_usaha_id')->nullable();
            $table->unsignedBigInteger('division_id')->nullable();
            $table->unsignedBigInteger('region_id')->nullable();
            $table->unsignedBigInteger('cluster_id')->nullable();
            $table->string('district');
            $table->string('photo_shop_sign')->nullable();
            $table->string('photo_front')->nullable();
            $table->string('photo_left')->nullable();
            $table->string('photo_right')->nullable();
            $table->string('photo_id_card')->nullable();
            $table->string('video')->nullable();
            $table->integer('limit')->nullable();
            $table->integer('radius')->nullable()->default(100);
            $table->string('location')->nullable();
            $table->enum('level', ['LEAD', 'NOO', 'MEMBER'])->default('LEAD');
            $table->enum('status', ['MAINTAIN', 'UNMAINTAIN', 'UNPRODUCTIVE'])->default('MAINTAIN');
            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table->foreign('badan_usaha_id')->references('id')->on('badan_usahas')->onDelete('cascade');
            $table->foreign('division_id')->references('id')->on('divisions')->onDelete('cascade');
            $table->foreign('region_id')->references('id')->on('regions')->onDelete('cascade');
            $table->foreign('cluster_id')->references('id')->on('clusters')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outlets');
    }
};
