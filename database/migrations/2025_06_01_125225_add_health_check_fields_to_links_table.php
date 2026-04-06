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
        Schema::table('links', function (Blueprint $table) {
            $table->timestamp('last_checked_at')->nullable()->after('click_count');
            $table->string('health_status', 20)
                ->default('unchecked')
                ->after('last_checked_at');
            $table->integer('http_status_code')->nullable()->after('health_status');
            $table->string('health_check_message')->nullable()->after('http_status_code');
            $table->string('final_url')->nullable()->after('health_check_message');

            // Index for finding links that need checking
            $table->index(['health_status', 'last_checked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->dropIndex(['health_status', 'last_checked_at']);
            $table->dropColumn([
                'last_checked_at',
                'health_status',
                'http_status_code',
                'health_check_message',
                'final_url',
            ]);
        });
    }
};
