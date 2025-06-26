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
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->after('name');
            $table->string('phone')->nullable()->after('email');
            // Relasi ke TM (atasan langsung)
            $table->unsignedBigInteger('tm_id')->nullable()->after('phone');
            $table->unsignedBigInteger('role_id')->nullable()->after('tm_id');
            $table->string('notif_id')->nullable()->after('role_id');

            // Foreign key constraints
            $table->foreign('tm_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('set null');
            $table->softDeletes()->after('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tm_id']);
            $table->dropForeign(['role_id']);
            // Drop columns
            $table->dropColumn([
                'username',
                'phone',
                'tm_id',
                'role_id',
                'notif_id',
            ]);
        });
    }
};
