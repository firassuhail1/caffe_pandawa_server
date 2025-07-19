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
        Schema::create('raw_material_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_material_id')->constrained('raw_materials')->onDelete('cascade');
            $table->enum('location_type', ['outlet','warehouse', 'main_warehouse'])->default('main_warehouse');
            $table->decimal('cost_price', 18, 2);
            $table->decimal('current_stock', 10, 2)->default(0);
            $table->decimal('min_stock_alert', 10, 2)->nullable();
            $table->decimal('quantity_allocated', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw_material_inventories');
    }
};
