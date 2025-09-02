<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EOQController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\TableController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\TransaksiController;
use App\Http\Controllers\RawMaterialController;
use App\Http\Controllers\CashMovementController;
use App\Http\Controllers\CashierSessionController;
use App\Http\Controllers\MainCashBalanceController;
use App\Http\Controllers\LaporanPenjualanController;
use App\Http\Controllers\MainCashMovementController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login']); 
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');

Route::get('products/producible', [ProductController::class, 'getProducibleProducts']);
Route::resource('products', ProductController::class);
Route::resource('transaksi', TransaksiController::class);
Route::resource('cashier-session', CashierSessionController::class)->middleware('auth');
Route::resource('cash-movement', CashMovementController::class);
Route::resource('main-cash-movement', MainCashMovementController::class);
Route::resource('main-cash-balance', MainCashBalanceController::class);

Route::resource('bahan-baku', RawMaterialController::class);
Route::resource('recipes', RecipeController::class);
Route::get('get-data-pembelian', [PurchaseController::class, 'get_data_pembelian']);
Route::get('get-product-for-pembelian', [PurchaseController::class, 'get_product_for_pembelian']);
Route::post('buat-pembelian', [PurchaseController::class, 'buat_pembelian'])->middleware('auth');

Route::get('get-laba-kotor', [LaporanPenjualanController::class, 'get_laba_kotor']);
Route::get('laporan-total-penjualan', [LaporanPenjualanController::class, 'laporan_total_penjualan']);
Route::get('laporan-penjualan', [LaporanPenjualanController::class, 'laporan_penjualan']);

Route::post('/eoq-settings', [EOQController::class, 'saveSetting']);         // Simpan EOQ
Route::get('/calculate-eoq/{id}', [EOQController::class, 'calculate']);      // Hitung EOQ bahan baku
Route::get('/all-eoq', [EOQController::class, 'listAll']); 

Route::resource('tables', TableController::class);

Route::get('/menu', [ProductController::class, 'index']);

// Endpoint untuk manajemen pesanan
Route::controller(OrderController::class)->group(function () {
    Route::post('/orders', 'store'); // Untuk membuat pesanan baru
    Route::get('/orders', 'index');  // Untuk melihat daftar pesanan
    Route::get('/orders/{order}', 'show'); // Untuk melihat detail pesanan
    Route::put('/orders/{order}/status', 'update'); // Untuk melihat detail pesanan
    Route::delete('/orders/{order}', 'destroy'); // Untuk melihat detail pesanan
});

// Endpoint tambahan untuk F&B (dapur)
Route::get('/orders/pending', [OrderController::class, 'getPendingOrders']);

Route::post('/webhook/webhook', [WebhookController::class, 'webhook']); 
Route::get('/webhook/check-payment-status', [WebhookController::class, 'checkPaymentStatus']); 