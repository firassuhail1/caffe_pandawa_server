<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $tipe = $request->header('TIPE');

        if ($tipe == "for-list") {
            $data = Product::all();
        } else if ($tipe == "for-kasir") {
            $data = Product::where('status', 1)->get();
        }
        
        return response()->json(['success' => true, 'data' => $data], 200);
    }

    public function getProducibleProducts(Request $request)
    {
        $query = Product::get();

        // // Tambahkan fitur pencarian jika ada parameter 'search' dari frontend
        // if ($request->has('search') && $request->input('search') != '') {
        //     $searchTerm = $request->input('search');
        //     $query->where('nama', 'like', '%' . $searchTerm . '%')
        //           ->orWhere('sku', 'like', '%' . $searchTerm . '%');
        // }

        $products = $query; // Ambil kolom yang relevan saja

        return response()->json([
            'success' => true,
            'message' => 'Daftar produk yang bisa diproduksi berhasil diambil.',
            'data' => $products
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
        try {
            $validated = $request->except(['image']);
            $filename = "";
            
            $validated = $request->validate([
                'kode_product' => 'nullable|string',
                'nama_product' => 'required|string',
                'image' => 'nullable',
                'harga' => 'required|numeric',
                'stock' => 'required|integer',
                'jml_product_per_bundling' => 'nullable|integer',
                'status' => 'required',
            ]);

            // Simpan foto jika ada
            if ($request->hasFile('image')) {
                // Buat nama file unik: timestamp + uuid pendek
                $filename = 'photos/' . now()->format('Ymd_Hisv') . '_' . Str::random(8) . '.jpg';
            
                Storage::disk('public')->put($filename, file_get_contents($request->file('image')));
            
                $validated['image'] = env('BASE_IMAGE_URL') . '/' . $filename;
            }

            $product = Product::create($validated);
            return response()->json(['success' => true, 'data' => $product], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
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
    public function show(string $identifier)
    {
        $product = Product::find($identifier);

        if (!$product) {
            $product = Product::where('kode_product', $identifier)->first();
        }

        return response()->json(['success' => true, 'data' => $product], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $product = Product::findOrFail($id);

            $validated = $request->except(['image', 'tenant']);
            $filename = "";

            // Simpan foto jika ada
            if ($request->hasFile('image')) {
                // Buat nama file unik: timestamp + uuid pendek
                $filename = 'photos/' . now()->format('Ymd_Hisv') . '_' . Str::random(8) . '.jpg';
            
                Storage::disk('public')->put($filename, file_get_contents($request->file('image')));
            
                $validated['image'] = env('BASE_IMAGE_URL') . '/' . $filename;
            }
            
            $product->update($validated);
            
            if ($product != "") {
                return response()->json(['success' => true, 'data' => $product], 200);
            } else {
                return response()->json(['success' => false, 'data' => $product], 500);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $product = Product::find($product->id);

        $product->delete();

        return response()->json(['success' => true, 'message' => "Product berhasil di hapus", 'data' => $product], 200);
    }
}
