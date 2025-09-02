<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Recipe;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\RawMaterialInventory;

class WebhookController extends Controller
{
    
    public function webhook(Request $request)
    {
        DB::beginTransaction();
        Log::info('Midtrans Webhook Data:', $request->all());

        try {
            $data = $request->all();
            $orderId = $data['order_id'] ?? null;
            $transactionStatus = $data['transaction_status'] ?? null;
            $signatureKey = $data['signature_key'] ?? null;
            $statusCode = $data['status_code'] ?? null;
            $grossAmount = $data['gross_amount'] ?? null;

            // 1. Validasi Signature Key
            $serverKey = env('MIDTRANS_SERVER_KEY');
            $expectedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
            
            if ($signatureKey !== $expectedSignature) {
                Log::warning('Webhook rejected: Invalid signature key');
                DB::rollBack();
                return response()->json(['message' => 'Invalid signature'], 403);
            }

            // 2. Temukan Order berdasarkan order_number
            $order = Order::where('order_number', $orderId)->with('items')->first();

            if (!$order) {
                Log::warning('Webhook rejected: Order not found.', ['order_id' => $orderId]);
                DB::rollBack();
                return response()->json(['message' => 'Order not found'], 404);
            }

            // 3. Update Status Order
            if ($transactionStatus == 'settlement' || $transactionStatus == 'capture') {
                if ($order->status_pembayaran === 'pending') { // Pastikan hanya diproses sekali
                    // 4. Lakukan pengurangan stok dan update status
                    foreach ($order->items as $orderItem) {
                        $product = Product::findOrFail($orderItem->product_id);
                        $quantity = $orderItem->qty;

                        // Logika pengurangan stok produk jadi
                        // Mengurangi stok bahan baku
                        $recipe = Recipe::where('product_id', $product->id)->where('is_active', true)->first();
                        if ($recipe) {
                            foreach ($recipe->ingredients as $ingredient) {
                                $requiredQty = $ingredient->quantity_needed * $quantity;
                                $inventory = RawMaterialInventory::where('raw_material_id', $ingredient->raw_material_id)->first();
                                
                                if ($inventory) {
                                    $inventory->current_stock -= $requiredQty;
                                    $inventory->save();
                                }
                            }
                        }

                        // Mengurangi stok produk jadi (jika Anda memiliki stok produk jadi)
                        $product->stock -= $quantity;
                        $product->save();
                    }

                    $order->status_pembayaran = 'paid';
                    $order->save();
                }
            } elseif ($transactionStatus == 'pending') {
                $order->status_pembayaran = 'pending'; // Pastikan status tetap pending
                $order->save();
            } elseif ($transactionStatus == 'deny' || $transactionStatus == 'cancel' || $transactionStatus == 'cancelled' || $transactionStatus == 'expire') {
                $order->status_pembayaran = 'canceled';
                $order->save();
            }

            DB::commit();
            return response()->json(['message' => 'Webhook processed successfully']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Webhook processing failed: ' . $e->getMessage());
            return response()->json(['message' => 'An error occurred'], 500);
        }
    }

    public function checkPaymentStatus(Request $request)
    {
        $order = Order::where('order_number', $request->order_number)->first();

        if (!$order) {
            return response()->json(['message' => 'Order tidak ditemukan'], 404);
        }

        return response()->json(['status' => $order->status_pembayaran], 200);
    }
}