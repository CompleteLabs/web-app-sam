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
        Schema::table('visits', function (Blueprint $table) {
            $table->boolean('external_synced')->default(false)->after('report');
            $table->timestamp('external_synced_at')->nullable()->after('external_synced');
            $table->json('external_sync_response')->nullable()->after('external_synced_at');
            $table->string('external_sync_status')->nullable()->after('external_sync_response');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn(['external_synced', 'external_synced_at', 'external_sync_response', 'external_sync_status']);
        });
    }
};
