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
        Schema::create('cashier_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            // $table->foreignId('store_id')->nullable(); // jika rencana ingin memiliki beberapa outlet dalam satu tenantnya
            // $table->foreign('store_id')->references('id')->on('outlets')->onDelete('cascade'); 
            $table->dateTime('shift_start_time');
            $table->dateTime('shift_end_time')->nullable();
            $table->decimal('starting_cash_amount');
            $table->decimal('ending_cash_amount')->nullable();
            $table->decimal('total_sales_cash')->nullable(); // total penjualan dari uang cash
            $table->decimal('total_sales_e_wallet')->nullable(); // total penjualan dari e-wallet
            $table->decimal('total_sales_transfer_bank')->nullable(); // total penjualan dari tf bank
            $table->decimal('total_sales_qris')->nullable(); // total penjualan dari qris
            $table->decimal('total_sales_gerai')->nullable(); // total penjualan dari gerai
            $table->decimal('total_cash_in')->nullable();
            $table->decimal('total_cash_out')->nullable();
            $table->string('notes')->nullable();
            $table->decimal('cash_difference', 18, 2)->nullable();
            $table->enum('status', ['open', 'closed', 'abandoned'])->default('open');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cashier_sessions');
    }
};
