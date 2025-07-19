<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Product::insert([
            [
                'kode_product' => 'BRG001',
                'nama_product' => 'Indomie Goreng',
                'image' => 'https://example.com/images/indomie.jpg',
                'harga' => 3000,
                'stock' => 0,
                'jml_product_per_bundling' => 40,
                'keterangan' => 'Mi instan goreng favorit',
                'harga_asli_product' => 2500,
                'harga_asli_product_bundling' => 100000,
                'harga_jual_product_bundling' => 120000,
                'harga_asli_sebelumnya' => 2400,
                'harga_jual_sebelumnya' => 2800,
                'qty_dibeli' => 1,
                'total_harga' => 3000,
            ],
            [
                'kode_product' => 'BRG003',
                'nama_product' => 'Kopi Kapal Api',
                'image' => 'https://example.com/images/kopi.jpg',
                'harga' => 1500,
                'stock' => 0,
                'jml_product_per_bundling' => 20,
                'keterangan' => 'Kopi hitam bubuk',
                'harga_asli_product' => 1200,
                'harga_asli_product_bundling' => 24000,
                'harga_jual_product_bundling' => 30000,
                'harga_asli_sebelumnya' => 1100,
                'harga_jual_sebelumnya' => 1400,
                'qty_dibeli' => 2,
                'total_harga' => 3000,
            ],
            [
                'kode_product' => 'BRG004',
                'nama_product' => 'Susu Putih',
                'image' => 'https://example.com/images/dancow.jpg',
                'harga' => 18000,
                'stock' => 0,
                'jml_product_per_bundling' => 6,
                'keterangan' => 'Susu bubuk balita',
                'harga_asli_product' => 15000,
                'harga_asli_product_bundling' => 90000,
                'harga_jual_product_bundling' => 108000,
                'harga_asli_sebelumnya' => 14500,
                'harga_jual_sebelumnya' => 17000,
                'qty_dibeli' => 1,
                'total_harga' => 18000,
            ],
        ]);
    }
}
