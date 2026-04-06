<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // No-op: health_status is now a string column, no enum modification needed.
        // The 'blocked' value is validated at the application level.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: health_status is now a string column.
    }
};
