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
        Schema::table('imports', function (Blueprint $table) {
            // Tambahkan kolom imported_rows jika belum ada
            if (!Schema::hasColumn('imports', 'imported_rows')) {
                $table->unsignedInteger('imported_rows')->default(0)->after('successful_rows');
            }
            
            // Tambahkan kolom failed_rows jika belum ada  
            if (!Schema::hasColumn('imports', 'failed_rows')) {
                $table->unsignedInteger('failed_rows')->default(0)->after('imported_rows');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            if (Schema::hasColumn('imports', 'imported_rows')) {
                $table->dropColumn('imported_rows');
            }
            if (Schema::hasColumn('imports', 'failed_rows')) {
                $table->dropColumn('failed_rows');
            }
        });
    }
};
