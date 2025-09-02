<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Transaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class LaporanPenjualanController extends Controller
{
    public function get_laba_kotor(Request $request) {
        $queryTotalPenjualan = Transaksi::where('status', 'completed');
        $queryTransaksis = Transaksi::where('status', 'completed');


        // Filter berdasarkan periode waktu
        $period = $request->input('period', 'daily'); // Default daily
        switch ($period) {
            case 'daily':
                $queryTotalPenjualan->whereDate('created_at', Carbon::today());
                $queryTransaksis->whereDate('created_at', Carbon::today());
                break;
            case 'weekly':
                $queryTotalPenjualan->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                $queryTransaksis->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                break;
            case 'monthly':
                $queryTotalPenjualan->whereMonth('created_at', Carbon::now()->month)
                    ->whereYear('created_at', Carbon::now()->year);
                $queryTransaksis->whereMonth('created_at', Carbon::now()->month)
                    ->whereYear('created_at', Carbon::now()->year);
                break;
            case 'yearly':
                $queryTotalPenjualan->whereYear('created_at', Carbon::now()->year);
                $queryTransaksis->whereYear('created_at', Carbon::now()->year);
                break;
            case 'custom':
                if ($request->has('start_date') && $request->start_date != null) {
                    $queryTotalPenjualan->whereDate('created_at', '>=', $request->start_date);
                    $queryTransaksis->whereDate('created_at', '>=', $request->start_date);
                }
                if ($request->has('end_date') && $request->end_date != null) {
                    $queryTotalPenjualan->whereDate('created_at', '<=', $request->end_date);
                    $queryTransaksis->whereDate('created_at', '<=', $request->end_date);
                }
                break;
        }

        $transaksis = $queryTransaksis->get();

        $total_harga_pokok = 0;
        foreach ($transaksis as $transaksi) {
            $daftarBarang = is_array($transaksi->daftar_barang)
                ? $transaksi->daftar_barang
                : json_decode($transaksi->daftar_barang, true); // fallback jika casting gagal

                
            // Karena 'daftar_barang' sudah dicast ke array, kita bisa mengulanginya langsung
            foreach ($daftarBarang as $item) {
                // Pastikan 'hpp_product' dan 'quantity' ada dan berupa angka
                $hpp = (float) ($item['hpp_product'] ?? 0); // Gunakan null coalescing operator untuk keamanan
                $quantity = (float) ($item['quantity'] ?? 0);
                $total_harga_pokok += ($hpp * $quantity);
            }
        }

        $total_penjualan = $queryTotalPenjualan->sum('total_amount');
        $total_laba_kotor = $total_penjualan - $total_harga_pokok;
        
        return response()->json(['success' => true, 'data' => $total_laba_kotor], 200);
    }

    public function laporan_total_penjualan(Request $request) {

        // Query dasar untuk transaksi yang sudah selesai
        $query = Transaksi::where('status', 'completed');

        // Filter berdasarkan periode waktu
        $period = $request->input('period', 'daily'); // Default daily
        switch ($period) {
            case 'daily':
                $query->whereDate('created_at', Carbon::today());
                break;
            case 'weekly':
                $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                break;
            case 'monthly':
                $query->whereMonth('created_at', Carbon::now()->month)
                    ->whereYear('created_at', Carbon::now()->year);
                break;
            case 'yearly':
                $query->whereYear('created_at', Carbon::now()->year);
                break;
            case 'custom':
                if ($request->has('start_date') && $request->start_date != null) {
                    $query->whereDate('created_at', '>=', $request->start_date);
                }
                if ($request->has('end_date') && $request->end_date != null) {
                    $query->whereDate('created_at', '<=', $request->end_date);
                }
                break;
        }

        // Lakukan agregasi
        $totalSales = $query->sum('total_amount'); // Asumsi kolom total penjualan Anda adalah 'total_amount'
        $totalTransaksi = $query->count();
        $totalProduct = 0;

        foreach ($query->get() as $transaksi) {
            // $transaksi adalah objek model
            // Akses properti menggunakan `->`
            Log::info($transaksi);
            if ($transaksi->daftar_barang) {
                $items = json_decode($transaksi->daftar_barang, true);
                if (is_array($items)) {
                    foreach ($items as $item) {
                        if (isset($item['qty'])) {
                            $totalProduct += $item['qty'];
                        }
                    }
                }
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Total penjualan berhasil diambil.',
            'data' => [
                'total_sales' => $totalSales,
                'total_transaksi' => $totalTransaksi,
                'total_product' => $totalProduct,
            ]
        ], 200);
    }

    public function laporan_penjualan(Request $request) {
        // Validasi parameter request (opsional: outlet_id, start_date, end_date, period)
        $request->validate([
            'period' => 'in:daily,weekly,monthly,yearly,custom', // Tambahkan validasi periode
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        // Query dasar untuk transaksi yang sudah selesai
        $query = Transaksi::where('status', 'completed');

        // Filter berdasarkan periode waktu
        $period = $request->input('period', 'daily'); // Default daily
        switch ($period) {
            case 'daily':
                $query->whereDate('created_at', Carbon::today());
                break;
            case 'weekly':
                $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                break;
            case 'monthly':
                $query->whereMonth('created_at', Carbon::now()->month)
                    ->whereYear('created_at', Carbon::now()->year);
                break;
            case 'yearly':
                $query->whereYear('created_at', Carbon::now()->year);
                break;
            case 'custom':
                if ($request->has('start_date') && $request->start_date != null) {
                    $query->whereDate('created_at', '>=', $request->start_date);
                }
                if ($request->has('end_date') && $request->end_date != null) {
                    $query->whereDate('created_at', '<=', $request->end_date);
                }
                break;
        }

        // Lakukan agregasi
        $totalSales = $query->sum('total_amount'); // Asumsi kolom total penjualan Anda adalah 'total_amount'
        $dataSales = $query->get();
        $data_ringkasan = $query->select(
            // DB::raw('SUM(total_amount) as total_penjualan'),
            DB::raw('COALESCE(SUM(total_amount), 0) as total_penjualan'), // Jika SUM hasilnya NULL, ganti dengan 0
            DB::raw('COUNT(*) as jumlah_transaksi')
        )->get();
        

        return response()->json([
            'success' => true,
            'message' => 'Total penjualan berhasil diambil.',
            'data' => [
                'total_sales' => $totalSales,
                'period' => $period,
                'data_penjualan' => $dataSales,
                'data_ringkasan' => $data_ringkasan,
            ]
        ], 200);
    }
}
