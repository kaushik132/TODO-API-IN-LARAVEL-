<?php
// ===============================================================
// FILE: database/migrations/2024_01_01_000008_add_department_role_to_users_table.php
// ===============================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')
                  ->nullable()
                  ->after('fcm_token')
                  ->constrained('departments')
                  ->onDelete('set null');

            $table->enum('role', ['super_admin', 'admin', 'member'])
                  ->default('member')
                  ->after('department_id');
            // super_admin = sabko dekh sakta hai
            // admin       = apne department ko manage kar sakta hai
            // member      = sirf apne department ke log dekh sakta hai
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn(['department_id', 'role']);
        });
    }
};
