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
        Schema::create('main_cash_balances', function (Blueprint $table) {
            $table->id();
            $table->string('account_name')->unique();
            $table->enum('account_type', ['cash', 'transfer_bank', 'e-wallet', 'qris']);
            $table->decimal('current_balance', 18, 2);
            $table->enum('currency', ['IDR', 'USD'])->default('IDR');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('main_cash_balances');
    }
};
