<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use App\Models\Product;
use App\Models\Transaksi;
use Illuminate\Http\Request;
use App\Models\RawMaterialInventory;

class TransaksiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = Transaksi::all();

        return response()->json(['success' => true, 'data' => $data], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $items = $request->input('items'); // array of cart items
        $totalBayar = $request->input('total_bayar'); 
        $jumlahBayar = $request->input('jumlah_bayar'); 
        $kembalian = $request->input('kembalian');

        $daftarBarang = [];

        // lalu stelah meng create data, saya ingin mengupdate data di table Product, berdasarkan id product yg ada di key items->product, update stocknya dikurangin sama key 'quantity' (qty di belinya)
        foreach ($items as $item) {
            $product = $item['product'];
            $quantity = $item['quantity'];
            $totalHarga = $item['totalHarga'];

            $productModel = Product::findOrFail($product['id']);

            // Cari resep aktif
            $recipe = Recipe::where('product_id', $productModel->id)
                            ->where('is_active', true)
                            ->with('ingredients.rawMaterial.inventories')
                            ->first();

            if (!$recipe || $recipe->ingredients->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => "Produk '{$productModel->nama}' belum memiliki resep aktif untuk produksi."
                ], 422);
            }

            $stockTersisa = PHP_INT_MAX; // Inisialisasi dengan nilai maksimum agar perbandingan pertama selalu lebih kecil
            $hppProduct = 0;

            // Array untuk menyimpan potensi produksi dari setiap bahan baku
            $potentialProductYields = [];

            // 🔧 Cek & proses bahan baku
            foreach ($recipe->ingredients as $ingredient) {
                $rawMaterial = $ingredient->rawMaterial;
                $requiredQty = $ingredient->quantity_needed * $quantity;

                $inventory = RawMaterialInventory::where('raw_material_id', $rawMaterial->id)
                                                ->first();

                if (!$inventory || ($inventory->current_stock - $inventory->quantity_allocated) < $requiredQty) {
                    return response()->json([
                        'success' => false,
                        'message' => "Bahan baku '{$rawMaterial->nama}' tidak cukup untuk produksi '{$productModel->nama}'."
                    ], 422);
                }

                // --- LOGIKA PENGHITUNGAN STOCK TERSISA UNTUK PRODUK JADI ---
                // Pastikan inventory sudah di-update setelah dikurangi untuk produksi saat ini
                $remainingStockForThisIngredient = $inventory->current_stock - $requiredQty;

                // Hitung berapa unit produk jadi yang bisa dibuat dari sisa bahan baku ini
                // Menggunakan quantity_needed (kebutuhan bahan baku per 1 unit produk jadi)
                if ($ingredient->quantity_needed > 0) {
                    $potentialProductYields[] = floor($remainingStockForThisIngredient / $ingredient->quantity_needed);
                } else {
                    // Handle kasus jika quantity_needed adalah 0 untuk menghindari pembagian dengan nol
                    // atau jika ingredient ini opsional dan tidak membatasi produksi
                    // Anda bisa memilih untuk mengabaikannya atau memberikan nilai tak terhingga (PHP_INT_MAX)
                    $potentialProductYields[] = PHP_INT_MAX;
                }
                // --- AKHIR LOGIKA PENGHITUNGAN STOCK TERSISA ---
                
                // Kurangi stok bahan baku
                $inventory->current_stock -= $requiredQty;
                $inventory->save();

                $hppProduct += $ingredient->quantity_needed * $rawMaterial->inventories[0]->cost_price;
            }

            // Setelah loop selesai, cari nilai minimum dari semua potensi produksi
            if (!empty($potentialProductYields)) {
                $stockTersisa = min($potentialProductYields);
            } else {
                // Jika tidak ada bahan baku dalam resep (resep kosong), maka stock tersisa bisa dianggap tak terbatas
                // Atau sesuai dengan logika bisnis Anda untuk resep kosong
                $stockTersisa = 0; // Atau PHP_INT_MAX jika resep kosong berarti bisa buat sebanyak-banyaknya
            }
            
            // logika membuat data daftar barang nya
            $daftarBarang[] = [
                'nama_product' => $product['nama_product'],
                'harga' => $product['harga'],
                'hpp_product' => $hppProduct,
                'quantity' => $quantity,
                'totalHarga' => $totalHarga,
            ];

            // update stok di table product nya
            Product::where('id', $product['id'])->update([
                'stock' => $stockTersisa,
            ]);
        }

        // saya ingin memasukkan data ke table Transaksi, tetapi skema nya itu bagian key 'product' di ganti nama produk nya saja, jadi tidak full se model
        $transaksi = Transaksi::create([
            'daftar_barang' => json_encode($daftarBarang),
            'cashier_session_id' => $request->cashier_session_id,
            'user_id' => $request->user_id,
            'total_amount' => $totalBayar,
            'amount_paid' => $jumlahBayar,
            'change_amount' => $kembalian,
        ]);

        return response()->json(['success' => true, 'data' => $transaksi], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Transaksi $transaksi)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Transaksi $transaksi)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Transaksi $transaksi)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Transaksi $transaksi)
    {
        //
    }
}
