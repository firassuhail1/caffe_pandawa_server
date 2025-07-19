<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\RecipeIngredient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\RawMaterialInventory;
use Illuminate\Validation\ValidationException;

class RecipeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Mengambil semua resep dengan relasi produk dan bahan baku (untuk tampilan data)
        $recipes = Recipe::with(['product', 'ingredients.rawMaterial'])->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar resep berhasil diambil.',
            'data' => $recipes
        ], 200);
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
        DB::beginTransaction();

        try {
            // --- Validasi Data ---
            $validatedData = $request->validate([
                'product_id' => 'required|exists:products,id|unique:recipes,product_id', // product_id harus unik di tabel recipes
                'name' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'is_active' => 'required',
                'ingredients' => 'required|array|min:1', // Harus ada minimal satu bahan baku
                'ingredients.*.raw_material_id' => 'required|exists:raw_materials,id',
                'ingredients.*.quantity_needed' => 'required|numeric|min:0.01', // Kuantitas harus positif
            ]);

            // Verifikasi bahwa product_id yang dipilih adalah produk yang bisa diproduksi
            $product = Product::find($validatedData['product_id']);
            if (!$product) {
                throw ValidationException::withMessages([
                    'product_id' => 'Produk yang dipilih tidak ada.'
                ]);
            }

            // --- Membuat Resep Utama ---
            $recipe = Recipe::create([
                'product_id' => $validatedData['product_id'],
                'name' => $validatedData['name'] ?? null,
                'description' => $validatedData['description'] ?? null,
                'is_active' => $validatedData['is_active'] ?? true,
            ]);

            $stockTersisa = PHP_INT_MAX; // Inisialisasi dengan nilai maksimum agar perbandingan pertama selalu lebih kecil
            // Array untuk menyimpan potensi produksi dari setiap bahan baku
            $potentialProductYields = [];

            // --- Menambahkan Bahan Baku Resep ---
            $recipeIngredientsData = [];
            foreach ($validatedData['ingredients'] as $ingredient) {
                $recipeIngredientsData[] = [
                    'recipe_id' => $recipe->id,
                    'raw_material_id' => $ingredient['raw_material_id'],
                    'quantity_needed' => $ingredient['quantity_needed'],
                    // unit_id tidak diperlukan lagi karena unit_of_measure ada di raw_material
                    'created_at' => now(),
                    'updated_at' => now(),
                ];


                $inventory = RawMaterialInventory::where('raw_material_id', $ingredient['raw_material_id'])
                                                ->first();

                // --- LOGIKA PENGHITUNGAN STOCK TERSISA UNTUK PRODUK JADI ---
                // Pastikan inventory sudah di-update setelah dikurangi untuk produksi saat ini
                $remainingStockForThisIngredient = $inventory->current_stock;

                // Hitung berapa unit produk jadi yang bisa dibuat dari sisa bahan baku ini
                // Menggunakan quantity_needed (kebutuhan bahan baku per 1 unit produk jadi)
                if ($ingredient['quantity_needed'] > 0) {
                    $potentialProductYields[] = floor($remainingStockForThisIngredient / $ingredient['quantity_needed']);
                } else {
                    // Handle kasus jika quantity_needed adalah 0 untuk menghindari pembagian dengan nol
                    // atau jika ingredient ini opsional dan tidak membatasi produksi
                    // Anda bisa memilih untuk mengabaikannya atau memberikan nilai tak terhingga (PHP_INT_MAX)
                    $potentialProductYields[] = PHP_INT_MAX;
                }
                // --- AKHIR LOGIKA PENGHITUNGAN STOCK TERSISA ---
            }

            // Setelah loop selesai, cari nilai minimum dari semua potensi produksi
            if (!empty($potentialProductYields)) {
                $stockTersisa = min($potentialProductYields);
            } else {
                // Jika tidak ada bahan baku dalam resep (resep kosong), maka stock tersisa bisa dianggap tak terbatas
                // Atau sesuai dengan logika bisnis Anda untuk resep kosong
                $stockTersisa = 0; // Atau PHP_INT_MAX jika resep kosong berarti bisa buat sebanyak-banyaknya
            }

            // update stok di table product nya
            Product::where('id', $validatedData['product_id'])->update([
                'stock' => $stockTersisa,
            ]);

            RecipeIngredient::insert($recipeIngredientsData); // Gunakan insert untuk multiple records lebih efisien

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Resep berhasil dibuat.',
                'data' => $recipe->load(['product', 'ingredients.rawMaterial']) // Muat ulang relasi untuk respons
            ], 201); // 201 Created

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $e->errors()
            ], 422); // 422 Unprocessable Entity
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal membuat resep: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat resep: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $recipe = Recipe::with(['product', 'ingredients.rawMaterial'])->find($id);

        if (!$recipe) {
            return response()->json([
                'success' => false,
                'message' => 'Resep tidak ditemukan.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail resep berhasil diambil.',
            'data' => $recipe
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        DB::beginTransaction();

        try {
            $recipe = Recipe::find($id);

            if (!$recipe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resep tidak ditemukan.'
                ], 404);
            }

            // --- Validasi Data ---
            $validatedData = $request->validate([
                'product_id' => 'required|exists:products,id|unique:recipes,product_id,' . $id, // product_id unik, kecuali untuk resep ini sendiri
                'name' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'is_active' => 'boolean',
                'ingredients' => 'required|array|min:1',
                'ingredients.*.raw_material_id' => 'required|exists:raw_materials,id',
                'ingredients.*.quantity_needed' => 'required|numeric|min:0.01',
            ]);

            // Verifikasi bahwa product_id yang dipilih adalah produk yang bisa diproduksi
            $product = Product::find($validatedData['product_id']);
            if (!$product) {
                throw ValidationException::withMessages([
                    'product_id' => 'Produk yang dipilih tidak ada.'
                ]);
            }

            // --- Memperbarui Resep Utama ---
            $recipe->update([
                'product_id' => $validatedData['product_id'],
                'name' => $validatedData['name'] ?? null,
                'description' => $validatedData['description'] ?? null,
                'is_active' => $validatedData['is_active'] ?? true,
            ]);

            // --- Memperbarui Bahan Baku Resep ---
            // Hapus semua bahan baku lama terlebih dahulu
            $recipe->ingredients()->delete();

            $stockTersisa = PHP_INT_MAX; // Inisialisasi dengan nilai maksimum agar perbandingan pertama selalu lebih kecil
            $cost_price_update = 0;

            // Array untuk menyimpan potensi produksi dari setiap bahan baku
            $potentialProductYields = [];
            
            // Tambahkan kembali bahan baku yang baru
            $recipeIngredientsData = [];
            foreach ($validatedData['ingredients'] as $ingredient) {
                $recipeIngredientsData[] = [
                    'recipe_id' => $recipe->id,
                    'raw_material_id' => $ingredient['raw_material_id'],
                    'quantity_needed' => $ingredient['quantity_needed'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $inventory = RawMaterialInventory::where('raw_material_id', $ingredient['raw_material_id'])
                                                ->first();

                // --- LOGIKA PENGHITUNGAN STOCK TERSISA UNTUK PRODUK JADI ---
                // Pastikan inventory sudah di-update setelah dikurangi untuk produksi saat ini
                $remainingStockForThisIngredient = $inventory->current_stock;

                // Hitung berapa unit produk jadi yang bisa dibuat dari sisa bahan baku ini
                // Menggunakan quantity_needed (kebutuhan bahan baku per 1 unit produk jadi)
                if ($ingredient['quantity_needed'] > 0) {
                    $potentialProductYields[] = floor($remainingStockForThisIngredient / $ingredient['quantity_needed']);
                    $cost_price_update += $inventory->cost_price;
                } else {
                    // Handle kasus jika quantity_needed adalah 0 untuk menghindari pembagian dengan nol
                    // atau jika ingredient ini opsional dan tidak membatasi produksi
                    // Anda bisa memilih untuk mengabaikannya atau memberikan nilai tak terhingga (PHP_INT_MAX)
                    $potentialProductYields[] = PHP_INT_MAX;
                }
                // --- AKHIR LOGIKA PENGHITUNGAN STOCK TERSISA ---
            }

            // Setelah loop selesai, cari nilai minimum dari semua potensi produksi
            if (!empty($potentialProductYields)) {
                $stockTersisa = min($potentialProductYields);
            } else {
                // Jika tidak ada bahan baku dalam resep (resep kosong), maka stock tersisa bisa dianggap tak terbatas
                // Atau sesuai dengan logika bisnis Anda untuk resep kosong
                $stockTersisa = 0; // Atau PHP_INT_MAX jika resep kosong berarti bisa buat sebanyak-banyaknya
            }

            Log::debug($stockTersisa);
            RecipeIngredient::insert($recipeIngredientsData);

            // update stok di table product nya
            Product::where('id', $validatedData['product_id'])->update([
                'stock' => $stockTersisa,
                'harga_asli_product' => $cost_price_update,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Resep berhasil diperbarui.',
                'data' => $recipe->load(['product', 'ingredients.rawMaterial'])
            ], 200);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal memperbarui resep: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui resep: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        DB::beginTransaction();

        try {
            $recipe = Recipe::find($id);

            if (!$recipe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resep tidak ditemukan.'
                ], 404);
            }

            // Menggunakan relasi untuk menghapus ingredients terlebih dahulu (onDelete cascade akan bekerja, tapi ini lebih eksplisit)
            $recipe->ingredients()->delete();
            $recipe->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Resep berhasil dihapus.'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal menghapus resep: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus resep: ' . $e->getMessage()
            ], 500);
        }
    }
}
