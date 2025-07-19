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
        Schema::create('main_cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('main_cash_balance_id')->constrained('main_cash_balances')->onDelete('cascade');
            $table->enum('transaction_type', ['deposit', 'withdrawal', 'transfer_in', 'transfer_out', 'expense']);
            $table->decimal('amount', 18, 2);
            $table->text('description');
            $table->foreignId('initiated_by_user_id')->constrained('users')->onDelete('cascade');
            $table->string('reference_id'); // Bisa mengacu ke cashier_session_id (jika setoran dari kasir), atau ID invoice/bukti lainnya.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('main_cash_movements');
    }
};
