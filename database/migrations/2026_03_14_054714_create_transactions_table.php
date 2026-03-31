<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            // Foreign Key - Kaun sa user ka transaction hai
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

               $table->text('name')->nullable();
            $table->text('phone')->nullable();

            // Main Type: debiter ya crediter
            $table->enum('type', ['debtor', 'creditor']);

            // Amounts
            $table->decimal('total_amount', 12, 2);
            $table->decimal('pending_amount', 12, 2)->default(0);

            // Payment Direction
            $table->enum('payment_type', ['to_pay', 'to_receive']);

            // Recurring Payment
            $table->boolean('is_recurring')->default(false);
            $table->decimal('installment_amount', 12, 2)->nullable(); // Only if recurring
            $table->date('installment_date')->nullable();              // Only if recurring

            // Common Fields
            $table->date('date');
            $table->text('note')->nullable();


            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
