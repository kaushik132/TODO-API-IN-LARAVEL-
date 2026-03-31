<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            $table->string('title');
            $table->decimal('amount', 12, 2);
            $table->date('reminder_date');

            // 0 = same day, 1 = 1 din pehle, 2 = 2 din pehle, 3 = 3 din pehle
            $table->tinyInteger('reminder_before')->default(1);

            $table->text('note')->nullable();
            $table->enum('status', ['pending', 'complete'])->default('pending');
            $table->boolean('is_notified')->default(false); // Notification bheji ya nahi

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
