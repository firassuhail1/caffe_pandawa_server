<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\RawMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\RawMaterialInventory;
use App\Models\RawMaterialInventoryBatch;

class RawMaterialController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = RawMaterial::with(['inventories', 'batches'])->get();

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
        $validated = $request->validate([
            'sku' => 'nullable',
            'nama' => 'required',
            'unit_of_measure' => 'required',
            'standart_cost_price' => 'nullable',
        ]);

        DB::beginTransaction();

        try {
            $data = RawMaterial::create($validated);

            RawMaterialInventory::create([
                'raw_material_id' => $data->id,
                'cost_price' => $request->standart_cost_price,
                'current_stock' => $request->stock,
                'min_stock_alert' => $request->min_stock_alert,
            ]);

            RawMaterialInventoryBatch::create([
                'raw_material_id' => $data->id,
                'source_type' => 'initial_stock',
                'quantity_in' => $request->stock,
                'quantity_remaining' => $request->stock,
                'unit_cost' => $request->standart_cost_price,
            ]);

            DB::commit(); // Komit transaksi jika semua berhasil

            return response()->json(['success' => true, 'data' => $data], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal di store method',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $raw_material = RawMaterial::find($id);

        // Mengambil data raw material berdasarkan ID
        $data = RawMaterialInventory::selectRaw('SUM(current_stock) as total_stock')->get();

        return response()->json(['success' => true, 'data' => $data], 200);
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
        $validated = $request->validate([
            'sku' => 'nullable',
            'nama' => 'required',
            'unit_of_measure' => 'required',
            'cost_price' => 'required',
            'min_stock_alert' => 'required',
        ]);

        $data = RawMaterial::where('id', $id)->update($validated);

        return response()->json(['success' => true, 'data' => $data], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $bahan_baku = RawMaterial::findOrFail($id);

        $bahan_baku->delete();
        
        return response()->json(['success' => true, 'message' => 'Bahan Baku deleted']);
    }
}
