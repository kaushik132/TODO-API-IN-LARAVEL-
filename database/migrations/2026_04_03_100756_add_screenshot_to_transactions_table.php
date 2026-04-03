<?php
// ===============================================================
// FILE: database/migrations/2024_01_01_000009_add_screenshot_to_transactions_table.php
// ===============================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('screenshot_name')->nullable()->after('note');
            // screenshot_name = image file path (storage mein save hogi)
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('screenshot_name');
        });
    }
};
