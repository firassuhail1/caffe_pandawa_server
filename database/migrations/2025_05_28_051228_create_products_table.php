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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('kode_product')->nullable();
            $table->string('nama_product');
            $table->string('image')->nullable();
            $table->double('harga', 10, 2);
            $table->integer('stock');
            $table->integer('jml_product_per_bundling')->nullable(); // jmlIsiBarang
            $table->text('keterangan')->nullable();
            $table->enum('unit_of_measure', ['kg', 'gram', 'liter', 'ml', 'pcs', 'slice', 'pack', 'box', 'sheet', 'roll', 'set', 'dozen'])->default('pcs');

            $table->double('harga_asli_product', 18, 2)->nullable();

            $table->double('harga_asli_product_bundling', 18, 2)->nullable();
            $table->double('harga_jual_product_bundling', 18, 2)->nullable();

            $table->double('harga_asli_sebelumnya', 18, 2)->nullable();
            $table->double('harga_jual_sebelumnya', 18, 2)->nullable();

            $table->double('qty_dibeli', 10, 2)->nullable();
            $table->double('total_harga', 10, 2)->nullable();

            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
