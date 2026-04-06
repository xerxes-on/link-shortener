<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // No-op: health_status is now a string column, no enum modification needed.
        // The 'timeout' value is validated at the application level.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Convert any 'timeout' statuses back to 'error'
        DB::table('links')->where('health_status', 'timeout')->update(['health_status' => 'error']);
    }
};
