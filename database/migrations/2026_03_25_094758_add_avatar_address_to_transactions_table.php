<?php
// ===============================================================
// FILE: database/migrations/2024_01_01_000006_add_avatar_address_to_transactions_table.php
// ===============================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('address')->nullable()->after('phone');
            $table->string('avatar')->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['address', 'avatar']);
        });
    }
};
