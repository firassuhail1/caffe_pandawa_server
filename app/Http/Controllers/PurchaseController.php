<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\RawMaterial;
use Illuminate\Http\Request;
use App\Models\StockMovement;
use App\Models\PurchaseDetail;
use App\Models\Tenant\ProfitRule;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant\PriceHistory;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\RawMaterialInventory;
use App\Models\Tenant\ProductInventory;
use App\Models\Tenant\RawMaterialInventoryBatch;

class PurchaseController extends Controller
{
    public function get_data_pembelian(Request $request) {
        $query = Purchase::with([
            'purchase_details.item', 
        ]);

        // Filter berdasarkan periode waktu
        $period = $request->input('period', 'monthly'); // Default daily
        switch ($period) {
            case 'daily':
                $query->whereDate('purchase_date', Carbon::today());
                break;
            case 'weekly':
                $query->whereBetween('purchase_date', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                break;
            case 'monthly':
                $query->whereMonth('purchase_date', Carbon::now()->month)
                    ->whereYear('purchase_date', Carbon::now()->year);
                break;
            case 'yearly':
                $query->whereYear('purchase_date', Carbon::now()->year);
                break;
            case 'custom':
                if ($request->has('start_date') && $request->start_date != null) {
                    $query->whereDate('purchase_date', '>=', $request->start_date);
                }
                if ($request->has('end_date') && $request->end_date != null) {
                    $query->whereDate('purchase_date', '<=', $request->end_date);
                }
                break;
        }

        $data = $query->orderBy('purchase_date', 'asc')->get();

        return response()->json(['success' => true, 'data' => $data], 200);
    }

    public function get_product_for_pembelian(Request $request) {
        $data_bahan_baku = RawMaterial::all()->map(function ($item) {
            return [
                'id' => $item->id,
                'nama' => $item->nama,
                'unit_of_measure' => $item->unit_of_measure,
                'tipe' => 'raw_material', // Keterangan asal data
                'inventories' => $item->inventories,
                'batches' => $item->batches,
                // tambahkan field lain sesuai kebutuhan
            ];
        });
        
        $data = array_merge(
            $data_bahan_baku->toArray()
        );        

        return response()->json(['success' => true, 'data' => $data], 200);
    }

    public function buat_pembelian(Request $request)
    {
        // --- VALIDASI AWAL (TAMBAHKAN JIKA BELUM ADA) ---
        // Anda mungkin perlu menambahkan validasi untuk request->items (id, quantity, unit_cost, tipe)
        // dan field lain seperti supplier_id, purchase_date, dll.
        // Contoh:
        // $request->validate([
        //     'supplier_id' => 'required|exists:suppliers,id',
        //     'outlet_id' => 'nullable|exists:outlets,id',
        //     'purchase_date' => 'required|date',
        //     'invoice_number' => 'nullable|string|max:255|unique:purchases,invoice_number',
        //     'total_amount' => 'required|numeric|min:0',
        //     'items' => 'required|array|min:1',
        //     'items.*.id' => 'required|numeric',
        //     'items.*.tipe' => 'required|in:product,raw_material',
        //     'items.*.quantity' => 'required|numeric|min:0',
        //     'items.*.unit_cost' => 'required|numeric|min:0',
        //     'items.*.subtotal' => 'required|numeric|min:0',
        // ]);


        DB::beginTransaction();

        try {
            // Buat Header Pembelian
            $purchase = Purchase::create([
                'purchase_date' => $request->purchase_date,
                'invoice_number' => $request->invoice_number,
                'total_amount' => $request->total_amount,
                'created_by' => $request->user()->id,
            ]);

            // Dapatkan metode akuntansi persediaan bahan baku.
            // Asumsi: ini diambil dari konfigurasi aplikasi. Pastikan Anda sudah mengaturnya.
            // Contoh di config/app.php atau services.php:
            // 'raw_material_costing_method' => env('RAW_MATERIAL_COSTING_METHOD', 'weighted_average'),
            // Dan di .env: RAW_MATERIAL_COSTING_METHOD=weighted_average/fifo/lifo

            // Ambil profit rules di luar loop jika semua produk menggunakan set profit rule yang sama
            // dan tidak ada perubahan profit rule per item.
            // Ini akan meningkatkan performa karena query hanya dieksekusi sekali.


            foreach ($request->items as $itemData) { // Menggunakan $itemData agar lebih mudah dibaca daripada $request->items[$i]
                // Buat Detail Pembelian untuk setiap item
                $purchaseDetail = PurchaseDetail::create([ // Gunakan $purchaseDetail (camelCase)
                    'purchase_id' => $purchase->id,
                    'item_id' => $itemData['id'], // Gunakan $itemData
                    'item_type' => $itemData['tipe'] == "product" ? Product::class : RawMaterial::class, // Gunakan $itemData
                    'quantity' => $itemData['quantity'], // Gunakan $itemData
                    'unit_cost' => $itemData['unit_cost'], // Gunakan $itemData
                    'subtotal' => $itemData['subtotal'], // Gunakan $itemData
                ]);

                $rawMaterial = RawMaterial::find($itemData['id']);
                if (!$rawMaterial) {
                    throw new \Exception("Bahan baku dengan ID {$itemData['id']} tidak ditemukan.");
                }


                // --- Langkah 1: Kelola Biaya / HPP Berdasarkan Metode yang Dipilih ---
                // Logika perhitungan HPP rata-rata harus dilakukan SEBELUM stok ditambahkan
                // ke inventaris utama agar 'oldTotalStock' merepresentasikan stok SEBELUM pembelian ini.
                

                // Ambil total stok yang ada SEBELUM penambahan kuantitas baru
                $oldTotalStock = RawMaterialInventory::where('raw_material_id', $rawMaterial->id)
                                                        ->value('current_stock') ?? 0; // Gunakan value() dan null coalesce untuk default 0

                $oldCostPrice = $rawMaterial->cost_price ?? 0; // Ambil HPP rata-rata terakhir, default 0 jika null
                $newPurchaseQuantity = $itemData['quantity'];
                $newPurchaseCost = $itemData['unit_cost'];

                $totalStockAfterThisPurchase = $oldTotalStock + $newPurchaseQuantity;

                // Hindari pembagian dengan nol
                if ($totalStockAfterThisPurchase > 0) {
                    $newAverageCost = (($oldTotalStock * $oldCostPrice) + ($newPurchaseQuantity * $newPurchaseCost)) / $totalStockAfterThisPurchase;
                    $rawMaterial->standart_cost_price = $newAverageCost;
                } else {
                    // Jika tidak ada stok lama, dan pembelian ini adalah yang pertama atau totalnya masih 0
                    $rawMaterial->standart_cost_price = $newPurchaseCost;
                }

                $rawMaterial->save(); // Simpan HPP rata-rata yang baru di master data bahan baku

                // --- Langkah 2 untuk weighted average: Update Total Stok di RawMaterialInventory (Selalu Dilakukan) ---
                // RawMaterialInventory hanya akan menyimpan total stok fisik per lokasi.
                // Ini dilakukan setelah perhitungan biaya karena itu membutuhkan data stok lama.
                $rawMaterialInventory = RawMaterialInventory::firstOrNew([
                    'raw_material_id' => $rawMaterial->id,
                    'location_type' => 'main_warehouse',
                ]);

                if ($totalStockAfterThisPurchase > 0) {
                    $newAverageCost = (($oldTotalStock * $oldCostPrice) + ($newPurchaseQuantity * $newPurchaseCost)) / $totalStockAfterThisPurchase;
                    $rawMaterialInventory->cost_price = $newAverageCost;
                    $rawMaterialInventory->current_stock += $itemData['quantity'];
                } else {
                    // Jika tidak ada stok lama, dan pembelian ini adalah yang pertama atau totalnya masih 0
                    $rawMaterialInventory->cost_price = $newPurchaseCost;
                    $rawMaterialInventory->current_stock += $itemData['quantity'];
                }
                $rawMaterialInventory->save();

                // --- Bagian yang Diperbarui: Update Potensi Stok Produk Jadi ---
                // Dapatkan semua produk yang menggunakan bahan baku ini
                // Gunakan relasi 'products' di RawMaterial model,
                // dan eager load 'recipe' dan 'ingredients' dari setiap produk.
                $productsToUpdate = Product::whereHas('recipe.ingredients', function ($query) use ($rawMaterial) {
                                            $query->where('raw_material_id', $rawMaterial->id);
                                        })
                                        ->with(['recipe.ingredients.rawMaterial.inventories']) // Eager load semua yang dibutuhkan
                                        ->get();

                foreach ($productsToUpdate as $product) {
                    // Ambil resep dari produk melalui relasi 'recipe()'
                    $recipe = $product->recipe;

                    if ($recipe) {
                        $potentialProductYields = []; // Reset untuk setiap produk
                        $cost_price_update = 0;

                        foreach ($recipe->ingredients as $recipeIngredient) { // Menggunakan $recipeIngredient
                            $relatedRawMaterial = $recipeIngredient->rawMaterial; // Menggunakan relasi dari RecipeIngredient

                            // Ambil stok terbaru dari RawMaterialInventory untuk bahan baku ini
                            // Gunakan eager loaded data jika tersedia, atau query ulang jika perlu (meskipun eager loaded lebih baik)
                            $currentRawMaterialStock = $relatedRawMaterial->inventories->where('location_type', 'main_warehouse')->first()->current_stock ?? 0;

                            // Kuantitas bahan baku yang dibutuhkan per 1 unit produk jadi
                            $neededPerProductUnit = $recipeIngredient->quantity_needed; // Menggunakan quantity_needed dari RecipeIngredient

                            if ($neededPerProductUnit > 0) {
                                $potentialProductYields[] = floor($currentRawMaterialStock / $neededPerProductUnit);
                                $cost_price_update += $recipeIngredient->rawMaterial->inventories[0]->cost_price;
                            } else {
                                $potentialProductYields[] = PHP_INT_MAX;
                            }
                        }

                        $minStockAvailableForProduct = 0;
                        if (!empty($potentialProductYields)) {
                            $minStockAvailableForProduct = min($potentialProductYields);
                        }

                        // Update kolom 'stock' di tabel products
                        $product->stock = $minStockAvailableForProduct; // Menggunakan kolom 'stock' yang sudah ada
                        $product->harga_asli_product += $cost_price_update;
                        $product->save();
                    }
                }
                // --- Akhir Bagian yang Diperbarui ---

                // --- Langkah 4: Catat Pergerakan Stok Bahan Baku (Selalu Dilakukan) ---
                StockMovement::create([
                    'item_id' => $itemData['id'], // Gunakan ID dari model yang sudah ditemukan
                    'item_type' => RawMaterial::class,
                    'quantity_change' => $itemData['quantity'],
                    'type' => 'in',
                    'reference_type' => PurchaseDetail::class,
                    'reference_id' => $purchaseDetail->id, // Menggunakan $purchaseDetail (camelCase)
                    'description' => 'Pembelian bahan baku dari supplier',
                    'created_by_user_id' => $request->user()->id,
                ]);
            }

            DB::commit(); // Komit transaksi jika semua berhasil

            return response()->json(['success' => true, 'message' => 'Pembelian berhasil dicatat.'], 200);

        } catch (\Exception $e) {
            DB::rollBack(); // Rollback transaksi jika terjadi kesalahan
            Log::error('Gagal membuat data pembelian: ' . $e->getMessage()); // Log error untuk debugging

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat data pembelian: ' . $e->getMessage(),
            ], 500); // 500 Internal Server Error untuk error server
        }
    }
}























// kasus pertama : 
// - pembelian 1 : 10k dengan pembelian 100
// - pembelian 2 : 12k dengan pembelian 50
// - pembelian 3 : 9.5k dengan pembelian 200

// menghasilkan : 
// hpp fifo = 1.885.000
// stok akhir = 1.615.000

// hpp lifo = 1.710.000
// stok akhir = 1.790.000


// kasus kedua : 
// - pembelian 1 : 9.5k dengan pembelian 200
// - pembelian 2 : 12k dengan pembelian 50
// - pembelian 3 : 10k dengan pembelian 100

// menghasilkan : 
// hpp fifo = 1.710.000
// stok akhir = 1.790.000

// hpp lifo = 1.885.000
// stok akhir = 1.615.000