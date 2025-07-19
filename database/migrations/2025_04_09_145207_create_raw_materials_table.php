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
        Schema::create('raw_materials', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('sku')->unique()->nullable();
            $table->enum('unit_of_measure', ['kg', 'gram', 'liter', 'ml', 'meter', 'cm', 'mm', 'pcs', 'rol', 'botol', 'sachet'])->default('pcs');
            $table->decimal('standart_cost_price', 18, 2);
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw_materials');
    }
};
