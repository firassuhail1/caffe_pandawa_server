<?php

namespace App\Http\Controllers;

use Midtrans\Config;
use App\Models\Order;
use Midtrans\CoreApi;
use App\Models\Product;
use App\Models\Transaksi;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        Log::info($request->status_pembayaran);
        $data = Order::with(['items'])
            ->where('status_pembayaran', $request->status_pembayaran)
            ->orderBy('id', 'desc')
            ->get();
        
        Log::info($data);
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
        $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'cashier_id' => 'nullable|exists:users,id',
            'order_source' => 'required|in:pos,online,qr_table',
            'table_number' => 'nullable|string',
            'notes' => 'nullable|string',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            $grandTotal = 0;
            $itemsData = [];

            // Hitung total harga dan siapkan data item
            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                $totalPrice = $product->harga * $item['qty'];
                $grandTotal += $totalPrice;

                $itemsData[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->nama_product,
                    'sku' => $product->kode_product,
                    'qty' => $item['qty'],
                    'unit_price' => $product->harga,
                    'total_price' => $totalPrice,
                ];
            }

            $order_number = 'ORD-' 
                . date('ymd') // tahun 2 digit, bulan, tanggal
                . strtoupper(Str::random(3)); // random string 3 char

                Log::info('sampai sini');

            // Buat pesanan baru
            $order = Order::create([
                'order_number' => $order_number,
                'customer_id' => $request->customer_id,
                'cashier_id' => $request->cashier_id,
                'order_source' => $request->order_source,
                'table_number' => $request->table_number,
                'subtotal' => $grandTotal,
                'grand_total' => $grandTotal,
                'status_pesanan' => 'pending', // Status awal selalu pending
                'payment_method' => 'QRIS',
                'notes' => $request->notes,
            ]);

            // Tambahkan item ke dalam pesanan
            $order->items()->createMany($itemsData);

            Config::$serverKey = config('midtrans.server_key');
            Config::$isProduction = config('midtrans.is_production');
            Config::$isSanitized = config('midtrans.is_sanitized');
            Config::$is3ds = config('midtrans.is_3ds');

            $payment_method = 'QRIS';

            // Validasi metode pembayaran
            $valid_payment_methods = ['Transfer Bank', 'QRIS', 'E-Wallet'];
            if (!in_array($payment_method, $valid_payment_methods)) {
                return response()->json(['message' => 'Metode pembayaran tidak valid'], 400);
            }

            $transaction = [
                'transaction_details' => [
                    'order_id' => $order_number,
                    'gross_amount' => $grandTotal,
                ]
            ];

            // QRIS
            if ($payment_method === 'QRIS') {
                $transaction['payment_type'] = 'qris';
                $transaction['qris'] = [
                    'acquirer_name' => 'gopay', // Midtrans default
                ];
            }

            // Tambahkan expiry time (24 jam dari sekarang)
            $transaction['custom_expiry'] = [
                'start_time' => date('Y-m-d H:i:s O'),
                'unit' => 'minute',
                'expiry_duration' => 15
            ];

            $response = CoreApi::charge($transaction);
            
            DB::commit();

            return response()->json([
                'order_number' => $order_number,
                'data' => $order->load('items'),
                'payment_info' => $response,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::info($e);
            return response()->json(['message' => 'Gagal membuat pesanan.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $order_number)
    {
        $data = Order::where('order_number', $order_number)->with('items')->first();

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
    public function update(Request $request, string $order_number)
    {
        DB::beginTransaction();

        try {
            // Cari order berdasarkan order_number
            $order = Order::where('order_number', $order_number)->first();
    
            // Pastikan order ditemukan sebelum melanjutkan
            if (!$order) {
                return response()->json(['error' => 'Order not found.'], 404);
            }
    
            // Perbarui status pesanan
            $order->status_pesanan = $request->status_pesanan;
            $order->save();
    
            // Jika status pesanan diubah menjadi "finished", buat record transaksi
            if ($request->status_pesanan == "finished") {
                // Ambil item pesanan dari order (misalnya dari relasi atau tabel pivot)
                // Di sini kita asumsikan Anda memiliki relasi 'items' pada model Order
                // Jika tidak, Anda perlu memuat data item dengan cara lain
                $order_items = $order->items; // Asumsikan ada relasi 'orderItems'
    
                // Ubah format data item menjadi JSON untuk kolom 'daftar_barang'
                $items_json = $order_items->toJson();
    
                // Buat record baru di tabel 'transaksis'
                Transaksi::create([
                    // 'user_id' => auth()->id, // ID kasir yang sedang login
                    'daftar_barang' => $items_json,
                    'cashier_session_id' => null, // Ganti dengan ID sesi kasir yang valid
                    'total_amount' => $order->grand_total,
                    'amount_paid' => $order->grand_total, // Asumsi dibayar penuh saat status selesai
                    'change_amount' => 0, // Asumsi kembalian nol jika dibayar pas
                    'payment_method' => $order->payment_method ?? "QRIS", // Ambil dari order
                    'status' => 'completed',
                ]);
            }
    
            DB::commit();

            return response()->json(['success' => true], 200);
        } catch (\Exception $e) {
            // 8. Rollback transaksi jika ada error
            DB::rollBack();
            Log::error('Gagal memproses pesanan: ' . $e->getMessage()); // Log error untuk debugging

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses pesanan: ' . $e->getMessage(),
            ], 500); // 500 Internal Server Error
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order)
    {
        $order->delete();

        return response()->json(true, 200);
    }
}