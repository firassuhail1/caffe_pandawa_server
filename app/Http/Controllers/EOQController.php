<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Product;
use App\Models\Transaksi;
use App\Models\eoqSetting;
use App\Models\RawMaterial;
use Illuminate\Http\Request;
use App\Models\RecipeIngredient;

class EOQController extends Controller
{
    // 💾 Buat atau update setting EOQ
    public function saveSetting(Request $request)
    {
        $validated = $request->validate([
            'raw_material_id' => 'required|exists:raw_materials,id',
            'ordering_cost' => 'required|numeric|min:0',
            'holding_cost_percent' => 'required|numeric|min:0',
        ]);

        $setting = EOQSetting::updateOrCreate(
            ['raw_material_id' => $validated['raw_material_id']],
            [
                'ordering_cost' => $validated['ordering_cost'],
                'holding_cost_percent' => $validated['holding_cost_percent'],
            ]
        );

        return response()->json(['success' => true, 'data' => $setting]);
    }

    /**
     * Calculate EOQ for a specific raw material.
     *
     * @param  int  $rawMaterialId
     * @return \Illuminate\Http\JsonResponse
     */
    public function calculate($rawMaterialId)
    {
        $setting = EOQSetting::where('raw_material_id', $rawMaterialId)->first();
        if (!$setting) {
            return response()->json(['error' => 'EOQ setting tidak ditemukan untuk bahan baku ini'], 404);
        }

        // Muat relasi rawMaterial untuk menghitung holding_cost yang benar
        $setting->load('rawMaterial');

        $D = $this->getDemandForRawMaterial($rawMaterialId);
        $S = $setting->ordering_cost;
        $H = $setting->holding_cost; // Ini akan memanggil accessor di model EOQSetting

        if ($D == 0 || $S == 0 || $H == 0) {
            return response()->json([
                'error' => 'Nilai Demand (D), Ordering Cost (S), atau Holding Cost (H) tidak boleh 0. Perhitungan EOQ tidak dapat dilakukan.',
                'demand_per_year' => round($D, 2),
                'ordering_cost' => round($S, 2),
                'holding_cost' => round($H, 2)
            ], 400);
        }

        $EOQ = sqrt((2 * $D * $S) / $H);

        $rawMaterial = RawMaterial::find($rawMaterialId); // Ambil nama bahan baku
        $materialName = $rawMaterial ? $rawMaterial->nama : 'N/A';
        $unit = $rawMaterial ? $rawMaterial->unit_of_measure : 'N/A';


        return response()->json([
            'raw_material_id' => $rawMaterialId,
            'raw_material_name' => $materialName,
            'unit' => $unit,
            'annual_demand' => round($D, 2),
            'order_cost' => round($S, 2),
            'holding_cost' => round($H, 2),
            'eoq' => round($EOQ, 2),
        ]);
    }

    /**
     * List all EOQ calculations for all raw materials with settings.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function listAll()
    {
        // Eager load eoqSetting dan rawMaterial di dalamnya untuk efisiensi
        $materials = RawMaterial::with(['eoqSetting.rawMaterial'])->get();
        $results = [];

        foreach ($materials as $material) {
            $setting = $material->eoqSetting;
            
            // Hanya proses bahan baku yang memiliki setting EOQ yang valid
            if (!$setting || !$setting->rawMaterial) {
                continue;
            }

            $D = $this->getDemandForRawMaterial($material->id);
            $S = $setting->ordering_cost;
            $H = $setting->holding_cost; // Memanggil accessor

            if ($D > 0 && $S > 0 && $H > 0) {
                $EOQ = sqrt((2 * $D * $S) / $H);
                $results[] = [
                    'raw_material_id' => $material->id,
                    'raw_material_name' => $material->nama,
                    'unit' => $material->unit_of_measure,
                    'annual_demand' => round($D, 2),
                    'order_cost' => round($S, 2),
                    'holding_cost' => round($H, 2),
                    'eoq' => round($EOQ, 2),
                ];
            }
        }

        return response()->json(['data' => $results]);
    }

    /**
     * Helper function to get annual demand for a raw material.
     *
     * @param  int  $rawMaterialId
     * @return float
     */
    private function getDemandForRawMaterial($rawMaterialId)
    {
        // Ambil semua transaksi penjualan yang completed di tahun berjalan
        $transaksis = Transaksi::where('status', 'completed')
            ->whereYear('created_at', Carbon::now()->year)
            ->get();

        $totalUsed = 0;

        foreach ($transaksis as $transaksi) {
            $items = is_string($transaksi->daftar_barang)
                ? json_decode($transaksi->daftar_barang, true)
                : $transaksi->daftar_barang;

            if (!is_array($items)) {
                continue; // Lewati jika daftar_barang tidak valid setelah decode
            }

            foreach ($items as $item) {
                $productName = $item['nama_product'] ?? null;
                $quantitySold = $item['quantity'] ?? 0;

                if (!$productName || $quantitySold == 0) continue;

                // Cari product_id berdasarkan nama_product dari tabel products
                // ASUMSI: Tabel 'products' memiliki kolom 'nama_product'
                $product = Product::where('nama_product', $productName)->first(); // Perbaikan: Gunakan 'nama' jika itu nama kolom produk di tabel products
                
                if (!$product) continue;

                $productId = $product->id;

                // Ambil resep bahan baku yang dibutuhkan untuk produk ini
                // dan bahan baku spesifik yang sedang dihitung
                $ingredients = RecipeIngredient::whereHas('recipe', function($q) use ($productId) {
                        $q->where('product_id', $productId);
                    })
                    ->where('raw_material_id', $rawMaterialId)
                    ->get();

                foreach ($ingredients as $ingredient) {
                    $totalUsed += $ingredient->quantity_needed * $quantitySold;
                }
            }
        }

        return $totalUsed;
    }
}
