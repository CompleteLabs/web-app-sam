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
        Schema::table('failed_import_rows', function (Blueprint $table) {
            // Tambahkan kolom validation_error jika belum ada
            if (!Schema::hasColumn('failed_import_rows', 'validation_error')) {
                $table->json('validation_error')->nullable()->after('import_id');
            }
            
            // Tambahkan kolom error jika belum ada (untuk plugin ini)
            if (!Schema::hasColumn('failed_import_rows', 'error')) {
                $table->text('error')->nullable()->after('validation_error');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('failed_import_rows', function (Blueprint $table) {
            if (Schema::hasColumn('failed_import_rows', 'error')) {
                $table->dropColumn('error');
            }
            // Tidak drop validation_error karena mungkin sudah ada sebelumnya
        });
    }
};
