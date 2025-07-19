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
        Schema::create('raw_material_inventory_batches', function (Blueprint $table) {
            $table->id(); // Kolom ID utama untuk tabel ini

            // Kunci asing ke bahan baku yang diwakili oleh batch ini
            $table->foreignId('raw_material_id')
                  ->constrained('raw_materials')
                  ->onDelete('cascade'); // Jika bahan baku dihapus, batch-nya juga dihapus

            // Kunci asing ke detail pembelian yang menciptakan batch ini. Nullable jika batch berasal dari sumber lain (misal: penyesuaian awal).
            // $table->foreignId('purchase_detail_id')
            //       ->nullable()
            //       ->constrained('purchase_details')
            //       ->onDelete('set NULL'); // Jika detail pembelian dihapus, set ID detail pembelian di batch menjadi NULL
            $table->enum('source_type', ['purchase', 'transfer_in', 'initial_stock']);
            $table->unsignedBigInteger('source_id')->nullable();

            // Kuantitas bahan baku yang diterima dalam batch ini
            $table->decimal('quantity_in', 10, 2)->default(0);
            // Kuantitas bahan baku yang tersisa dari batch ini (akan berkurang saat digunakan)
            $table->decimal('quantity_remaining', 10, 2)->default(0);
            // Biaya per unit dari bahan baku dalam batch spesifik ini
            $table->decimal('unit_cost', 15, 2)->default(0);
            // Tanggal masuknya batch, penting untuk penentuan urutan FIFO/LIFO
            $table->timestamp('entry_datetime')->useCurrent(); // Default menggunakan waktu saat ini

            $table->timestamps(); // Kolom created_at dan updated_at

            // Indeks untuk pencarian cepat berdasarkan bahan baku, lokasi, dan tanggal masuk
            // $table->index(['raw_material_id', 'outlet_id', 'entry_date']);
            $table->index(
                ['raw_material_id', 'entry_datetime'],
                'rm_inventory_batches_idx'
            );            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw_material_inventory_batches');
    }
};
